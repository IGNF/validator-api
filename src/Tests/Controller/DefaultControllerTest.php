<?php

namespace App\Tests\Controller;

use App\Tests\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Tests for DefaultController class
 */
class DefaultControllerTest extends WebTestCase
{
    /**
     * Test homepage with content
     */
    public function testHome()
    {
        $client = static::createClient();
        $crawler = $client->request(
            'GET',
            '/'
        );
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#demo-wrapper', "Chargement...");
    }

}
