<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Security\OidcRolesExtractor;
use App\Tests\Security\FakeAccessTokenHandler;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\User\OidcUser;

/**
 * Tests the permissions on the validations when OIDC is enabled (OIDC_ENABLED=1).
 *
 * Browser users are logged in with loginUser() (session), the bearer tokens are handled by FakeAccessTokenHandler.
 */
class OidcAuthTest extends WebTestCase
{
    private const OWNER = 'owner-sub';
    private const OTHER = 'other-sub';
    private const ADMIN = 'admin-sub';

    /**
     * OIDC configuration of the tests (independent of the .env files, ex : on the CI).
     */
    private const ENV = [
        'OIDC_ENABLED' => '1',
        'OIDC_CLIENT_ID' => 'validator-test',
        'OIDC_ADMIN_ROLE' => 'admin',
        'OIDC_DEV_LOGIN' => '0',
    ];

    private const ARGS = ['srs' => 'EPSG:2154', 'model' => 'https://www.geoportail-urbanisme.gouv.fr/standard/cnig_SUP_PM3_2016.json'];

    /**
     * @var KernelBrowser
     */
    private $client;

    /**
     * Values of ENV before the test.
     *
     * @var array<string,mixed>
     */
    private array $previousEnv = [];

    public function setUp(): void
    {
        foreach (self::ENV as $name => $value) {
            $this->previousEnv[$name] = $_SERVER[$name] ?? null;
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $value) {
            if (null === $value) {
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }

        parent::tearDown();
    }

    public function testCreateRequiresAuthentication()
    {
        $this->client->request('POST', '/api/validations/', [], ['dataset' => $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3)]);
        $this->assertStatusCode(401, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Authentication required', $json['message']);
    }

    public function testCreateSetsOwner()
    {
        $this->client->loginUser($this->createUser(self::OWNER), 'main');
        $this->client->request('POST', '/api/validations/', [], ['dataset' => $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3)]);
        $this->assertStatusCode(201, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($json['can_edit']);
        // the owner is not exposed
        $this->assertArrayNotHasKey('owner', $json);

        $validation = $this->getEntityManager()->getRepository(Validation::class)->find($json['uid']);
        $this->assertEquals(self::OWNER, $validation->getOwner());
        $this->assertEquals(self::OWNER.'-name', $validation->getOwnerName());
    }

    public function testCreateWithBearer()
    {
        $this->client->request('POST', '/api/validations/', [], ['dataset' => $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3)], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FakeAccessTokenHandler::createToken(['sub' => self::OWNER, 'preferred_username' => 'bearer-user']),
        ]);
        $this->assertStatusCode(201, $this->client);
        // stateless : no session cookie
        $this->assertEmpty($this->client->getResponse()->headers->getCookies());

        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $validation = $this->getEntityManager()->getRepository(Validation::class)->find($json['uid']);
        $this->assertEquals(self::OWNER, $validation->getOwner());
        $this->assertEquals('bearer-user', $validation->getOwnerName());
    }

    public function testInvalidBearer()
    {
        $this->client->request('POST', '/api/validations/', [], ['dataset' => $this->createFakeUpload(ValidationsFixtures::FILENAME_SUP_PM3)], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid',
        ]);
        $this->assertStatusCode(401, $this->client);
    }

    public function testReadIsPublic()
    {
        $uid = $this->getOwnedValidation()->getUid();

        $this->client->request('GET', '/api/validations/'.$uid);
        $this->assertStatusCode(200, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($json['can_edit']);

        $this->client->loginUser($this->createUser(self::OWNER), 'main');
        $this->client->request('GET', '/api/validations/'.$uid);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($json['can_edit']);
    }

    public function testUpdateAndDeleteRequireOwner()
    {
        $uid = $this->getOwnedValidation()->getUid();

        // anonymous
        $this->patch($uid);
        $this->assertStatusCode(401, $this->client);
        $this->client->request('DELETE', '/api/validations/'.$uid);
        $this->assertStatusCode(401, $this->client);

        // another user
        $this->client->loginUser($this->createUser(self::OTHER), 'main');
        $this->patch($uid);
        $this->assertStatusCode(403, $this->client);
        $this->client->request('DELETE', '/api/validations/'.$uid);
        $this->assertStatusCode(403, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Only the owner of the validation can delete it', $json['message']);

        // owner
        $this->client->loginUser($this->createUser(self::OWNER), 'main');
        $this->patch($uid);
        $this->assertStatusCode(200, $this->client);
        $this->client->request('DELETE', '/api/validations/'.$uid);
        $this->assertStatusCode(204, $this->client);
    }

    public function testAdminCanUpdateAndDelete()
    {
        $uid = $this->getOwnedValidation()->getUid();

        $this->client->loginUser($this->createUser(self::ADMIN, true), 'main');
        $this->patch($uid);
        $this->assertStatusCode(200, $this->client);
        $this->client->request('DELETE', '/api/validations/'.$uid);
        $this->assertStatusCode(204, $this->client);
    }

    public function testValidationWithoutOwnerOnlyEditableByAdmin()
    {
        // created before OIDC was enabled
        $uid = $this->getValidationFixture(ValidationsFixtures::VALIDATION_NO_ARGS)->getUid();

        $this->client->loginUser($this->createUser(self::OWNER), 'main');
        $this->patch($uid);
        $this->assertStatusCode(403, $this->client);

        $this->client->loginUser($this->createUser(self::ADMIN, true), 'main');
        $this->patch($uid);
        $this->assertStatusCode(200, $this->client);
    }

    public function testAdminRoleFromBearer()
    {
        $uid = $this->getOwnedValidation()->getUid();
        $claims = ['sub' => self::ADMIN, 'resource_access' => [self::ENV['OIDC_CLIENT_ID'] => ['roles' => ['admin']]]];

        $this->client->request('DELETE', '/api/validations/'.$uid, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FakeAccessTokenHandler::createToken($claims),
        ]);
        $this->assertStatusCode(204, $this->client);
    }

    public function testAdminRoleOfAnotherClientIgnored()
    {
        $uid = $this->getOwnedValidation()->getUid();
        $claims = ['sub' => self::OTHER, 'resource_access' => ['another-client' => ['roles' => ['admin']]]];

        $this->client->request('DELETE', '/api/validations/'.$uid, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.FakeAccessTokenHandler::createToken($claims),
        ]);
        $this->assertStatusCode(403, $this->client);
    }

    public function testListValidations()
    {
        $this->getOwnedValidation();

        $this->client->request('GET', '/api/validations/');
        $this->assertStatusCode(401, $this->client);

        // users only see their validations (owner filter ignored)
        $this->client->loginUser($this->createUser(self::OWNER), 'main');
        $this->client->request('GET', '/api/validations/?owner='.self::OTHER);
        $this->assertStatusCode(200, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(1, $json['total']);
        $this->assertEquals(self::OWNER, $json['items'][0]['owner']);

        $this->client->loginUser($this->createUser(self::OTHER), 'main');
        $this->client->request('GET', '/api/validations/');
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(0, $json['total']);

        $this->client->loginUser($this->createUser(self::ADMIN, true), 'main');
        $this->client->request('GET', '/api/validations/?limit=2');
        $this->assertStatusCode(200, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $json['items']);
        $this->assertGreaterThan(2, $json['total']);
        $this->assertEquals(1, $json['page']);
        $this->assertEquals(2, $json['limit']);
        $this->assertArrayHasKey('owner_name', $json['items'][0]);
        $this->assertTrue($json['items'][0]['can_edit']);

        $this->client->request('GET', '/api/validations/?owner='.self::OWNER);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(1, $json['total']);
        $this->assertEquals(self::OWNER, $json['items'][0]['owner']);
        $this->assertEquals(self::OWNER.'-name', $json['items'][0]['owner_name']);

        $this->client->request('GET', '/api/validations/?status='.Validation::STATUS_ARCHIVED);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        foreach ($json['items'] as $item) {
            $this->assertEquals(Validation::STATUS_ARCHIVED, $item['status']);
        }
    }

    public function testDataDownloadRequiresOwner()
    {
        $uid = $this->getOwnedValidation()->getUid();
        $url = '/api/validations/'.$uid.'/files/source';

        $this->client->request('GET', $url);
        $this->assertStatusCode(401, $this->client);

        $this->client->loginUser($this->createUser(self::OTHER), 'main');
        $this->client->request('GET', $url);
        $this->assertStatusCode(403, $this->client);

        $this->client->loginUser($this->createUser(self::OWNER), 'main');
        $this->client->request('GET', $url);
        $this->assertStatusCode(200, $this->client);

        $this->client->loginUser($this->createUser(self::ADMIN, true), 'main');
        $this->client->request('GET', $url);
        $this->assertStatusCode(200, $this->client);

        // the reports stay public
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/api/validations/'.$uid.'/logs');
        $this->assertNotEquals(401, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginFailureRedirectsToTheDemo()
    {
        // no state in the session : authentication failure (no loop with /login)
        $this->client->request('GET', '/login_check?code=abc&state=def');
        $this->assertResponseRedirects('/?login_error=1');
    }

    public function testMe()
    {
        $this->client->request('GET', '/api/me');
        $this->assertStatusCode(200, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($json['enabled']);
        $this->assertFalse($json['authenticated']);
        $this->assertNull($json['user']);
        $this->assertEquals('/login', $json['login_url']);
        $this->assertEquals('/logout', $json['logout_url']);

        $this->client->loginUser($this->createUser(self::ADMIN, true), 'main');
        $this->client->request('GET', '/api/me');
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($json['authenticated']);
        $this->assertEquals(['name' => self::ADMIN.'-name', 'email' => self::ADMIN.'@example.org', 'is_admin' => true], $json['user']);
    }

    public function testCrossOriginRequestWithCookieRejected()
    {
        $uid = $this->getOwnedValidation()->getUid();
        $this->client->loginUser($this->createUser(self::OWNER), 'main');

        $this->client->request('DELETE', '/api/validations/'.$uid, [], [], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $this->assertStatusCode(403, $this->client);
        $this->client->request('DELETE', '/api/validations/'.$uid, [], [], ['HTTP_ORIGIN' => 'https://evil.example.org']);
        $this->assertStatusCode(403, $this->client);

        $this->client->request('DELETE', '/api/validations/'.$uid, [], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        $this->assertStatusCode(204, $this->client);
    }

    private function patch(string $uid): void
    {
        $this->client->request('PATCH', '/api/validations/'.$uid, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(self::ARGS));
    }

    private function createUser(string $sub, bool $admin = false): OidcUser
    {
        return new OidcUser(
            userIdentifier: $sub,
            roles: $admin ? [OidcRolesExtractor::ROLE_USER, OidcRolesExtractor::ROLE_ADMIN] : [OidcRolesExtractor::ROLE_USER],
            sub: $sub,
            preferredUsername: $sub.'-name',
            email: $sub.'@example.org',
        );
    }

    /**
     * Fixture (pending) owned by OWNER.
     */
    private function getOwnedValidation(): Validation
    {
        $em = $this->getEntityManager();
        $validation = $em->find(Validation::class, $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS)->getUid());
        $validation->setOwner(self::OWNER)->setOwnerName(self::OWNER.'-name');
        $em->flush();

        return $validation;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
