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
    ],

    // How long the readiness of an index is remembered, in seconds.
    'readiness_cache_seconds' => 60,
];
