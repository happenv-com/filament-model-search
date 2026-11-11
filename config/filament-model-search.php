<?php

declare(strict_types=1);

return [
    'palette' => [
        'enabled' => true,
        // The built-in trigger button next to the global search; the modal and the shortcut stay when it is hidden.
        'trigger' => true,
        'keybindings' => ['mod+shift+k'],
        'debounce_ms' => 250,
        'per_group' => 5,
        // How many records the palette shows once it is narrowed to one resource ("Search in").
        'per_scope' => 20,
        // The translation key of the field's placeholder; null keeps the package's own.
        'placeholder' => null,
        // "Recently opened": the records the signed-in user opened from the palette, shown while the field is empty.
        // Only the type and the key of a record are stored, never the phrase.
        'history' => [
            'enabled' => true,
            // How many are shown.
            'limit' => 5,
            // How many are kept per user; older ones are dropped when a new one is written.
            'keep' => 20,
            'table' => 'model_search_palette_history',
            // false when the host manages its own migrations: publish the package's one (tag
            // `filament-model-search-migrations`) and run it there.
            'run_migrations' => true,
        ],
    ],

    // How long the readiness of an index is remembered, in seconds.
    'readiness_cache_seconds' => 60,
];
