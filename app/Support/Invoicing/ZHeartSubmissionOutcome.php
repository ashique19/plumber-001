<?php

namespace App\Support\Invoicing;

/**
 * data.outcome values from Z-Heart report/clear (success and 422).
 */
final class ZHeartSubmissionOutcome
{
    public const ACCEPTED = 'accepted';

    public const RETRY = 'retry';

    public const REJECTED = 'rejected';

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = strtolower(trim($value));

        return in_array($value, [self::ACCEPTED, self::RETRY, self::REJECTED], true) ? $value : null;
    }

    public static function isRetryable(mixed $outcome, mixed $retryableFlag = null): bool
    {
        if (self::normalize($outcome) === self::RETRY) {
            return true;
        }

        return $retryableFlag === true || $retryableFlag === 1 || $retryableFlag === '1';
    }
}
