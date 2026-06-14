# Answers — Product FAQs for WooCommerce

Add per-product FAQs as an accessible accordion to reduce pre-sale questions.

Answers is a **self-contained** WooCommerce plugin (no external runtime
dependencies) that lets merchants attach a frequently-asked-questions section to
products and render it as an accessible, keyboard-operable accordion — optionally
with FAQPage schema.org structured data.

## Features

- Per-product FAQ items via a "FAQs" tab in the Product data panel.
- Reusable global FAQ sets (a private `answers_faq_set` post type) assignable to
  products and product categories; merged and de-duplicated with per-product items.
- Accessible accordion: `<button aria-expanded aria-controls>` + `role="region"`,
  keyboard operable, focus-visible, motion-reduced friendly, no layout shift.
- Placement: a product-information tab or after the product summary.
- Optional FAQPage schema.org JSON-LD.
- `[answers_faqs]` shortcode.
- Answer HTML filtered with `wp_kses_post`; all input sanitised, all output escaped.

## Architecture

- `answers.php` — bootstrap. Boots on `init:0` and fires `answers/booted`.
- `src/Plugin.php`, `src/Container.php` — DI container + boot pipeline.
- `src/Data/FaqRepository.php` — resolves per-product + global-set FAQs.
- `src/Service/FaqRenderer.php` — front-end accordion + JSON-LD.
- `src/Admin/` — product FAQ tab, FAQ-set editor, settings, shared repeater.
- `config/` — `services.php`, `hooks.php`, `defaults.php`.

## Extension points

- `answers/booted` — fires after boot with the `Plugin` instance (PRO hooks here).
- `answers/product_faqs` — filter the resolved `FaqItem[]` for a product.
- `answers/sanitize_settings` — filter sanitised settings on save.

## Development

```bash
composer install
composer cs        # PHPCS (WordPress security sniffs)
composer analyse   # PHPStan level 6
```

## Quality gates

PHPCS, PHPStan L6, `php -l`, `node -c`, and the wp.org Plugin Check all run green.
