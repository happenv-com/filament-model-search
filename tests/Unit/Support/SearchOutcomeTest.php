<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\ModelSearch\Exceptions\SearchIndexNotReady;
use Happenv\ModelSearch\Exceptions\SearchRateLimited;
use Happenv\ModelSearch\Exceptions\SearchTimedOut;
use Happenv\ModelSearch\Exceptions\SearchTooBroad;
use Happenv\ModelSearch\Query\SearchResult;
use Happenv\ModelSearch\Query\SearchResults;
use Illuminate\Support\Arr;

covers(SearchOutcome::class);

function outcomeItems(): array
{
    return [(new ReflectionClass(SearchResult::class))->newInstanceWithoutConstructor()];
}

it('maps each core exception to its outcome', function (Throwable $exception, SearchOutcome $expected): void {
    expect(SearchOutcome::fromException($exception))->toBe($expected);
})->with([
    'timed out' => [fn () => new SearchTimedOut, SearchOutcome::TimedOut],
    'too broad' => [fn () => new SearchTooBroad, SearchOutcome::TooBroad],
    'index not ready' => [fn () => new SearchIndexNotReady, SearchOutcome::IndexBuilding],
    'rate limited' => [fn () => new SearchRateLimited(5), SearchOutcome::RateLimited],
]);

it('returns null for an unrelated exception', function (): void {
    expect(SearchOutcome::fromException(new RuntimeException('boom')))->toBeNull();
});

it('derives the outcome from results by precedence', function (SearchResults $results, SearchOutcome $expected): void {
    expect(SearchOutcome::fromResults($results))->toBe($expected);
})->with(function (): array {
    $items = outcomeItems();

    return [
        'timed out and building' => [new SearchResults([], true, null, true), SearchOutcome::TimedOut],
        'timed out with items' => [new SearchResults($items, true), SearchOutcome::TimedOut],
        'building and empty' => [new SearchResults([], false, null, true), SearchOutcome::IndexBuilding],
        'building with items' => [new SearchResults($items, false, null, true), SearchOutcome::IndexBuilding],
        'empty' => [new SearchResults, SearchOutcome::Empty],
        'results' => [new SearchResults($items), SearchOutcome::Results],
    ];
});

it('has a non-empty heading in every locale', function (SearchOutcome $outcome, string $locale): void {
    expect(trans($outcome->heading(), [], $locale))->not->toBe($outcome->heading())->not->toBe('');
})->with(SearchOutcome::cases())->with(['en', 'pl', 'de']);

it('has a description in every locale except for results', function (SearchOutcome $outcome, string $locale): void {
    $text = trans($outcome->description(), [], $locale);

    if ($outcome === SearchOutcome::Results) {
        expect($text)->toBe('');

        return;
    }

    expect($text)->not->toBe($outcome->description())->not->toBe('');
})->with(SearchOutcome::cases())->with(['en', 'pl', 'de']);

it('uses the documented english sentences', function (): void {
    expect(trans(SearchOutcome::TimedOut->description(), [], 'en'))->toBe('The search took too long — type a longer phrase.')
        ->and(trans(SearchOutcome::TooBroad->description(), [], 'en'))->toBe('Too many matches — narrow the phrase.')
        ->and(trans(SearchOutcome::IndexBuilding->description(), [], 'en'))->toBe('The search index is being built — results may be incomplete.')
        ->and(trans(SearchOutcome::RateLimited->description(), [], 'en'))->toBe('Too many searches — wait a moment.')
        ->and(trans(SearchOutcome::Empty->description(), [], 'en'))->toBe('No records found.')
        ->and(trans(SearchOutcome::Unavailable->description(), [], 'en'))->toBe('Search is not available yet.');
});

it('keeps identical key sets in every locale', function (): void {
    $keys = fn (string $locale): array => array_keys(Arr::dot(require __DIR__."/../../../resources/lang/{$locale}/search-outcome.php"));

    expect($keys('pl'))->toEqualCanonicalizing($keys('en'))
        ->and($keys('de'))->toEqualCanonicalizing($keys('en'));
});

it('takes no placeholders so the phrase can never appear', function (SearchOutcome $outcome, string $locale): void {
    expect(trans($outcome->heading(), ['query' => 'secret', 'search' => 'secret'], $locale))->not->toContain('secret')->not->toContain(':')
        ->and(trans($outcome->description(), ['query' => 'secret', 'search' => 'secret'], $locale))->not->toContain('secret')->not->toContain(':');
})->with(SearchOutcome::cases())->with(['en', 'pl', 'de']);
