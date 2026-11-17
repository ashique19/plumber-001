<?php

namespace App\Support\Hub;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class HubFormSubmissionIdempotency
{
    public const LOCK_SECONDS = 120;

    public const RESULT_TTL_SECONDS = 900;

    public static function resultCacheKey(string $scope, string $token, ?int $actorId): string
    {
        return 'hub-form-submit:result:'.$scope.':'.($actorId ?? 0).':'.$token;
    }

    public static function lockCacheKey(string $scope, string $token, ?int $actorId): string
    {
        return 'hub-form-submit:lock:'.$scope.':'.($actorId ?? 0).':'.$token;
    }

    public static function storedResourceId(string $scope, string $token, ?int $actorId): ?int
    {
        $value = Cache::get(self::resultCacheKey($scope, $token, $actorId));

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    public static function rememberResourceId(string $scope, string $token, ?int $actorId, int $resourceId): void
    {
        Cache::put(
            self::resultCacheKey($scope, $token, $actorId),
            $resourceId,
            self::RESULT_TTL_SECONDS,
        );
    }

    public static function acquireLock(string $scope, string $token, ?int $actorId): ?Lock
    {
        $lock = Cache::lock(self::lockCacheKey($scope, $token, $actorId), self::LOCK_SECONDS);

        if (! $lock->get()) {
            return null;
        }

        return $lock;
    }

  /**
   * @param  positive-int  $seconds
   */
    public static function waitForStoredResourceId(string $scope, string $token, ?int $actorId, int $seconds = 8): ?int
    {
        $deadline = microtime(true) + $seconds;

        while (microtime(true) < $deadline) {
            $id = self::storedResourceId($scope, $token, $actorId);
            if ($id !== null) {
                return $id;
            }

            usleep(100_000);
        }

        return self::storedResourceId($scope, $token, $actorId);
    }
}