<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. This plugin is self-contained — all FAQ logic lives in these
 * services; there is no external runtime dependency.
 *
 * @package Answers
 */

declare(strict_types=1);

use Answers\Admin\GlobalFaqSets;
use Answers\Admin\ProductFaqTab;
use Answers\Admin\Settings;
use Answers\Container;
use Answers\Data\FaqRepository;
use Answers\Migrator;
use Answers\Service\FaqRenderer;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    $c->singleton(FaqRepository::class, static fn (): FaqRepository => new FaqRepository());

    // Front-end renderer (accordion + schema).
    $c->singleton(
        FaqRenderer::class,
        static fn (Container $c): FaqRenderer => new FaqRenderer($c->get(FaqRepository::class)),
    );

    // GlobalFaqSets registers the FAQ-set post type in every context (the
    // storefront needs it registered to resolve assigned sets); its editor
    // hooks are admin-gated inside registerHooks().
    $c->singleton(GlobalFaqSets::class, static fn (): GlobalFaqSets => new GlobalFaqSets());

    if (is_admin()) {
        $c->singleton(
            ProductFaqTab::class,
            static fn (Container $c): ProductFaqTab => new ProductFaqTab($c->get(FaqRepository::class)),
        );
        $c->singleton(Settings::class, static fn (): Settings => new Settings());
    }
};
