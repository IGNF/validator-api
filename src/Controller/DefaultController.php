<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    /**
     * Root path for the API displaying swagger.
     *
     * @Route("/validator/", name="validator_api_root")
     */
    public function apidoc()
    {
        return $this->render('swagger.html.twig');
    }

}
