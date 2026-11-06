<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Browser');

// Real-browser tests need Playwright; they run on their own: `vendor/bin/pest --testsuite=Browser`.
pest()->group('browser')->in('Browser');
