<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Filament\Support\Icons\Heroicon;
use Happenv\ModelSearch\Exceptions\SearchIndexNotReady;
use Happenv\ModelSearch\Exceptions\SearchPrivacyUnavailable;
use Happenv\ModelSearch\Exceptions\SearchRateLimited;
use Happenv\ModelSearch\Exceptions\SearchTimedOut;
use Happenv\ModelSearch\Exceptions\SearchTooBroad;
use Happenv\ModelSearch\Query\SearchResults;
use Throwable;

/**
 * What a search came back with, shared by every surface. Texts never carry the phrase.
 */
enum SearchOutcome: string
{
    case Results = 'results';
    case Empty = 'empty';
    case TimedOut = 'timed_out';
    case TooBroad = 'too_broad';
    case IndexBuilding = 'index_building';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';

    public static function fromResults(SearchResults $results): self
    {
        return match (true) {
            $results->timedOut => self::TimedOut,
            $results->indexBuilding => self::IndexBuilding,
            $results->isEmpty() => self::Empty,
            default => self::Results,
        };
    }

    /**
     * The outcome for a core search exception, or null when the exception is not one of them.
     */
    public static function fromException(Throwable $exception): ?self
    {
        return match (true) {
            $exception instanceof SearchTimedOut => self::TimedOut,
            $exception instanceof SearchTooBroad => self::TooBroad,
            $exception instanceof SearchIndexNotReady => self::IndexBuilding,
            $exception instanceof SearchRateLimited => self::RateLimited,
            $exception instanceof SearchPrivacyUnavailable => self::Unavailable,
            default => null,
        };
    }

    /**
     * The icon of the state a search with no items ended in.
     */
    public function icon(): Heroicon
    {
        return match ($this) {
            self::RateLimited, self::TimedOut => Heroicon::OutlinedClock,
            self::Unavailable => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedMagnifyingGlass,
        };
    }

    public function heading(): string
    {
        return "filament-model-search::search-outcome.{$this->value}.heading";
    }

    public function description(): string
    {
        return "filament-model-search::search-outcome.{$this->value}.description";
    }
}
