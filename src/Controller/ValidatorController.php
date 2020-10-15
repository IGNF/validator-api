<?php

namespace App\Controller;

use App\Entity\Validation;
use Hoa\Console\Parser;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
            if (!$uid) {
                throw new BadRequestHttpException("Argument [uid] is missing");
            }

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
        } catch (BadRequestHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
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
            $file->move('./../' . $validation->getDirectory(), $validation->getDatasetName() . '.zip');

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

        try {
            $data = json_decode($request->getContent(), true);

            if (!$uid) {
                throw new BadRequestHttpException("Argument [uid] is missing");
            }

            $validation = $repository->findOneByUid($uid);
            if (!$validation) {
                throw new NotFoundHttpException("No record found for uid=$uid");

            }

            if ($validation->getStatus() == Validation::STATUS_ARCHIVED) {
                throw new AccessDeniedHttpException("Validation has been archived");
            }

            $arguments = $data['arguments'];
            if (!$arguments) {
                throw new BadRequestHttpException("Argument [arguments] is missing or invalid");
            }

            $arguments = $this->parseArguments($arguments);
            $arguments = json_encode($arguments, \JSON_UNESCAPED_UNICODE);

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

            return new JsonResponse($serializer->toArray($validation), JsonResponse::HTTP_OK);

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (BadRequestHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (AccessDeniedHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_FORBIDDEN);
        }

    }

    /**
     * @Route(
     *      "/{uid}",
     *      name="validator_api_delete_validation",
     *      methods={"DELETE"}
     * )
     */
    public function deleteValidation(Request $request, $uid)
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Validation::class);

        try {
            $data = json_decode($request->getContent(), true);

            if (!$uid) {
                throw new BadRequestHttpException("Argument [uid] is missing");
            }

            $validation = $repository->findOneByUid($uid);
            if (!$validation) {
                throw new NotFoundHttpException("No record found for uid=$uid");
            }

            $em->remove($validation);
            $em->flush();

            $filesystem = new FileSystem();

            if ($filesystem->exists('./../' . $validation->getDirectory())) {
                $filesystem->remove('./../' . $validation->getDirectory());
            }

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (BadRequestHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    private function parseArguments($arguments)
    {
        // removing multiple spaces
        $arguments = preg_replace('/\s+/', ' ', $arguments);
        $arguments = trim($arguments);

        // parsing command
        $parser = new Parser();
        $parser->parse($arguments);

        // checking for unauthorized options or command
        $inputs = $parser->getInputs();
        if (count($inputs) > 0) {
            throw new BadRequestHttpException(sprintf("Invalid arguments: [%s]", implode(' ', $inputs)));
        }

        $switches = $parser->getSwitches();
        return $switches;
    }
}
