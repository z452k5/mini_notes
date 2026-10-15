<?php
declare(strict_types=1);
namespace App\Shared\Http;
use App\Notes\Application\NoteNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array { return ['kernel.exception'=>['onException', 0]]; }
    public function onException(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        $status = match (true) {
            $error instanceof NoteNotFound => 404,
            $error instanceof \DomainException => 422,
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            default => 500,
        };
        if ($status >= 500) { error_log($error::class.': '.$error->getMessage()); }
        $message = $status >= 500 ? 'Сервис временно недоступен.' : $error->getMessage();
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
        $body = ['status'=>$status, 'message'=>$message];
        if ($error instanceof ValidationException) { $body['fields'] = $error->fields; }
        $event->setResponse(new JsonResponse(['error'=>$body], $status, $headers));
    }
}
