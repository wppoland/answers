<?php

declare(strict_types=1);

namespace Answers\Service;

use Answers\Contract\HasHooks;
use Answers\Data\FaqItem;
use Answers\Data\FaqRepository;

defined('ABSPATH') || exit;

/**
 * Front-end rendering of the product FAQ accordion.
 *
 * Renders as a dedicated "FAQs" product-information tab. The accordion is built
 * from semantic disclosure markup (a heading-wrapped <button> controlling an
 * aria-region) so it is keyboard operable and screen-reader friendly. When a
 * product has no FAQ items nothing is output and no assets are enqueued.
 */
final class FaqRenderer implements HasHooks
{
    private const OPTION = 'answers_settings';

    private FaqRepository $repository;

    public function __construct(FaqRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_filter('woocommerce_product_tabs', [$this, 'registerProductTab']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Only load assets on single product pages that actually have FAQs.
     */
    public function enqueueAssets(): void
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        if ($this->itemsForCurrentProduct() === []) {
            return;
        }

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
     * Register a "FAQs" product-information tab.
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

        echo $this->renderAccordion($items, $this->currentProductId()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderAccordion escapes every value.
    }

    /**
     * Build the accordion markup. Every dynamic value is escaped here.
     *
     * @param list<FaqItem> $items
     */
    private function renderAccordion(array $items, int $productId): string
    {
        $instance = wp_unique_id('answers-faq-');

        ob_start();
        ?>
        <div class="answers-faq" data-answers-faq data-product-id="<?php echo esc_attr((string) $productId); ?>">
            <div class="answers-faq__list">
                <?php foreach ($items as $index => $item) :
                    $panelId  = $instance . '-panel-' . $index;
                    $buttonId = $instance . '-button-' . $index;
                    ?>
                    <div class="answers-faq__item" data-faq-key="<?php echo esc_attr($item->key); ?>">
                        <h3 class="answers-faq__question">
                            <button
                                type="button"
                                id="<?php echo esc_attr($buttonId); ?>"
                                class="answers-faq__trigger"
                                aria-expanded="false"
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
                            hidden
                        >
                            <div class="answers-faq__answer">
                                <?php echo wp_kses_post(wpautop($item->answer)); ?>
                            </div>
                            <?php
                            /**
                             * Fires after a single FAQ answer is rendered.
                             *
                             * @param int     $productId Product post ID.
                             * @param int     $index     Zero-based FAQ index.
                             * @param FaqItem $item      FAQ item being rendered.
                             */
                            do_action('answers/faq_after_answer', $productId, $index, $item);
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * FAQ items for the product currently being viewed.
     *
     * @return list<FaqItem>
     */
    private function itemsForCurrentProduct(): array
    {
        $productId = $this->currentProductId();

        if ($productId <= 0) {
            return [];
        }

        return apply_filters('answers/faq_items', $this->repository->forProduct($productId), $productId);
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

    private function tabTitle(): string
    {
        $title = trim((string) ($this->settings()['tab_title'] ?? ''));

        return $title !== '' ? $title : __('FAQs', 'answers');
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
