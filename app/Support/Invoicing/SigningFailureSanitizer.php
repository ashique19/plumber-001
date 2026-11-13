<?php

namespace App\Support\Invoicing;

final class SigningFailureSanitizer
{
    public static function sanitizeMessage(string $message): string
    {
        $message = preg_replace(
            '/-----BEGIN [^-]+-----[\s\S]*?-----END [^-]+-----/',
            '[redacted-pem]',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/\b[A-Za-z0-9+\/]{64,}={0,2}\b/',
            '[redacted-base64]',
            $message
        ) ?? $message;

        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

        return mb_substr(trim($message), 0, 320);
    }

    public static function userMessage(string $translatedBase, ?\Throwable $throwable = null, ?string $context = null): string
    {
        $suffix = $throwable !== null
            ? self::sanitizeMessage(self::describeThrowable($throwable))
            : ($context !== null ? self::sanitizeMessage($context) : null);

        if ($suffix === null || $suffix === '') {
            return $translatedBase;
        }

        return $translatedBase.' — '.$suffix;
    }

    /**
     * @return array<string, int|string>
     */
    public static function logContext(\Throwable $throwable): array
    {
        return [
            'exception' => $throwable::class,
            'message' => self::sanitizeMessage($throwable->getMessage()),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'file_basename' => basename($throwable->getFile()),
        ];
    }

    public static function describeThrowable(\Throwable $throwable): string
    {
        return sprintf(
            '%s: %s (%s:%d)',
            $throwable::class,
            $throwable->getMessage(),
            basename($throwable->getFile()),
            $throwable->getLine()
        );
    }
}
