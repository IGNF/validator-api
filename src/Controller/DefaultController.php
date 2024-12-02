<?php

namespace App\Controller;

use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * Display demonstrator.
     *
     * @Route("/", name="validator_api_demo")
     */
    public function demo()
    {
        return $this->render('demo.html.twig');
    }

}
