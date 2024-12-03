<?php

namespace App\Controller\Api;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use League\Flysystem\FilesystemOperator;


/**
 * @Route("/health")
 */
class HealthController extends AbstractController
{

    /**
     * @var FileSystemOperator
     */
    private $dataStorage;

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(
        FilesystemOperator $dataStorage,
        EntityManagerInterface $entityManager
    )
    {
        $this->dataStorage = $dataStorage;
        $this->entityManager = $entityManager;
    }
    /**
     * Checks for Database connection
     *
     * @Route("/db", name="health_db")
     */
    public function healthDB()
    {
        try{
            $this->entityManager->getConnection()->connect();
            $check = $this->entityManager->getConnection()->isConnected();
            $httpCode = $check ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
            return new JsonResponse($check, $httpCode);
        } catch (Exception $e){
            return new JsonResponse(False, Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Checks for S3 connection
     *
     * @Route("/s3", name="health_s3")
     */
    public function healthS3()
    {
        try {
            $files = $this->dataStorage->listContents('.', TRUE);
            $response = [];
            foreach ($files as $file) {
                $response[] = $file->path();
            }
            return new JsonResponse($response, Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse(False, Response::HTTP_NOT_FOUND);
        }
    }

}
