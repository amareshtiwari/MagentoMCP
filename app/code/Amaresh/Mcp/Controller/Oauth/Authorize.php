<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Controller\Oauth;

use Amaresh\Mcp\Model\OAuth\Service as OAuthService;
use Magento\Backend\Model\Auth as BackendAuth;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\AuthenticationException;

class Authorize implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly Escaper $escaper,
        private readonly OAuthService $oauthService,
        private readonly BackendAuth $backendAuth,
        private readonly AdminSession $adminSession
    ) {
    }

    public function execute()
    {
        if ($this->request->isPost()) {
            return $this->approve();
        }

        return $this->renderForm();
    }

    private function approve()
    {
        $clientId = trim((string)$this->request->getParam('client_id'));
        $redirectUri = trim((string)$this->request->getParam('redirect_uri'));
        $state = (string)$this->request->getParam('state');
        $codeChallenge = trim((string)$this->request->getParam('code_challenge'));
        $codeChallengeMethod = trim((string)$this->request->getParam('code_challenge_method', 'S256'));

        if (!$this->isAdminLoggedIn() && !$this->authenticateAdmin()) {
            return $this->renderForm('The admin username or password is incorrect.');
        }

        if ($clientId === '' || $redirectUri === '' || $codeChallenge === ''
            || !$this->oauthService->isAllowedRedirectUri($clientId, $redirectUri)
        ) {
            return $this->redirectWithError($redirectUri, $state, 'invalid_request');
        }

        $code = $this->oauthService->issueAuthorizationCode(
            $clientId,
            $redirectUri,
            $codeChallenge,
            $codeChallengeMethod,
            (string)$this->request->getParam('scope', 'mcp'),
            (string)$this->request->getParam('resource', '')
        );

        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        $location = $redirectUri . $separator . http_build_query([
            'code' => $code['code'],
            'state' => $state,
        ]);

        return $this->renderClaudeRedirect($location);
    }

    private function renderClaudeRedirect(string $location)
    {
        $escapedUrl = $this->escaper->escapeUrl($location);
        $escapedJsUrl = json_encode($location, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Returning to Claude</title>'
            . '<meta http-equiv="refresh" content="0;url=' . $escapedUrl . '">'
            . '<style>body{font-family:Arial,sans-serif;margin:40px;max-width:560px}'
            . 'a,button{display:inline-block;margin-top:18px;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:6px}</style>'
            . '</head><body><h1>Returning to Claude</h1>'
            . '<p>The connector was approved. Continue to Claude to finish connecting.</p>'
            . '<a href="' . $escapedUrl . '">Continue to Claude</a>'
            . '<script>window.location.replace(' . $escapedJsUrl . ');</script>'
            . '</body></html>';

        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setContents($html);
        return $result;
    }

    private function renderForm(string $error = '')
    {
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Approve Magento MCP</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:40px;max-width:560px}label{display:block;margin:16px 0 6px}'
            . 'input{width:100%;padding:10px;box-sizing:border-box}button{margin-top:18px;padding:10px 16px;background:#111;color:#fff;border:0;border-radius:6px}'
            . '.error{color:#b00020}</style></head><body>'
            . '<h1>Approve Magento MCP</h1><p>Sign in with a Magento admin user to approve this Claude connection.</p>';

        if ($error !== '') {
            $html .= '<p class="error">' . $this->escaper->escapeHtml($error) . '</p>';
        }

        $fields = ['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'code_challenge', 'code_challenge_method', 'resource'];
        $html .= '<form method="post">';
        foreach ($fields as $field) {
            $html .= '<input type="hidden" name="' . $field . '" value="'
                . $this->escaper->escapeHtmlAttr((string)$this->request->getParam($field)) . '">';
        }
        if (!$this->isAdminLoggedIn()) {
            $html .= '<label for="admin_username">Admin username</label>'
                . '<input id="admin_username" type="text" name="admin_username" autocomplete="username" required>'
                . '<label for="admin_password">Admin password</label>'
                . '<input id="admin_password" type="password" name="admin_password" autocomplete="current-password" required>';
        }
        $html .= '<button type="submit">Approve connector</button></form></body></html>';

        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $result->setContents($html);
        return $result;
    }

    private function isAdminLoggedIn(): bool
    {
        return (bool)$this->adminSession->isLoggedIn();
    }

    private function authenticateAdmin(): bool
    {
        $username = trim((string)$this->request->getParam('admin_username'));
        $password = (string)$this->request->getParam('admin_password');
        if ($username === '' || $password === '') {
            return false;
        }

        try {
            $this->backendAuth->login($username, $password);
        } catch (AuthenticationException) {
            return false;
        }

        return $this->isAdminLoggedIn();
    }

    private function redirectWithError(string $redirectUri, string $state, string $error)
    {
        if ($redirectUri === '') {
            return $this->renderForm($error);
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl(
            $redirectUri . $separator . http_build_query(['error' => $error, 'state' => $state])
        );
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
