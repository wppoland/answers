<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Answers\Contract\HasHooks.
 *
 * @package Answers
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Answers\Admin\GlobalFaqSets;
use Answers\Admin\ProductFaqTab;
use Answers\Admin\Settings;
use Answers\Service\FaqRenderer;

defined('ABSPATH') || exit;

return is_admin()
    ? [
        GlobalFaqSets::class,
        ProductFaqTab::class,
        Settings::class,
        FaqRenderer::class,
    ]
    : [
        GlobalFaqSets::class,
        FaqRenderer::class,
    ];
