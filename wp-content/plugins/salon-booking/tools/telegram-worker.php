<?php
// Optional background worker for Telegram notifications and commands.
if (PHP_SAPI !== 'cli') exit;

define('WP_USE_THEMES', false);
define('DISABLE_WP_CRON', true);
require dirname(__DIR__, 4) . '/wp-load.php';
if (!class_exists('SBK_Telegram')) exit;
set_time_limit(0);

$lock_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sbk-telegram-' . substr(hash('sha256', ABSPATH), 0, 16) . '.lock';
$lock = fopen($lock_path, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;

$last_heartbeat = 0;
$started = time();
$once = in_array('--once', $argv, true);
while (time() - $started < 4 * HOUR_IN_SECONDS) {
    try {
        // The WordPress option cache in a long-running process can outlive settings changes.
        foreach (array('sbk_telegram_token', 'sbk_telegram_chat', 'sbk_telegram_offset') as $option) {
            wp_cache_delete($option, 'options');
        }
        if (!get_option('sbk_telegram_token')) {
            if ($once) break;
            sleep(3);
            continue;
        }
        if (time() - $last_heartbeat >= 20) {
            update_option('sbk_telegram_worker_seen', time(), false);
            $last_heartbeat = time();
        }
        SBK_Telegram::process_queue();
        $polled = SBK_Telegram::poll_updates(true);
        SBK_Telegram::process_queue();
        if ($once) break;
        if (!$polled) sleep(2);
    } catch (Throwable $error) {
        error_log('Salon Telegram worker: ' . $error->getMessage());
        if ($once) exit(1);
        sleep(3);
    }
}
