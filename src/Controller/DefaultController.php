<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="validator_api_home")
     */
    public function home()
    {
        return new Response("// TODO : doc swagger");
    }
}
