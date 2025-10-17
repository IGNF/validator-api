<?php

namespace App\Validation;

use App\Entity\Validation;
use App\Exception\ApiException;
use App\Storage\ValidationsStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\MimeTypeGuesserService;
use App\Service\ValidatorArgumentsService;
use Symfony\Component\Filesystem\Filesystem;

class ValidationFactory
{

    public function __construct(
        private LoggerInterface $logger,
        private MimeTypeGuesserService $mimeTypeGuesserService,
        private ValidatorArgumentsService $validatorArgumentsService,
        private EntityManagerInterface $entityManager,
        private ValidationsStorage $storage,
    ) {}

    /**
     * Create valiation based on request
     *
     * @param Request $request
     * @return Validation
     */
    public function create(Request $request): Validation
    {
        $uid = $this->generateUid();

        $this->logger->info('Validation[{uid}] : creating...', [
            'uid' => $uid,
        ]);

        $validation = new Validation();

        $validation->setUid($uid);
        $validation->setDateCreation(new \DateTime('now'));
        $validation->setStatus(Validation::STATUS_WAITING_ARGS);
        $validation->setProcessing(false);

        $arguments = $this->validatorArgumentsService->validate($request->request->get('args'));
        $validation->setArguments($arguments);

        $this->saveFile($request->files->get('dataset'), $validation);

        $this->entityManager->persist($validation);
        $this->entityManager->flush();

        return $validation;
    }

    function saveFile($file, Validation $validation)
    {
        // Ensure that input file is submitted
        if (!$file) {
            throw new ApiException("Argument [dataset] is missing", Response::HTTP_BAD_REQUEST);
        }

        // Ensure that input file is a ZIP file.
        $mimeType = $this->mimeTypeGuesserService->guessMimeType($file->getPathName());
        if ($mimeType !== 'application/zip') {
            throw new ApiException("Dataset must be in a compressed [.zip] file", Response::HTTP_BAD_REQUEST);
        }

        $datasetName = str_replace('.zip', '', $file->getClientOriginalName());
        $validation->setDatasetName($datasetName);

        // Save file to storage
        $this->storage->init($validation);
        $this->storage->write($file, true);
    }

    private function generateUid($length = 24)
    {
        $randomUid = "";

        for ($i = 0; $i < $length; $i++) {
            if (random_int(1, 2) == 1) {
                // a digit between 0 and 9
                $randomUid .= chr(random_int(48, 57));
            } else {
                // a lowercase letter between a and z
                $randomUid .= chr(random_int(97, 122));
            }
        }
        return $randomUid;
    }

}