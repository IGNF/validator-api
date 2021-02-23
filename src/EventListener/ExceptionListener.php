<?php

namespace App\EventListener;

use App\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Class for custom exception handling
 */
class ExceptionListener
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handles the exception caught by the listener
     *
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $throwable = $event->getThrowable();
        $event->setResponse($this->getErrorResponse($throwable));
    }

    /**
     * Returns the error response data corresponding to the exception caught by the listener
     *
     * @param \Throwable $throwable
     * @return JsonResponse
     */
    private function getErrorResponse(\Throwable $throwable)
    {
        $code = $throwable->getCode();

        $responseData = [
            'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'status' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
            'message' => "An internal error has occurred",
            'details' => [],
        ];

        if ($throwable instanceof ApiException) {
            $responseData['code'] = $code;
            $responseData['status'] = Response::$statusTexts[$code];
            $responseData['message'] = $throwable->getMessage();
            $responseData['details'] = $throwable->getDetails();
        }

        $this->logger->error("Exception[{exception}]: {message}", ['exception' => get_class($throwable), 'message' => $throwable->getMessage()]);

        return new JsonResponse($responseData, $responseData['code']);
    }
}
