<?php

namespace App\Http\Errors;

use App\Domain\Shared\BusinessRuleViolation;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The only place that turns errors into HTTP (architecture.md §5). Every error uses the single body
 * { code, message, details } of api-contract.md §1. It never includes traces, SQL, paths or passwords.
 */
final class ApiErrorRenderer
{
    /** Business code -> HTTP status (api-contract.md §2). */
    private const STATUS = [
        'INVALID_CREDENTIALS' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'ISBN_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'BOOK_HAS_OPEN_LOANS' => 409,
        'BOOK_NOT_AVAILABLE' => 409,
        'MEMBER_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'MEMBER_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_BOOK_IN_LOAN' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof BusinessRuleViolation => self::body(self::STATUS[$e->errorCode] ?? 422, $e->errorCode, $e->getMessage(), $e->details),
            $e instanceof ValidationException => self::body(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', self::validationDetails($e)),
            $e instanceof AuthenticationException => self::unauthenticated(),
            $e instanceof PostTooLargeException => self::body(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', [
                ['field' => 'file', 'issue' => 'supera el tamaño máximo de 2 MB'],
            ]),
            $e instanceof NotFoundHttpException, $e instanceof MethodNotAllowedHttpException => self::body(404, 'NOT_FOUND', 'El recurso solicitado no existe.'),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500 => self::body($e->getStatusCode(), 'VALIDATION_ERROR', 'La petición no es válida.'),
            default => self::body(500, 'INTERNAL_ERROR', 'Ocurrió un error inesperado. Intente de nuevo.'),
        };
    }

    /** Missing, invalid or expired token. The contract lists only INVALID_CREDENTIALS for 401, so it is reused. */
    public static function unauthenticated(): JsonResponse
    {
        return self::body(401, 'INVALID_CREDENTIALS', 'Debe iniciar sesión: el token falta, no es válido o venció.');
    }

    public static function forbidden(): JsonResponse
    {
        return self::body(403, 'FORBIDDEN_ROLE', 'Su rol no tiene permiso para esta operación.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    private static function body(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message, 'details' => $details], $status, [], JSON_UNESCAPED_UNICODE);
    }

    /** @return list<array{field: string, issue: string}> */
    private static function validationDetails(ValidationException $e): array
    {
        $details = [];
        foreach ($e->errors() as $field => $messages) {
            $details[] = ['field' => (string) $field, 'issue' => (string) ($messages[0] ?? 'inválido')];
        }

        return $details;
    }
}
