<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SecurityController extends AbstractController
{
    /**
    * @Route(name="api_login", path="/api/login_check")
    * @return JsonResponse
    */
    public function api_login(): JsonResponse
    {

        $currentCustomer = $this->getUser(); 

        if (null === $currentCustomer) {
            return new JsonResponse([
            'message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'email' => $currentCustomer->getUserIdentifier(),
            'roles' => $currentCustomer->getRoles()
        ]);
    }

}
