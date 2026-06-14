<?php
/**
 * Uninstall cleanup for Answers.
 *
 * Runs when the plugin is deleted from wp-admin. Removes the plugin's options
 * and its global FAQ sets (a private post type the plugin owns). Per-product FAQ
 * meta (_answers_faqs) is intentionally left in place: it is user content
 * attached to products that may be reused if the plugin is reinstalled.
 *
 * @package Answers
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('answers_settings');
delete_option('answers_db_version');

// Delete the plugin-owned FAQ sets and their meta.
$answers_set_ids = get_posts([
    'post_type'      => 'answers_faq_set',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
]);

foreach ($answers_set_ids as $answers_set_id) {
    wp_delete_post((int) $answers_set_id, true);
}
