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

class ValidationFactory
{

    private LoggerInterface $logger;
    private MimeTypeGuesserService $mimeTypeGuesserService;
    private ValidatorArgumentsService $validatorArgumentsService;

    public function __construct(
        EntityManagerInterface $em,
        ValidationsStorage $storage,
        ValidatorCLI $validatorCli,
        ZipArchiveValidator $zipArchiveValidator,
        LoggerInterface $logger,
        MimeTypeGuesserService $mimeTypeGuesserService,
        ValidatorArgumentsService $validatorArgumentsService
    ) {
        $this->em = $em;
        $this->storage = $storage;
        $this->validatorCli = $validatorCli;
        $this->zipArchiveValidator = $zipArchiveValidator;
        $this->logger = $logger;
        $this->mimeTypeGuesserService -> $mimeTypeGuesserService;
        $this->validatorArgumentsService -> $validatorArgumentsService;
    }

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
        $validation->setStatus(Validation::STATUS_PENDING);

        $arguments = $this->validatorArgumentsService->validate($request->request->get('args'));
        $validation->setArguments($arguments);

        $files = $request->files;

        //Ensure that input file is submitted
        $file = $files->get('dataset');
        if (!$file) {
            throw new ApiException("Argument [dataset] is missing", Response::HTTP_BAD_REQUEST);
        }

        //Ensure that input file is a ZIP file.
        $mimeType = $this->mimeTypeGuesserService->guessMimeType($file->getPathName());
        if ($mimeType !== 'application/zip') {
            throw new ApiException("Dataset must be in a compressed [.zip] file", Response::HTTP_BAD_REQUEST);
        }

        $validation->setPathName($file->getPathName());

        $datasetName = str_replace('.zip', '', $file->getClientOriginalName());
        $validation->setDatasetName($datasetName);

        return $validation;
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