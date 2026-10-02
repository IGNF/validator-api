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
 * Tests for logs and reports (results.csv, report) endpoints.
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

    public function testReportNotExecuted()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/report');

        $this->assertStatusCode(403, $this->client);
    }

    /**
     * Printable HTML report, printed to PDF by the browser.
     */
    public function testReport()
    {
        $longMessage = 'chemin '.str_repeat('Donnees_geographiques/', 20).'PM3_ACTE_SUP.dbf';
        $validation = $this->updateValidation(ValidationsFixtures::VALIDATION_WITH_ARGS, Validation::STATUS_FINISHED, [
            ['level' => 'ERROR', 'code' => 'FILE_EMPTY', 'message' => 'fichier vide <b>é</b>', 'file' => 'a.csv'],
            ['level' => 'WARNING', 'code' => 'ATTRIBUTE_UNEXPECTED', 'message' => $longMessage],
            ['level' => 'INFO', 'code' => 'TABLE_LOADED', 'message' => 'table chargée'],
        ]);

        $crawler = $this->client->request('GET', '/api/validations/'.$validation->getUid().'/report');

        $this->assertStatusCode(200, $this->client);
        $response = $this->client->getResponse();
        $this->assertEquals('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSelectorTextContains('title', 'Rapport de validation - '.$validation->getDatasetName());
        $this->assertSelectorTextContains('.banner', 'Document Invalide');
        $this->assertEquals(['ERROR', 'WARNING', 'INFO'], $crawler->filter('h2.group-title')->each(fn ($node) => $node->text()));
        // messages are escaped
        $this->assertStringContainsString('fichier vide &lt;b&gt;é&lt;/b&gt;', $response->getContent());
        $this->assertStringContainsString($longMessage, $response->getContent());
        // print script served by the API (no inline script)
        $this->assertEquals(['/js/report.js'], $crawler->filter('script')->each(fn ($node) => $node->attr('src')));
    }

    /**
     * Zip pre-validation errors (file, code, message) have no level.
     */
    public function testReportZipErrors()
    {
        $validation = $this->updateValidation(ValidationsFixtures::VALIDATION_WITH_ARGS, Validation::STATUS_ERROR, [
            ['file' => 'data/run.exe', 'code' => 'FILE_EXTENSION_NOT_ALLOWED', 'message' => "file extension is not allowed ('run.exe')"],
        ]);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/report');

        $this->assertStatusCode(200, $this->client);
        $this->assertSelectorTextContains('h2.group-title', 'ERROR');
        $this->assertSelectorTextContains('.file', 'data/run.exe');
    }

    /**
     * The former PDF report redirects to the printable report.
     */
    public function testPdfRedirectsToReport()
    {
        $validation = $this->getValidationFixture(ValidationsFixtures::VALIDATION_WITH_ARGS);

        $this->client->request('GET', '/api/validations/'.$validation->getUid().'/results.pdf');

        $this->assertResponseRedirects('/api/validations/'.$validation->getUid().'/report?print=1');
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
