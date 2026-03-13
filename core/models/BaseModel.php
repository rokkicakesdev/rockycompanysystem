<?php
// core/models/BaseModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Shared base for all domain models.
//  Provides the db() helper so every child class gets a PDO connection
//  through the existing Database singleton — no changes to Database.php needed.
// ─────────────────────────────────────────────────────────────────────────────

if (!class_exists('Database')) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../Database.php';
}

abstract class BaseModel
{
    protected static function db(): PDO
    {
        return Database::getInstance();
    }
}
