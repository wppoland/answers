<?php

declare(strict_types=1);

namespace Answers\Data;

defined('ABSPATH') || exit;

/**
 * Resolves the ordered list of FAQ items for a product.
 *
 * Items come from the per-product FAQ repeater stored in the `_answers_faqs`
 * post meta (an array of question/answer pairs authored in the product data
 * "FAQs" tab). Accessors are defensive: malformed or missing data yields an
 * empty list rather than a warning, so the renderer can never produce broken
 * markup.
 */
final class FaqRepository
{
    /** Post meta key holding the per-product FAQ repeater (array of pairs). */
    public const META_PRODUCT_FAQS = '_answers_faqs';

    /** Hard cap on rendered items to keep output sane. */
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

        $items = [];
        $seen  = [];

        foreach ($this->rawProductItems($productId) as $pair) {
            $question = trim($pair['question']);
            $answer   = trim($pair['answer']);

            if ($question === '' || $answer === '') {
                continue;
            }

            $key = strtolower($question);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[]    = new FaqItem($question, $answer, md5($key));

            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * Raw per-product FAQ pairs from post meta.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function rawProductItems(int $productId): array
    {
        return $this->normalisePairs(get_post_meta($productId, self::META_PRODUCT_FAQS, true));
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
}
