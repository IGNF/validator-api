<?php

namespace App\EventListener;

use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Class for custom exception handling
 */
class ExceptionListener
{
    /**
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $code = $exception->getCode();

        $responseData = [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'status' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
            'message' => $exception->getMessage(),
            'details' => [],
        ];

        if ($exception instanceof ApiException) {
            $responseData['details'] = $exception->getDetails();
            $responseData['code'] = $code;
            $responseData['status'] = Response::$statusTexts[$code];
        }

        $event->setResponse(new JsonResponse($responseData, $responseData['code']));
    }
}
