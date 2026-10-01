<?php

namespace App\Tests\Controller\Api;

use App\DataFixtures\ValidationsFixtures;
use App\Entity\Validation;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests for logs and reports (results.csv) endpoints.
 */
class ValidationFilesControllerTest extends WebTestCase
{
    /**
     * @var KernelBrowser
     */
    private $client;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = $this->getContainer()->get('doctrine')->getManager();

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->fixtures = $databaseTool->loadFixtures([
            ValidationsFixtures::class,
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        $fs->remove($this->getValidationsStorage()->getPath());
    }

    public function testLogsNotFound()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/logs');

        $this->assertStatusCode(404, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('No logs found for this validation', $json['message']);
    }

    /**
     * Logs are returned as plain text (never rendered as HTML by browsers).
     */
    public function testLogs()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);
        $storage = $this->getValidationsStorage();
        $storage->getStorage()->write(
            $storage->getOutputDirectory($validation).'validator-debug.log',
            '<script>alert(1)</script>'
        );

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/logs');

        $this->assertStatusCode(200, $this->client);
        $response = $this->client->getResponse();
        $this->assertEquals('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('<script>alert(1)</script>', $response->getContent());
    }

    public function testCsvNotExecuted()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/results.csv');

        $this->assertStatusCode(403, $this->client);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals("Validation hasn't been executed yet", $json['message']);
    }

    public function testCsvNoResults()
    {
        $validation = $this->updateValidation(ValidationsFixtures::VALIDATION_WITH_ARGS, Validation::STATUS_ERROR, null);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/results.csv');

        $this->assertStatusCode(404, $this->client);
    }

    public function testCsv()
    {
        $validation = $this->updateValidation(ValidationsFixtures::VALIDATION_WITH_ARGS, Validation::STATUS_FINISHED, [
            ['level' => 'ERROR', 'code' => 'FILE_EMPTY', 'message' => 'fichier vide', 'file' => 'a.csv'],
        ]);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/results.csv');

        $this->assertStatusCode(200, $this->client);
        $response = $this->client->getResponse();
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals(
            'attachment; filename='.$validation->getUid().'-results.csv',
            $response->headers->get('Content-Disposition')
        );
    }

    private function updateValidation(string $fixture, string $status, ?array $results): Validation
    {
        $uid = $this->getValidationFixture($fixture)->getUid();
        $validation = $this->em->getRepository(Validation::class)->findOneByUid($uid);
        $validation->setStatus($status);
        $validation->setResults($results);
        $this->em->flush();

        return $validation;
    }
}
