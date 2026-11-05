<?php

declare(strict_types=1);

return [
    'palette' => [
        'enabled' => true,
        'keybindings' => ['mod+shift+k'],
        'debounce_ms' => 250,
        'per_group' => 5,
    ],

    // How long the readiness of an index is remembered, in seconds.
    'readiness_cache_seconds' => 60,
];
