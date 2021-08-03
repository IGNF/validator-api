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
     * Get validation
     */
    public function testHome()
    {
        $client = static::createClient();
        $crawler = $client->request(
            'GET',
            '/'
        );
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#main', "Chargement...");
    }

    /**
     * Get validation without uid parameter
     */
    public function testApidoc()
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/',
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#swagger-ui-container', "Chargement...");
    }

}
