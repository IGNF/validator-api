<?php

namespace App\Controller\Api;

use App\Storage\ValidationsStorage;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * @Route("/health")
 */
class HealthController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private ValidationsStorage $storage){

    }

    /**
     * Checks for Database connection
     *
     * @Route("/db", name="health_db")
     */
    public function healthDB(EntityManagerInterface $entityManager)
    {
        $sql = "SELECT postgis_version() as postgis_version";
        $this->logger->info('get postgis version',[
            'sql' => $sql
        ]);
        try{
            $stmt = $entityManager->getConnection()->prepare($sql);
            $result = $stmt->executeQuery();
            return new JsonResponse($result->fetchOne(), Response::HTTP_OK);
        } catch (Exception $e){
            $this->logger->error((string) $e);
            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Checks for S3 connection
     *
     * @Route("/s3", name="health_s3")
     */
    public function healthS3()
    {
        $this->logger->info('list files from S3 bucket...');
        try {
            $files = $this->storage->getStorage()->listContents('.', false);
            $numFiles = count($files->toArray());
            return new JsonResponse('found '.$numFiles.' files', Response::HTTP_OK);
        } catch (Exception $e) {
            $this->logger->error((string) $e);
            return new JsonResponse("fail to list files from S3 bucket", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
