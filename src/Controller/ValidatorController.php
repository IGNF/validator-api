<?php

namespace App\Controller;

use App\Entity\Validation;
use App\Exception\ApiException;
use App\Service\ValidatorArgumentsService;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/validator/validations")
 */
class ValidatorController extends AbstractController
{
    private $projectDir;
    private $valArgsService;

    public function __construct($projectDir, ValidatorArgumentsService $valArgsService)
    {
        $this->projectDir = $projectDir;
        $this->valArgsService = $valArgsService;
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
        return new JsonResponse(['error' => "This route is not allowed"], JsonResponse::HTTP_METHOD_NOT_ALLOWED);
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
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Validation::class);

        try {
            $validation = $repository->findOneByUid($uid);
            if (!$validation) {
                throw new NotFoundHttpException("No record found for uid=$uid");

            }

            $serializer = SerializerBuilder::create()
                ->setSerializationContextFactory(function () {
                    return SerializationContext::create()
                        ->setSerializeNull(true)
                        ->enableMaxDepthChecks();
                })->build();

            return new JsonResponse($serializer->toArray($validation), JsonResponse::HTTP_OK);

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }
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
        $em = $this->getDoctrine()->getManager();
        $validation = new Validation();

        try {
            $files = $request->files;

            $file = $files->get('dataset');
            if (!$file) {
                throw new BadRequestHttpException("Argument [dataset] is missing");
            }

            $mimeTypes = new MimeTypes();

            if ($mimeTypes->guessMimeType($file->getPathName()) != 'application/zip') {
                throw new BadRequestHttpException("Dataset must be in a compressed [.zip] file");
            }

            $validation->setDatasetName(str_replace('.zip', '', $file->getClientOriginalName()));
            $file->move($this->projectDir . '/' . $validation->getDirectory(), $validation->getDatasetName() . '.zip');

            $em->persist($validation);
            $em->flush();
            $em->refresh($validation);

            $serializer = SerializerBuilder::create()
                ->setSerializationContextFactory(function () {
                    return SerializationContext::create()
                        ->setSerializeNull(true)
                        ->enableMaxDepthChecks();
                })->build();

            return new JsonResponse($serializer->toArray($validation), JsonResponse::HTTP_CREATED);

        } catch (BadRequestHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
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
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Validation::class);

        $data = $request->getContent();

        if (!json_decode($data, true)) {
            throw new ApiException("Request body must be a valid JSON string", Response::HTTP_BAD_REQUEST);
        }

        $validation = $repository->findOneByUid($uid);
        if (!$validation) {
            throw new ApiException("No record found for uid=$uid", Response::HTTP_NOT_FOUND);

        }

        if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
            throw new ApiException("Validation has been archived", Response::HTTP_FORBIDDEN);
        }

        $arguments = $this->valArgsService->validate($data);

        $validation->reset();
        $validation->setArguments($arguments);
        $validation->setStatus(Validation::STATUS_PENDING);

        $em->flush();
        $em->refresh($validation);

        $serializer = SerializerBuilder::create()
            ->setSerializationContextFactory(function () {
                return SerializationContext::create()
                    ->setSerializeNull(true)
                    ->enableMaxDepthChecks();
            })->build();

        return new JsonResponse($serializer->toArray($validation), Response::HTTP_OK);
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
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Validation::class);

        try {
            $validation = $repository->findOneByUid($uid);
            if (!$validation) {
                throw new NotFoundHttpException("No record found for uid=$uid");
            }

            $em->remove($validation);
            $em->flush();

            $filesystem = new FileSystem();

            if ($filesystem->exists($this->projectDir . '/' . $validation->getDirectory())) {
                $filesystem->remove($this->projectDir . '/' . $validation->getDirectory());
            }

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }
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
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Validation::class);

        try {
            $validation = $repository->findOneByUid($uid);
            if (!$validation) {
                throw new NotFoundHttpException("No record found for uid=$uid");
            }

            if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
                throw new AccessDeniedHttpException("Validation has been archived");
            }

            if ($validation->getStatus() == Validation::STATUS_ERROR) {
                throw new AccessDeniedHttpException("Validation failed, no normalized data");
            }

            if (in_array($validation->getStatus(), [Validation::STATUS_PENDING, Validation::STATUS_PROCESSING, Validation::STATUS_WAITING_ARGS])) {
                throw new AccessDeniedHttpException("Validation hasn't been executed yet");
            }

            $zipFilepath = $this->projectDir . '/' . $validation->getDirectory() . '/validation/' . $validation->getDatasetName() . '.zip';
            return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . "-normalized.zip");

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (AccessDeniedHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_FORBIDDEN);
        }
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
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Validation::class);

        try {
            $validation = $repository->findOneByUid($uid);
            if (!$validation) {
                throw new NotFoundHttpException("No record found for uid=$uid");
            }

            if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
                throw new AccessDeniedHttpException("Validation has been archived");
            }

            $zipFilepath = $this->projectDir . '/' . $validation->getDirectory() . '/' . $validation->getDatasetName() . '.zip';
            return $this->getDownloadResponse($zipFilepath, $validation->getDatasetName() . "-source.zip");

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (AccessDeniedHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_FORBIDDEN);
        }
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
            throw new AccessDeniedHttpException("Requested files not found for this validation");
        }

        $response = new BinaryFileResponse($filepath);
        $mimeTypes = new MimeTypes();

        $response->headers->set('Content-Type', $mimeTypes->guessMimeType($filepath));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }
}
