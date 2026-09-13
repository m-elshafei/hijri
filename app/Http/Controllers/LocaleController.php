<?php

declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Backward-compatible alias after Phase 4 move to Web\.
 * Keeps stale route/opcache classmaps from breaking locale switching.
 */
class LocaleController extends Web\LocaleController {}
