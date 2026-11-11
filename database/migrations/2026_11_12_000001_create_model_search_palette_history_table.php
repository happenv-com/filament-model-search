<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            // The user and the record are kept as strings: any key type (integer, uuid, ulid) fits. The lengths keep
            // the unique index (150 + 64 + 150 + 64 characters, 1752 bytes in utf8mb4) under the 3072 bytes
            // InnoDB allows for a key.
            $table->string('user_type', 150);
            $table->string('user_id', 64);
            $table->string('record_type', 150);
            $table->string('record_key', 64);
            $table->timestampTz('opened_at', 6);

            $table->unique(['user_type', 'user_id', 'record_type', 'record_key'], 'model_search_palette_history_record_unique');
            $table->index(['user_type', 'user_id', 'opened_at'], 'model_search_palette_history_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        $table = config('filament-model-search.palette.history.table');

        return is_string($table) && $table !== '' ? $table : 'model_search_palette_history';
    }
};
