=== Answers - Product FAQs for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, faq, product faq, accordion
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add per-product FAQs as an accessible accordion to reduce pre-sale questions.

== Description ==

Answers adds a frequently-asked-questions section to your WooCommerce products,
so shoppers can read the common pre-sale questions on the product page instead of
emailing to ask.

You write the FAQs in a "FAQs" tab inside the WooCommerce Product data panel using
a question/answer repeater. They show up in their own "FAQs" tab on the single
product page, next to Description and Reviews.

The front-end FAQs are an **accessible accordion**. Each question is a real
`<button>` with `aria-expanded` controlling an `aria`-labelled region, so it works
with the keyboard and is announced correctly by screen readers. Panels open and
close with a height transition that is switched off under `prefers-reduced-motion`,
the answer text stays reachable when JavaScript is off, and the styling follows the
visitor's light or dark colour scheme.

Source code and bug reports live on GitHub: https://github.com/wppoland/answers

= Features =

* Per-product FAQ items, authored in a "FAQs" product data tab.
* Accordion built from a `<button>` plus an `aria`-labelled region: keyboard operable, visible focus ring, `aria-expanded` kept in sync.
* Shows in its own "FAQs" product-information tab; the tab label is editable.
* Answers accept basic HTML, sanitised with `wp_kses_post` on save and again on output.
* CSS custom properties for theming, a dark-scheme palette, and motion that respects `prefers-reduced-motion`.
* Front-end assets load only on product pages that actually have FAQs.
* Translation ready (POT included), and removes its options on uninstall.
* Declares HPOS and cart/checkout blocks compatibility.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/answers`, or install via Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Edit a product and open the **FAQs** tab to add questions.
4. Rename the FAQ tab under **WooCommerce → Answers** if you like.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Where do FAQs appear? =

In a dedicated "FAQs" product-information tab on the single product page.

= Is the accordion accessible? =

Yes. Each question is a button with `aria-expanded` controlling an `aria`-labelled
region, it is keyboard operable, has visible focus, and respects reduced-motion.

== Screenshots ==

1. The FAQ accordion on a product page.
2. The per-product FAQs tab in the product data panel.
3. The Answers settings screen under WooCommerce.

== Changelog ==

= 0.1.0 =
* Initial release: per-product FAQs and an accessible accordion in a "FAQs" product tab.
