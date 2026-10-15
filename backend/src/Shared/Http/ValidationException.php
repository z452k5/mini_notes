<?php
declare(strict_types=1);
namespace App\Shared\Http;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
final class ValidationException extends UnprocessableEntityHttpException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(public readonly array $fields)
    { parent::__construct('Проверьте поля формы.'); }
}
