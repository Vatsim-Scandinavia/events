<?php

namespace App\Http\Controllers;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class OAuthController extends GenericProvider
{
    /**
     * @var GenericProvider
     */
    private $provider;

    /**
     * Initializes the provider variable.
     */
    public function __construct()
    {
        parent::__construct([
            'clientId' => config('oauth.id'),
            'clientSecret' => config('oauth.secret'),
            'redirectUri' => route('login'),
            'urlAuthorize' => config('oauth.base') . '/oauth/authorize',
            'urlAccessToken' => config('oauth.base') . '/oauth/token',
            'urlResourceOwnerDetails' => config('oauth.base') . '/api/user',
            'scopes' => config('scopes'),
        ]);
    }

    /**
     * Gets an (updated) user token
     * @param Token $token
     * @return Token
     * @return null
     */
    public function updateToken($token)
    {
        $controller = new OAuthController;

        try {
            return $controller->getAccessToken('refresh_token', [
                'refresh_token' => $token->getRefreshToken(),
            ]);
        } catch (IdentityProviderException $e) {
            return null;
        }
    }

    public static function mapOAuthProperties($response)
    {

        $data = [
            'id' => OAuthController::getOAuthProperty(config('oauth.mapping_cid'), $response),
            'email' => OAuthController::getOAuthProperty(config('oauth.mapping_mail'), $response),
            'first_name' => OAuthController::getOAuthProperty(config('oauth.mapping_first_name'), $response),
            'last_name' => OAuthController::getOAuthProperty(config('oauth.mapping_last_name'), $response),
        ];

        return $data;

    }

    // Thanks to Moodle for this snippet
    // https://github.com/moodle/moodle/blob/48ad73619f870e4fd87240bd3c74202a300da6b2/lib/classes/oauth2/client.php#L255
    public static function getOAuthProperty($property, $data)
    {
        $getfunc = function ($obj, $prop) use (&$getfunc) {
            $proplist = explode('-', $prop, 2);
            if (! isset($proplist[0]) || ! isset($obj->{$proplist[0]})) {
                return null;
            }
            $obj = $obj->{$proplist[0]};

            if (count($proplist) > 1) {
                return $getfunc($obj, $proplist[1]);
            }

            return $obj;
        };

        $resolved = $getfunc($data, $property);
        if (isset($resolved) && $resolved != '') {
            return $resolved;
        }

        return null;
    }
}
