<?php

namespace App\Adapters\Http;

use App\Application\Services\Conflict;
use App\Application\Services\InvalidCredentials;
use App\Application\Services\MemberLimitExceeded;
use App\Application\Services\NotFound;
use App\Application\Services\UsernameTaken;
use App\Domain\BookNotAvailable;
use App\Domain\DomainRuleViolated;
use App\Domain\DuplicateBookInLoan;
use App\Domain\InvalidDateRange;
use App\Domain\ItemAlreadyReturned;
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
            $e instanceof Conflict => self::body(409, $e->errorCode, $e->getMessage()),
            $e instanceof UsernameTaken => self::body(409, 'USERNAME_TAKEN', $e->getMessage()),
            $e instanceof BookNotAvailable => self::body(409, 'BOOK_NOT_AVAILABLE', $e->getMessage()),
            $e instanceof ItemAlreadyReturned => self::body(409, 'ITEM_ALREADY_RETURNED', $e->getMessage()),
            $e instanceof InvalidCredentials => self::body(401, 'INVALID_CREDENTIALS', $e->getMessage()),
            $e instanceof NotFound => self::body(404, 'NOT_FOUND', $e->getMessage()),
            $e instanceof MemberLimitExceeded => self::body(422, 'MEMBER_LIMIT_EXCEEDED', $e->getMessage(),
                [['field' => 'member_id', 'issue' => sprintf('tiene %d libros sin devolver; máximo 3', $e->currentCount)]]),
            $e instanceof DuplicateBookInLoan => self::body(422, 'DUPLICATE_BOOK_IN_LOAN', $e->getMessage()),
            $e instanceof InvalidDateRange => self::body(422, 'INVALID_DATE_RANGE', $e->getMessage()),
            $e instanceof DomainRuleViolated => self::body(422, 'VALIDATION_ERROR', $e->getMessage()),
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
