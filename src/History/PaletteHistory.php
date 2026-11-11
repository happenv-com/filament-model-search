<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\History;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What a user opened from the record palette: the record's type and key, never the phrase that led to it.
 *
 * Every read and write belongs to one user. History is off with `palette.history.enabled` and for a request without
 * a signed-in user: then nothing is read and nothing is written.
 */
final class PaletteHistory
{
    /**
     * Notes that the user opened a record: it becomes the newest entry, and what falls outside `keep` is forgotten.
     */
    public function record(?Authenticatable $user, string $type, string $key): void
    {
        if (! $user instanceof Authenticatable || ! $this->enabled()) {
            return;
        }

        $owner = $this->owner($user);
        // The column keeps microseconds; the query builder would format the date without them.
        $now = now()->format('Y-m-d H:i:s.u');

        DB::transaction(function () use ($owner, $type, $key, $now): void {
            $updated = $this->table()
                ->where($owner)
                ->where('record_type', $type)
                ->where('record_key', $key)
                ->update(['opened_at' => $now]);

            if ($updated === 0) {
                $this->table()->insertOrIgnore([...$owner, 'record_type' => $type, 'record_key' => $key, 'opened_at' => $now]);
            }

            $this->prune($owner);
        });
    }

    /**
     * Every entry kept for the user, newest first. The caller skips the ones it can no longer show (deleted, no
     * longer allowed) and shows `palette.history.limit` of the rest.
     *
     * @return list<array{type: string, key: string}>
     */
    public function recent(?Authenticatable $user): array
    {
        if (! $user instanceof Authenticatable || ! $this->enabled()) {
            return [];
        }

        $entries = [];

        $rows = $this->table()
            ->where($this->owner($user))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit($this->keep())
            ->get(['record_type', 'record_key']);

        foreach ($rows as $row) {
            $entries[] = ['type' => (string) $row->record_type, 'key' => (string) $row->record_key];
        }

        return $entries;
    }

    /**
     * Forgets everything the user opened.
     */
    public function clear(?Authenticatable $user): void
    {
        if (! $user instanceof Authenticatable || ! $this->enabled()) {
            return;
        }

        $this->table()->where($this->owner($user))->delete();
    }

    /**
     * Whether history is kept: `palette.history.enabled`. The table must exist (the package's migration, unless the
     * host runs its own): it is not checked here.
     */
    public function enabled(): bool
    {
        return config('filament-model-search.palette.history.enabled', true) === true;
    }

    /**
     * @param  array{user_type: string, user_id: string}  $owner
     */
    private function prune(array $owner): void
    {
        $surplus = $this->table()
            ->where($owner)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->skip($this->keep())
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($surplus->isNotEmpty()) {
            $this->table()->whereIn('id', $surplus->all())->delete();
        }
    }

    /**
     * @return array{user_type: string, user_id: string}
     */
    private function owner(Authenticatable $user): array
    {
        return [
            'user_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
        ];
    }

    private function keep(): int
    {
        return max(1, (int) config('filament-model-search.palette.history.keep', 20));
    }

    private function table(): Builder
    {
        $table = config('filament-model-search.palette.history.table');

        return DB::table(is_string($table) && $table !== '' ? $table : 'model_search_palette_history');
    }
}
