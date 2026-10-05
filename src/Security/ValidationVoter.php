<?php

namespace App\Security;

use App\Entity\Validation;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Permissions on the validations :
 * - OIDC disabled : anyone can create, update and delete a validation (nobody can list them)
 * - OIDC enabled : a user is required to create a validation, only its owner and the admins can update or delete it,
 *   the users can list their validations (LIST) and the admins all the validations (LIST_ALL),
 *   only the owner and the admins can download the source and normalized data (DOWNLOAD_DATA).
 *
 * Note that reading a validation (from its uid) is always allowed.
 *
 * @extends Voter<string,Validation|null>
 */
class ValidationVoter extends Voter
{
    public const CREATE = 'VALIDATION_CREATE';
    public const EDIT = 'VALIDATION_EDIT';
    public const DOWNLOAD_DATA = 'VALIDATION_DOWNLOAD_DATA';
    public const LIST = 'VALIDATION_LIST';
    public const LIST_ALL = 'VALIDATION_LIST_ALL';

    public function __construct(
        private bool $oidcEnabled,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE, self::LIST, self::LIST_ALL => true,
            self::EDIT, self::DOWNLOAD_DATA => $subject instanceof Validation,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->oidcEnabled) {
            return !in_array($attribute, [self::LIST, self::LIST_ALL], true);
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        $isAdmin = in_array(OidcRolesExtractor::ROLE_ADMIN, $user->getRoles(), true);

        return match ($attribute) {
            self::CREATE, self::LIST => true,
            self::LIST_ALL => $isAdmin,
            self::EDIT, self::DOWNLOAD_DATA => $isAdmin || (null !== $subject->getOwner() && $subject->getOwner() === $user->getUserIdentifier()),
            default => false,
        };
    }
}
