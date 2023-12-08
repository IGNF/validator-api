<?php

namespace App\Tests\Controller;

use App\Tests\WebTestCase;

/**
 * Tests for DefaultController class.
 */
class DefaultControllerTest extends WebTestCase
{
    /**
     * Test homepage with content.
     */
    public function testHome()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        /* $crawler = */ $client->request(
            'GET',
            '/'
        );
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#demo-wrapper', 'Chargement...');
    }
}
