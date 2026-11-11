<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\History\PaletteHistory;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

covers(PaletteHistory::class);

beforeEach(function (): void {
    config([
        'filament-model-search.palette.history.enabled' => true,
        'filament-model-search.palette.history.keep' => 3,
    ]);
    $this->history = new PaletteHistory;
});

it('returns what a user opened, newest first', function (): void {
    $user = $this->signIn();

    $this->travelTo(now()->subMinutes(3));
    $this->history->record($user, 'order', 'a');
    $this->travel(1)->minutes();
    $this->history->record($user, 'customer', '7');
    $this->travel(1)->minutes();
    $this->history->record($user, 'order', 'b');

    expect($this->history->recent($user))->toBe([
        ['type' => 'order', 'key' => 'b'],
        ['type' => 'customer', 'key' => '7'],
        ['type' => 'order', 'key' => 'a'],
    ]);
});

it('moves a record that is opened again to the top without keeping it twice', function (): void {
    $user = $this->signIn();

    $this->travelTo(now()->subMinutes(2));
    $this->history->record($user, 'order', 'a');
    $this->travel(1)->minutes();
    $this->history->record($user, 'order', 'b');
    $this->travel(1)->minutes();
    $this->history->record($user, 'order', 'a');

    expect($this->history->recent($user))->toBe([['type' => 'order', 'key' => 'a'], ['type' => 'order', 'key' => 'b']]);
});

it('keeps only the newest entries of a user and prunes the rest when writing', function (): void {
    $user = $this->signIn();
    $other = User::factory()->create();
    $this->history->record($other, 'order', 'theirs');

    $this->travelTo(now()->subMinutes(5));

    foreach (['a', 'b', 'c', 'd', 'e'] as $key) {
        $this->history->record($user, 'order', $key);
        $this->travel(1)->minutes();
    }

    expect(array_column($this->history->recent($user), 'key'))->toBe(['e', 'd', 'c'])
        ->and($this->history->recent($other))->toBe([['type' => 'order', 'key' => 'theirs']]);
});

it('keeps the history of each user apart and clears only the one asked', function (): void {
    $user = $this->signIn();
    $other = User::factory()->create();
    $this->history->record($user, 'order', 'mine');
    $this->history->record($other, 'order', 'theirs');

    $this->history->clear($user);

    expect($this->history->recent($user))->toBe([])
        ->and($this->history->recent($other))->toBe([['type' => 'order', 'key' => 'theirs']]);
});

it('stores the type and the key of the record and nothing about a phrase', function (): void {
    $user = $this->signIn();
    $this->history->record($user, 'order', 'a');

    $row = (array) DB::table('model_search_palette_history')->first();

    expect(array_keys($row))->toEqualCanonicalizing(['id', 'user_type', 'user_id', 'record_type', 'record_key', 'opened_at'])
        ->and($row['user_id'])->toBe((string) $user->getKey());
});

it('reads and writes nothing when it is switched off or nobody is signed in', function (): void {
    $user = $this->signIn();
    $this->history->record($user, 'order', 'a');

    config(['filament-model-search.palette.history.enabled' => false]);
    $this->history->record($user, 'order', 'b');
    $this->history->clear($user);

    expect($this->history->recent($user))->toBe([]);

    config(['filament-model-search.palette.history.enabled' => true]);
    $this->history->record(null, 'order', 'c');
    $this->history->clear(null);

    expect($this->history->recent(null))->toBe([])
        ->and(DB::table('model_search_palette_history')->pluck('record_key')->all())->toBe(['a']);
});

it('keeps the order of records opened within the same second', function (): void {
    $user = $this->signIn();

    $this->history->record($user, 'order', 'a');
    $this->history->record($user, 'order', 'b');
    $this->history->record($user, 'order', 'a');

    expect($this->history->recent($user))->toBe([['type' => 'order', 'key' => 'a'], ['type' => 'order', 'key' => 'b']]);
});

it('holds a uuid, a ulid and a bigint key and keeps the unique index short enough for MySQL', function (): void {
    $user = $this->signIn();
    $keys = ['01a1caba-3c38-7318-b1c4-661febc0cf39', '01ARZ3NDEKTSV4RRFFQ69G5FAV', '9223372036854775807'];

    foreach ($keys as $key) {
        $this->history->record($user, 'order', $key);
    }

    $lengths = collect(Schema::getColumns('model_search_palette_history'))
        ->filter(static fn (array $column): bool => in_array($column['name'], ['user_type', 'user_id', 'record_type', 'record_key'], true))
        ->map(static fn (array $column): int => (int) preg_replace('/\D/', '', (string) $column['type']))
        ->sum();

    expect(array_column($this->history->recent($user), 'key'))->toEqualCanonicalizing($keys)
        // The characters of the unique index, at four bytes each, must stay under InnoDB's 3072 bytes.
        ->and($lengths * 4)->toBeLessThan(3072);
});
