<?php

namespace App\Tests\Controller;

use App\Tests\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Tests the fake login of the dev environment (OIDC_DEV_LOGIN).
 */
class DevLoginControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->setEnv('1', '1');
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    public function tearDown(): void
    {
        $this->setEnv('0', '0');
        parent::tearDown();
    }

    public function testLoginUrl()
    {
        $this->client->request('GET', '/api/me');
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('/_dev/login', $json['login_url']);
    }

    public function testForm()
    {
        $this->client->request('GET', '/_dev/login?_target_path=/admin');
        $this->assertStatusCode(200, $this->client);
        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('name="_target_path" value="/admin"', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('name="admin"', $html);
    }

    public function testInvalidUsername()
    {
        $this->client->request('GET', '/_dev/login?username=a%20b&admin=1');
        $this->assertStatusCode(200, $this->client);
        $html = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('id="admin" name="admin" value="1" checked', $html);
    }

    public function testLoginAdmin()
    {
        $this->client->request('GET', '/_dev/login?username=admin&admin=1&_target_path=/admin');
        $this->assertResponseRedirects('/admin');

        $this->client->request('GET', '/api/me');
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($json['authenticated']);
        $this->assertEquals(['name' => 'admin', 'email' => 'admin@example.org', 'is_admin' => true], $json['user']);

        $this->client->request('GET', '/api/validations/');
        $this->assertStatusCode(200, $this->client);

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/api/me');
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($json['authenticated']);
    }

    public function testLoginUser()
    {
        $this->client->request('GET', '/_dev/login?username=utilisateur2&_target_path=//evil.example.org');
        $this->assertResponseRedirects('/');

        // only the validations of the user
        $this->client->request('GET', '/api/validations/');
        $this->assertStatusCode(200, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(0, $json['total']);
    }

    public function testDisabled()
    {
        $this->setEnv('1', '0');
        static::ensureKernelShutdown();
        $client = static::createClient();

        $client->request('GET', '/_dev/login?username=admin&admin=1');
        $this->assertStatusCode(404, $client);
        $client->request('GET', '/api/me');
        $json = \json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('/login', $json['login_url']);
    }

    private function setEnv(string $oidcEnabled, string $devLogin): void
    {
        $_ENV['OIDC_ENABLED'] = $_SERVER['OIDC_ENABLED'] = $oidcEnabled;
        $_ENV['OIDC_DEV_LOGIN'] = $_SERVER['OIDC_DEV_LOGIN'] = $devLogin;
    }
}
