<?php

namespace App\Security;

/**
 * Computes the roles of a user from the claims of a Keycloak access token :
 * ROLE_ADMIN is granted to the users having the client role OIDC_ADMIN_ROLE on the client OIDC_CLIENT_ID
 * ("resource_access": {"<client_id>": {"roles": ["admin"]}}).
 */
class OidcRolesExtractor
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private string $oidcClientId,
        private string $oidcAdminRole,
    ) {
    }

    /**
     * @param array<string,mixed> $claims claims of the access token
     *
     * @return string[]
     */
    public function getRoles(array $claims): array
    {
        $roles = [self::ROLE_USER];

        $clientRoles = $claims['resource_access'][$this->oidcClientId]['roles'] ?? [];
        if ('' !== $this->oidcAdminRole && is_array($clientRoles) && in_array($this->oidcAdminRole, $clientRoles, true)) {
            $roles[] = self::ROLE_ADMIN;
        }

        return $roles;
    }

    /**
     * Reads the claims of a JWT access token without checking its signature
     * (only for a token received from the token endpoint of the OIDC provider).
     *
     * @return array<string,mixed>
     */
    public static function decodeClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (3 !== count($parts)) {
            return [];
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = false === $payload ? null : json_decode($payload, true);

        return is_array($claims) ? $claims : [];
    }
}
