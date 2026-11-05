<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

/**
 * What a phrase looks like, for the offers the palette makes. The core does not expose its own check
 * of an identifier-shaped phrase, so the same rule is applied here to the phrase itself: it contains
 * an `@`, or at least `model-search.identifier_rule.digit_share` of its letters and digits are digits.
 */
final class PhraseKind
{
    public static function hasLetter(#[\SensitiveParameter] string $phrase): bool
    {
        return preg_match('/\p{L}/u', $phrase) === 1;
    }

    public static function isIdentifierShaped(#[\SensitiveParameter] string $phrase): bool
    {
        if (str_contains($phrase, '@')) {
            return true;
        }

        $significant = preg_match_all('/[\p{L}\p{N}]/u', $phrase);

        if (! is_int($significant) || $significant === 0) {
            return false;
        }

        $digits = preg_match_all('/\p{Nd}/u', $phrase);
        $share = config('model-search.identifier_rule.digit_share', 0.5);

        return is_int($digits) && $digits / $significant >= (is_numeric($share) ? (float) $share : 0.5);
    }
}
