<?php

namespace App\Tests\Security;

use App\Security\OidcRolesExtractor;
use PHPUnit\Framework\TestCase;

class OidcRolesExtractorTest extends TestCase
{
    public function testGetRoles()
    {
        $extractor = new OidcRolesExtractor('client_validateur', 'admin');

        $this->assertEquals(['ROLE_USER'], $extractor->getRoles([]));
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $extractor->getRoles([
            'resource_access' => ['client_validateur' => ['roles' => ['admin', 'other']]],
        ]));
        // role of another client or realm role
        $this->assertEquals(['ROLE_USER'], $extractor->getRoles([
            'realm_access' => ['roles' => ['admin']],
            'resource_access' => ['another-client' => ['roles' => ['admin']]],
        ]));
        // admin role disabled
        $this->assertEquals(['ROLE_USER'], (new OidcRolesExtractor('client_validateur', ''))->getRoles([
            'resource_access' => ['client_validateur' => ['roles' => ['']]],
        ]));
    }

    public function testDecodeClaims()
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => 'abc', 'name' => 'é?>'])), '+/', '-_'), '=');
        $this->assertEquals(['sub' => 'abc', 'name' => 'é?>'], OidcRolesExtractor::decodeClaims('header.'.$payload.'.signature'));

        $this->assertEquals([], OidcRolesExtractor::decodeClaims('not-a-jwt'));
        $this->assertEquals([], OidcRolesExtractor::decodeClaims('a.!!!.c'));
    }
}
