<?php

namespace App\Support\Api\V1;

use App\Support\PermissionDeniedMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiErrorResponse
{
    public static function validation(ValidationException $exception, Request $request): JsonResponse
    {
        $errors = $exception->errors();

        return self::make(
            $request,
            'validation_failed',
            $exception->getMessage() ?: 'The given data was invalid.',
            $exception->status,
            ['fields' => $errors],
            [],
            $errors,
        );
    }

    public static function unauthenticated(AuthenticationException $exception, Request $request): JsonResponse
    {
        return self::make(
            $request,
            'unauthenticated',
            $exception->getMessage() ?: 'Authentication is required.',
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function forbidden(AuthorizationException $exception, Request $request): JsonResponse
    {
        $message = trim((string) $exception->getMessage());

        return self::make(
            $request,
            'forbidden',
            PermissionDeniedMessage::normalize($message),
            Response::HTTP_FORBIDDEN,
        );
    }

    public static function modelNotFound(ModelNotFoundException $exception, Request $request): JsonResponse
    {
        return self::make(
            $request,
            'not_found',
            'The requested resource was not found.',
            Response::HTTP_NOT_FOUND,
        );
    }

    public static function notFound(NotFoundHttpException $exception, Request $request): JsonResponse
    {
        return self::make(
            $request,
            'not_found',
            self::messageFor($exception, Response::HTTP_NOT_FOUND),
            Response::HTTP_NOT_FOUND,
        );
    }

    public static function methodNotAllowed(MethodNotAllowedHttpException $exception, Request $request): JsonResponse
    {
        return self::make(
            $request,
            'method_not_allowed',
            self::messageFor($exception, Response::HTTP_METHOD_NOT_ALLOWED),
            Response::HTTP_METHOD_NOT_ALLOWED,
            [],
            $exception->getHeaders(),
        );
    }

    public static function http(HttpExceptionInterface $exception, Request $request): JsonResponse
    {
        $status = $exception->getStatusCode();

        return self::make(
            $request,
            self::codeForStatus($status),
            self::messageFor($exception, $status),
            $status,
            [],
            $exception->getHeaders(),
        );
    }

    public static function serverError(Throwable $exception, Request $request): JsonResponse
    {
        $message = config('app.debug') && trim((string) $exception->getMessage()) !== ''
            ? trim((string) $exception->getMessage())
            : 'An unexpected error occurred.';

        return self::make(
            $request,
            'server_error',
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    private static function make(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $error = [],
        array $headers = [],
        ?array $errors = null,
    ): JsonResponse {
        $errors ??= $error['fields'] ?? [];

        return response()->json([
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
            'code' => $code,
            'error' => array_merge([
                'code' => $code,
                'message' => $message,
            ], $error),
            'meta' => ApiContract::meta($request),
        ], $status, array_merge($headers, ApiContract::headers($request)));
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'unprocessable_entity',
            Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
            default => $status >= Response::HTTP_INTERNAL_SERVER_ERROR ? 'server_error' : 'http_error',
        };
    }

    private static function messageFor(Throwable $exception, int $status): string
    {
        $message = trim((string) $exception->getMessage());

        if ($status === Response::HTTP_FORBIDDEN) {
            return PermissionDeniedMessage::normalize($message);
        }

        if ($message !== '') {
            return $message;
        }

        return Response::$statusTexts[$status] ?? 'Request failed.';
    }
}
