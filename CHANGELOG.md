# Changelog

All notable changes to `filament-model-search` will be documented in this file.

## v0.5.0 - 2026-11-11

### Breaking: a row is described on a builder

A resource no longer names a `SearchResultRow` subclass. It gets a `SearchResultRow` builder and returns it, the way `table(Table $table)` describes a table:

```php
public static function globalSearchResultRow(SearchResultRow $row): SearchResultRow
{
    return $row
        ->with(['channel'])
        ->besideTitle([TextColumn::make('state')->badge()])
        ->belowTitle([TextColumn::make('customer_name')])
        ->trailing(fn (SearchResultRow $row): array => [TextColumn::make('placed_at')->date()]);
}

```
- Every slot (`leading`, `aboveTitle`, `besideTitle`, `belowTitle`, `trailing`) takes table columns or `Split` / `Stack` layouts, or a closure returning them that gets the row as `$row` (by name or by type).
- `with()` takes the relations to eager load once per group. It only eager loads: the records are the ones the search found and the user may open, and nothing here can filter them away.
- The method is called through the container, so it may ask for more than the row.
- A row returned untouched is the default look. A slot that holds something else than columns throws a `LogicException` naming the slot.

#### Upgrading from 0.4

1. Replace `public static function getGlobalSearchResultRow(): ?string` with `public static function globalSearchResultRow(SearchResultRow $row): SearchResultRow`. A resource that still declares the old method throws a `LogicException` pointing at the new one.
2. Turn the row class into calls on the builder: `with(): array` → `->with([...])`, `besideTitle(): array` → `->besideTitle([...])`, and so on. To keep the description in its own class, make it a `configure(SearchResultRow $row): SearchResultRow` helper and call it from the resource.

### Record palette

- **The stage line names the stage.** When the results came from a stage, the line under them says which one ("Showing: Open orders") and the button that searches every stage carries the label of `SearchStage::all()` ("All orders"). Both come from the definition's `label()` closures, resolved in the viewer's locale. Without a label, or when several types answered from their own stages, the line falls back to "Showing only records in progress." and "Search all". "Search for similar" stays a separate button on the same line. Labels are not part of the definition's fingerprint: adding one needs no `version()` bump and no reindex.

### Translations

- New key `record-palette.stage_shown` (`Showing: :stage`) in en, pl and de.

## v0.4.1 - 2026-11-11

### What's Changed

* chore: keep dev-only files out of the Composer dist archive by @webard in https://github.com/happenv-com/filament-model-search/pull/15

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.4.0...v0.4.1

## v0.4.0 - 2026-11-11

### Record palette

- **Rows described with table columns.** A resource names a `SearchResultRow` subclass from the optional static `getGlobalSearchResultRow(): ?string`; its `leading()`, `aboveTitle()`, `besideTitle()`, `belowTitle()` and `trailing()` return Filament columns, `with()` the relations to load once per group. A resource without one renders exactly as before. Interactive columns (editable, `url()`, `action()`) are refused with a `LogicException`.
- **Type chips** replace "Search in": a row under the field, for an empty field and for results, with "All" and one chip per resource that can be chosen (label in navigation order; icons only when every resource has one, then "All" gets one too). Shown when at least two can be chosen. The welcome list and the "Search in: ..." label inside the field are gone; Backspace in an empty field still clears the narrowing.
- **"Show all <resources>"** ends a group that has more than `palette.per_group` records and narrows the search to that resource.
- **"Open the <resources> list with “phrase”"** is the last option of a narrowed search: the list page with the phrase in the table search (`ResourceBinding::listUrl()`).
- **"Recently opened"** while the field is empty: the last records the signed-in user opened from the palette (newest first, every type, the same rows as results, no match line or magnifier), with a quiet "Clear history" option at the bottom. Only the type and key of the record, the user and the time are stored, never the phrase. A plain click on a result goes through the component (`open()`) so it is noted; modified clicks stay native and are not noted. Configured under `palette.history` (`enabled`, `limit`, `keep`, `table`, `run_migrations`).
- The match line is also dropped when the row's own columns already show its value.
- **On a wide screen "Search all" and "Search for similar" share one short line** under the results; on a narrow one they stay rows at the end.
- **The field button is always there**: it clears the phrase, and closes the palette when nothing is typed.
- **The window fits the visual viewport**, so a phone keyboard no longer hides the last results; the field is taller on a phone.
- `open()` opens any record the user may open through the resource's scope, whether or not it is still among the results (a click never does nothing because the results moved on).

### Upgrading

- **Run the migration** (`php artisan migrate`): it creates the `model_search_palette_history` table. The package loads it itself. A host that manages its own migrations sets `palette.history.run_migrations` to `false` and publishes it with `php artisan vendor:publish --tag=filament-model-search-migrations`. **The palette needs the table**: without it (migration not run, or not shipped) the palette fails to load. To turn the history off, set `palette.history.enabled` to `false`; the table is then not read.
- The welcome list of "Search in" and the "Search in: ..." label inside the field are **replaced by chips** under the field.
- A result is opened by a click handler instead of `wire:navigate` (modified clicks still open natively and are not noted in the history).
- Translations: `welcome_heading`, `scope` and `clear_scope` are removed; `all`, `scope_label`, `show_all`, `open_list_with_phrase`, `open_list_enter`, `recently_opened` and `clear_history` are new. Published translations need them.
- A published config that replaces the `palette` array should gain the `history` block (config files merge one level deep).

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.3.1...v0.4.0

## v0.3.1 - 2026-11-11

### What's Changed

* docs: refresh the Laravel Boost skill by @webard in https://github.com/happenv-com/filament-model-search/pull/13

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.3.0...v0.3.1

## v0.3.0 - 2026-11-11

### Record palette

- **"Search in" narrows the search to one resource** instead of opening its list: the field shows "Search in: Orders" with a cross, the chosen resource is checked in the list, and choosing it again, the cross or Backspace in an empty field searches everywhere again. Up to `palette.per_scope` records come back (new config key, default 20). The scope is a locked property, accepted only for a resource the user may open, and asked again on every search.
- **A resource can leave "Search in"** with the optional static `isOfferedAsPaletteScope(): bool` (offered without one). It is still searched everywhere; it only cannot be chosen alone.
- **The spinner stands where the reset button is** while a search runs, and the button comes back with the answer.
- **The magnifier** names its term ("Search for “PA5555”"), searches every resource for it (clearing "Search in"), and is not offered when the term is the phrase already searched.

### Other

- The package ships a Laravel Boost skill, `filament-model-search-development` (`resources/boost/skills/`).

### Upgrading

- `ResourceBinding::listUrl()` is removed (nothing read it any more).
- The `open_list` translation key is gone; `narrow`, `scope`, `clear_scope` and `search_term` are new. Published translations need them.

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.2.2...v0.3.0

## v0.2.2 - 2026-11-10

### What's Changed

* chore: allow laravel-model-search 0.5 by @webard in https://github.com/happenv-com/filament-model-search/pull/11

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.2.1...v0.2.2

## v0.2.1 - 2026-11-10

### What's Changed

* chore: allow laravel-model-search 0.4 by @webard in https://github.com/happenv-com/filament-model-search/pull/10

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.2.0...v0.2.1

## v0.1.6 - 2026-11-06

### What's Changed

* chore(deps): bump the github-actions group with 3 updates by @dependabot[bot] in https://github.com/happenv-com/filament-model-search/pull/7
* chore: allow laravel-model-search 0.3 by @webard in https://github.com/happenv-com/filament-model-search/pull/8

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/happenv-com/filament-model-search/pull/7

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.1.5...v0.1.6

## v0.1.5 - 2026-11-06

### What's Changed

* feat: the record palette behaves like a command palette by @webard in https://github.com/happenv-com/filament-model-search/pull/6

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.1.4...v0.1.5

## v0.1.4 - 2026-11-06

### What's Changed

* feat: custom palette triggers (paletteTrigger option, isPaletteOffered) by @webard in https://github.com/happenv-com/filament-model-search/pull/5

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.1.3...v0.1.4

## v0.1.3 - 2026-11-05

### What's Changed

* fix: render scalable icon enums as navigation icons in the record palette by @webard in https://github.com/happenv-com/filament-model-search/pull/4

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.1.2...v0.1.3

## v0.1.2 - 2026-11-05

### What's Changed

* chore: allow laravel-model-search 0.2 by @webard in https://github.com/happenv-com/filament-model-search/pull/3

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.1.1...v0.1.2

## v0.1.1 - 2026-11-05

### What's Changed

* fix: open the palette from the topbar trigger, address review of #1 by @webard in https://github.com/happenv-com/filament-model-search/pull/2

**Full Changelog**: https://github.com/happenv-com/filament-model-search/compare/v0.1.0...v0.1.1

## v0.1.0 - 2026-11-05

### What's Changed

* feat: v0.1 — index-backed table, select, global search and record palette by @webard in https://github.com/happenv-com/filament-model-search/pull/1

### New Contributors

* @webard made their first contribution in https://github.com/happenv-com/filament-model-search/pull/1

**Full Changelog**: https://github.com/happenv-com/filament-model-search/commits/v0.1.0
