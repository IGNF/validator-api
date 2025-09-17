<?php

namespace App\Controller;

use App\Entity\Validation;
use App\Exception\ApiException;
use App\Export\CsvReportWriter;
use App\Repository\ValidationRepository;
use App\Service\MimeTypeGuesserService;
use App\Service\ValidatorArgumentsService;
use App\Storage\ValidationsStorage;
use App\Validation\ValidationFactory;
use Aws\Api\Validator;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/validation")
 */
class ValidationController extends AbstractController
{
    public function __construct(
        private ValidationRepository $repository,
        private SerializerInterface $serializer,
        private ValidationsStorage $storage,
        private ValidatorArgumentsService $valArgsService,
        private ValidationFactory $validationFactory,
        private MimeTypeGuesserService $mimeTypeGuesserService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {

    }

    /**
     * @Route(
     *      "/",
     *      name="validator_api_disabled_routes",
     *      methods={"GET","DELETE","PATCH","PUT"}
     * )
     */
    public function disabledRoutes()
    {
        return new JsonResponse(['error' => "This route is not allowed"], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @Route(
     *      "/{uid}",
     *      name="validator_api_get_validation",
     *      methods={"GET"}
     * )
     */
    public function getValidation($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->toArray($validation), Response::HTTP_OK);
    }

    /**
     * @Route(
     *      "/{uid}/logs",
     *      name="validator_api_read_logs",
     *      methods={"GET"}
     * )
     */
    public function readConsole($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
            throw new ApiException("Validation has been archived", Response::HTTP_FORBIDDEN);
        }

        $outputDirectory = $this->storage->getOutputDirectory($validation);
        $filepath = $outputDirectory . '/validator-debug.log';

        $content = $this->storage->getStorage()->read($filepath);

        return new Response(
            $content,
            Response::HTTP_CREATED
        );
    }


    /**
     * @Route(
     *      "/{uid}/results.csv",
     *      name="validator_api_get_validation_csv",
     *      methods={"GET"}
     * )
     */
    public function getValidationCsv($uid, CsvReportWriter $csvWriter)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        $response = new StreamedResponse(function () use ($validation, $csvWriter) {
            $csvWriter->write($validation);
        });
        $response->headers->set('Content-Type', 'application/force-download');
        $filename = $uid . '-results.csv';
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * @Route(
     *      "/",
     *      name="validator_publi_upload_dataset",
     *      methods={"POST"}
     * )
     */
    public function uploadDataset(Request $request)
    {
        $request->request->all();

        $validation = $this->validationFactory->create($request);
        // TODO : check getClientOriginalName


        $this->entityManager->persist($validation);
        $this->entityManager->flush();

        return new JsonResponse(
            $this->serializer->toArray($validation),
            Response::HTTP_CREATED
        );
    }

    /**
     * @Route(
     *      "/{uid}",
     *      name="validator_api_delete_validation",
     *      methods={"DELETE"}
     * )
     */
    public function deleteValidation($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        $this->logger->info('Validation[{uid}] : removing all saved data...', [
            'uid' => $validation->getUid(),
            'datasetName' => $validation->getDatasetName(),
        ]);

        $em = $this->getDoctrine()->getManager();
        $em->remove($validation);
        $em->flush();

        // Delete from storage
        $uploadDirectory = $this->storage->getUploadDirectory($validation);
        if ($this->storage->getStorage()->directoryExists($uploadDirectory)) {
            $this->storage->getStorage()->deleteDirectory($uploadDirectory);
        }
        $outputDirectory = $this->storage->getOutputDirectory($validation);
        if ($this->storage->getStorage()->directoryExists($outputDirectory)) {
            $this->storage->getStorage()->deleteDirectory($outputDirectory);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(
     *      "/{uid}/files/normalized",
     *      name="validator_api_download_normalized_data",
     *      methods={"GET"}
     * )
     */
    public function downloadNormalizedData($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
            throw new ApiException("Validation has been archived", Response::HTTP_FORBIDDEN);
        }

        if ($validation->getStatus() == Validation::STATUS_ERROR) {
            throw new ApiException("Validation failed, no normalized data", Response::HTTP_FORBIDDEN);
        }

        if (in_array($validation->getStatus(), [Validation::STATUS_PENDING, Validation::STATUS_PROCESSING, Validation::STATUS_WAITING_ARGS])) {
            throw new ApiException("Validation hasn't been executed yet", Response::HTTP_FORBIDDEN);
        }

        $outputDirectory = $this->storage->getOutputDirectory($validation);
        $zipFilepath = $outputDirectory . $validation->getDatasetName() . '.zip';
        return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . "-normalized.zip");
    }

    /**
     * @Route(
     *      "/{uid}/files/source",
     *      name="validator_api_download_source_data",
     *      methods={"GET"}
     * )
     */
    public function downloadSourceData($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
            throw new ApiException("Validation has been archived", Response::HTTP_FORBIDDEN);
        }

        $uploadDirectory = $this->storage->getUploadDirectory($validation);
        $zipFilepath = $uploadDirectory . $validation->getDatasetName() . '.zip';
        return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . "-source.zip");
    }

    /**
     * Returns binary response of the specified file
     *
     * @param string $dirpath
     * @param string $filename
     * @return StreamedResponse
     */
    private function getDownloadResponse($filepath, $filename)
    {
        if (!$this->storage->getStorage()->has($filepath)) {
            throw new ApiException("Requested files not found for this validation", Response::HTTP_FORBIDDEN);
        }

        $stream = $this->storage->getStorage()->readStream($filepath);

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            exit();
        }, 200, [
            'Content-Transfer-Encoding',
            'binary',
            'Content-Type' => 'application/zip',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Content-Length' => fstat($stream)['size']
        ]);
    }
}
