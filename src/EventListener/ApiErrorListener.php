<?php

namespace App\EventListener;

use Styleguide\Contracts\CatalogueException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final readonly class ApiErrorListener
{
    public function __construct(private TokenStorageInterface $tokens, private LoggerInterface $logger)
    {
    }

    #[AsEventListener(event: 'kernel.exception', priority: 10)]
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $error = $event->getThrowable();
        $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
        if ($error instanceof AccessDeniedException || $error instanceof AuthenticationException) {
            $status = $this->tokens->getToken()?->getUser() ? 403 : 401;
        }
        if ($error instanceof \Symfony\Component\Serializer\Exception\ExtraAttributesException) $status = 422;
        $fields = $error instanceof CatalogueException ? $error->fieldErrors : [];
        for ($cause = $error; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof ValidationFailedException) {
                foreach ($cause->getViolations() as $violation) {
                    $fields[$violation->getPropertyPath()][] = $violation->getMessage();
                }
            }
        }
        $code = $error instanceof CatalogueException ? $error->errorCode : match ($status) {
            401 => 'authentication_required', 403 => 'forbidden', 404 => 'not_found', 412 => 'revision_mismatch',
            422 => 'validation_failed', default => 'request_failed',
        };
        $message = match (true) {
            $status >= 500 => 'The request could not be completed.',
            401 === $status => 'Sign in to access the private catalogue.',
            403 === $status => 'Permission or a valid CSRF token is required.',
            default => $error->getMessage(),
        };
        $requestId = (string) new Ulid();
        if ($status >= 500) {
            $this->logger->error('API request failed.', ['requestId' => $requestId, 'exceptionClass' => $error::class]);
        }
        $event->setResponse(new JsonResponse(['code' => $code, 'message' => $message, 'fieldErrors' => (object) $fields, 'requestId' => $requestId], $status, ['Cache-Control' => 'private, no-store', 'X-Request-Id' => $requestId]));
    }
}
