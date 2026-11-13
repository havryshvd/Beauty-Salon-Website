<?php
/**
 * Plugin Name: Запись в салон
 * Description: Услуги, мастера, расписание и запись клиентов с уведомлениями Telegram.
 * Version: 1.4.4
 * Requires PHP: 8.0
 * Text Domain: salon-booking
 */
defined('ABSPATH') || exit;

define('SBK_DIR', plugin_dir_path(__FILE__));
define('SBK_URL', plugin_dir_url(__FILE__));
define('SBK_VERSION', '1.4.4');
foreach (array('database', 'booking', 'telegram', 'telegram-admin', 'admin', 'public') as $part) {
    require_once SBK_DIR . 'includes/class-sbk-' . $part . '.php';
}
register_activation_hook(__FILE__, array('SBK_Database', 'activate'));
register_deactivation_hook(__FILE__, array('SBK_Database', 'deactivate'));
add_filter('cron_schedules', array('SBK_Database', 'schedules'));
add_action('plugins_loaded', array('SBK_Database', 'maybe_upgrade'));
add_action('init', array('SBK_Public', 'init'));
add_action('rest_api_init', array('SBK_Public', 'routes'));
add_action('admin_menu', array('SBK_Admin', 'menu'));
add_action('admin_enqueue_scripts', array('SBK_Admin', 'assets'));
add_action('admin_post_sbk_save', array('SBK_Admin', 'save'));
add_action('admin_post_sbk_status', array('SBK_Admin', 'status'));
add_action('admin_post_sbk_test_telegram', array('SBK_Admin', 'test_telegram'));
add_action('sbk_send_telegram', array('SBK_Telegram', 'process_queue'));
add_action('sbk_poll_telegram', array('SBK_Telegram', 'poll_updates'));
add_action('shutdown', array('SBK_Telegram', 'dispatch_queued'), 20);
