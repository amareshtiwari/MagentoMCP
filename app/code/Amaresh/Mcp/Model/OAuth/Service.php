<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\OAuth;

use Amaresh\Mcp\Model\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Service
{
    private const CACHE_TAG = 'AMARESH_MCP_OAUTH';
    private const CLIENT_PREFIX = 'amaresh_mcp_oauth_client_';
    private const CODE_PREFIX = 'amaresh_mcp_oauth_code_';
    private const TOKEN_PREFIX = 'amaresh_mcp_oauth_token_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly Config $config
    ) {
    }

    /**
     * @param string[] $redirectUris
     * @return array<string, mixed>
     */
    public function registerClient(array $redirectUris, string $clientName = 'Claude MCP Connector'): array
    {
        $clientId = 'gh_mcp_' . $this->randomToken(24);
        $client = [
            'client_id' => $clientId,
            'client_name' => $clientName,
            'redirect_uris' => array_values(array_filter($redirectUris)),
            'created_at' => time(),
        ];

        $this->cache->save($this->json->serialize($client), self::CLIENT_PREFIX . $clientId, [self::CACHE_TAG], 86400 * 30);

        return $client;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getClient(string $clientId): ?array
    {
        $data = $this->cache->load(self::CLIENT_PREFIX . $clientId);
        if (!$data) {
            return null;
        }

        $client = $this->json->unserialize($data);
        return is_array($client) ? $client : null;
    }

    public function isAllowedRedirectUri(string $clientId, string $redirectUri): bool
    {
        $client = $this->getClient($clientId);
        if (!$client || empty($client['redirect_uris'])) {
            return true;
        }

        return in_array($redirectUri, (array)$client['redirect_uris'], true);
    }

    public function validateAdminToken(string $token): bool
    {
        return $this->config->validateBearerToken($token);
    }

    /**
     * @return array<string, mixed>
     */
    public function issueAuthorizationCode(
        string $clientId,
        string $redirectUri,
        string $codeChallenge,
        string $codeChallengeMethod,
        string $scope = 'mcp',
        string $resource = ''
    ): array {
        $code = 'gh_code_' . $this->randomToken(32);
        $data = [
            'code' => $code,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => strtoupper($codeChallengeMethod ?: 'S256'),
            'scope' => $scope ?: 'mcp',
            'resource' => $resource,
            'created_at' => time(),
        ];

        $this->cache->save($this->json->serialize($data), self::CODE_PREFIX . $code, [self::CACHE_TAG], 600);

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exchangeCode(
        string $code,
        string $clientId,
        string $redirectUri,
        string $codeVerifier,
        string $resource = ''
    ): ?array {
        $cacheKey = self::CODE_PREFIX . $code;
        $data = $this->cache->load($cacheKey);
        if (!$data) {
            return null;
        }

        $payload = $this->json->unserialize($data);
        $this->cache->remove($cacheKey);

        if (!is_array($payload)
            || ($payload['client_id'] ?? '') !== $clientId
            || ($payload['redirect_uri'] ?? '') !== $redirectUri
            || !$this->isValidPkce($payload, $codeVerifier)
        ) {
            return null;
        }

        if (($payload['resource'] ?? '') !== '' && $resource !== '' && ($payload['resource'] ?? '') !== $resource) {
            return null;
        }

        $accessToken = 'gh_mcp_at_' . $this->randomToken(40);
        $tokenData = [
            'client_id' => $clientId,
            'scope' => $payload['scope'] ?? 'mcp',
            'resource' => $payload['resource'] ?? $resource,
            'created_at' => time(),
        ];

        $this->cache->save(
            $this->json->serialize($tokenData),
            self::TOKEN_PREFIX . hash('sha256', $accessToken),
            [self::CACHE_TAG],
            86400
        );

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
            'scope' => $payload['scope'] ?? 'mcp',
        ];
    }

    public function validateAccessToken(string $token): bool
    {
        if ($this->config->validateBearerToken($token)) {
            return true;
        }

        return (bool)$this->cache->load(self::TOKEN_PREFIX . hash('sha256', $token));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isValidPkce(array $payload, string $codeVerifier): bool
    {
        if ($codeVerifier === '') {
            return false;
        }

        $challenge = (string)($payload['code_challenge'] ?? '');
        $method = strtoupper((string)($payload['code_challenge_method'] ?? 'S256'));
        if ($method === 'PLAIN') {
            return hash_equals($challenge, $codeVerifier);
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        return hash_equals($challenge, $computed);
    }

    private function randomToken(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
