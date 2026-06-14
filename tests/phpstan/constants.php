<?php
/**
 * Constants defined at runtime in the main plugin file, declared here so static
 * analysis can resolve them without loading WordPress.
 *
 * @package Answers
 */

declare(strict_types=1);

namespace Answers;

const VERSION = '0.1.0';

if (! defined('ANSWERS_DIR')) {
    define('ANSWERS_DIR', __DIR__ . '/');
}

if (! defined('ANSWERS_URL')) {
    define('ANSWERS_URL', 'https://example.test/wp-content/plugins/answers/');
}
