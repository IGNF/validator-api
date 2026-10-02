<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to the responses (headers already defined by a controller are kept).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * Content Security Policy for HTML pages (demo client) : everything is served by the API,
     * inline styles are required by the client (style-loader, swagger-ui).
     */
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function __construct(
        // the web profiler toolbar (debug only) injects inline scripts in HTML pages
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $headers = $event->getResponse()->headers;

        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        // Content-Type of HTML pages is only set when the response is prepared (harmless for other responses)
        $contentType = (string) $headers->get('Content-Type');
        if (!$this->debug
            && ('' === $contentType || str_starts_with($contentType, 'text/html'))
            && !$headers->has('Content-Security-Policy')
        ) {
            $headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }
    }
}
