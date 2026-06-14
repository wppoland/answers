<?php

declare(strict_types=1);

namespace Answers\Admin;

use Answers\Contract\HasHooks;
use Answers\Data\FaqRepository;

defined('ABSPATH') || exit;

/**
 * Adds a "FAQs" tab to the WooCommerce Product data panel where the merchant
 * authors per-product question/answer items. Items are stored as an array under
 * the `_answers_faqs` post meta; questions are plain text and answers are
 * filtered with wp_kses_post on save. A nonce guards the write.
 */
final class ProductFaqTab implements HasHooks
{
    private const NONCE_ACTION = 'answers_save_product_faqs';
    private const NONCE_FIELD  = 'answers_product_faqs_nonce';

    private FaqRepository $repository;

    public function __construct(FaqRepository $repository)
    {
        $this->repository = $repository;
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'addTab']);
        add_action('woocommerce_product_data_panels', [$this, 'renderPanel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function addTab(array $tabs): array
    {
        $tabs['answers_faqs'] = [
            'label'    => __('FAQs', 'answers'),
            'target'   => 'answers_faqs_data',
            'class'    => ['hide_if_external'],
            'priority' => 65,
        ];

        return $tabs;
    }

    public function renderPanel(): void
    {
        global $post;

        $productId = $post instanceof \WP_Post ? (int) $post->ID : 0;
        $items     = $this->repository->rawProductItems($productId);
        ?>
        <div id="answers_faqs_data" class="panel woocommerce_options_panel answers-product-panel">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
            <div class="options_group">
                <p class="answers-product-panel__intro">
                    <?php esc_html_e('Add questions buyers ask before purchase. They render as an accessible accordion on the product page and can feed FAQ rich results.', 'answers'); ?>
                </p>
                <?php FaqRepeater::render($items, 'answers_faqs'); ?>
            </div>
        </div>
        <?php
    }

    public function save(\WC_Product $product): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified on the next line before any input is read.
        $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('edit_product', $product->get_id())) {
            return;
        }

        // wp_unslash + per-field sanitisation happens inside FaqRepeater::sanitize.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $raw   = isset($_POST['answers_faqs']) && is_array($_POST['answers_faqs']) ? $_POST['answers_faqs'] : [];
        $items = FaqRepeater::sanitize($raw);

        if ($items === []) {
            $product->delete_meta_data(FaqRepository::META_PRODUCT_FAQS);

            return;
        }

        $product->update_meta_data(FaqRepository::META_PRODUCT_FAQS, $items);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = get_current_screen();

        if (! $screen instanceof \WP_Screen || $screen->post_type !== 'product') {
            return;
        }

        $this->enqueueRepeaterAssets();
    }

    /**
     * Shared enqueue for the repeater CSS/JS (also used by the FAQ-set editor).
     */
    public static function enqueueRepeaterAssets(): void
    {
        wp_enqueue_style(
            'answers-admin',
            ANSWERS_URL . 'assets/css/admin.css',
            [],
            \Answers\VERSION,
        );

        wp_enqueue_script(
            'answers-admin',
            ANSWERS_URL . 'assets/js/admin.js',
            [],
            \Answers\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }
}
