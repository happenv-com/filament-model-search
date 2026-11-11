<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Palette;

/**
 * The slots of one record's row, as the HTML Filament's columns rendered. An empty string is an empty slot.
 */
final readonly class RenderedPaletteRow
{
    public function __construct(
        public string $leading = '',
        public string $aboveTitle = '',
        public string $besideTitle = '',
        public string $belowTitle = '',
        public string $trailing = '',
    ) {}

    public function isEmpty(): bool
    {
        return $this->leading === '' && $this->aboveTitle === '' && $this->besideTitle === '' && $this->belowTitle === '' && $this->trailing === '';
    }

    /**
     * What the slots read as plain text, lower-cased and with the white space folded: what a value is looked for in.
     */
    public function text(): string
    {
        $html = implode(' ', [$this->leading, $this->aboveTitle, $this->besideTitle, $this->belowTitle, $this->trailing]);
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /**
     * Whether a value already reads in the row, so a line repeating it adds nothing.
     */
    public function shows(string $value): bool
    {
        $needle = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        return $needle !== '' && str_contains($this->text(), $needle);
    }
}
