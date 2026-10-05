<?php

namespace App\Controller\Api;

use App\Security\OidcRolesExtractor;
use App\Security\OidcUserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\OidcUser;

/**
 * Current user (used by the demo to display the login / logout buttons).
 */
class MeController extends AbstractController
{
    public function __construct(
        private bool $oidcEnabled,
        // fake login of the dev environment (see DevLoginController)
        #[Autowire(env: 'bool:default::OIDC_DEV_LOGIN')]
        private ?bool $devLoginEnabled,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    #[Route('/api/me', name: 'validator_api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->oidcEnabled ? $this->getUser() : null;

        $response = new JsonResponse([
            'enabled' => $this->oidcEnabled,
            'authenticated' => $user instanceof OidcUser,
            'user' => $user instanceof OidcUser ? [
                'name' => OidcUserProvider::getDisplayName($user),
                'email' => $user->getEmail(),
                'is_admin' => in_array(OidcRolesExtractor::ROLE_ADMIN, $user->getRoles(), true),
            ] : null,
            'login_url' => $this->oidcEnabled ? $this->generateUrl($this->getLoginRoute()) : null,
            'logout_url' => $this->oidcEnabled ? $this->generateUrl('_logout_main') : null,
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function getLoginRoute(): string
    {
        return $this->devLoginEnabled && in_array($this->environment, ['dev', 'test'], true)
            ? 'validator_dev_login'
            : 'validator_login';
    }
}
