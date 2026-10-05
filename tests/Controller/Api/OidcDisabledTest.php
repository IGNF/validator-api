<?php

namespace App\Tests\Controller\Api;

use App\Controller\SecurityController;
use App\Tests\WebTestCase;

/**
 * Tests the authentication routes when OIDC is disabled (default, see .env.test).
 */
class OidcDisabledTest extends WebTestCase
{
    public function testMe()
    {
        $client = static::createClient();
        $client->request('GET', '/api/me');
        $this->assertStatusCode(200, $client);
        $json = \json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals([
            'enabled' => false,
            'authenticated' => false,
            'user' => null,
            'login_url' => null,
            'logout_url' => null,
        ], $json);
        // no session
        $this->assertEmpty($client->getResponse()->headers->getCookies());
    }

    public function testLoginNotFound()
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $this->assertStatusCode(404, $client);
        $client->request('GET', '/login_check');
        $this->assertStatusCode(404, $client);
    }

    public function testIsLocalPath()
    {
        $this->assertTrue(SecurityController::isLocalPath('/'));
        $this->assertTrue(SecurityController::isLocalPath('/validation/abc?x=1#/y'));
        $this->assertFalse(SecurityController::isLocalPath('//evil.example.org'));
        $this->assertFalse(SecurityController::isLocalPath('/\\evil.example.org'));
        $this->assertFalse(SecurityController::isLocalPath('https://evil.example.org'));
        $this->assertFalse(SecurityController::isLocalPath("/a\nb"));
        $this->assertFalse(SecurityController::isLocalPath(''));
    }
}
