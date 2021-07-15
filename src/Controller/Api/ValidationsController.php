<?php

namespace App\Controller\Api;

use App\Entity\Validation;
use App\Exception\ApiException;
use App\Repository\ValidationRepository;
use App\Service\MimeTypeGuesserService;
use App\Service\ValidatorArgumentsService;
use App\Storage\ValidationsStorage;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/validator/validations")
 */
class ValidationsController extends AbstractController
{

    /**
     * @var ValidationsStorage
     */
    private $storage;

    /**
     * Helper class to validate arguments.
     *
     * @var ValidatorArgumentsService
     */
    private $valArgsService;

    /**
     * @var MimeTypeGuesserService
     */
    private $mimeTypeGuesserService;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ValidationRepository
     */
    private $repository;

    public function __construct(
        ValidationRepository $repository,
        SerializerInterface $serializer,
        ValidationsStorage $storage,
        ValidatorArgumentsService $valArgsService,
        MimeTypeGuesserService $mimeTypeGuesserService
    )
    {
        $this->repository = $repository;
        $this->storage = $storage;
        $this->valArgsService = $valArgsService;
        $this->mimeTypeGuesserService = $mimeTypeGuesserService;
        $this->serializer = $serializer;
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
     *      "/",
     *      name="validator_api_upload_dataset",
     *      methods={"POST"}
     * )
     */
    public function uploadDataset(Request $request)
    {
        $files = $request->files;
        /*
         * Ensure that input file is submitted
         */
        $file = $files->get('dataset');
        if (!$file) {
            throw new ApiException("Argument [dataset] is missing", Response::HTTP_BAD_REQUEST);
        }
        /*
         * Ensure that input file is a ZIP file.
         */
        $mimeType = $this->mimeTypeGuesserService->guessMimeType($file->getPathName());
        if ($mimeType !== 'application/zip') {
            throw new ApiException("Dataset must be in a compressed [.zip] file", Response::HTTP_BAD_REQUEST);
        }

        /*
         * create validation and same validation
         */
        $validation = new Validation();
        // TODO : check getClientOriginalName
        $datasetName = str_replace('.zip', '', $file->getClientOriginalName());
        $validation->setDatasetName($datasetName);
        $file->move(
            $this->storage->getDirectory($validation),
            $validation->getDatasetName() . '.zip'
        );

        $em = $this->getDoctrine()->getManager();
        $em->persist($validation);
        $em->flush();
        $em->refresh($validation);

        return new JsonResponse(
            $this->serializer->toArray($validation),
            Response::HTTP_CREATED
        );
    }

    /**
     * @Route(
     *      "/{uid}",
     *      name="validator_api_update_arguments",
     *      methods={"PATCH"}
     * )
     */
    public function updateArguments(Request $request, $uid)
    {
        $data = $request->getContent();

        if (!json_decode($data, true)) {
            throw new ApiException("Request body must be a valid JSON string", Response::HTTP_BAD_REQUEST);
        }

        $validation = $this->repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);

        }

        if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
            throw new ApiException("Validation has been archived", Response::HTTP_FORBIDDEN);
        }
        // TODO : review (json_decode in this method and inside of validate)
        $arguments = $this->valArgsService->validate($data);

        $validation->reset();
        $validation->setArguments($arguments);
        $validation->setStatus(Validation::STATUS_PENDING);

        $em = $this->getDoctrine()->getManager();
        $em->flush();
        $em->refresh($validation);

        return new JsonResponse(
            $this->serializer->toArray($validation),
            Response::HTTP_OK
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

        $em = $this->getDoctrine()->getManager();
        $em->remove($validation);
        $em->flush();

        $fs = new FileSystem();
        $validationDirectory = $this->storage->getDirectory($validation) ;
        if ($fs->exists($validationDirectory)) {
            $fs->remove($validationDirectory);
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

        $validationDirectory = $this->storage->getDirectory($validation) ;
        $zipFilepath = $validationDirectory . '/validation/' . $validation->getDatasetName() . '.zip';
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

        $validationDirectory = $this->storage->getDirectory($validation) ;
        $zipFilepath = $validationDirectory . '/' . $validation->getDatasetName() . '.zip';
        return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . "-source.zip");
    }

    /**
     * Returns binary response of the specified file
     *
     * @param string $filepath
     * @param string $filename
     * @return BinaryFileResponse
     */
    private function getDownloadResponse($filepath, $filename)
    {
        $filesystem = new FileSystem();
        if (!$filesystem->exists($filepath)) {
            throw new ApiException("Requested files not found for this validation", Response::HTTP_FORBIDDEN);
        }

        $response = new BinaryFileResponse($filepath);
        $mimeType = $this->mimeTypeGuesserService->guessMimeType($filepath);

        $response->headers->set('Content-Type', $mimeType);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }
}
