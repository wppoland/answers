=== Answers - Product FAQs for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, faq, product faq, accordion, structured data
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add per-product FAQs as an accessible accordion to reduce pre-sale questions.

== Description ==

Answers lets you add a frequently-asked-questions section to your WooCommerce
products. Buyers get their pre-sale questions answered right on the product page —
without contacting support — which lifts conversions and cuts ticket volume.

Author FAQs two ways:

* **Per-product** — a "FAQs" tab in the WooCommerce Product data panel with a
  simple question/answer repeater.
* **Reusable FAQ sets** — build a set once (e.g. "Shipping & returns") and assign
  it to specific products or whole product categories. Sets and per-product items
  are merged and de-duplicated automatically.

FAQs render as an **accessible accordion**: each question is a real button with
`aria-expanded` and an `aria-`controlled region, so it is keyboard operable and
announced correctly by screen readers. Motion is disabled under
`prefers-reduced-motion`, the markup never shifts layout, and the accordion is
styled with modern CSS that adapts to light and dark themes.

Optionally emit **FAQPage schema.org JSON-LD** so search engines can understand
your questions and answers.

= Features =

* Per-product FAQ items via a "FAQs" product data tab.
* Reusable global FAQ sets assignable to products and categories.
* Accessible accordion (button + region, `aria-expanded`, keyboard operable, focus-visible).
* Placement control: a product-information tab or after the product summary.
* Optional FAQPage schema.org JSON-LD output.
* `[answers_faqs]` shortcode to render the current product's FAQs anywhere.
* Answers accept basic HTML, filtered with `wp_kses_post`.
* Light/dark aware styling, no layout shift, motion-reduced friendly.
* Translation ready (POT included) and clean uninstall.
* HPOS and cart/checkout blocks compatible.

= The [answers_faqs] shortcode =

Render a product's FAQ accordion anywhere with `[answers_faqs]`. It uses the
current product on a single product page. Pass `id` to target a specific product
and `heading` to add a heading:

`[answers_faqs id="123" heading="Common questions"]`

== Installation ==

1. Upload the plugin to `/wp-content/plugins/answers`, or install via Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Edit a product and open the **FAQs** tab to add questions, or create reusable
   sets under **FAQ Sets**.
4. Tune placement and structured data under **WooCommerce → Answers**.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Where do FAQs appear? =

Either in a dedicated "FAQs" product-information tab, or directly after the
product summary — your choice under WooCommerce → Answers.

= Can I reuse the same FAQs across many products? =

Yes. Create a FAQ Set and assign it to specific products or whole product
categories. Its items are merged with each product's own FAQs.

= Does it output structured data? =

Optionally. Enable "FAQ structured data" to emit FAQPage JSON-LD for products
that have FAQs. Only enable it if the FAQs are genuinely visible on the page.

= Is the accordion accessible? =

Yes. Each question is a button with `aria-expanded` controlling an `aria`-labelled
region, it is keyboard operable, has visible focus, and respects reduced-motion.

== Screenshots ==

1. The FAQ accordion on a product page.
2. The per-product FAQs tab in the product data panel.
3. The Answers settings screen under WooCommerce.

== Changelog ==

= 0.1.0 =
* Initial release: per-product FAQs, reusable FAQ sets, accessible accordion, placement control, optional FAQPage JSON-LD, and the `[answers_faqs]` shortcode.
