<?php
declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Api\Client\ClientInterface;
use App\Service\Api\OAuth1\Consumer;
use App\Service\Api\OAuth1\QueryBuilder;
use App\Service\Api\OAuth2\Auth;

use App\Exception\ApiException;
use App\Exception\ApiMessageException;
use App\Exception\ApiStatusCodeException;
use App\Service\Api\OAuth1\Token;
use Psr\SimpleCache\CacheInterface;

class LinkedinApi implements NetworkInterface
{
    /** @var ClientInterface */
    private $client;

    /** @var Auth */
    private $auth;

    /** @var CacheInterface */
    private $cache;

    /** @var array */
    private $acceptedStatusCode = [
        200,
        201,
    ];

    const API_HOST = 'https://www.linkedin.com';
    const API_AUTH_HOST = 'https://www.linkedin.com/oauth';

    const API_TOKEN_AUTHORISE_APP_METHOD = 'authorization';
    const API_TOKEN_FETCH_LONGTIME_METHOD = 'accessToken';

    const API_SHARE_METHOD = 'people/~/shares?format=json';
    const API_USER_INFO_METHOD = 'people/~?format=json';

    const API_VERSION = 'v2';
    const API_TIMEOUT = '1000';

    const NETWORK_SLUG = 'linkedin';

    public function __construct(
        CacheInterface $cache,
        ClientInterface $client,
        string $consumerKey,
        string $consumerSecret
    ) {
        $consumer = new Consumer($consumerKey, $consumerSecret);

        $this->client = $client;
        $this->auth = new Auth($cache, $client, $consumer, 'linkedin');
    }

    public function getNetworkSlug(): string
    {
        return self::NETWORK_SLUG;
    }

    public function getAuthUrl(int $uid, string $redirectUri = '/'): string
    {
        $url = $this->createAuthBaseUrl() . self::API_TOKEN_AUTHORISE_APP_METHOD;
        return $this->auth->getAuthUrl($url, $uid, $redirectUri);
    }

    public function getLongTimeToken(string $oneTimeToken, int $uid, string $redirectUri)
    {
        $url = $this->createAuthBaseUrl() . self::API_TOKEN_FETCH_LONGTIME_METHOD;
        $token = $this->auth->getLongTimeToken($url, $oneTimeToken, $uid, $redirectUri);

        return $token;
    }

    public function verifyCallbackToken(string $callbackToken, int $uid)
    {
        return $this->auth->verifyCallbackToken($callbackToken, $uid);
    }

    // TODO sort out this token mess (OAUTH1 / OAUTH2)
    public function getUserInfo(string $tokenKey)
    {
        $url = self::API_HOST . '/v1/' . self::API_USER_INFO_METHOD;

        $headers = [
            'Authorization' => 'Bearer ' . $tokenKey,
        ];

        try {
            $response = $this->client->get($url, $headers);
        } catch (\Exception $e) {
            throw new ApiMessageException($e->getMessage());
        }

        if (!in_array($response->getStatusCode(), $this->acceptedStatusCode)) {
            throw new ApiStatusCodeException(
                sprintf(
                    'Wrong status code: %d with body %s',
                    $response->getStatusCode(),
                    $response->getBody()
                )
            );
        }

        return $response;
    }
    public function postUpdate(string $content, Token $token)
    {
        $url = self::API_HOST . '/v1/' . self::API_SHARE_METHOD;

        $parameters = [
            'comment' => $content,
            'visibility' => [
                'code' => 'anyone'
            ]
        ];

        $headers = [
            'Content-type' => 'application/json',
            'x-li-format' => 'json',
            'Authorization' => 'Bearer ' . $token->getKey(),
        ];

        try {
            $response = $this->client->post($url, $headers, $parameters, 'json');
        } catch (\Exception $e) {
            throw new ApiMessageException($e->getMessage());
        }

        if (!in_array($response->getStatusCode(), $this->acceptedStatusCode)) {
            throw new ApiStatusCodeException(
                sprintf(
                    'Wrong status code: %d with body %s',
                    $response->getStatusCode(),
                    (string)$response->getBody()
                )
            );
        }

        return $response;
    }

    private function createAuthBaseUrl(): string
    {
        return self::API_AUTH_HOST . '/' . self::API_VERSION . '/';
    }
}
