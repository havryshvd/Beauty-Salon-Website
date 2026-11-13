<?php
defined('ABSPATH') || exit;

final class SBK_Admin {
    private static function page() {
        $page = sanitize_key($_GET['page'] ?? 'sbk-bookings');
        if ($page === 'sbk-settings' && !current_user_can('manage_options')) return 'sbk-overview';
        return in_array($page, array('sbk-overview', 'sbk-bookings', 'sbk-services', 'sbk-masters', 'sbk-schedule', 'sbk-settings'), true) ? $page : 'sbk-overview';
    }

    public static function menu() {
        add_menu_page('Запись в салон', 'Запись в салон', 'manage_salon_bookings', 'sbk-overview', array(__CLASS__, 'render'), 'dashicons-calendar-alt', 26);
        foreach (array('sbk-overview' => 'Обзор', 'sbk-bookings' => 'Записи', 'sbk-services' => 'Услуги', 'sbk-masters' => 'Мастера', 'sbk-schedule' => 'Расписание', 'sbk-settings' => 'Настройки') as $slug => $label) {
            add_submenu_page('sbk-overview', $label, $label, $slug === 'sbk-settings' ? 'manage_options' : 'manage_salon_bookings', $slug, array(__CLASS__, 'render'));
        }
    }

    public static function assets() {
        if (!in_array(sanitize_key($_GET['page'] ?? ''), array('sbk-overview', 'sbk-bookings', 'sbk-services', 'sbk-masters', 'sbk-schedule', 'sbk-settings'), true)) return;
        wp_enqueue_style('sbk-admin', SBK_URL . 'assets/admin.css', array(), SBK_VERSION);
    }

    private static function redirect($page, $message = 'saved', $extra = array()) {
        wp_safe_redirect(add_query_arg(array_merge(array('page' => $page, 'sbk_notice' => $message), $extra), admin_url('admin.php')));
        exit;
    }

    private static function fail($message) {
        wp_die(esc_html($message), 'Ошибка', array('response' => 400, 'back_link' => true));
    }

    private static function check($owner_only = false) {
        if (!current_user_can($owner_only ? 'manage_options' : 'manage_salon_bookings')) wp_die(esc_html('Недостаточно прав.'), '', array('response' => 403));
        check_admin_referer('sbk_admin');
    }

    private static function post_open($kind, $page, $extra = array()) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="sbk_save"><input type="hidden" name="kind" value="' . esc_attr($kind) . '"><input type="hidden" name="page" value="' . esc_attr($page) . '">';
        foreach ($extra as $key => $value) echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        wp_nonce_field('sbk_admin');
    }

    private static function field($label, $name, $value = '', $type = 'text', $attrs = '') {
        echo '<p><label><strong>' . esc_html($label) . '</strong><br><input class="regular-text" type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" ' . $attrs . '></label></p>';
    }

    public static function save() {
        self::check(sanitize_key($_POST['kind'] ?? '') === 'settings');
        global $wpdb;
        $kind = sanitize_key($_POST['kind'] ?? '');
        $page = sanitize_key($_POST['page'] ?? 'sbk-bookings');
        $id = absint($_POST['id'] ?? 0);
        if ($kind === 'service') {
            $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
            $duration = absint($_POST['duration'] ?? 0);
            $price = str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['price'] ?? '')));
            if (!$name || $duration < 5 || $duration > 600 || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $price)) self::fail('Проверьте название, длительность и цену.');
            $data = array('name' => $name, 'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')), 'duration' => $duration, 'price' => $price, 'active' => isset($_POST['active']) ? 1 : 0);
            $ok = $id ? $wpdb->update(SBK_Database::table('services'), $data, array('id' => $id)) : $wpdb->insert(SBK_Database::table('services'), $data);
            if ($ok === false) self::fail('Не удалось сохранить услугу.');
        } elseif ($kind === 'master') {
            $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
            if (!$name) self::fail('Укажите имя мастера.');
            $data = array('name' => $name, 'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')), 'telegram_chat_id' => '', 'active' => isset($_POST['active']) ? 1 : 0);
            $ok = $id ? $wpdb->update(SBK_Database::table('masters'), $data, array('id' => $id)) : $wpdb->insert(SBK_Database::table('masters'), $data);
            if ($ok === false) self::fail('Не удалось сохранить мастера.');
            if (!$id) $id = (int) $wpdb->insert_id;
            $wpdb->delete(SBK_Database::table('master_services'), array('master_id' => $id));
            $services = array_map('absint', (array) wp_unslash($_POST['services'] ?? array()));
            foreach (array_unique($services) as $service_id) {
                if ($service_id && $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . SBK_Database::table('services') . ' WHERE id=%d', $service_id))) {
                    $wpdb->insert(SBK_Database::table('master_services'), array('master_id' => $id, 'service_id' => $service_id));
                }
            }
        } elseif ($kind === 'schedule') {
            $master = absint($_POST['master_id'] ?? 0);
            if (!$wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $master))) self::fail('Выберите мастера.');
            $days = (array) wp_unslash($_POST['days'] ?? array());
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $row = (array) ($days[$weekday] ?? array());
                $hours = self::hours($row);
                $wpdb->replace(SBK_Database::table('schedule'), array_merge(array('master_id' => $master, 'weekday' => $weekday), $hours));
            }
            self::redirect('sbk-schedule', 'saved', array('master_id' => $master));
        } elseif ($kind === 'exception') {
            $master = absint($_POST['master_id'] ?? 0);
            $date = sanitize_text_field(wp_unslash($_POST['on_date'] ?? ''));
            if (!$wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $master)) || !SBK_Booking::valid_date($date)) self::fail('Выберите мастера и правильную дату.');
            $hours = self::hours(wp_unslash($_POST));
            $wpdb->replace(SBK_Database::table('exceptions'), array_merge(array('master_id' => $master, 'on_date' => $date), $hours));
            self::redirect('sbk-schedule', 'saved', array('master_id' => $master));
        } elseif ($kind === 'delete_exception') {
            $master = absint($_POST['master_id'] ?? 0);
            $wpdb->delete(SBK_Database::table('exceptions'), array('id' => $id, 'master_id' => $master));
            self::redirect('sbk-schedule', 'saved', array('master_id' => $master));
        } elseif ($kind === 'settings') {
            $step = absint($_POST['step'] ?? 0);
            $notice = absint($_POST['notice'] ?? 0);
            $horizon = absint($_POST['horizon'] ?? 0);
            $buffer = absint($_POST['buffer'] ?? 0);
            if ($step < 5 || $step > 120 || $notice > 10080 || $horizon < 1 || $horizon > 365 || $buffer > 240) self::fail('Проверьте настройки записи.');
            update_option('sbk_settings', compact('step', 'notice', 'horizon', 'buffer'), false);
            $chat = sanitize_text_field(wp_unslash($_POST['chat_id'] ?? ''));
            if ($chat !== '' && !preg_match('/^[1-9]\d{0,19}$/D', $chat)) self::fail('Укажите положительный chat ID личного чата администратора.');
            if ($chat !== (string) get_option('sbk_telegram_chat', '')) delete_option('sbk_telegram_last_test');
            update_option('sbk_telegram_chat', $chat, false);
            if (!empty($_POST['clear_token'])) {
                delete_option('sbk_telegram_token');
                delete_option('sbk_telegram_last_test');
                delete_option('sbk_telegram_bot_username');
            }
            $token = trim((string) wp_unslash($_POST['token'] ?? ''));
            if ($token !== '') {
                if (!preg_match('/^\d{5,20}:[A-Za-z0-9_-]{20,100}$/D', $token)) self::fail('Неверный формат токена бота.');
                $encrypted = SBK_Telegram::encrypt($token);
                if (is_wp_error($encrypted)) self::fail($encrypted->get_error_message());
                update_option('sbk_telegram_token', $encrypted, false);
                delete_option('sbk_telegram_last_test');
                delete_option('sbk_telegram_bot_username');
            }
        } elseif ($kind === 'booking') {
            $data = array_map('wp_unslash', array_intersect_key($_POST, array_flip(array('service_id', 'master_id', 'date', 'time', 'name', 'phone'))));
            $result = SBK_Booking::create($data, true);
            if (is_wp_error($result)) self::fail($result->get_error_message());
        } elseif ($kind === 'edit_booking') {
            $data = array_map('wp_unslash', array_intersect_key($_POST, array_flip(array('service_id', 'master_id', 'date', 'time', 'name', 'phone'))));
            $result = SBK_Booking::update($id, $data);
            if (is_wp_error($result)) self::fail($result->get_error_message());
        } else self::fail('Неизвестное действие.');
        self::redirect($page);
    }

    private static function hours($row) {
        $closed = isset($row['is_closed']) ? 1 : 0;
        $start = sanitize_text_field($row['starts'] ?? '09:00');
        $end = sanitize_text_field($row['ends'] ?? '18:00');
        $breaks = SBK_Booking::parse_breaks(sanitize_textarea_field($row['breaks'] ?? ''));
        if (!SBK_Booking::valid_time($start) || !SBK_Booking::valid_time($end) || $start >= $end || is_wp_error($breaks)) self::fail('Проверьте часы работы и перерывы.');
        foreach ($breaks as $break) if ($break[0] < $start || $break[1] > $end) self::fail('Перерыв должен быть внутри рабочего времени.');
        return array('is_closed' => $closed, 'starts' => $start . ':00', 'ends' => $end . ':00', 'breaks' => wp_json_encode($breaks));
    }

    public static function status() {
        self::check();
        $id = absint($_POST['id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        $result = SBK_Booking::change_status($id, $status);
        if (is_wp_error($result)) self::fail($result->get_error_message());
        self::redirect('sbk-bookings');
    }

    public static function test_telegram() {
        self::check(true);
        $result = SBK_Telegram::test();
        if (is_wp_error($result)) {
            delete_option('sbk_telegram_last_test');
            self::redirect('sbk-settings', 'telegram_error');
        }
        update_option('sbk_telegram_last_test', time(), false);
        update_option('sbk_telegram_bot_username', $result['username'], false);
        self::redirect('sbk-settings', 'telegram_ok');
    }

    public static function render() {
        if (!current_user_can('manage_salon_bookings')) return;
        $page = self::page();
        echo '<div class="wrap sbk-admin"><h1>Запись в салон</h1><nav class="nav-tab-wrapper" aria-label="Разделы записи">';
        foreach (array('sbk-overview' => 'Обзор', 'sbk-bookings' => 'Записи', 'sbk-services' => 'Услуги', 'sbk-masters' => 'Мастера', 'sbk-schedule' => 'Расписание', 'sbk-settings' => 'Настройки') as $slug => $label) {
            if ($slug === 'sbk-settings' && !current_user_can('manage_options')) continue;
            echo '<a class="nav-tab' . ($page === $slug ? ' nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        $notice = sanitize_key($_GET['sbk_notice'] ?? '');
        if ($notice) echo '<div class="notice ' . ($notice === 'telegram_error' ? 'notice-error' : 'notice-success') . '"><p>' . esc_html(array('saved' => 'Сохранено.', 'telegram_ok' => 'Сообщение отправлено в Telegram.', 'telegram_error' => 'Не удалось отправить сообщение. Проверьте токен, chat ID и соединение.')[$notice] ?? '') . '</p></div>';
        if ($page === 'sbk-overview') self::overview();
        elseif ($page === 'sbk-services') self::services();
        elseif ($page === 'sbk-masters') self::masters();
        elseif ($page === 'sbk-schedule') self::schedule_page();
        elseif ($page === 'sbk-settings') self::settings_page();
        else self::bookings();
        echo '</div>';
    }

    private static function overview() {
        global $wpdb;
        $table = SBK_Database::table('bookings');
        $today = new DateTimeImmutable('today', wp_timezone());
        $tomorrow = $today->modify('+1 day');
        $week = $today->modify('+7 days');
        $today_utc = $today->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $tomorrow_utc = $tomorrow->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $week_utc = $week->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $now_utc = current_time('mysql', true);
        $today_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE starts_utc >= %s AND starts_utc < %s AND status IN ('pending','confirmed')", $today_utc, $tomorrow_utc));
        $week_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE starts_utc >= %s AND starts_utc < %s AND status IN ('pending','confirmed')", $today_utc, $week_utc));
        $pending_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        $next = $wpdb->get_results($wpdb->prepare("SELECT id,starts_utc,service_name,master_name,client_name,status FROM $table WHERE starts_utc >= %s AND status IN ('pending','confirmed') ORDER BY starts_utc ASC LIMIT 8", $now_utc));
        $active_services = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SBK_Database::table('services') . ' WHERE active=1');
        $priced_services = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SBK_Database::table('services') . ' WHERE active=1 AND price>0');
        $active_masters = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SBK_Database::table('masters') . ' WHERE active=1');
        $scheduled_masters = (int) $wpdb->get_var('SELECT COUNT(DISTINCT m.id) FROM ' . SBK_Database::table('masters') . ' m INNER JOIN ' . SBK_Database::table('schedule') . ' s ON s.master_id=m.id WHERE m.active=1 AND s.is_closed=0');
        $telegram_ready = (bool) get_option('sbk_telegram_last_test');
        $contact_ready = (bool) get_theme_mod('atelier_phone') && (bool) get_theme_mod('atelier_address');

        echo '<div class="sbk-overview-heading"><div><p class="sbk-eyebrow">Панель салона</p><h2>Рабочий день под контролем</h2><p>Сегодня ' . esc_html(wp_date('j F Y', $today->getTimestamp())) . '. Время записей указано по часовому поясу сайта.</p></div><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sbk-bookings')) . '">Открыть записи</a></div>';
        echo '<div class="sbk-stat-grid">';
        foreach (array(
            array('Сегодня', $today_count, 'sbk-bookings&date=' . $today->format('Y-m-d')),
            array('За 7 дней', $week_count, 'sbk-bookings&period=week'),
            array('Ожидают решения', $pending_count, 'sbk-bookings&status=pending'),
            array('Активных мастеров', $active_masters, 'sbk-masters'),
        ) as $stat) {
            echo '<a class="sbk-stat" href="' . esc_url(admin_url('admin.php?page=' . $stat[2])) . '"><span>' . esc_html($stat[0]) . '</span><strong>' . esc_html($stat[1]) . '</strong><small>Открыть →</small></a>';
        }
        echo '</div><div class="sbk-overview-columns"><section class="sbk-panel"><div class="sbk-panel-heading"><h3>Ближайшие записи</h3><a href="' . esc_url(admin_url('admin.php?page=sbk-bookings')) . '">Все записи →</a></div>';
        if ($next) {
            echo '<div class="sbk-table-scroll"><table class="widefat striped"><thead><tr><th>Когда</th><th>Клиент</th><th>Услуга и мастер</th><th>Статус</th></tr></thead><tbody>';
            foreach ($next as $row) {
                $url = add_query_arg(array('page' => 'sbk-bookings', 'edit_booking' => $row->id), admin_url('admin.php'));
                echo '<tr><td><a href="' . esc_url($url) . '">' . esc_html(wp_date('d.m, H:i', strtotime($row->starts_utc . ' UTC'), wp_timezone())) . '</a></td><td>' . esc_html($row->client_name) . '</td><td>' . esc_html($row->service_name) . '<small>' . esc_html($row->master_name) . '</small></td><td><span class="sbk-status sbk-status-' . esc_attr($row->status) . '">' . ($row->status === 'pending' ? 'Ожидает' : 'Подтверждена') . '</span></td></tr>';
            }
            echo '</tbody></table></div>';
        } else echo '<p class="sbk-empty">Ближайших записей пока нет.</p>';
        echo '</section><section class="sbk-panel"><h3>Подготовка к работе</h3><ul class="sbk-checklist">';
        $checks = array(
            array($active_services > 0 && $priced_services >= $active_services, 'Услуги и цены', $active_services . ' активных, ' . $priced_services . ' с ценой', 'sbk-services'),
            array($active_masters > 0 && $scheduled_masters >= $active_masters, 'Мастера и расписание', $scheduled_masters . ' из ' . $active_masters . ' с рабочими днями', 'sbk-schedule'),
            array($contact_ready, 'Контакты студии', $contact_ready ? 'Телефон и адрес указаны' : 'Проверьте телефон и адрес', 'customize.php?autofocus[section]=atelier_contacts'),
            array($telegram_ready, 'Telegram', $telegram_ready ? 'Тестовое сообщение доставлено' : 'Проверьте подключение', 'sbk-settings'),
        );
        foreach ($checks as $check) {
            $url = strpos($check[3], '.php') !== false ? admin_url($check[3]) : admin_url('admin.php?page=' . $check[3]);
            $owner_only = in_array($check[3], array('sbk-settings', 'customize.php?autofocus[section]=atelier_contacts'), true);
            echo '<li><span class="sbk-check ' . ($check[0] ? 'is-ready' : '') . '" aria-label="' . ($check[0] ? 'Готово' : 'Требует настройки') . '">' . ($check[0] ? '✓' : '·') . '</span><span><strong>' . esc_html($check[1]) . '</strong><small>' . esc_html($check[2]) . '</small></span>';
            echo $owner_only && !current_user_can('manage_options') ? '<span class="sbk-owner-note">Настраивает владелец</span>' : '<a href="' . esc_url($url) . '">Открыть →</a>';
            echo '</li>';
        }
        echo '</ul><p class="description">После сохранения Telegram нажмите «Проверить связь» в настройках.</p></section></div>';
    }

    private static function services() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . SBK_Database::table('services') . ' ORDER BY name');
        $id = absint($_GET['edit'] ?? 0);
        $edit = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('services') . ' WHERE id=%d', $id)) : null;
        echo '<h2>Услуги</h2><table class="widefat striped"><thead><tr><th>Название</th><th>Длительность</th><th>Цена</th><th>Статус</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) echo '<tr><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->duration) . ' мин.</td><td>' . esc_html($row->price) . '</td><td>' . ($row->active ? 'Активна' : 'Неактивна') . '</td><td><a href="' . esc_url(add_query_arg(array('page' => 'sbk-services', 'edit' => $row->id), admin_url('admin.php'))) . '">Изменить</a></td></tr>';
        echo '</tbody></table><h2>' . ($edit ? 'Изменить услугу' : 'Добавить услугу') . '</h2>';
        self::post_open('service', 'sbk-services', array('id' => $id));
        self::field('Название', 'name', $edit->name ?? '', 'text', 'required maxlength="190"');
        echo '<p><label><strong>Описание</strong><br><textarea class="large-text" rows="3" name="description">' . esc_textarea($edit->description ?? '') . '</textarea></label></p>';
        self::field('Длительность, минут', 'duration', $edit->duration ?? 60, 'number', 'required min="5" max="600"');
        self::field('Цена', 'price', $edit->price ?? '0.00', 'number', 'required min="0" step="0.01"');
        echo '<p><label><input type="checkbox" name="active" value="1" ' . checked($edit->active ?? 1, 1, false) . '> Активна</label></p>';
        submit_button('Сохранить услугу'); echo '</form>';
    }

    private static function masters() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . SBK_Database::table('masters') . ' ORDER BY name');
        $services = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('services') . ' ORDER BY name');
        $id = absint($_GET['edit'] ?? 0);
        $edit = $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $id)) : null;
        $selected = $id ? $wpdb->get_col($wpdb->prepare('SELECT service_id FROM ' . SBK_Database::table('master_services') . ' WHERE master_id=%d', $id)) : array();
        echo '<h2>Мастера</h2><table class="widefat striped"><thead><tr><th>Имя</th><th>Статус</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) echo '<tr><td>' . esc_html($row->name) . '</td><td>' . ($row->active ? 'Активен' : 'Неактивен') . '</td><td><a href="' . esc_url(add_query_arg(array('page' => 'sbk-masters', 'edit' => $row->id), admin_url('admin.php'))) . '">Изменить</a> · <a href="' . esc_url(add_query_arg(array('page' => 'sbk-schedule', 'master_id' => $row->id), admin_url('admin.php'))) . '">Расписание</a></td></tr>';
        echo '</tbody></table><h2>' . ($edit ? 'Изменить мастера' : 'Добавить мастера') . '</h2>';
        self::post_open('master', 'sbk-masters', array('id' => $id));
        self::field('Имя', 'name', $edit->name ?? '', 'text', 'required maxlength="190"');
        echo '<p><label><strong>Описание</strong><br><textarea class="large-text" rows="3" name="description">' . esc_textarea($edit->description ?? '') . '</textarea></label></p><fieldset><legend><strong>Услуги мастера</strong></legend>';
        foreach ($services as $service) echo '<label style="display:block;margin:6px 0"><input type="checkbox" name="services[]" value="' . esc_attr($service->id) . '" ' . checked(in_array($service->id, $selected, false), true, false) . '> ' . esc_html($service->name) . '</label>';
        echo '</fieldset><p><label><input type="checkbox" name="active" value="1" ' . checked($edit->active ?? 1, 1, false) . '> Активен</label></p>';
        submit_button('Сохранить мастера'); echo '</form>';
    }

    private static function hours_fields($prefix, $row = null) {
        $start = substr($row->starts ?? '09:00', 0, 5);
        $end = substr($row->ends ?? '18:00', 0, 5);
        $breaks = json_decode($row->breaks ?? '[]', true);
        $lines = is_array($breaks) ? implode("\n", array_map(static fn($b) => implode('-', $b), $breaks)) : '';
        echo '<label><input type="checkbox" name="' . esc_attr($prefix) . '[is_closed]" value="1" ' . checked($row->is_closed ?? 1, 1, false) . '> Выходной</label> ';
        echo '<input type="time" name="' . esc_attr($prefix) . '[starts]" value="' . esc_attr($start) . '"> — <input type="time" name="' . esc_attr($prefix) . '[ends]" value="' . esc_attr($end) . '"> ';
        echo '<textarea name="' . esc_attr($prefix) . '[breaks]" rows="2" placeholder="13:00-14:00" aria-label="Перерывы">' . esc_textarea($lines) . '</textarea>';
    }

    private static function schedule_page() {
        global $wpdb;
        $masters = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('masters') . ' ORDER BY name');
        $master = absint($_GET['master_id'] ?? ($masters[0]->id ?? 0));
        echo '<h2>Расписание мастера</h2><form method="get"><input type="hidden" name="page" value="sbk-schedule"><select name="master_id">';
        foreach ($masters as $m) echo '<option value="' . esc_attr($m->id) . '" ' . selected($master, $m->id, false) . '>' . esc_html($m->name) . '</option>';
        echo '</select> <button class="button">Открыть</button></form>';
        if (!$master) { echo '<p>Сначала добавьте мастера.</p>'; return; }
        $days = $wpdb->get_results($wpdb->prepare('SELECT weekday,master_id,is_closed,starts,ends,breaks FROM ' . SBK_Database::table('schedule') . ' WHERE master_id=%d', $master), OBJECT_K);
        echo '<h3>Дни недели</h3><p>Перерывы вводите по одному на строку: 13:00-14:00.</p>';
        self::post_open('schedule', 'sbk-schedule', array('master_id' => $master));
        echo '<table class="widefat striped"><thead><tr><th>День</th><th>Часы и перерывы</th></tr></thead><tbody>';
        foreach (array(1 => 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье') as $number => $label) {
            $row = $days[$number] ?? null;
            echo '<tr><th>' . esc_html($label) . '</th><td>';
            self::hours_fields('days[' . $number . ']', $row);
            echo '</td></tr>';
        }
        echo '</tbody></table>'; submit_button('Сохранить расписание'); echo '</form>';
        echo '<h3>Исключения на даты</h3>';
        $exceptions = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('exceptions') . ' WHERE master_id=%d AND on_date>=%s ORDER BY on_date LIMIT 100', $master, wp_date('Y-m-d')));
        echo '<table class="widefat striped"><thead><tr><th>Дата</th><th>Часы</th><th></th></tr></thead><tbody>';
        foreach ($exceptions as $row) {
            echo '<tr><td>' . esc_html($row->on_date) . '</td><td>' . ($row->is_closed ? 'Выходной' : esc_html(substr($row->starts, 0, 5) . '–' . substr($row->ends, 0, 5))) . '</td><td>';
            self::post_open('delete_exception', 'sbk-schedule', array('id' => $row->id, 'master_id' => $master));
            echo '<button class="button" type="submit">Удалить исключение</button></form></td></tr>';
        }
        echo '</tbody></table><h4>Добавить или заменить исключение</h4>';
        self::post_open('exception', 'sbk-schedule', array('master_id' => $master));
        self::field('Дата', 'on_date', '', 'date', 'required');
        echo '<p><label><input type="checkbox" name="is_closed" value="1" checked> Выходной</label></p>';
        self::field('Начало', 'starts', '09:00', 'time'); self::field('Конец', 'ends', '18:00', 'time');
        echo '<p><label>Перерывы<br><textarea name="breaks" rows="3" placeholder="13:00-14:00"></textarea></label></p>';
        submit_button('Сохранить исключение'); echo '</form>';
    }

    private static function settings_page() {
        global $wpdb;
        $s = SBK_Booking::settings();
        $token_saved = (bool) get_option('sbk_telegram_token');
        $chat_saved = (bool) get_option('sbk_telegram_chat');
        $last_test = (int) get_option('sbk_telegram_last_test', 0);
        $bot_username = (string) get_option('sbk_telegram_bot_username', '');
        echo '<h2>Настройки записи</h2>';
        self::post_open('settings', 'sbk-settings');
        self::field('Шаг времени, минут', 'step', $s['step'], 'number', 'min="5" max="120" required');
        self::field('Минимальное время до записи, минут', 'notice', $s['notice'], 'number', 'min="0" max="10080" required');
        self::field('Календарь открыт на дней вперёд', 'horizon', $s['horizon'], 'number', 'min="1" max="365" required');
        self::field('Пауза между клиентами, минут', 'buffer', $s['buffer'], 'number', 'min="0" max="240" required');
        echo '<h2>Telegram</h2><p>Откройте личный чат с ботом и отправьте <code>/id</code>. Укажите полученный chat ID ниже. Только этот один администратор получает уведомления и может управлять записями через <code>/menu</code>.</p>';
        echo '<div class="sbk-telegram-status"><span class="sbk-check ' . ($last_test ? 'is-ready' : '') . '">' . ($last_test ? '✓' : '·') . '</span><div><strong>' . ($last_test ? 'Подключение проверено' : 'Подключение ожидает проверки') . '</strong><small>Токен: ' . ($token_saved ? 'сохранён' : 'не задан') . ' · chat ID: ' . ($chat_saved ? 'сохранён' : 'не задан') . ($last_test ? ' · тест: ' . esc_html(wp_date('d.m.Y H:i', $last_test)) : '') . '</small>';
        if ($bot_username) echo '<small>Бот: <a href="' . esc_url('https://t.me/' . $bot_username) . '" target="_blank" rel="noopener noreferrer">@' . esc_html($bot_username) . '</a></small>';
        echo '</div></div>';
        self::field('Токен бота', 'token', '', 'password', 'autocomplete="new-password"');
        echo '<p class="description">Токен хранится в зашифрованном виде. Пустое поле сохраняет действующий токен.</p>';
        echo '<p><label><input type="checkbox" name="clear_token" value="1"> Удалить сохранённый токен</label></p>';
        self::field('Chat ID администратора', 'chat_id', get_option('sbk_telegram_chat', ''), 'text', 'inputmode="numeric"');
        $pending = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SBK_Database::table('telegram_queue') . ' WHERE sent_utc IS NULL AND attempts<10');
        $failed = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SBK_Database::table('telegram_queue') . ' WHERE sent_utc IS NULL AND attempts>=10');
        $last_poll = (int) get_option('sbk_telegram_last_poll', 0);
        $worker_seen = (int) get_option('sbk_telegram_worker_seen', 0);
        echo '<p>Доставка: в очереди ' . esc_html($pending) . ', не доставлено после повторов ' . esc_html($failed) . '. Проверка кнопок: ' . ($last_poll ? esc_html(wp_date('d.m.Y H:i', $last_poll)) : 'ещё не выполнялась') . '.</p>';
        if (wp_get_environment_type() === 'local') echo '<p>Быстрый отклик бота: <strong>' . ($worker_seen && time() - $worker_seen < 90 ? 'фоновый обработчик работает' : 'фоновый обработчик не запущен') . '</strong>.</p>';
        submit_button('Сохранить настройки'); echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sbk_test_telegram">';
        wp_nonce_field('sbk_admin');
        echo '<p><button class="button button-secondary" type="submit" ' . (!$token_saved || !$chat_saved ? 'disabled' : '') . '>Отправить тестовое сообщение</button></p></form>';
        if (!$token_saved || !$chat_saved) echo '<p class="description">Для проверки сначала сохраните токен и chat ID.</p>';
    }

    private static function bookings() {
        global $wpdb;
        $edit_id = absint($_GET['edit_booking'] ?? 0);
        if ($edit_id) { self::booking_editor($edit_id); return; }
        $services = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('services') . ' WHERE active=1 ORDER BY name');
        $masters = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('masters') . ' WHERE active=1 ORDER BY name');
        $date = sanitize_text_field(wp_unslash($_GET['date'] ?? ''));
        $period = sanitize_key($_GET['period'] ?? '');
        $master = absint($_GET['master_id'] ?? 0);
        $status = sanitize_key($_GET['status'] ?? '');
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        echo '<h2>Записи</h2><div class="sbk-quick-filters">';
        foreach (array(
            array('Все', array()),
            array('Сегодня', array('date' => wp_date('Y-m-d'))),
            array('Ближайшие 7 дней', array('period' => 'week')),
            array('Ожидают', array('status' => 'pending')),
        ) as $filter) echo '<a class="button" href="' . esc_url(add_query_arg(array_merge(array('page' => 'sbk-bookings'), $filter[1]), admin_url('admin.php'))) . '">' . esc_html($filter[0]) . '</a>';
        echo '</div><form method="get"><input type="hidden" name="page" value="sbk-bookings">';
        if ($period === 'week') echo '<input type="hidden" name="period" value="week">';
        echo '<input type="search" name="s" placeholder="Имя, телефон или №" value="' . esc_attr($search) . '"> <input type="date" name="date" value="' . esc_attr($date) . '"> <select name="master_id"><option value="0">Все мастера</option>';
        foreach ($masters as $m) echo '<option value="' . esc_attr($m->id) . '" ' . selected($master, $m->id, false) . '>' . esc_html($m->name) . '</option>';
        echo '</select> <select name="status"><option value="">Все статусы</option>';
        $labels = array('pending' => 'Ожидает', 'confirmed' => 'Подтверждена', 'cancelled' => 'Отменена', 'completed' => 'Завершена');
        foreach ($labels as $key => $label) echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select> <button class="button">Найти</button></form>';
        $where = array('1=1'); $args = array();
        if (SBK_Booking::valid_date($date)) {
            $start = (new DateTimeImmutable($date, wp_timezone()))->setTimezone(new DateTimeZone('UTC'));
            $where[] = 'starts_utc>=%s AND starts_utc<%s'; $args[] = $start->format('Y-m-d H:i:s'); $args[] = $start->modify('+1 day')->format('Y-m-d H:i:s');
        } elseif ($period === 'week') {
            $start = new DateTimeImmutable('today', wp_timezone());
            $where[] = 'starts_utc>=%s AND starts_utc<%s';
            $args[] = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $args[] = $start->modify('+7 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if ($master) { $where[] = 'master_id=%d'; $args[] = $master; }
        if (isset($labels[$status])) { $where[] = 'status=%s'; $args[] = $status; }
        if ($search) {
            $where[] = '(client_name LIKE %s OR phone LIKE %s' . (ctype_digit($search) ? ' OR id=%d' : '') . ')';
            $like = '%' . $wpdb->esc_like($search) . '%'; $args[] = $like; $args[] = $like;
            if (ctype_digit($search)) $args[] = (int) $search;
        }
        $sql = 'SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY starts_utc DESC LIMIT 200';
        $rows = $wpdb->get_results($args ? $wpdb->prepare($sql, $args) : $sql);
        echo '<table class="widefat striped"><thead><tr><th>№</th><th>Дата и время</th><th>Услуга</th><th>Мастер</th><th>Клиент</th><th>Телефон</th><th>Статус</th><th>Действия</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html(wp_date('d.m.Y H:i', strtotime($row->starts_utc . ' UTC'), wp_timezone())) . '</td><td>' . esc_html($row->service_name) . '</td><td>' . esc_html($row->master_name) . '</td><td>' . esc_html($row->client_name) . '</td><td>' . esc_html($row->phone) . '</td><td>' . esc_html($labels[$row->status] ?? $row->status) . '</td><td>';
            if (in_array($row->status, array('pending', 'confirmed'), true)) echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('page' => 'sbk-bookings', 'edit_booking' => $row->id), admin_url('admin.php'))) . '">Изменить</a> ';
            foreach (array('confirmed' => 'Подтвердить', 'cancelled' => 'Отменить', 'completed' => 'Завершить') as $key => $label) {
                if (!in_array($key, array('pending' => array('confirmed', 'cancelled'), 'confirmed' => array('cancelled', 'completed'))[$row->status] ?? array(), true)) continue;
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:4px"><input type="hidden" name="action" value="sbk_status"><input type="hidden" name="id" value="' . esc_attr($row->id) . '"><input type="hidden" name="status" value="' . esc_attr($key) . '">';
                wp_nonce_field('sbk_admin'); echo '<button class="button button-small">' . esc_html($label) . '</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table><h2>Добавить запись вручную</h2><p>Запись проверяется по расписанию и занятым интервалам. Сначала укажите услугу и мастера.</p>';
        self::post_open('booking', 'sbk-bookings');
        echo '<p><label>Услуга<br><select name="service_id" required><option value="">Выберите</option>';
        foreach ($services as $s) echo '<option value="' . esc_attr($s->id) . '">' . esc_html($s->name) . '</option>';
        echo '</select></label></p><p><label>Мастер<br><select name="master_id" required><option value="">Выберите</option>';
        foreach ($masters as $m) echo '<option value="' . esc_attr($m->id) . '">' . esc_html($m->name) . '</option>';
        echo '</select></label></p>';
        self::field('Дата', 'date', '', 'date', 'required'); self::field('Время', 'time', '', 'time', 'required');
        self::field('Имя клиента', 'name', '', 'text', 'required maxlength="190"'); self::field('Телефон', 'phone', '', 'tel', 'required maxlength="25"');
        submit_button('Добавить запись'); echo '</form>';
    }

    private static function booking_editor($id) {
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id));
        if (!$booking || !in_array($booking->status, array('pending', 'confirmed'), true)) { echo '<p>Действующая запись не найдена.</p>'; return; }
        $services = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('services') . ' ORDER BY name');
        $masters = $wpdb->get_results('SELECT id,name FROM ' . SBK_Database::table('masters') . ' ORDER BY name');
        $local = wp_date('Y-m-d H:i', strtotime($booking->starts_utc . ' UTC'), wp_timezone());
        echo '<h2>Изменить запись №' . esc_html($id) . '</h2><p><a href="' . esc_url(admin_url('admin.php?page=sbk-bookings')) . '">← К списку записей</a></p><p>При смене услуги, мастера или времени плагин заново проверит расписание и занятость.</p>';
        self::post_open('edit_booking', 'sbk-bookings', array('id' => $id));
        echo '<p><label><strong>Услуга</strong><br><select name="service_id" required>';
        foreach ($services as $service) echo '<option value="' . esc_attr($service->id) . '" ' . selected($booking->service_id, $service->id, false) . '>' . esc_html($service->name) . '</option>';
        echo '</select></label></p><p><label><strong>Мастер</strong><br><select name="master_id" required>';
        foreach ($masters as $master) echo '<option value="' . esc_attr($master->id) . '" ' . selected($booking->master_id, $master->id, false) . '>' . esc_html($master->name) . '</option>';
        echo '</select></label></p>';
        self::field('Дата', 'date', substr($local, 0, 10), 'date', 'required');
        self::field('Время', 'time', substr($local, 11, 5), 'time', 'required');
        self::field('Имя клиента', 'name', $booking->client_name, 'text', 'required maxlength="190"');
        self::field('Телефон', 'phone', $booking->phone, 'tel', 'required maxlength="25"');
        submit_button('Сохранить изменения'); echo '</form>';
    }
}
