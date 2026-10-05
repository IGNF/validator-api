<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Returns a 401 error (same format as App\EventListener\ExceptionListener) instead of redirecting to the login page
 * when an authentication is required.
 */
class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'code' => Response::HTTP_UNAUTHORIZED,
            'status' => Response::$statusTexts[Response::HTTP_UNAUTHORIZED],
            'message' => 'Authentication required',
            'details' => [],
        ], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Bearer',
        ]);
    }
}
