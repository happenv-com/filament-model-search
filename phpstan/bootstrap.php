<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;

// Larastan checks view names against the views of the application it boots; this package's are not part of it.
resolve(Factory::class)->addNamespace('filament-model-search', __DIR__.'/../resources/views');
