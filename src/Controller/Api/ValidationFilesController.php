<?php

namespace App\Controller\Api;

use App\Entity\Validation;
use App\Exception\ApiException;
use App\Export\CsvReportWriter;
use App\Export\PdfReportWriter;
use App\Repository\ValidationRepository;
use App\Storage\ValidationsStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/validations')]
class ValidationFilesController extends AbstractController
{
    public function __construct(
        private ValidationRepository $repository,
        private ValidationsStorage $storage,
        private bool $dataDownloadEnabled,
    ) {}

    #[Route('/{uid}/logs', name: 'validator_api_read_logs', methods: ['GET'])]
    public function readConsole($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if (Validation::STATUS_ARCHIVED == $validation->getStatus()) {
            throw new ApiException('Validation has been archived', Response::HTTP_FORBIDDEN);
        }

        $filepath = $this->storage->getOutputDirectory($validation) . 'validator-debug.log';
        if (!$this->storage->getStorage()->fileExists($filepath)) {
            throw new ApiException('No logs found for this validation', Response::HTTP_NOT_FOUND);
        }

        // text/plain + nosniff : the log may contain values from the dataset (no HTML rendering)
        return new Response($this->storage->getStorage()->read($filepath), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route('/{uid}/results.csv', name: 'validator_api_get_validation_csv', methods: ['GET'])]
    public function getValidationCsv($uid, CsvReportWriter $csvWriter)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }
        $this->denyIfNoResults($validation);

        $response = new StreamedResponse(function () use ($validation, $csvWriter) {
            $csvWriter->write($validation);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $validation->getUid() . '-results.csv'
        ));

        return $response;
    }

    #[Route('/{uid}/results.pdf', name: 'validator_api_get_validation_pdf', methods: ['GET'])]
    public function generatePdf($uid, PdfReportWriter $writer,
    ): Response {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }
        $this->denyIfNoResults($validation);

        $pdf = $writer->generate($validation);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $this->makeDisposition(HeaderUtils::DISPOSITION_INLINE, $validation->getDatasetName() . '.pdf'),
        ]);
    }

    #[Route('/{uid}/files/normalized', name: 'validator_api_download_normalized_data', methods: ['GET'])]
    public function downloadNormalizedData($uid)
    {
        $this->denyIfDataDownloadDisabled();

        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if (Validation::STATUS_ARCHIVED == $validation->getStatus()) {
            throw new ApiException('Validation has been archived', Response::HTTP_FORBIDDEN);
        }

        if (Validation::STATUS_ERROR == $validation->getStatus()) {
            throw new ApiException('Validation failed, no normalized data', Response::HTTP_FORBIDDEN);
        }

        if (in_array($validation->getStatus(), [Validation::STATUS_PENDING, Validation::STATUS_PROCESSING, Validation::STATUS_WAITING_ARGS])) {
            throw new ApiException("Validation hasn't been executed yet", Response::HTTP_FORBIDDEN);
        }

        $outputDirectory = $this->storage->getOutputDirectory($validation);
        $zipFilepath = $outputDirectory . $validation->getDatasetName() . '.zip';

        return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . '-normalized.zip');
    }

    #[Route('/{uid}/files/source', name: 'validator_api_download_source_data', methods: ['GET'])]
    public function downloadSourceData($uid)
    {
        $this->denyIfDataDownloadDisabled();

        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if (Validation::STATUS_ARCHIVED == $validation->getStatus()) {
            throw new ApiException('Validation has been archived', Response::HTTP_FORBIDDEN);
        }

        $uploadDirectory = $this->storage->getUploadDirectory($validation);
        $zipFilepath = $uploadDirectory . $validation->getDatasetName() . '.zip';

        return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . '-source.zip');
    }

    /**
     * Rejects source/normalized data downloads unless DATA_DOWNLOAD_ENABLED is set.
     * Checked before the uid lookup so that disabled endpoints do not reveal which uids exist.
     */
    private function denyIfDataDownloadDisabled(): void
    {
        if (!$this->dataDownloadEnabled) {
            throw new ApiException('Data download is disabled', Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Rejects report generation for validations without results (not executed yet or failed without report).
     */
    private function denyIfNoResults(Validation $validation): void
    {
        if (in_array($validation->getStatus(), [Validation::STATUS_PENDING, Validation::STATUS_PROCESSING, Validation::STATUS_WAITING_ARGS])) {
            throw new ApiException("Validation hasn't been executed yet", Response::HTTP_FORBIDDEN);
        }
        if (null === $validation->getResults()) {
            throw new ApiException('No results found for this validation', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Content-Disposition header with an escaped filename (with an ASCII fallback).
     */
    private function makeDisposition(string $disposition, string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename);

        return HeaderUtils::makeDisposition($disposition, $filename, $fallback);
    }

    /**
     * Returns binary response of the specified file.
     *
     * @param string $filename
     *
     * @return StreamedResponse
     */
    private function getDownloadResponse($filepath, $filename)
    {
        if (!$this->storage->getStorage()->has($filepath)) {
            throw new ApiException('Requested files not found for this validation', Response::HTTP_NOT_FOUND);
        }

        $stream = $this->storage->getStorage()->readStream($filepath);

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => $this->makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'Content-Length' => $this->storage->getStorage()->fileSize($filepath),
        ]);
    }
}
