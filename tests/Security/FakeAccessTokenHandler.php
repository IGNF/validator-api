<?php

namespace App\Tests\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Replaces the OIDC token handler of the "bearer" firewall in the tests (no Keycloak) :
 * the access token is "test.<base64 of the json claims>" (see createToken()).
 */
class FakeAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $claims = str_starts_with($accessToken, 'test.')
            ? json_decode((string) base64_decode(substr($accessToken, 5), true), true)
            : null;
        if (!is_array($claims) || empty($claims['sub'])) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        return new UserBadge($claims['sub'], null, $claims);
    }

    /**
     * @param array<string,mixed> $claims
     */
    public static function createToken(array $claims): string
    {
        return 'test.'.base64_encode(json_encode($claims));
    }
}
