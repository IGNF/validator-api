<?php

namespace App\EventListener;

use App\Security\BearerRequestMatcher;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF protection of the requests authenticated with the session cookie (users logged in with the browser) :
 * POST, PATCH, PUT and DELETE must come from the same origin (in addition to the SameSite=Lax cookie).
 *
 * Requests authenticated with "Authorization: Bearer" are not concerned (no cookie).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)] // after the firewall (8)
class SameOriginListener
{
    public function __construct(
        private Security $security,
        private BearerRequestMatcher $bearerRequestMatcher,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || $request->isMethodSafe()
            || $this->bearerRequestMatcher->matches($request)
            || null === $this->security->getUser()
        ) {
            return;
        }

        // Sec-Fetch-Site is sent by the modern browsers, Origin is the fallback for the older ones
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        $origin = $request->headers->get('Origin');
        if (null !== $fetchSite) {
            $sameOrigin = 'same-origin' === $fetchSite;
        } else {
            $sameOrigin = null === $origin || $origin === $request->getSchemeAndHttpHost();
        }

        if (!$sameOrigin) {
            throw new AccessDeniedHttpException('Cross-origin request rejected');
        }
    }
}
