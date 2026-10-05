<?php

namespace App\Controller;

use Drenso\OidcBundle\OidcClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Login of the browser users with OIDC (the logout is handled by the firewall, see security.yaml).
 */
class SecurityController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private bool $oidcEnabled,
    ) {
    }

    /**
     * Redirects to the OIDC provider (then back to "_target_path", a path of this application, see /login_check).
     */
    #[Route('/login', name: 'validator_login', methods: ['GET'])]
    public function login(Request $request, OidcClientInterface $oidcClient): RedirectResponse
    {
        $this->denyIfOidcDisabled();

        $targetPath = $request->query->get('_target_path');
        if (is_string($targetPath) && self::isLocalPath($targetPath)) {
            $this->saveTargetPath($request->getSession(), 'main', $targetPath);
        }

        return $oidcClient->generateAuthorizationRedirect(scopes: ['openid', 'profile', 'email']);
    }

    /**
     * Redirect URI of the OIDC client (handled by the OIDC authenticator of the firewall).
     */
    #[Route('/login_check', name: 'validator_login_check', methods: ['GET'])]
    public function loginCheck(): never
    {
        $this->denyIfOidcDisabled();

        throw new \LogicException('This method should not be reached (see the "oidc" authenticator in security.yaml)');
    }

    /**
     * Only paths of this application are allowed (no open redirect, ex : "//evil.example.org").
     */
    public static function isLocalPath(string $path): bool
    {
        return 1 === preg_match('#^/(?![/\\\\])#', $path) && !preg_match('/[\x00-\x1f]/', $path);
    }

    private function denyIfOidcDisabled(): void
    {
        if (!$this->oidcEnabled) {
            throw new NotFoundHttpException('OIDC authentication is disabled');
        }
    }
}
