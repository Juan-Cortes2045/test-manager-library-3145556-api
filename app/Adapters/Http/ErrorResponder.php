<?php

namespace App\Adapters\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Translates any exception into the single error body of docs/spec-python/api-contract.md §1:
 * { "code": ..., "message": ..., "details": [ { "field": ..., "issue": ... } ] }.
 * Only the HTTP adapter translates errors (architecture.md §5). Never exposes traces, SQL or paths.
 */
final class ErrorResponder
{
    public static function from(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::body(422, 'VALIDATION_ERROR', 'Hay campos inválidos.', self::validationDetails($e)),
            $e instanceof AuthenticationException => self::body(401, 'UNAUTHENTICATED', 'Se requiere autenticación.'),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => self::body(403, 'FORBIDDEN_ROLE', 'No tiene permiso para esta operación.'),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException,
            $e instanceof MethodNotAllowedHttpException => self::body(404, 'NOT_FOUND', 'El recurso no existe.'),
            default => self::body(500, 'INTERNAL_ERROR', 'Error interno del servidor.'),
        };
    }

    /**
     * @param  list<array{field: string, issue: string}>  $details
     */
    public static function body(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(
            ['code' => $code, 'message' => $message, 'details' => $details],
            $status,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @return list<array{field: string, issue: string}>
     */
    private static function validationDetails(ValidationException $e): array
    {
        $details = [];
        foreach ($e->errors() as $field => $issues) {
            foreach ($issues as $issue) {
                $details[] = ['field' => $field, 'issue' => $issue];
            }
        }

        return $details;
    }
}
