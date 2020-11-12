<?php

namespace App\Controller;

use App\Entity\Validation;
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
    private $projectDir;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
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
            // this will never happen because without the uid in url path, the request becomes a GET request at the address /validator/validations, which is not allowed and will raise a 405 HTTP_METHOD_NOT_ALLOWED
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

        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                throw new BadRequestHttpException("Request body must be a valid JSON string");
            }

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

            $arguments = $this->checkArguments($data);
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

            if ($filesystem->exists($this->projectDir . '/' . $validation->getDirectory())) {
                $filesystem->remove($this->projectDir . '/' . $validation->getDirectory());
            }

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);

        } catch (NotFoundHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (BadRequestHttpException $ex) {
            return new JsonResponse(['error' => $ex->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Returns the arguments in an associated array if they pass verification
     *
     * @param string $arguments
     * @return array[string]
     * @throws BadRequestHttpException
     */
    private function checkArguments($arguments)
    {
        $validatorArguments = $this->loadValidatorArguments();

        // checking for required arguments
        foreach ($validatorArguments as $arg) {
            if ($arg['required']) {
                if (!array_key_exists($arg['name'], $arguments)) {
                    throw new BadRequestHttpException(sprintf("Argument [%s] is required", $arg['name']));
                }
            }
        }

        foreach ($arguments as $argName => $arg) {
            // checking for unknown arguments
            if (!array_key_exists($argName, $validatorArguments)) {
                throw new BadRequestHttpException(sprintf("Argument [%s] is unknown", $argName));
            }

            // checking for boolean arguments
            if ($validatorArguments[$argName]['type'] == 'boolean' && !\is_bool($arg)) {
                throw new BadRequestHttpException(sprintf("Argument [%s] must be a valid boolean value", $argName));
            }
        }

        // checking srs argument
        $projections = $this->loadValidatorProjections();
        if (!in_array($arguments['srs'], $projections)) {
            throw new BadRequestHttpException(sprintf("Projection [%s] is not accepted by validator", $arguments['srs']));
        }

        return $arguments;
    }

    /**
     * Returns the accepted validator arguments in an array
     *
     * @return array[mixed]
     */
    private function loadValidatorArguments()
    {
        // reading from arguments config file
        $temp = \json_decode(\file_get_contents(dirname(__FILE__) . '/../../resources/validator-arguments.json'), true);
        $validatorArguments = [];

        foreach ($temp as $value) {
            $validatorArguments[$value['name']] = $value;
        }
        unset($temp);

        return $validatorArguments;
    }

    /**
     * Returns the projections accepted by validator in an array
     *
     * @return array[mixed]
     */
    private function loadValidatorProjections()
    {
        // reading from projections config file
        $temp = \json_decode(\file_get_contents(dirname(__FILE__) . '/../../resources/projection.json'), true);
        $projections = [];

        foreach ($temp as $value) {
            \array_push($projections, $value['code']);
        }
        unset($temp);

        return $projections;
    }
}
