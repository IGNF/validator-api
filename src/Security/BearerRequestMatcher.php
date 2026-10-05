<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Selects the stateless "bearer" firewall (see security.yaml) for the requests with "Authorization: Bearer ..."
 * when OIDC is enabled (the other requests use the "main" firewall with the session).
 */
class BearerRequestMatcher implements RequestMatcherInterface
{
    public function __construct(
        private bool $oidcEnabled,
    ) {
    }

    public function matches(Request $request): bool
    {
        return $this->oidcEnabled
            && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }
}
