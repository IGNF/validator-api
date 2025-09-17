<?php

namespace App\Controller;

use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * Display demonstrator.
     *
     * @Route("/validations", name="validator_user_validations")
     */
    public function getValidations()
    {
        $data = [];
        $data[] = (['couleur'=>'rouge']);
        $data[] = (['jajaja']);
        return $this->json($data);
    }

}
