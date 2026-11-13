<?php
defined('ABSPATH') || exit;

final class SBK_Telegram {
    private static $dispatch_needed = false;

    public static function encrypt($token) {
        if (!function_exists('openssl_encrypt')) return new WP_Error('sbk_crypto', 'Для хранения токена требуется расширение OpenSSL.');
        $iv = random_bytes(12);
        $tag = '';
        $value = openssl_encrypt($token, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
        return $value === false ? new WP_Error('sbk_crypto', 'Не удалось сохранить токен.') : base64_encode($iv . $tag . $value);
    }

    private static function token() {
        $raw = base64_decode((string) get_option('sbk_telegram_token', ''), true);
        if (!$raw || strlen($raw) < 29 || !function_exists('openssl_decrypt')) return '';
        return (string) openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    }

    private static function request($method, $body = array()) {
        $token = self::token();
        if (!$token) return new WP_Error('sbk_telegram_missing', 'Telegram не настроен.');
        $timeout = max(12, (int) ($body['timeout'] ?? 0) + 5);
        $response = wp_remote_post('https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method, array('timeout' => $timeout, 'body' => $body));
        if (is_wp_error($response)) return new WP_Error('sbk_telegram_failed', 'Telegram недоступен.');
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($decoded) || empty($decoded['ok'])) return new WP_Error('sbk_telegram_failed', 'Telegram отклонил запрос.');
        return $decoded['result'];
    }

    private static function send($chat, $text, $buttons = array()) {
        if (!$chat) return false;
        $body = array('chat_id' => (string) $chat, 'text' => $text, 'disable_web_page_preview' => 'true');
        if ($buttons) $body['reply_markup'] = wp_json_encode(array('inline_keyboard' => $buttons));
        return !is_wp_error(self::request('sendMessage', $body));
    }

    public static function test() {
        $chat = (string) get_option('sbk_telegram_chat', '');
        if (!$chat) return new WP_Error('sbk_telegram_chat_missing', 'Сначала укажите chat ID личного чата.');
        $bot = self::request('getMe');
        if (is_wp_error($bot)) return $bot;
        $sent = self::request('sendMessage', array('chat_id' => $chat, 'text' => 'Проверка связи: бот записи в салон подключён.'));
        if (is_wp_error($sent)) return $sent;
        return array('username' => sanitize_text_field($bot['username'] ?? ''));
    }

    public static function admin_chat_id() {
        $chat = (string) get_option('sbk_telegram_chat', '');
        return ctype_digit($chat) && (int) $chat > 0 ? $chat : '';
    }

    public static function send_admin($text, $buttons = array()) {
        return self::send(self::admin_chat_id(), $text, $buttons);
    }

    public static function booking_buttons($booking, $with_open = true) {
        $id = (int) $booking->id;
        $buttons = array();
        if ($booking->status === 'pending') $buttons[] = array(array('text' => '✅ Подтвердить', 'callback_data' => "sbk:s:$id:c"));
        if (in_array($booking->status, array('pending', 'confirmed'), true)) {
            $buttons[] = array(
                array('text' => '🗓 Перенести', 'callback_data' => "sbk:r:$id:0"),
                array('text' => $booking->status === 'pending' ? '❌ Отклонить' : '❌ Отменить', 'callback_data' => "sbk:s:$id:x")
            );
        }
        if ($with_open) $buttons[] = array(
            array('text' => 'Открыть запись', 'callback_data' => "sba:booking:$id"),
            array('text' => 'Главное меню', 'callback_data' => 'sba:menu')
        );
        return $buttons;
    }

    public static function queue($booking_id, $event, $old_master_id = 0) {
        global $wpdb;
        if (!get_option('sbk_telegram_token')) return;
        $chat = self::admin_chat_id();
        if (!$chat) return;
        $chats = array($chat);
        foreach ($chats as $chat) {
            $wpdb->insert(SBK_Database::table('telegram_queue'), array('booking_id' => $booking_id, 'chat_id' => $chat, 'event' => $event, 'next_utc' => gmdate('Y-m-d H:i:s')));
        }
        if ($chats) {
            $next = wp_next_scheduled('sbk_send_telegram');
            if ($next && $next > time() + 5) wp_unschedule_event($next, 'sbk_send_telegram');
            if (!wp_next_scheduled('sbk_send_telegram')) wp_schedule_single_event(time(), 'sbk_send_telegram');
            self::$dispatch_needed = true;
        }
    }

    public static function dispatch_queued() {
        if (!self::$dispatch_needed || wp_doing_cron()) return;
        if (function_exists('spawn_cron')) spawn_cron();
    }

    public static function process_queue() {
        global $wpdb;
        if (!self::token()) return;
        if ('1' !== (string) $wpdb->get_var("SELECT GET_LOCK('sbk_telegram_queue', 0)")) return;
        try {
            $rows = $wpdb->get_results('SELECT * FROM ' . SBK_Database::table('telegram_queue') . ' WHERE sent_utc IS NULL AND attempts<10 AND next_utc<=UTC_TIMESTAMP() ORDER BY id LIMIT 10');
            foreach ($rows as $row) {
                if ((string) $row->chat_id !== self::admin_chat_id()) {
                    $wpdb->update(SBK_Database::table('telegram_queue'), array('sent_utc' => gmdate('Y-m-d H:i:s')), array('id' => $row->id));
                    continue;
                }
                $booking = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $row->booking_id));
                if (!$booking) { $wpdb->update(SBK_Database::table('telegram_queue'), array('sent_utc' => gmdate('Y-m-d H:i:s')), array('id' => $row->id)); continue; }
                $labels = array('pending' => 'ожидает подтверждения', 'confirmed' => 'подтверждена', 'cancelled' => 'отменена', 'completed' => 'завершена');
                $headings = array('new' => 'Новая запись', 'edit' => 'Запись изменена', 'status' => 'Статус записи изменён');
                $date = wp_date('d.m.Y H:i', strtotime($booking->starts_utc . ' UTC'), wp_timezone());
                $message = ($headings[$row->event] ?? 'Запись изменена') . ' №' . $booking->id . "\n" .
                    'Статус: ' . ($labels[$booking->status] ?? $booking->status) . "\n" .
                    'Услуга: ' . $booking->service_name . "\n" . 'Мастер: ' . $booking->master_name . "\n" .
                    'Время: ' . $date . "\n" . 'Клиент: ' . $booking->client_name . "\n" . 'Телефон: ' . $booking->phone;
                $chat = $row->chat_id ?: (string) get_option('sbk_telegram_chat', '');
                $buttons = self::booking_buttons($booking);
                if (self::send($chat, $message, $buttons)) {
                    $wpdb->update(SBK_Database::table('telegram_queue'), array('sent_utc' => gmdate('Y-m-d H:i:s')), array('id' => $row->id));
                } else {
                    $attempts = min(10, (int) $row->attempts + 1);
                    $wpdb->update(SBK_Database::table('telegram_queue'), array('attempts' => $attempts, 'next_utc' => gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** min($attempts, 6))))), array('id' => $row->id));
                }
            }
            $pending = $wpdb->get_var('SELECT MIN(next_utc) FROM ' . SBK_Database::table('telegram_queue') . ' WHERE sent_utc IS NULL AND attempts<10');
            if ($pending) wp_schedule_single_event(max(time() + 30, strtotime($pending . ' UTC')), 'sbk_send_telegram');
        } finally {
            $wpdb->get_var("SELECT RELEASE_LOCK('sbk_telegram_queue')");
        }
    }

    public static function poll_updates($live = false) {
        if (!self::token()) return false;
        global $wpdb;
        if ('1' !== (string) $wpdb->get_var("SELECT GET_LOCK('sbk_telegram_poll', 0)")) return false;
        try {
            $offset = (int) get_option('sbk_telegram_offset', 0);
            $updates = self::request('getUpdates', array('offset' => $offset, 'timeout' => $live ? 2 : 0, 'limit' => 20, 'allowed_updates' => wp_json_encode(array('callback_query', 'message'))));
            if (is_wp_error($updates) || !is_array($updates)) return false;
            update_option('sbk_telegram_last_poll', time(), false);
            foreach ($updates as $update) {
                $id = absint($update['update_id'] ?? 0);
                if ($id < $offset) continue;
                if (isset($update['callback_query']) && is_array($update['callback_query'])) self::callback($update['callback_query']);
                if (isset($update['message']) && is_array($update['message'])) self::message($update['message']);
                $offset = $id + 1;
                update_option('sbk_telegram_offset', $offset, false);
            }
            return true;
        } finally {
            $wpdb->get_var("SELECT RELEASE_LOCK('sbk_telegram_poll')");
        }
    }

    private static function message($message) {
        $chat = (string) ($message['chat']['id'] ?? '');
        $from = (string) ($message['from']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        $is_id_request = preg_match('~^/(?:start|id)(?:@\w+)?(?:\s|$)|^(?:id|айди)$~iu', $text);
        if (($message['chat']['type'] ?? '') !== 'private' || $chat !== $from) return;
        if ($chat === self::admin_chat_id()) {
            SBK_Telegram_Admin::message($text, (string) ($message['contact']['phone_number'] ?? ''));
            return;
        }
        if (!$is_id_request) return;
        self::send($chat, 'Ваш личный chat ID: ' . $chat . '. Передайте его администратору салона для уведомлений.');
    }

    private static function callback($query) {
        global $wpdb;
        $chat = (string) ($query['message']['chat']['id'] ?? '');
        $from = (string) ($query['from']['id'] ?? '');
        $allowed = ($query['message']['chat']['type'] ?? '') === 'private' && $chat === $from && $chat === self::admin_chat_id();
        self::request('answerCallbackQuery', array('callback_query_id' => (string) ($query['id'] ?? ''), 'text' => $allowed ? 'Обрабатываем…' : 'Нет доступа.'));
        if (!$allowed) return;
        $parts = explode(':', (string) ($query['data'] ?? ''));
        if (($parts[0] ?? '') === 'sba') {
            SBK_Telegram_Admin::callback($parts);
            return;
        }
        if (($parts[0] ?? '') !== 'sbk') return;
        $id = isset($parts[2]) && ctype_digit($parts[2]) ? (int) $parts[2] : 0;
        $booking = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id)) : null;
        if (!$booking || !in_array($booking->status, array('pending', 'confirmed'), true)) {
            SBK_Telegram_Admin::send_text('Запись уже обработана или не найдена. Откройте /menu.');
            return;
        }
        $action = $parts[1] ?? '';
        if ($action === 's') {
            $status = array('c' => 'confirmed', 'x' => 'cancelled')[$parts[3] ?? ''] ?? '';
            if (!$status) { self::send($chat, 'Эта кнопка больше не используется. Откройте /menu.'); return; }
            $result = SBK_Booking::change_status($id, $status);
            if (!is_wp_error($result) && !empty($query['message']['message_id'])) {
                self::request('editMessageReplyMarkup', array(
                    'chat_id' => $chat,
                    'message_id' => (int) $query['message']['message_id'],
                    'reply_markup' => wp_json_encode(array('inline_keyboard' => array()))
                ));
            }
            if (is_wp_error($result)) self::send($chat, $result->get_error_message());
        } elseif ($action === 'r') {
            self::date_menu($chat, $booking, absint($parts[3] ?? 0));
        } elseif ($action === 'd' || $action === 't' || $action === 'u') {
            $packed = $parts[3] ?? '';
            if (!preg_match('/^\d{8}$/D', $packed)) return;
            $date = substr($packed, 0, 4) . '-' . substr($packed, 4, 2) . '-' . substr($packed, 6, 2);
            if (!SBK_Booking::valid_date($date)) return;
            if ($action === 'u') {
                $packed_time = $parts[4] ?? '';
                if (!preg_match('/^\d{4}$/D', $packed_time)) return;
                $time = substr($packed_time, 0, 2) . ':' . substr($packed_time, 2, 2);
                $result = SBK_Booking::update($id, array('service_id' => $booking->service_id, 'master_id' => $booking->master_id, 'date' => $date, 'time' => $time, 'name' => $booking->client_name, 'phone' => $booking->phone));
                if (is_wp_error($result)) {
                    self::send($chat, $result->get_error_message());
                } elseif (!empty($query['message']['message_id'])) {
                    self::request('editMessageReplyMarkup', array(
                        'chat_id' => $chat,
                        'message_id' => (int) $query['message']['message_id'],
                        'reply_markup' => wp_json_encode(array('inline_keyboard' => array()))
                    ));
                }
            } else {
                self::time_menu($chat, $booking, $date, $action === 't' ? absint($parts[4] ?? 0) : 0);
            }
        }
    }

    private static function date_menu($chat, $booking, $page) {
        $horizon = (int) SBK_Booking::settings()['horizon'];
        $last_page = intdiv($horizon, 7);
        $page = min($page, $last_page);
        $today = new DateTimeImmutable('today', wp_timezone());
        $buttons = array();
        for ($n = $page * 7; $n <= min($horizon, $page * 7 + 6); $n++) {
            $day = $today->modify('+' . $n . ' days');
            $date = $day->format('Y-m-d');
            if (!SBK_Booking::slots($booking->service_id, $booking->master_id, $date, true, $booking->id)) continue;
            $buttons[] = array(array('text' => wp_date('d.m (D)', $day->getTimestamp(), wp_timezone()), 'callback_data' => 'sbk:d:' . $booking->id . ':' . $day->format('Ymd')));
        }
        $nav = array();
        if ($page > 0) $nav[] = array('text' => '← Раньше', 'callback_data' => 'sbk:r:' . $booking->id . ':' . ($page - 1));
        if ($page < $last_page) $nav[] = array('text' => 'Позже →', 'callback_data' => 'sbk:r:' . $booking->id . ':' . ($page + 1));
        if ($nav) $buttons[] = $nav;
        self::send($chat, $buttons ? 'Выберите новую дату для записи №' . $booking->id . ':' : 'Свободных дат нет.', $buttons);
    }

    private static function time_menu($chat, $booking, $date, $page) {
        $slots = SBK_Booking::slots($booking->service_id, $booking->master_id, $date, true, $booking->id);
        $pages = max(1, (int) ceil(count($slots) / 12));
        $page = min($page, $pages - 1);
        $packed = str_replace('-', '', $date);
        $buttons = array();
        foreach (array_chunk(array_slice($slots, $page * 12, 12), 3) as $row) {
            $line = array();
            foreach ($row as $time) $line[] = array('text' => $time, 'callback_data' => 'sbk:u:' . $booking->id . ':' . $packed . ':' . str_replace(':', '', $time));
            $buttons[] = $line;
        }
        $nav = array();
        if ($page > 0) $nav[] = array('text' => '← Раньше', 'callback_data' => 'sbk:t:' . $booking->id . ':' . $packed . ':' . ($page - 1));
        if ($page + 1 < $pages) $nav[] = array('text' => 'Позже →', 'callback_data' => 'sbk:t:' . $booking->id . ':' . $packed . ':' . ($page + 1));
        if ($nav) $buttons[] = $nav;
        self::send($chat, $slots ? 'Выберите время на ' . $date . ':' : 'На эту дату свободного времени нет.', $buttons);
    }
}
