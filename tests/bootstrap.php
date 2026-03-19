<?php
// tests/bootstrap.php
// ─────────────────────────────────────────────────────────────
// PHPUnit bootstrap — loads only what tests need.
// No database, no session, no HTTP — pure unit test environment.
// ─────────────────────────────────────────────────────────────

declare(strict_types=1);

// Autoload via Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load the class under test directly — no framework needed
require_once __DIR__ . '/../core/PhilippineDeductions.php';
