<?php

declare(strict_types=1);

namespace Answers\Admin;

use Answers\Contract\HasHooks;
use Answers\Data\FaqRepository;

defined('ABSPATH') || exit;

/**
 * Registers the reusable "FAQ Set" post type and its editor metaboxes.
 *
 * A FAQ set holds a question/answer repeater plus assignment rules — a list of
 * products and/or product categories it applies to. The repository merges a
 * product's matching sets with its per-product items at render time. The post
 * type is private (not publicly queryable) and managed under its own menu; only
 * users with manage_woocommerce may edit it.
 */
final class GlobalFaqSets implements HasHooks
{
    private const NONCE_ACTION = 'answers_save_faq_set';
    private const NONCE_FIELD  = 'answers_faq_set_nonce';

    public function registerHooks(): void
    {
        // The post type must be registered in every context so the repository's
        // WP_Query for assigned sets returns results on the storefront too.
        add_action('init', [$this, 'registerPostType']);

        if (! is_admin()) {
            return;
        }

        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . FaqRepository::POST_TYPE, [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerPostType(): void
    {
        register_post_type(FaqRepository::POST_TYPE, [
            'labels' => [
                'name'               => __('FAQ Sets', 'answers'),
                'singular_name'      => __('FAQ Set', 'answers'),
                'add_new'            => __('Add New', 'answers'),
                'add_new_item'       => __('Add New FAQ Set', 'answers'),
                'edit_item'          => __('Edit FAQ Set', 'answers'),
                'new_item'           => __('New FAQ Set', 'answers'),
                'view_item'          => __('View FAQ Set', 'answers'),
                'search_items'       => __('Search FAQ Sets', 'answers'),
                'not_found'          => __('No FAQ sets found.', 'answers'),
                'not_found_in_trash' => __('No FAQ sets found in Trash.', 'answers'),
                'menu_name'          => __('FAQ Sets', 'answers'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'hierarchical'        => false,
            'menu_icon'           => 'dashicons-editor-help',
            'menu_position'       => 58,
            'supports'            => ['title', 'page-attributes'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ]);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'answers_faq_set_items',
            __('Questions & Answers', 'answers'),
            [$this, 'renderItemsBox'],
            FaqRepository::POST_TYPE,
            'normal',
            'high',
        );

        add_meta_box(
            'answers_faq_set_assignment',
            __('Where to show this set', 'answers'),
            [$this, 'renderAssignmentBox'],
            FaqRepository::POST_TYPE,
            'side',
            'default',
        );
    }

    public function renderItemsBox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $stored = get_post_meta($post->ID, FaqRepository::META_SET_ITEMS, true);
        $items  = $this->normalisePairs($stored);

        FaqRepeater::render($items, 'answers_set_items');
    }

    public function renderAssignmentBox(\WP_Post $post): void
    {
        $products   = $this->normaliseIds(get_post_meta($post->ID, FaqRepository::META_SET_PRODUCTS, true));
        $categories = $this->normaliseIds(get_post_meta($post->ID, FaqRepository::META_SET_CATEGORIES, true));
        ?>
        <div class="answers-assignment">
            <p class="answers-assignment__field">
                <label for="answers_set_products"><strong><?php esc_html_e('Products', 'answers'); ?></strong></label>
                <input
                    type="text"
                    id="answers_set_products"
                    name="answers_set_products"
                    class="widefat"
                    value="<?php echo esc_attr(implode(', ', $products)); ?>"
                    placeholder="<?php esc_attr_e('e.g. 12, 34, 56', 'answers'); ?>"
                />
                <span class="description"><?php esc_html_e('Comma-separated product IDs this set applies to.', 'answers'); ?></span>
            </p>
            <p class="answers-assignment__field">
                <label for="answers_set_categories"><strong><?php esc_html_e('Product categories', 'answers'); ?></strong></label>
                <?php $this->renderCategoryChecklist($categories); ?>
                <span class="description"><?php esc_html_e('The set also shows on every product in the checked categories.', 'answers'); ?></span>
            </p>
        </div>
        <?php
    }

    /**
     * @param list<int> $selected
     */
    private function renderCategoryChecklist(array $selected): void
    {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => 200,
        ]);

        if (! is_array($terms) || $terms === []) {
            echo '<span class="description">' . esc_html__('No product categories yet.', 'answers') . '</span>';

            return;
        }
        ?>
        <span class="answers-assignment__checklist" id="answers_set_categories">
            <?php foreach ($terms as $term) :
                if (! $term instanceof \WP_Term) {
                    continue;
                }
                ?>
                <label class="answers-assignment__check">
                    <input
                        type="checkbox"
                        name="answers_set_categories[]"
                        value="<?php echo esc_attr((string) $term->term_id); ?>"
                        <?php checked(in_array((int) $term->term_id, $selected, true), true); ?>
                    />
                    <?php echo esc_html($term->name); ?>
                </label>
            <?php endforeach; ?>
        </span>
        <?php
    }

    /**
     * @param int $postId
     */
    public function save(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== FaqRepository::POST_TYPE) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
        $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $rawItems = isset($_POST['answers_set_items']) && is_array($_POST['answers_set_items']) ? $_POST['answers_set_items'] : [];
        $items    = FaqRepeater::sanitize($rawItems);

        if ($items === []) {
            delete_post_meta($postId, FaqRepository::META_SET_ITEMS);
        } else {
            update_post_meta($postId, FaqRepository::META_SET_ITEMS, $items);
        }

        $products = isset($_POST['answers_set_products'])
            ? $this->parseIdList(sanitize_text_field(wp_unslash($_POST['answers_set_products'])))
            : [];
        update_post_meta($postId, FaqRepository::META_SET_PRODUCTS, $products);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Each element is cast to int via normaliseIds.
        $rawCategories = isset($_POST['answers_set_categories']) && is_array($_POST['answers_set_categories']) ? wp_unslash($_POST['answers_set_categories']) : [];
        $categories    = $this->normaliseIds($rawCategories);
        update_post_meta($postId, FaqRepository::META_SET_CATEGORIES, $categories);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = get_current_screen();

        if (! $screen instanceof \WP_Screen || $screen->post_type !== FaqRepository::POST_TYPE) {
            return;
        }

        ProductFaqTab::enqueueRepeaterAssets();
    }

    /**
     * @return list<int>
     */
    private function parseIdList(string $value): array
    {
        $parts = preg_split('/[\s,]+/', $value) ?: [];

        return $this->normaliseIds($parts);
    }

    /**
     * @param mixed $stored
     * @return list<int>
     */
    private function normaliseIds(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $ids = [];

        foreach ($stored as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $stored
     * @return list<array{question: string, answer: string}>
     */
    private function normalisePairs(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $pairs = [];

        foreach ($stored as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pairs[] = [
                'question' => isset($row['question']) ? (string) $row['question'] : '',
                'answer'   => isset($row['answer']) ? (string) $row['answer'] : '',
            ];
        }

        return $pairs;
    }
}
