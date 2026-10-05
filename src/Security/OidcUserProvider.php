<?php

namespace App\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Users authenticated with OIDC (no user stored in the database) :
 * - browser login (drenso/symfony-oidc-bundle) : claims of the ID token, roles from the access token
 * - bearer (Symfony access_token "oidc" handler) : claims of the access token.
 *
 * @implements OidcUserProviderInterface<OidcUser>
 * @implements AttributesBasedUserProviderInterface<OidcUser>
 */
class OidcUserProvider implements OidcUserProviderInterface, AttributesBasedUserProviderInterface
{
    /**
     * Users created by ensureUserExists() for the current login (then loaded by loadOidcUser()).
     *
     * @var array<string,OidcUser>
     */
    private array $loggedInUsers = [];

    public function __construct(
        private OidcRolesExtractor $rolesExtractor,
    ) {
    }

    public function ensureUserExists(string $userIdentifier, OidcUserData $userData, OidcTokens $tokens): void
    {
        $claims = [
            'sub' => $userIdentifier,
            'name' => $userData->getFullName(),
            'preferred_username' => $userData->getDisplayName(),
            'email' => $userData->getEmail(),
        ];
        $roles = $this->rolesExtractor->getRoles(OidcRolesExtractor::decodeClaims($tokens->getAccessToken()));

        $this->loggedInUsers[$userIdentifier] = $this->createUser($claims, $roles);
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        return $this->loggedInUsers[$userIdentifier] ?? throw new UserNotFoundException();
    }

    /**
     * @param array<string,mixed> $attributes claims of the bearer access token
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        if (empty($attributes)) {
            throw new UserNotFoundException();
        }
        $attributes['sub'] = $identifier;

        return $this->createUser($attributes, $this->rolesExtractor->getRoles($attributes));
    }

    /**
     * The users are kept in the session as they are (the roles are refreshed on the next login).
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof OidcUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s"', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return OidcUser::class === $class || is_subclass_of($class, OidcUser::class);
    }

    /**
     * Name displayed for a user (demo, list of the validations for the admins).
     */
    public static function getDisplayName(OidcUser $user): string
    {
        foreach ([$user->getPreferredUsername(), $user->getEmail(), $user->getName()] as $name) {
            if (!empty($name)) {
                return $name;
            }
        }

        return $user->getUserIdentifier();
    }

    /**
     * @param array<string,mixed> $claims
     * @param string[]            $roles
     */
    private function createUser(array $claims, array $roles): OidcUser
    {
        $string = static fn (string $name): ?string => is_string($claims[$name] ?? null) && '' !== $claims[$name] ? $claims[$name] : null;

        return new OidcUser(
            userIdentifier: $claims['sub'],
            roles: $roles,
            sub: $claims['sub'],
            name: $string('name'),
            preferredUsername: $string('preferred_username'),
            email: $string('email'),
        );
    }
}
