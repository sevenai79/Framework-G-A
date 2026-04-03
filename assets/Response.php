<?php

declare(strict_types=1);

namespace Config;

final class Response
{
    public static function okPayload(
        array $data = [],
        string $message = 'Operazione completata'
    ): array {
        return [
            'status' => 'ok',
            'message' => $message,
            'data' => $data,
        ];
    }

    public static function errorPayload(string $error, array $extra = []): array
    {
        return array_merge([
            'status' => 'ko',
            'error' => $error,
        ], $extra);
    }

    public static function validationPayload(array $errors, string $message = 'Dati non validi'): array
    {
        return [
            'status' => 'ko',
            'error' => $message,
            'validation_errors' => $errors,
        ];
    }

    public static function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public static function ok(
        array $data = [],
        string $message = 'Operazione completata',
        int $statusCode = 200,
        bool $terminate = true
    ): void {
        $payload = self::okPayload($data, $message);
        self::send($payload, $statusCode, $terminate);
    }

    public static function error(
        string $error,
        int $statusCode = 400,
        array $extra = [],
        bool $terminate = true
    ): void {
        $payload = self::errorPayload($error, $extra);
        self::send($payload, $statusCode, $terminate);
    }

    public static function validation(
        array $errors,
        string $message = 'Dati non validi',
        bool $terminate = true
    ): void {
        $payload = self::validationPayload($errors, $message);
        self::send($payload, 422, $terminate);
    }

    public static function json(array $payload, int $statusCode = 200, bool $terminate = true): void
    {
        self::send($payload, $statusCode, $terminate);
    }

    private static function send(array $payload, int $statusCode, bool $terminate): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo self::encode($payload);

        if ($terminate) {
            exit;
        }
    }
}
