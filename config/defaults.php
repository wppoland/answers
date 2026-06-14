<?php
/**
 * Default settings, merged under the option key `answers_settings`.
 *
 * The feature ships enabled. The merchant tunes where FAQs render on the
 * product page and whether to emit FAQPage schema.org JSON-LD from the
 * Answers settings screen (WooCommerce → Answers). Per-product FAQ items are
 * authored in the product data "FAQs" tab; reusable global FAQ sets are
 * authored under their own admin menu and assigned to products or categories.
 *
 * @package Answers
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Master switch. When off, nothing renders and no assets load.
    'enabled' => true,

    // Where the FAQ accordion renders on the single product page.
    // 'tab'     => its own "FAQs" product-information tab.
    // 'summary' => directly after the product summary (add-to-cart area).
    'placement' => 'tab',

    // Label for the product-information tab (when placement = tab).
    'tab_title' => '',

    // Heading shown above the accordion (when placement = summary).
    'heading' => '',

    // Whether the first FAQ item starts expanded.
    'first_open' => false,

    // Emit FAQPage schema.org JSON-LD in the page <head> for the product's FAQs.
    'output_schema' => true,
];
