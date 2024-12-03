<?php

namespace App\Controller\Api;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManager;
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
     * Checks for Database connection
     *
     * @Route("/db", name="health_db")
     */
    public function healthDB(EntityManagerInterface $entityManager)
    {
        $sql = "SELECT postgis_version() as postgis_version";
        try{
            $stmt = $entityManager->getConnection()->prepare($sql);
            $result = $stmt->executeQuery();
            return new JsonResponse($result->fetchOne(), Response::HTTP_OK);
        } catch (Exception $e){
            return new JsonResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Checks for S3 connection
     *
     * @Route("/s3", name="health_s3")
     */
    public function healthS3(FilesystemOperator $dataStorage)
    {
        try {
            $files = $dataStorage->listContents('.', false);
            $numFiles = count($files->toArray());
            return new JsonResponse('found '.$numFiles.' files', Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse(False, Response::HTTP_NOT_FOUND);
        }
    }

}
