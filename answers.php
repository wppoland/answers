<?php
/**
 * Plugin Name:       Answers - Product FAQs for WooCommerce
 * Plugin URI:        https://plogins.com/answers/
 * Description:        Add per-product FAQs as an accessible accordion to reduce pre-sale questions.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       answers
 * Domain Path:       /languages
 * WC requires at least: 8.0
 *
 * @package Answers
 */

declare(strict_types=1);

namespace Answers;

defined('ABSPATH') || exit;

const VERSION     = '0.1.1';
const PLUGIN_FILE = __FILE__;

define('ANSWERS_DIR', plugin_dir_path(__FILE__));
define('ANSWERS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// HPOS + cart/checkout blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Answers - Product FAQs for WooCommerce requires WooCommerce to be active.', 'answers');
            echo '</p></div>';
        });
        return;
    }

    add_action('init', static function (): void {
        Plugin::instance()->boot();
    }, 0);
}, 10);
