<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Support\PhraseKind;

covers(PhraseKind::class);

it('reads the identifier shape the way the core documents it', function (string $phrase, bool $identifier): void {
    expect(PhraseKind::isIdentifierShaped($phrase))->toBe($identifier);
})->with([
    'an e-mail' => ['a@b.test', true],
    'an order number' => ['OR12345', true],
    'half digits' => ['ab12', true],
    'a name' => ['john smith', false],
    'a name with a digit' => ['Müller 2', false],
    'only separators' => ['--', false],
    'only emoji' => ['😀😀', false],
]);

it('follows the configured digit share', function (): void {
    config(['model-search.identifier_rule.digit_share' => 0.9]);

    expect(PhraseKind::isIdentifierShaped('OR12345'))->toBeFalse();
});

it('finds a letter in any script and none in digits or emoji', function (string $phrase, bool $hasLetter): void {
    expect(PhraseKind::hasLetter($phrase))->toBe($hasLetter);
})->with([
    'latin' => ['Smith', true],
    'diacritics' => ['Łukasz', true],
    'cyrillic' => ['Иван', true],
    'digits' => ['12345', false],
    'emoji' => ['😀', false],
]);
