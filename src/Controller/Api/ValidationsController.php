<?php

namespace App\Controller\Api;

use App\Entity\Validation;
use App\Exception\ApiException;
use App\Repository\ValidationRepository;
use App\Service\MimeTypeGuesserService;
use App\Service\ValidatorArgumentsService;
use App\Storage\ValidationsStorage;
use App\Validation\ValidationManager;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\ArrayTransformerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/validations')]
class ValidationsController extends AbstractController
{
    public function __construct(
        private ValidationRepository $repository,
        private ArrayTransformerInterface $serializer,
        private ValidationsStorage $storage,
        private ValidatorArgumentsService $valArgsService,
        private MimeTypeGuesserService $mimeTypeGuesser,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private ValidationManager $validationManager,
    ) {}

    #[Route('/', name: 'validator_api_disabled_routes', methods: ['GET', 'DELETE', 'PATCH', 'PUT'])]
    public function disabledRoutes()
    {
        return new JsonResponse(['error' => 'This route is not allowed'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    #[Route('/{uid}', name: 'validator_api_get_validation', methods: ['GET'])]
    public function getValidation($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->toArray($validation), Response::HTTP_OK);
    }

    #[Route('/', name: 'validator_api_upload_dataset', methods: ['POST'])]
    public function uploadDataset(Request $request)
    {
        $files = $request->files;
        /*
         * Ensure that input file is submitted
         */
        $file = $files->get('dataset');
        if (!$file) {
            throw new ApiException('Argument [dataset] is missing', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('handle new upload...', [
            'path_name' => $file->getPathName(),
            'client_original_name' => $file->getClientOriginalName(),
        ]);

        /*
         * Ensure that input file is a ZIP file.
         */
        $mimeType = $this->mimeTypeGuesser->guessMimeType($file->getPathName());
        if ('application/zip' !== $mimeType) {
            throw new ApiException('Dataset must be in a compressed [.zip] file', Response::HTTP_BAD_REQUEST);
        }

        /*
         * Ensure that the dataset name (used in file paths) is safe.
         */
        $datasetName = preg_replace('/\.zip$/i', '', basename($file->getClientOriginalName()));
        if (!Validation::isValidDatasetName($datasetName)) {
            throw new ApiException(sprintf(
                'Dataset filename is not valid (name without .zip must match %s)',
                Validation::REGEXP_DATASET_NAME
            ), Response::HTTP_BAD_REQUEST);
        }

        /*
         * create validation and same validation
         */
        $validation = new Validation();
        $validation->setDatasetName($datasetName);

        // Save file to storage
        $uploadDirectory = $this->storage->getUploadDirectory($validation);
        if (!$this->storage->getStorage()->directoryExists($uploadDirectory)) {
            $this->storage->getStorage()->createDirectory($uploadDirectory);
        }
        $fileLocation = $uploadDirectory . $validation->getDatasetName() . '.zip';
        if ($this->storage->getStorage()->fileExists($fileLocation)) {
            $this->storage->getStorage()->delete($fileLocation);
        }
        $stream = fopen($file->getRealPath(), 'r+');
        $this->storage->getStorage()->writeStream($fileLocation, $stream);
        fclose($stream);

        if (file_exists($file->getRealPath())) {
            $this->logger->debug('Validation[{uid}] : rm -rf {path}...', [
                'uid' => $validation->getUid(),
                'path' => $file->getRealPath(),
            ]);
            unlink($file->getRealPath());
        }

        $this->entityManager->persist($validation);
        $this->entityManager->flush();
        $this->entityManager->refresh($validation);

        return new JsonResponse(
            $this->serializer->toArray($validation),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{uid}', name: 'validator_api_update_arguments', methods: ['PATCH'])]
    public function updateArguments(Request $request, $uid)
    {
        $data = $request->getContent();

        if (!json_decode($data, true)) {
            throw new ApiException('Request body must be a valid JSON string', Response::HTTP_BAD_REQUEST);
        }

        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        if (Validation::STATUS_ARCHIVED == $validation->getStatus()) {
            throw new ApiException('Validation has been archived', Response::HTTP_FORBIDDEN);
        }
        $this->denyIfProcessing($validation);
        // TODO : review (json_decode in this method and inside of validate)
        $arguments = $this->valArgsService->validate($data);

        // checks if we need to keep data
        $validation->setDeleteData($arguments['delete-data']);
        unset($arguments['delete-data']);

        $validation->reset();
        $validation->setArguments($arguments);
        $validation->setStatus(Validation::STATUS_PENDING);

        $this->entityManager->flush();
        $this->entityManager->refresh($validation);

        return new JsonResponse(
            $this->serializer->toArray($validation),
            Response::HTTP_OK
        );
    }

    #[Route('/{uid}', name: 'validator_api_delete_validation', methods: ['DELETE'])]
    public function deleteValidation($uid)
    {
        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);
        }

        $this->denyIfProcessing($validation);

        $this->validationManager->delete($validation);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * A validation can't be modified or deleted while a worker is processing it.
     *
     * @throws ApiException
     */
    private function denyIfProcessing(Validation $validation): void
    {
        if (Validation::STATUS_PROCESSING === $validation->getStatus()) {
            throw new ApiException('Validation is being processed, retry later', Response::HTTP_CONFLICT);
        }
    }
}
