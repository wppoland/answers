<?php

declare(strict_types=1);

namespace Answers\Admin;

defined('ABSPATH') || exit;

/**
 * Shared rendering and sanitisation for the question/answer repeater used by
 * both the per-product FAQ tab and the global FAQ-set editor.
 *
 * Markup is built from a hidden <template> row that the bundled admin script
 * clones for "Add question", so the control works with zero rows and degrades
 * to the server-rendered rows without JS (existing rows remain editable).
 */
final class FaqRepeater
{
    /**
     * Render the repeater control.
     *
     * @param list<array{question: string, answer: string}> $items
     * @param string                                        $fieldName The name= base, e.g. "answers_faqs".
     */
    public static function render(array $items, string $fieldName): void
    {
        ?>
        <div class="answers-repeater" data-answers-repeater>
            <div class="answers-repeater__rows" data-answers-rows>
                <?php
                if ($items === []) {
                    self::renderRow('__INDEX__', '', '', $fieldName, true);
                } else {
                    foreach ($items as $index => $item) {
                        self::renderRow((string) $index, $item['question'], $item['answer'], $fieldName, false);
                    }
                }
                ?>
            </div>

            <template data-answers-template>
                <?php self::renderRow('__INDEX__', '', '', $fieldName, true); ?>
            </template>

            <p class="answers-repeater__actions">
                <button type="button" class="button answers-repeater__add" data-answers-add>
                    <?php esc_html_e('Add question', 'answers'); ?>
                </button>
            </p>
            <p class="description answers-repeater__hint">
                <?php esc_html_e('Drag is not required — questions render in the order listed. Leave a row blank to remove it on save. Answers accept basic HTML (links, lists, bold).', 'answers'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render a single repeater row.
     */
    private static function renderRow(string $index, string $question, string $answer, string $fieldName, bool $isTemplate): void
    {
        // Template rows are inert until cloned, so disable their inputs to keep
        // them out of the POST payload.
        $disabled = $isTemplate ? ' disabled' : '';
        $base     = $fieldName . '[' . $index . ']';
        ?>
        <div class="answers-repeater__row" data-answers-row>
            <div class="answers-repeater__field">
                <label class="answers-repeater__label">
                    <span class="answers-repeater__label-text"><?php esc_html_e('Question', 'answers'); ?></span>
                    <input
                        type="text"
                        class="answers-repeater__question widefat"
                        name="<?php echo esc_attr($base . '[question]'); ?>"
                        value="<?php echo esc_attr($question); ?>"
                        placeholder="<?php esc_attr_e('e.g. What is your return policy?', 'answers'); ?>"
                        <?php echo esc_attr($disabled); ?>
                    />
                </label>
            </div>
            <div class="answers-repeater__field">
                <label class="answers-repeater__label">
                    <span class="answers-repeater__label-text"><?php esc_html_e('Answer', 'answers'); ?></span>
                    <textarea
                        class="answers-repeater__answer widefat"
                        name="<?php echo esc_attr($base . '[answer]'); ?>"
                        rows="3"
                        placeholder="<?php esc_attr_e('Write a clear, concise answer. Basic HTML is allowed.', 'answers'); ?>"
                        <?php echo esc_attr($disabled); ?>
                    ><?php echo esc_textarea($answer); ?></textarea>
                </label>
            </div>
            <button type="button" class="button-link answers-repeater__remove" data-answers-remove aria-label="<?php esc_attr_e('Remove this question', 'answers'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php
    }

    /**
     * Sanitise a submitted repeater payload into clean question/answer pairs.
     * Questions are plain text; answers are filtered with wp_kses_post. Rows
     * missing either field are dropped.
     *
     * @param mixed $raw
     * @return list<array{question: string, answer: string}>
     */
    public static function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $pairs = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = isset($row['question']) ? sanitize_text_field((string) wp_unslash($row['question'])) : '';
            $answer   = isset($row['answer']) ? wp_kses_post((string) wp_unslash($row['answer'])) : '';

            $question = trim($question);
            $answer   = trim($answer);

            if ($question === '' || $answer === '') {
                continue;
            }

            $pairs[] = [
                'question' => $question,
                'answer'   => $answer,
            ];
        }

        return $pairs;
    }
}
