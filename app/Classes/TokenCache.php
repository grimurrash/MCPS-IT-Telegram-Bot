<?php

namespace App\Classes;

use App\Models\Token;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;

class TokenCache
{
    public function storeTokens($accessToken, $user): void
    {
        $token = Token::currentToken();
        if ($token === null) {
            Token::query()->create([
                'accessToken' => $accessToken->getToken(),
                'refreshToken' => $accessToken->getRefreshToken(),
                'tokenExpires' => $accessToken->getExpires(),
                'userName' => $user->getDisplayName(),
                'userEmail' => $user->getMail() ?? $user->getUserPrincipalName(),
                'userTimeZone' => $user->getMailboxSettings()->getTimeZone()
            ]);
            return;
        }
        $token->update([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'tokenExpires' => $accessToken->getExpires(),
            'userName' => $user->getDisplayName(),
            'userEmail' => $user->getMail() ?? $user->getUserPrincipalName(),
            'userTimeZone' => $user->getMailboxSettings()->getTimeZone()
        ]);
    }

    public function clearTokens(): void
    {
        $token = Token::currentToken();
        $token->clearTokens();
    }

    public function getAccessToken()
    {
        $token = Token::currentToken();
        // Check if tokens exist
        if ($token->accessToken === null ||
            $token->refreshToken === null ||
            $token->tokenExpires === null) {
            return '';
        }

        // Check if token is expired
        //Get current time + 5 minutes (to allow for time differences)
        $now = time() + 300;
        if ($token->tokenExpires <= $now) {
            // Token is expired (or very close to it)
            // so let's refresh

            // Initialize the OAuth client
            $oauthClient = new GenericProvider([
                'clientId'                => config('azure.appId'),
                'clientSecret'            => config('azure.appSecret'),
                'redirectUri'             => config('azure.redirectUri'),
                'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
                'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
                'urlResourceOwnerDetails' => '',
                'scopes'                  => config('azure.scopes')
            ]);
            try {
                $newToken = $oauthClient->getAccessToken('refresh_token', [
                    'refresh_token' => $token->refreshToken
                ]);
                // Store the new values
                $token->updateTokens($newToken);

                return $newToken->getToken();
            } catch (IdentityProviderException $e) {
                return '';
            }
        }

        // Token is still valid, just return it
        return $token->accessToken;
    }
}
