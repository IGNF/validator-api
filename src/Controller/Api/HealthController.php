<?php

namespace App\Controller\Api;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use League\Flysystem\FilesystemOperator;


/**
 * @Route("/health")
 */
class HealthController extends AbstractController
{
    /**
     * Checks for Database connection
     *
     * @Route("/db", name="heatlh_db")
     */
    public function headthDB(EntityManager $entityManager)
    {
        try{
            $entityManager->getConnection()->connect();
            $check = $entityManager->getConnection()->isConnected();
            $httpCode = $check ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
            return new JsonResponse($check, $httpCode);
        } catch (Exception $e){
            return new JsonResponse(False, Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Checks for S3 connection
     *
     * @Route("/s3", name="heatlh_s3")
     */
    public function heatlhS3(FilesystemOperator $dataStorage)
    {
        try {
            $files = $dataStorage->listContents('.', TRUE);
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
