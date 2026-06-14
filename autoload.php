<?php
/**
 * Autoloading: prefer Composer's vendor autoloader (the optimized classmap).
 * Fall back to a minimal PSR-4 autoloader so the plugin still boots if vendor/
 * is somehow absent. This plugin is self-contained — no external runtime deps.
 *
 * @package Answers
 */

declare(strict_types=1);

namespace Answers;

defined('ABSPATH') || exit;

$answers_composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($answers_composer)) {
    require_once $answers_composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'Answers\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
