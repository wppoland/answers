<?php

declare(strict_types=1);

namespace Answers\Data;

defined('ABSPATH') || exit;

/**
 * An immutable value object for a single FAQ entry.
 *
 * The question is plain text; the answer is trusted HTML already filtered with
 * wp_kses_post on save. Both are guaranteed non-empty by the repository before a
 * FaqItem is constructed, so the renderer can treat them as display-ready.
 */
final class FaqItem
{
    public function __construct(
        public readonly string $question,
        public readonly string $answer,
    ) {
    }

    /**
     * Plain-text answer for schema output (strips tags, collapses whitespace).
     */
    public function plainAnswer(): string
    {
        return trim((string) preg_replace('/\s+/', ' ', wp_strip_all_tags($this->answer)));
    }
}
