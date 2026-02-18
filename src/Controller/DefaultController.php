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
}
