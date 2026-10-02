<?php

namespace App\Tests\EventListener;

use App\EventListener\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SecurityHeadersListenerTest extends TestCase
{
    private function dispatch(Response $response, bool $debug = false, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), $type, $response);
        (new SecurityHeadersListener($debug))($event);

        return $response;
    }

    public function testHtmlPage()
    {
        $response = $this->dispatch(new Response('<html></html>'));

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString("script-src 'self';", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function testJsonResponseHasNoCsp()
    {
        $response = $this->dispatch(new JsonResponse([]));

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    /**
     * The web profiler toolbar injects inline scripts in debug mode.
     */
    public function testNoCspInDebug()
    {
        $response = $this->dispatch(new Response('<html></html>'), true);

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testExistingHeadersAreKept()
    {
        $response = $this->dispatch(new Response('', 200, ['X-Frame-Options' => 'SAMEORIGIN']));

        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testSubRequestsAreIgnored()
    {
        $response = $this->dispatch(new Response(''), false, HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }
}
