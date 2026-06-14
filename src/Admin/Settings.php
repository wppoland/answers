<?php

declare(strict_types=1);

namespace Answers\Admin;

use Answers\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Settings screen registered as a WooCommerce submenu ("WooCommerce → Answers").
 *
 * Stores settings in the `answers_settings` option (array): placement (tab or
 * after-summary), the tab title / heading overrides, whether the first item
 * starts open, and whether to emit FAQPage schema.org JSON-LD. All output is
 * escaped; all input is sanitised and constrained on save. The save capability
 * is aligned to manage_woocommerce so shop managers can edit it.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'answers_settings';
    private const PAGE   = 'answers-settings';

    /** Allowed placement keys. */
    private const PLACEMENTS = ['tab', 'summary'];

    /** Incremented to give each inline-help control a unique id/anchor. */
    private int $helpSeq = 0;

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_' . self::PAGE) {
            return;
        }

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

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Answers — Product FAQs', 'answers'),
            __('Answers', 'answers'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings();
        ?>
        <div class="wrap answers-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="answers-intro">
                <div>
                    <h2><?php esc_html_e('Answer buyer questions, right on the product page', 'answers'); ?></h2>
                    <p>
                        <?php esc_html_e('Add FAQs to a product in its "FAQs" data tab, or build reusable FAQ sets and assign them to products and categories. They render as an accessible, keyboard-friendly accordion — and can emit FAQ rich-result structured data.', 'answers'); ?>
                    </p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="answers-card">
                    <h2><?php esc_html_e('Display', 'answers'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Enable FAQs', 'answers'); ?>
                                    <?php $this->help(__('The master switch. When off, no FAQs render on the storefront and the FAQ stylesheet is not loaded — zero front-end impact.', 'answers')); ?>
                                </th>
                                <td>
                                    <label for="answers_enabled">
                                        <input
                                            type="checkbox"
                                            id="answers_enabled"
                                            name="<?php echo esc_attr(self::OPTION); ?>[enabled]"
                                            value="1"
                                            <?php checked((bool) ($settings['enabled'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Show product FAQs on the storefront.', 'answers'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="answers_placement"><?php esc_html_e('Placement', 'answers'); ?></label>
                                    <?php $this->help(__('Choose a dedicated "FAQs" product-information tab, or render the accordion directly after the product summary (near the add-to-cart area).', 'answers')); ?>
                                </th>
                                <td>
                                    <select id="answers_placement" name="<?php echo esc_attr(self::OPTION); ?>[placement]">
                                        <?php
                                        $current      = (string) ($settings['placement'] ?? 'tab');
                                        $placeLabels  = [
                                            'tab'     => __('Product information tab', 'answers'),
                                            'summary' => __('After the product summary', 'answers'),
                                        ];
                                        foreach (self::PLACEMENTS as $placement) :
                                            ?>
                                            <option value="<?php echo esc_attr($placement); ?>" <?php selected($current, $placement); ?>>
                                                <?php echo esc_html($placeLabels[$placement] ?? $placement); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="answers_tab_title"><?php esc_html_e('Tab title', 'answers'); ?></label>
                                    <?php $this->help(__('The label of the FAQ tab. Leave blank to use the default "FAQs". Only used with the tab placement.', 'answers')); ?>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="answers_tab_title"
                                        name="<?php echo esc_attr(self::OPTION); ?>[tab_title]"
                                        value="<?php echo esc_attr((string) ($settings['tab_title'] ?? '')); ?>"
                                        class="regular-text"
                                        placeholder="<?php esc_attr_e('FAQs', 'answers'); ?>"
                                    />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="answers_heading"><?php esc_html_e('Heading', 'answers'); ?></label>
                                    <?php $this->help(__('The heading shown above the accordion when rendered after the summary. Leave blank for the default "Frequently asked questions".', 'answers')); ?>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="answers_heading"
                                        name="<?php echo esc_attr(self::OPTION); ?>[heading]"
                                        value="<?php echo esc_attr((string) ($settings['heading'] ?? '')); ?>"
                                        class="regular-text"
                                        placeholder="<?php esc_attr_e('Frequently asked questions', 'answers'); ?>"
                                    />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('First item open', 'answers'); ?>
                                    <?php $this->help(__('Start the accordion with its first question already expanded. Off keeps every item collapsed until clicked.', 'answers')); ?>
                                </th>
                                <td>
                                    <label for="answers_first_open">
                                        <input
                                            type="checkbox"
                                            id="answers_first_open"
                                            name="<?php echo esc_attr(self::OPTION); ?>[first_open]"
                                            value="1"
                                            <?php checked((bool) ($settings['first_open'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Expand the first question by default.', 'answers'); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="answers-card">
                    <h2><?php esc_html_e('Search engine results', 'answers'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('FAQ structured data', 'answers'); ?>
                                    <?php $this->help(__('Outputs FAQPage schema.org JSON-LD for products that have FAQs, helping search engines understand your questions and answers. Only enable if these FAQs are genuinely visible on the page.', 'answers')); ?>
                                </th>
                                <td>
                                    <label for="answers_output_schema">
                                        <input
                                            type="checkbox"
                                            id="answers_output_schema"
                                            name="<?php echo esc_attr(self::OPTION); ?>[output_schema]"
                                            value="1"
                                            <?php checked((bool) ($settings['output_schema'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Emit FAQPage JSON-LD structured data.', 'answers'); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: shortcode wrapped in <code>. */
                            esc_html__('Need FAQs somewhere else on the product page? Drop %s into a block or template to render the current product\'s FAQs.', 'answers'),
                            '<code>[answers_faqs]</code>',
                        );
                        ?>
                    </p>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render an accessible inline-help affordance using the native Popover API,
     * also wired via aria-describedby; the bundled script provides a fallback.
     */
    private function help(string $text): void
    {
        $id = 'answers-help-' . (++$this->helpSeq);
        ?>
        <button
            type="button"
            class="answers-help"
            aria-label="<?php esc_attr_e('More information', 'answers'); ?>"
            aria-describedby="<?php echo esc_attr($id); ?>"
            aria-expanded="false"
            popovertarget="<?php echo esc_attr($id); ?>"
        >?</button>
        <div id="<?php echo esc_attr($id); ?>" class="answers-tip" role="tooltip" popover hidden>
            <?php echo esc_html($text); ?>
        </div>
        <?php
    }

    /**
     * Sanitise and constrain the submitted settings before save.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $placement = isset($raw['placement']) ? sanitize_key((string) $raw['placement']) : 'tab';

        if (! in_array($placement, self::PLACEMENTS, true)) {
            $placement = 'tab';
        }

        $sanitized = [
            'enabled'       => ! empty($raw['enabled']),
            'placement'     => $placement,
            'tab_title'     => isset($raw['tab_title']) ? sanitize_text_field((string) $raw['tab_title']) : '',
            'heading'       => isset($raw['heading']) ? sanitize_text_field((string) $raw['heading']) : '',
            'first_open'    => ! empty($raw['first_open']),
            'output_schema' => ! empty($raw['output_schema']),
        ];

        return (array) apply_filters('answers/sanitize_settings', $sanitized, $raw);
    }

    /**
     * Stored settings merged over packaged defaults.
     *
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
