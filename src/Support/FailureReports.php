<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Throwable;

/**
 * Reports a recurring failure once per worker instead of on every call.
 *
 * Bound as a singleton: under Octane it lives as long as the worker, so a cache store that keeps failing is
 * reported once, not on every search. It holds failure keys only, never request data.
 */
final class FailureReports
{
    /** @var array<string, true> */
    private array $reported = [];

    public function reportOnce(string $key, Throwable $failure): void
    {
        if (isset($this->reported[$key])) {
            return;
        }

        $this->reported[$key] = true;

        report($failure);
    }
}
