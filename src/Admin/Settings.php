<?php

declare(strict_types=1);

namespace Answers\Admin;

use Answers\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Settings screen registered as a WooCommerce submenu ("WooCommerce → Answers").
 *
 * Stores settings in the `answers_settings` option (array): whether FAQs are
 * shown on the storefront and the label of the FAQ tab. All output is escaped;
 * all input is sanitised on save. The save capability is aligned to
 * manage_woocommerce so shop managers can edit it.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'answers_settings';
    private const PAGE   = 'answers-settings';

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

        $tabTitle      = trim((string) ($settings['tab_title'] ?? ''));
        $defaultTitle  = __('FAQs', 'answers');
        $effectiveTitle = $tabTitle !== '' ? $tabTitle : $defaultTitle;
        ?>
        <div class="wrap answers-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="answers-intro">
                <div>
                    <h2><?php esc_html_e('Answer buyer questions, right on the product page', 'answers'); ?></h2>
                    <p>
                        <?php esc_html_e('Add FAQs to a product in its "FAQs" data tab. They render as an accessible, keyboard-friendly accordion in a "FAQs" tab on the product page.', 'answers'); ?>
                    </p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="answers-card">
                    <h2><?php esc_html_e('Display', 'answers'); ?></h2>
                    <p class="answers-card__desc">
                        <?php esc_html_e('Control whether the FAQ tab appears on product pages and what it is called. With FAQs enabled, any product that has FAQ items gets the tab automatically — no per-product setup beyond authoring the questions.', 'answers'); ?>
                    </p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable FAQs', 'answers'); ?></th>
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
                                    <p class="description"><?php esc_html_e('When off, no FAQs render and the FAQ stylesheet is not loaded.', 'answers'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="answers_tab_title"><?php esc_html_e('Tab title', 'answers'); ?></label>
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
                                    <p class="description">
                                        <?php
                                        printf(
                                            /* translators: %s: the tab label currently in effect, e.g. "FAQs". */
                                            esc_html__('Sets the tab label shoppers see next to "Description" and "Reviews". Leave blank to use "FAQs". Currently showing: %s', 'answers'),
                                            '<strong>' . esc_html($effectiveTitle) . '</strong>',
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="answers-preview" aria-hidden="true">
                        <p class="answers-preview__caption"><?php esc_html_e('Preview — how the product-page tabs read:', 'answers'); ?></p>
                        <ul class="answers-preview__tabs">
                            <li class="answers-preview__tab"><?php esc_html_e('Description', 'answers'); ?></li>
                            <li class="answers-preview__tab answers-preview__tab--active"><?php echo esc_html($effectiveTitle); ?></li>
                            <li class="answers-preview__tab"><?php esc_html_e('Reviews', 'answers'); ?></li>
                        </ul>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitise the submitted settings before save.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        return [
            'enabled'   => ! empty($raw['enabled']),
            'tab_title' => isset($raw['tab_title']) ? sanitize_text_field((string) $raw['tab_title']) : '',
        ];
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
