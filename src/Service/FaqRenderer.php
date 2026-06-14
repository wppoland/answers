<?php

declare(strict_types=1);

namespace Answers\Service;

use Answers\Contract\HasHooks;
use Answers\Data\FaqItem;
use Answers\Data\FaqRepository;

defined('ABSPATH') || exit;

/**
 * Front-end rendering of the product FAQ accordion plus optional FAQPage
 * schema.org JSON-LD.
 *
 * Renders either as a dedicated product-information tab or directly after the
 * product summary, per the merchant's placement setting. The accordion is built
 * from semantic disclosure markup (a heading-wrapped <button> controlling an
 * aria-region) so it is keyboard operable and screen-reader friendly. When a
 * product has no FAQ items nothing is output and no assets are enqueued.
 */
final class FaqRenderer implements HasHooks
{
    private const OPTION = 'answers_settings';

    private const SHORTCODE = 'answers_faqs';

    private FaqRepository $repository;

    /** Guards against emitting schema/markup twice for the same product. */
    private bool $schemaPrinted = false;

    public function __construct(FaqRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $placement = $this->placement();

        if ($placement === 'summary') {
            add_action('woocommerce_after_single_product_summary', [$this, 'renderAfterSummary'], 15);
        } else {
            add_filter('woocommerce_product_tabs', [$this, 'registerProductTab']);
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_head', [$this, 'maybePrintSchema'], 20);
        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
    }

    /**
     * Only load the stylesheet on single product pages that actually have FAQs.
     */
    public function enqueueAssets(): void
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        if ($this->itemsForCurrentProduct() === []) {
            return;
        }

        $this->enqueueFrontAssets();
    }

    /**
     * Enqueue the storefront stylesheet and the progressive accordion script.
     */
    private function enqueueFrontAssets(): void
    {
        wp_enqueue_style(
            'answers',
            ANSWERS_URL . 'assets/css/faq.css',
            [],
            \Answers\VERSION,
        );

        wp_enqueue_script(
            'answers',
            ANSWERS_URL . 'assets/js/faq.js',
            [],
            \Answers\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }

    /**
     * Register a "FAQs" product-information tab (placement = tab).
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function registerProductTab(array $tabs): array
    {
        if ($this->itemsForCurrentProduct() === []) {
            return $tabs;
        }

        $tabs['answers_faqs'] = [
            'title'    => $this->tabTitle(),
            'priority' => 25,
            'callback' => [$this, 'renderTabContent'],
        ];

        return $tabs;
    }

    public function renderTabContent(): void
    {
        $items = $this->itemsForCurrentProduct();

        if ($items === []) {
            return;
        }

        echo $this->renderAccordion($items, ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderAccordion escapes every value.
    }

    public function renderAfterSummary(): void
    {
        $items = $this->itemsForCurrentProduct();

        if ($items === []) {
            return;
        }

        echo $this->renderAccordion($items, $this->heading()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderAccordion escapes every value.
    }

    /**
     * Shortcode `[answers_faqs]` — render a product's FAQ accordion anywhere.
     *
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(mixed $atts): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $atts = shortcode_atts(
            ['id' => 0, 'heading' => ''],
            is_array($atts) ? $atts : [],
            self::SHORTCODE,
        );

        $productId = (int) $atts['id'];

        if ($productId <= 0) {
            $productId = $this->currentProductId();
        }

        if ($productId <= 0) {
            return '';
        }

        $items = $this->repository->forProduct($productId);

        if ($items === []) {
            return '';
        }

        $this->enqueueFrontAssets();

        return $this->renderAccordion($items, sanitize_text_field((string) $atts['heading']));
    }

    /**
     * Emit FAQPage JSON-LD for the current product, once, when enabled.
     */
    public function maybePrintSchema(): void
    {
        if ($this->schemaPrinted || ! $this->outputSchema()) {
            return;
        }

        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $items = $this->itemsForCurrentProduct();

        if ($items === []) {
            return;
        }

        $this->schemaPrinted = true;

        $entities = [];

        foreach ($items as $item) {
            $answer = $item->plainAnswer();

            if ($answer === '') {
                continue;
            }

            $entities[] = [
                '@type'          => 'Question',
                'name'           => $item->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return;
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];

        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            $json, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output for a script/ld+json context; HTML-escaping would corrupt the JSON.
        );
    }

    /**
     * Build the accordion markup. Every dynamic value is escaped here.
     *
     * @param list<FaqItem> $items
     */
    private function renderAccordion(array $items, string $heading): string
    {
        $instance = wp_unique_id('answers-faq-');

        ob_start();
        ?>
        <div class="answers-faq" data-answers-faq>
            <?php if ($heading !== '') : ?>
                <h2 class="answers-faq__heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <div class="answers-faq__list">
                <?php
                $open = $this->firstOpen();
                foreach ($items as $index => $item) :
                    $panelId    = $instance . '-panel-' . $index;
                    $buttonId   = $instance . '-button-' . $index;
                    $isExpanded = $open && $index === 0;
                    ?>
                    <div class="answers-faq__item">
                        <h3 class="answers-faq__question">
                            <button
                                type="button"
                                id="<?php echo esc_attr($buttonId); ?>"
                                class="answers-faq__trigger"
                                aria-expanded="<?php echo $isExpanded ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo esc_attr($panelId); ?>"
                            >
                                <span class="answers-faq__trigger-text"><?php echo esc_html($item->question); ?></span>
                                <span class="answers-faq__icon" aria-hidden="true"></span>
                            </button>
                        </h3>
                        <div
                            id="<?php echo esc_attr($panelId); ?>"
                            class="answers-faq__panel"
                            role="region"
                            aria-labelledby="<?php echo esc_attr($buttonId); ?>"
                            <?php echo $isExpanded ? '' : 'hidden'; ?>
                        >
                            <div class="answers-faq__answer">
                                <?php echo wp_kses_post(wpautop($item->answer)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * FAQ items for the product currently being viewed (memoised per request
     * by the underlying object cache of get_post_meta / WP_Query is sufficient).
     *
     * @return list<FaqItem>
     */
    private function itemsForCurrentProduct(): array
    {
        $productId = $this->currentProductId();

        if ($productId <= 0) {
            return [];
        }

        return $this->repository->forProduct($productId);
    }

    private function currentProductId(): int
    {
        global $product;

        if ($product instanceof \WC_Product) {
            return (int) $product->get_id();
        }

        if (function_exists('is_product') && is_product()) {
            return (int) get_queried_object_id();
        }

        return 0;
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
    }

    private function placement(): string
    {
        $placement = (string) ($this->settings()['placement'] ?? 'tab');

        return $placement === 'summary' ? 'summary' : 'tab';
    }

    private function firstOpen(): bool
    {
        return (bool) ($this->settings()['first_open'] ?? false);
    }

    private function outputSchema(): bool
    {
        return (bool) ($this->settings()['output_schema'] ?? false);
    }

    private function tabTitle(): string
    {
        $title = trim((string) ($this->settings()['tab_title'] ?? ''));

        return $title !== '' ? $title : __('FAQs', 'answers');
    }

    private function heading(): string
    {
        $heading = trim((string) ($this->settings()['heading'] ?? ''));

        return $heading !== '' ? $heading : __('Frequently asked questions', 'answers');
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require ANSWERS_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }
}
