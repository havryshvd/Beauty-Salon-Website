<?php
defined('ABSPATH') || exit;

/** Simple private Telegram dashboard for the configured salon administrator. */
final class SBK_Telegram_Admin {
    private static function flow_key() {
        return 'sbk_tg_admin_new_' . SBK_Telegram::admin_chat_id();
    }

    private static function flow() {
        $flow = get_transient(self::flow_key());
        return is_array($flow) ? $flow : array();
    }

    private static function save_flow($flow) {
        set_transient(self::flow_key(), $flow, 20 * MINUTE_IN_SECONDS);
    }

    private static function clear_flow() {
        delete_transient(self::flow_key());
    }

    private static function button($label, $data) {
        return array('text' => $label, 'callback_data' => 'sba:' . $data);
    }

    private static function date_key($date) {
        return str_replace('-', '', $date);
    }

    private static function unpack_date($packed) {
        if (!is_string($packed) || !preg_match('/^\d{8}$/D', $packed)) return '';
        $date = substr($packed, 0, 4) . '-' . substr($packed, 4, 2) . '-' . substr($packed, 6, 2);
        return SBK_Booking::valid_date($date) ? $date : '';
    }

    private static function bounds($date) {
        $day = new DateTimeImmutable($date, wp_timezone());
        return array(
            $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $day->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );
    }

    private static function booking($id) {
        global $wpdb;
        return $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id)) : null;
    }

    public static function send_text($text, $buttons = array()) {
        return SBK_Telegram::send_admin($text, $buttons);
    }

    public static function menu() {
        global $wpdb;
        delete_transient('sbk_tg_admin_date_' . SBK_Telegram::admin_chat_id());
        self::clear_flow();
        $date = wp_date('Y-m-d');
        list($from, $to) = self::bounds($date);
        $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . SBK_Database::table('bookings') . " WHERE status IN ('pending','confirmed') AND starts_utc >= %s AND starts_utc < %s", $from, $to));
        return self::send_text("Записи салона\nСегодня, " . wp_date('d.m.Y') . ": $count\n\nВыберите действие:", array(
            array(self::button('📋 Сегодня по мастерам', 'today')),
            array(self::button('📅 Выбрать дату', 'calendar:0')),
            array(self::button('➕ Новая запись', 'new'))
        ));
    }

    public static function message($text, $contact_phone = '') {
        $text = trim((string) $text);
        if (preg_match('~^/(?:start|menu|admin|cancel)(?:@\w+)?$~iu', $text)) { self::menu(); return; }
        if (preg_match('~^/id(?:@\w+)?$~iu', $text)) { self::send_text('Ваш личный chat ID: ' . SBK_Telegram::admin_chat_id()); return; }
        $flow = self::flow();
        if (($flow['stage'] ?? '') === 'name') {
            $name = sanitize_text_field($text);
            if (mb_strlen($name) < 2 || mb_strlen($name) > 190) { self::send_text('Введите имя клиента: от 2 до 190 символов.'); return; }
            $flow['name'] = $name;
            $flow['stage'] = 'phone';
            self::save_flow($flow);
            self::send_text('Введите телефон клиента или отправьте контакт. Для отмены: /cancel');
            return;
        }
        if (($flow['stage'] ?? '') === 'phone') {
            $phone = sanitize_text_field(trim((string) ($contact_phone ?: $text)));
            if (!preg_match('/^\+?[0-9 ()-]{7,25}$/D', $phone)) { self::send_text('Введите телефон от 7 до 25 символов: цифры, пробелы, +, ( ), -.'); return; }
            $flow['phone'] = $phone;
            $flow['stage'] = 'confirm';
            self::save_flow($flow);
            self::confirm_new($flow);
            return;
        }
        if (($flow['stage'] ?? '') === 'date') {
            $date = self::entered_date($text);
            if (!$date || !self::choose_new_date($flow, $date)) self::send_text('Введите свободную дату в формате ДД.ММ.ГГГГ или выберите её кнопкой.');
            return;
        }
        if (($flow['stage'] ?? '') === 'time') {
            if (!SBK_Booking::valid_time($text) || !self::choose_new_time($flow, $text)) self::send_text('Это время недоступно. Выберите свободное время кнопкой.');
            return;
        }
        if ($flow) { self::send_text('Выберите вариант кнопкой выше или отправьте /cancel.'); return; }
        $key = 'sbk_tg_admin_date_' . SBK_Telegram::admin_chat_id();
        if (get_transient($key)) {
            $date = self::entered_date($text);
            if ($date) {
                delete_transient($key);
                self::masters($date);
            } else {
                self::send_text('Укажите дату в формате 25.10.2026 или 2026-10-25. Для выхода: /menu');
            }
            return;
        }
        self::send_text('Откройте /menu, чтобы посмотреть записи.');
    }

    private static function entered_date($text) {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/D', $text, $m)) {
            $text = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return SBK_Booking::valid_date($text) ? $text : '';
    }

    public static function callback($parts) {
        $action = $parts[1] ?? '';
        if ($action === 'menu') { self::menu(); return; }
        if ($action === 'new') { self::start_new(); return; }
        if (in_array($action, array('ns', 'nm', 'nd', 'np', 'nt', 'tp', 'save'), true)) { self::new_callback($parts); return; }
        self::clear_flow();
        if ($action === 'today') { self::masters(wp_date('Y-m-d')); return; }
        if ($action === 'calendar') { self::calendar((int) ($parts[2] ?? 0)); return; }
        if ($action === 'day') {
            $date = self::unpack_date($parts[2] ?? '');
            if ($date) self::masters($date);
            return;
        }
        if ($action === 'masters') {
            $date = self::unpack_date($parts[2] ?? '');
            if ($date) self::masters($date);
            return;
        }
        if ($action === 'list') {
            $date = self::unpack_date($parts[2] ?? '');
            if ($date) self::listing($date, absint($parts[3] ?? 0), absint($parts[4] ?? 0));
            return;
        }
        if ($action === 'booking' || $action === 'b') {
            $date = self::unpack_date($parts[3] ?? '');
            self::show_booking(absint($parts[2] ?? 0), $date, absint($parts[4] ?? 0), absint($parts[5] ?? 0));
            return;
        }
        self::send_text('Это меню устарело. Откройте /menu.');
    }

    private static function start_new() {
        delete_transient('sbk_tg_admin_date_' . SBK_Telegram::admin_chat_id());
        $flow = array('stage' => 'service', 'nonce' => wp_generate_password(10, false, false));
        self::save_flow($flow);
        self::new_services($flow);
    }

    private static function new_callback($parts) {
        global $wpdb;
        $action = $parts[1] ?? '';
        $flow = self::flow();
        if (empty($flow['nonce']) || !hash_equals($flow['nonce'], (string) ($parts[2] ?? ''))) {
            self::send_text('Этот шаг устарел. Начните новую запись через /menu.');
            return;
        }
        if ($action === 'ns' && ($flow['stage'] ?? '') === 'service') {
            $service_id = absint($parts[3] ?? 0);
            $active = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . SBK_Database::table('services') . ' WHERE id=%d AND active=1', $service_id));
            if (!$active) { self::send_text('Услуга недоступна. Выберите другую.'); return; }
            $flow['service_id'] = $service_id;
            $flow['stage'] = 'master';
            self::save_flow($flow);
            self::new_masters($flow);
            return;
        }
        if ($action === 'nm' && ($flow['stage'] ?? '') === 'master') {
            $master_id = absint($parts[3] ?? 0);
            if (!self::active_pair($flow['service_id'], $master_id)) { self::send_text('Мастер недоступен для этой услуги.'); return; }
            $flow['master_id'] = $master_id;
            $flow['stage'] = 'date';
            self::save_flow($flow);
            self::new_dates($flow, 0);
            return;
        }
        if ($action === 'np' && ($flow['stage'] ?? '') === 'date') { self::new_dates($flow, absint($parts[3] ?? 0)); return; }
        if ($action === 'nd' && ($flow['stage'] ?? '') === 'date') {
            $date = self::unpack_date($parts[3] ?? '');
            if (!$date || !self::choose_new_date($flow, $date)) self::send_text('На эту дату свободного времени нет. Выберите другую.');
            return;
        }
        if ($action === 'tp' && ($flow['stage'] ?? '') === 'time') { self::new_times($flow, absint($parts[3] ?? 0)); return; }
        if ($action === 'nt' && ($flow['stage'] ?? '') === 'time') {
            $packed = $parts[3] ?? '';
            $time = preg_match('/^\d{4}$/D', $packed) ? substr($packed, 0, 2) . ':' . substr($packed, 2, 2) : '';
            if (!self::choose_new_time($flow, $time)) self::send_text('Это время уже занято. Выберите другое.');
            return;
        }
        if ($action === 'save' && ($flow['stage'] ?? '') === 'confirm') {
            self::clear_flow();
            if (!self::active_pair($flow['service_id'], $flow['master_id'])) {
                self::send_text('Услуга или мастер больше недоступны. Начните новую запись.', array(array(self::button('➕ Новая запись', 'new'))));
                return;
            }
            $result = SBK_Booking::create($flow, true);
            if (is_wp_error($result)) {
                self::send_text($result->get_error_message(), array(array(self::button('➕ Попробовать снова', 'new'))));
                return;
            }
            self::send_text('Запись №' . $result . ' создана и подтверждена.', array(
                array(self::button('Открыть запись', 'booking:' . $result), self::button('Меню', 'menu'))
            ));
            return;
        }
        self::send_text('Этот шаг уже пройден. Продолжите ввод выше или начните заново через /menu.');
    }

    private static function active_pair($service_id, $master_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . SBK_Database::table('master_services') . ' ms INNER JOIN ' . SBK_Database::table('services') . ' s ON s.id=ms.service_id INNER JOIN ' . SBK_Database::table('masters') . ' m ON m.id=ms.master_id WHERE s.id=%d AND m.id=%d AND s.active=1 AND m.active=1', $service_id, $master_id));
    }

    private static function new_services($flow) {
        global $wpdb;
        $services = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('services') . ' WHERE active=1 ORDER BY name');
        $buttons = array();
        foreach ($services as $service) $buttons[] = array(self::button(mb_substr($service->name, 0, 50), 'ns:' . $flow['nonce'] . ':' . $service->id));
        $buttons[] = array(self::button('Отмена', 'menu'));
        self::send_text($services ? "Новая запись · 1/6\nВыберите услугу:" : 'Активных услуг нет.', $buttons);
    }

    private static function new_masters($flow) {
        global $wpdb;
        $masters = $wpdb->get_results($wpdb->prepare('SELECT m.id,m.name FROM ' . SBK_Database::table('masters') . ' m INNER JOIN ' . SBK_Database::table('master_services') . ' ms ON ms.master_id=m.id WHERE ms.service_id=%d AND m.active=1 ORDER BY m.name', $flow['service_id']));
        $buttons = array();
        foreach ($masters as $master) $buttons[] = array(self::button(mb_substr($master->name, 0, 50), 'nm:' . $flow['nonce'] . ':' . $master->id));
        $buttons[] = array(self::button('Отмена', 'menu'));
        self::send_text($masters ? "Новая запись · 2/6\nВыберите мастера:" : 'Для этой услуги нет доступных мастеров.', $buttons);
    }

    private static function new_dates($flow, $page) {
        $page = min($page, 52);
        $today = new DateTimeImmutable('today', wp_timezone());
        $buttons = array();
        for ($n = $page * 7; $n <= min(365, $page * 7 + 6); $n++) {
            $day = $today->modify('+' . $n . ' days');
            if (!SBK_Booking::slots($flow['service_id'], $flow['master_id'], $day->format('Y-m-d'), true)) continue;
            $buttons[] = array(self::button(wp_date('d.m.Y (D)', $day->getTimestamp(), wp_timezone()), 'nd:' . $flow['nonce'] . ':' . $day->format('Ymd')));
        }
        $nav = array();
        if ($page) $nav[] = self::button('← Ранее', 'np:' . $flow['nonce'] . ':' . ($page - 1));
        if ($page < 52) $nav[] = self::button('Позже →', 'np:' . $flow['nonce'] . ':' . ($page + 1));
        if ($nav) $buttons[] = $nav;
        $buttons[] = array(self::button('Отмена', 'menu'));
        self::send_text("Новая запись · 3/6\nВыберите свободную дату или напишите её: ДД.ММ.ГГГГ", $buttons);
    }

    private static function choose_new_date($flow, $date) {
        if (!SBK_Booking::slots($flow['service_id'], $flow['master_id'], $date, true)) return false;
        $flow['date'] = $date;
        $flow['stage'] = 'time';
        self::save_flow($flow);
        self::new_times($flow, 0);
        return true;
    }

    private static function new_times($flow, $page) {
        $slots = SBK_Booking::slots($flow['service_id'], $flow['master_id'], $flow['date'], true);
        $page = min($page, max(0, (int) ceil(count($slots) / 12) - 1));
        $buttons = array();
        foreach (array_chunk(array_slice($slots, $page * 12, 12), 3) as $chunk) {
            $row = array();
            foreach ($chunk as $time) $row[] = self::button($time, 'nt:' . $flow['nonce'] . ':' . str_replace(':', '', $time));
            $buttons[] = $row;
        }
        $nav = array();
        if ($page) $nav[] = self::button('← Ранее', 'tp:' . $flow['nonce'] . ':' . ($page - 1));
        if (count($slots) > ($page + 1) * 12) $nav[] = self::button('Позже →', 'tp:' . $flow['nonce'] . ':' . ($page + 1));
        if ($nav) $buttons[] = $nav;
        $buttons[] = array(self::button('Отмена', 'menu'));
        self::send_text($slots ? "Новая запись · 4/6\nВыберите время на " . wp_date('d.m.Y', strtotime($flow['date'] . ' UTC'), wp_timezone()) . ':' : 'Свободного времени больше нет. Начните заново через /menu.', $buttons);
    }

    private static function choose_new_time($flow, $time) {
        if (!SBK_Booking::valid_time($time) || !in_array($time, SBK_Booking::slots($flow['service_id'], $flow['master_id'], $flow['date'], true), true)) return false;
        $flow['time'] = $time;
        $flow['stage'] = 'name';
        self::save_flow($flow);
        self::send_text("Новая запись · 5/6\nВведите имя клиента. Для отмены: /cancel");
        return true;
    }

    private static function confirm_new($flow) {
        global $wpdb;
        $service = $wpdb->get_var($wpdb->prepare('SELECT name FROM ' . SBK_Database::table('services') . ' WHERE id=%d', $flow['service_id']));
        $master = $wpdb->get_var($wpdb->prepare('SELECT name FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $flow['master_id']));
        self::send_text("Новая запись · 6/6\nПроверьте данные:\n" . $service . ' · ' . $master . "\n" . $flow['date'] . ' ' . $flow['time'] . "\n" . $flow['name'] . ' · ' . $flow['phone'], array(
            array(self::button('✅ Сохранить запись', 'save:' . $flow['nonce'])),
            array(self::button('Начать заново', 'new'), self::button('Отмена', 'menu'))
        ));
    }

    private static function calendar($week) {
        $week = max(-52, min(52, $week));
        set_transient('sbk_tg_admin_date_' . SBK_Telegram::admin_chat_id(), 1, 15 * MINUTE_IN_SECONDS);
        $first = (new DateTimeImmutable('today', wp_timezone()))->modify(($week * 7) . ' days');
        $buttons = array();
        for ($n = 0; $n < 7; $n++) {
            $day = $first->modify('+' . $n . ' days');
            $buttons[] = array(self::button(wp_date('d.m.Y (D)', $day->getTimestamp(), wp_timezone()), 'day:' . $day->format('Ymd')));
        }
        $nav = array();
        if ($week > -52) $nav[] = self::button('← Ранее', 'calendar:' . ($week - 1));
        if ($week < 52) $nav[] = self::button('Позже →', 'calendar:' . ($week + 1));
        if ($nav) $buttons[] = $nav;
        $buttons[] = array(self::button('Главное меню', 'menu'));
        self::send_text("Выберите дату или напишите её сообщением: ДД.ММ.ГГГГ", $buttons);
    }

    private static function masters($date) {
        global $wpdb;
        delete_transient('sbk_tg_admin_date_' . SBK_Telegram::admin_chat_id());
        list($from, $to) = self::bounds($date);
        $masters = $wpdb->get_results('SELECT id,name,active FROM ' . SBK_Database::table('masters') . ' ORDER BY name');
        $counts = $wpdb->get_results($wpdb->prepare('SELECT master_id,COUNT(*) total FROM ' . SBK_Database::table('bookings') . " WHERE status IN ('pending','confirmed') AND starts_utc >= %s AND starts_utc < %s GROUP BY master_id", $from, $to), OBJECT_K);
        $buttons = array();
        foreach ($masters as $master) {
            $count = (int) ($counts[$master->id]->total ?? 0);
            if (!$master->active && !$count) continue;
            $buttons[] = array(self::button(mb_substr($master->name, 0, 42) . ' · ' . $count, 'list:' . self::date_key($date) . ':' . $master->id . ':0'));
        }
        $buttons[] = array(self::button('Другая дата', 'calendar:0'), self::button('Меню', 'menu'));
        self::send_text('Записи на ' . wp_date('d.m.Y', strtotime($date . ' UTC'), wp_timezone()) . "\nВыберите мастера. Число рядом с именем — количество записей.", $buttons);
    }

    private static function listing($date, $master_id, $page) {
        global $wpdb;
        $master = $wpdb->get_row($wpdb->prepare('SELECT id,name FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $master_id));
        if (!$master) { self::send_text('Мастер не найден.'); return; }
        list($from, $to) = self::bounds($date);
        $page = min($page, 1000);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,client_name,service_name,starts_utc,status FROM ' . SBK_Database::table('bookings') . " WHERE master_id=%d AND status IN ('pending','confirmed') AND starts_utc >= %s AND starts_utc < %s ORDER BY starts_utc LIMIT %d OFFSET %d", $master_id, $from, $to, 9, $page * 8));
        $has_more = count($rows) > 8;
        $rows = array_slice($rows, 0, 8);
        $lines = array($master->name . ' · ' . wp_date('d.m.Y', strtotime($date . ' UTC'), wp_timezone()));
        $buttons = array();
        foreach ($rows as $row) {
            $time = wp_date('H:i', strtotime($row->starts_utc . ' UTC'), wp_timezone());
            $mark = $row->status === 'pending' ? '⏳' : '✅';
            $lines[] = $mark . ' ' . $time . ' · ' . $row->client_name . ' · ' . $row->service_name;
            $buttons[] = array(self::button($time . ' · ' . mb_substr($row->client_name, 0, 32), 'booking:' . $row->id . ':' . self::date_key($date) . ':' . $master_id . ':' . $page));
        }
        if (!$rows) $lines[] = 'Записей нет.';
        $nav = array();
        if ($page) $nav[] = self::button('← Назад', 'list:' . self::date_key($date) . ':' . $master_id . ':' . ($page - 1));
        if ($has_more) $nav[] = self::button('Далее →', 'list:' . self::date_key($date) . ':' . $master_id . ':' . ($page + 1));
        if ($nav) $buttons[] = $nav;
        $buttons[] = array(self::button('Все мастера', 'masters:' . self::date_key($date)), self::button('Меню', 'menu'));
        self::send_text(implode("\n", $lines), $buttons);
    }

    public static function show_booking($id, $date = '', $master_id = 0, $page = 0) {
        $booking = self::booking($id);
        if (!$booking) { self::send_text('Запись не найдена.'); return; }
        $statuses = array('pending' => 'Ожидает подтверждения', 'confirmed' => 'Подтверждена', 'cancelled' => 'Отклонена', 'completed' => 'Завершена');
        $text = 'Запись №' . $booking->id . "\n" .
            'Статус: ' . ($statuses[$booking->status] ?? $booking->status) . "\n" .
            'Когда: ' . wp_date('d.m.Y H:i', strtotime($booking->starts_utc . ' UTC'), wp_timezone()) . "\n" .
            'Мастер: ' . $booking->master_name . "\n" .
            'Услуга: ' . $booking->service_name . "\n" .
            'Клиент: ' . $booking->client_name . "\n" .
            'Телефон: ' . $booking->phone;
        $buttons = SBK_Telegram::booking_buttons($booking, false);
        if ($date && $master_id) $buttons[] = array(self::button('← К списку мастера', 'list:' . self::date_key($date) . ':' . $master_id . ':' . min($page, 1000)));
        $buttons[] = array(self::button('Главное меню', 'menu'));
        self::send_text($text, $buttons);
    }
}
