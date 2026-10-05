<?php

namespace App\Controller;

use App\Security\OidcRolesExtractor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Fake login without the OIDC provider, to test the permissions locally (owner, admin).
 *
 * Only defined in the dev and test environments, and only enabled with OIDC_ENABLED=1 and OIDC_DEV_LOGIN=1
 * (then used as login_url by /api/me).
 */
class DevLoginController extends AbstractController
{
    public function __construct(
        private bool $oidcEnabled,
        #[Autowire(env: 'bool:default::OIDC_DEV_LOGIN')]
        private ?bool $devLoginEnabled,
    ) {
    }

    /**
     * Without "username" : displays the login form, else logs in "dev-<username>" (ROLE_ADMIN with admin=1).
     */
    #[Route('/_dev/login', name: 'validator_dev_login', methods: ['GET'], env: ['dev', 'test'])]
    public function login(Request $request, TokenStorageInterface $tokenStorage): Response
    {
        if (!$this->oidcEnabled || !$this->devLoginEnabled) {
            throw new NotFoundHttpException('Fake login is disabled (see OIDC_DEV_LOGIN)');
        }

        $targetPath = (string) $request->query->get('_target_path', '/');
        if (!SecurityController::isLocalPath($targetPath)) {
            $targetPath = '/';
        }

        $username = trim((string) $request->query->get('username', ''));
        if (!preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $username)) {
            return $this->render('dev_login.html.twig', [
                'target_path' => $targetPath,
                'username' => $username,
                'admin' => $request->query->getBoolean('admin'),
                'invalid' => '' !== $username,
            ]);
        }

        $roles = [OidcRolesExtractor::ROLE_USER];
        if ($request->query->getBoolean('admin')) {
            $roles[] = OidcRolesExtractor::ROLE_ADMIN;
        }
        $user = new OidcUser(
            userIdentifier: 'dev-'.$username,
            roles: $roles,
            sub: 'dev-'.$username,
            preferredUsername: $username,
            email: $username.'@example.org',
        );

        // stored in the session by the "main" firewall (see ContextListener)
        $request->getSession()->migrate(true);
        $tokenStorage->setToken(new PostAuthenticationToken($user, 'main', $roles));

        return $this->redirect($targetPath);
    }
}
