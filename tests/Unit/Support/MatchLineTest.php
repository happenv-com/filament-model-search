<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Support\MatchLine;
use Happenv\ModelSearch\Definition\Mode;
use Happenv\ModelSearch\Query\SearchMatch;

covers(MatchLine::class);

it('tells the label and the value that matched', function (): void {
    expect(MatchLine::of(new SearchMatch('email', 'Email', 'a@b.test', Mode::Exact), 'Alice'))->toBe(['Email', 'a@b.test']);
});

it('tells the label alone when the value is hidden', function (): void {
    expect(MatchLine::of(new SearchMatch('email', 'Email', null, Mode::Exact), 'Alice'))->toBe([trans('filament-model-search::match-line.matched_by'), 'Email']);
});

it('adds nothing when the value is the title', function (): void {
    expect(MatchLine::of(new SearchMatch('name', 'Name', 'Alice', Mode::Prefix), 'Alice'))->toBeNull();
});
