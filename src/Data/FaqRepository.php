<?php

declare(strict_types=1);

namespace Answers\Data;

defined('ABSPATH') || exit;

/**
 * Resolves the full, ordered list of FAQ items for a product.
 *
 * Two sources are merged, de-duplicated by question, and capped:
 *  1. Per-product FAQ items stored in the `_answers_faqs` post meta (an array
 *     of question/answer pairs authored in the product data "FAQs" tab).
 *  2. Reusable global FAQ sets (the `answers_faq_set` post type) that are
 *     assigned to the product directly or to one of its categories.
 *
 * All accessors are defensive: malformed or missing data yields an empty list
 * rather than a warning, so the renderer can never produce broken markup.
 */
final class FaqRepository
{
    /** Post meta key holding the per-product FAQ repeater (array of pairs). */
    public const META_PRODUCT_FAQS = '_answers_faqs';

    /** Post type slug for reusable global FAQ sets. */
    public const POST_TYPE = 'answers_faq_set';

    /** Meta on a FAQ set: array of product IDs it is assigned to. */
    public const META_SET_PRODUCTS = '_answers_set_products';

    /** Meta on a FAQ set: array of product-category term IDs it applies to. */
    public const META_SET_CATEGORIES = '_answers_set_categories';

    /** Meta on a FAQ set: the array of question/answer pairs. */
    public const META_SET_ITEMS = '_answers_set_items';

    /** Hard cap on rendered items to keep output and schema sane. */
    private const MAX_ITEMS = 50;

    /**
     * Get the ordered, de-duplicated FAQ items for a product.
     *
     * @return list<FaqItem>
     */
    public function forProduct(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $pairs = array_merge(
            $this->productItems($productId),
            $this->globalSetItems($productId),
        );

        $items = [];
        $seen  = [];

        foreach ($pairs as $pair) {
            $question = isset($pair['question']) ? trim((string) $pair['question']) : '';
            $answer   = isset($pair['answer']) ? trim((string) $pair['answer']) : '';

            if ($question === '' || $answer === '') {
                continue;
            }

            $key = strtolower($question);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[]    = new FaqItem($question, $answer);

            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        /**
         * Filter the final FAQ item list for a product.
         *
         * @param list<FaqItem> $items     The resolved FAQ items.
         * @param int           $productId The product ID.
         */
        $filtered = apply_filters('answers/product_faqs', $items, $productId);

        return is_array($filtered) ? array_values(array_filter(
            $filtered,
            static fn ($item): bool => $item instanceof FaqItem,
        )) : $items;
    }

    /**
     * Raw per-product FAQ pairs from post meta.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function rawProductItems(int $productId): array
    {
        $stored = get_post_meta($productId, self::META_PRODUCT_FAQS, true);

        return $this->normalisePairs($stored);
    }

    /**
     * Per-product FAQ pairs.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function productItems(int $productId): array
    {
        return $this->rawProductItems($productId);
    }

    /**
     * FAQ pairs gathered from every global set that targets the product (by
     * direct assignment or by one of the product's categories).
     *
     * @return list<array{question: string, answer: string}>
     */
    private function globalSetItems(int $productId): array
    {
        $setIds = $this->setIdsForProduct($productId);

        if ($setIds === []) {
            return [];
        }

        $pairs = [];

        foreach ($setIds as $setId) {
            $stored = get_post_meta($setId, self::META_SET_ITEMS, true);
            foreach ($this->normalisePairs($stored) as $pair) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * IDs of published FAQ sets applicable to the product.
     *
     * @return list<int>
     */
    private function setIdsForProduct(int $productId): array
    {
        $query = new \WP_Query([
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => 100,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'orderby'                => 'menu_order title',
            'order'                  => 'ASC',
        ]);

        /** @var list<int> $ids */
        $ids = array_map('intval', $query->posts);

        if ($ids === []) {
            return [];
        }

        $categoryIds = $this->productCategoryIds($productId);
        $matched     = [];

        foreach ($ids as $setId) {
            if ($this->setMatchesProduct($setId, $productId, $categoryIds)) {
                $matched[] = $setId;
            }
        }

        return $matched;
    }

    /**
     * Whether a FAQ set targets the given product or any of its categories.
     *
     * @param list<int> $categoryIds
     */
    private function setMatchesProduct(int $setId, int $productId, array $categoryIds): bool
    {
        $products = $this->normaliseIds(get_post_meta($setId, self::META_SET_PRODUCTS, true));

        if (in_array($productId, $products, true)) {
            return true;
        }

        $categories = $this->normaliseIds(get_post_meta($setId, self::META_SET_CATEGORIES, true));

        if ($categories === [] || $categoryIds === []) {
            return false;
        }

        return array_intersect($categories, $categoryIds) !== [];
    }

    /**
     * Product-category term IDs for a product.
     *
     * @return list<int>
     */
    private function productCategoryIds(int $productId): array
    {
        $terms = get_the_terms($productId, 'product_cat');

        if (! is_array($terms)) {
            return [];
        }

        $ids = [];

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $ids[] = (int) $term->term_id;
            }
        }

        return $ids;
    }

    /**
     * Coerce arbitrary stored data into a clean list of question/answer pairs.
     *
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

    /**
     * Coerce arbitrary stored data into a list of positive integer IDs.
     *
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
}
