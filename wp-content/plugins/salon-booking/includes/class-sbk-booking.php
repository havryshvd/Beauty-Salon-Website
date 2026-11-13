<?php
defined('ABSPATH') || exit;

final class SBK_Booking {
    public static function settings() {
        return wp_parse_args((array) get_option('sbk_settings', array()), array('step' => 15, 'notice' => 120, 'horizon' => 60, 'buffer' => 0));
    }

    public static function valid_date($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        return $d && $d->format('Y-m-d') === $value;
    }

    public static function valid_time($value) {
        return is_string($value) && (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value);
    }

    public static function parse_breaks($raw) {
        $items = preg_split('/\r\n|\r|\n/', trim((string) $raw));
        $result = array();
        foreach ($items as $line) {
            if ($line === '') continue;
            if (!preg_match('/^\s*((?:[01]\d|2[0-3]):[0-5]\d)\s*-\s*((?:[01]\d|2[0-3]):[0-5]\d)\s*$/D', $line, $m) || $m[1] >= $m[2]) {
                return new WP_Error('sbk_break', 'Укажите перерывы строками в формате 13:00-14:00.');
            }
            $result[] = array($m[1], $m[2]);
        }
        return $result;
    }

    public static function schedule($master_id, $date) {
        global $wpdb;
        $exception = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('exceptions') . ' WHERE master_id=%d AND on_date=%s', $master_id, $date));
        if ($exception) return $exception;
        $weekday = (int) (new DateTimeImmutable($date, wp_timezone()))->format('N');
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('schedule') . ' WHERE master_id=%d AND weekday=%d', $master_id, $weekday));
    }

    private static function local_datetime($date, $time) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, wp_timezone());
        return $d && $d->format('Y-m-d H:i') === $date . ' ' . $time ? $d : false;
    }

    public static function slots($service_id, $master_id, $date, $admin = false, $exclude_booking_id = 0) {
        $available = array();
        foreach (self::slot_options($service_id, $master_id, $date, $admin, $exclude_booking_id) as $slot) {
            if ($slot['status'] === 'available') $available[] = $slot['time'];
        }
        return $available;
    }

    public static function slot_options($service_id, $master_id, $date, $admin = false, $exclude_booking_id = 0) {
        global $wpdb;
        if (!self::valid_date($date)) return array();
        $service = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('services') . ' WHERE id=%d', $service_id));
        $master = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $master_id));
        $relation = $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . SBK_Database::table('master_services') . ' WHERE master_id=%d AND service_id=%d', $master_id, $service_id));
        if (!$service || !$master || !$relation || (!$admin && (!$service->active || !$master->active))) return array();
        $settings = self::settings();
        $now = new DateTimeImmutable('now', wp_timezone());
        $day = new DateTimeImmutable($date, wp_timezone());
        if ($day < $now->setTime(0, 0) || $day > $now->setTime(0, 0)->modify('+' . ($admin ? 365 : (int) $settings['horizon']) . ' days')) return array();
        $schedule = self::schedule($master_id, $date);
        if (!$schedule || $schedule->is_closed) return array();
        $start = substr($schedule->starts, 0, 5);
        $end = substr($schedule->ends, 0, 5);
        if (!self::valid_time($start) || !self::valid_time($end) || $start >= $end) return array();
        $breaks = json_decode($schedule->breaks, true);
        if (!is_array($breaks)) $breaks = array();
        $day_start = self::local_datetime($date, $start);
        $day_end = self::local_datetime($date, $end);
        if (!$day_start || !$day_end) return array();
        $buffer = (int) $settings['buffer'];
        $utc_start = $day_start->modify('-' . $buffer . ' minutes')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $utc_end = $day_end->modify('+' . $buffer . ' minutes')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $bookings = $wpdb->get_results($wpdb->prepare('SELECT starts_utc,ends_utc FROM ' . SBK_Database::table('bookings') . " WHERE master_id=%d AND id<>%d AND status IN ('pending','confirmed') AND starts_utc<%s AND ends_utc>%s", $master_id, $exclude_booking_id, $utc_end, $utc_start));
        $step = max(5, (int) $settings['step']);
        $duration = (int) $service->duration;
        $first = (int) (ceil(((int) $day_start->format('H') * 60 + (int) $day_start->format('i')) / $step) * $step);
        $last = (int) $day_end->format('H') * 60 + (int) $day_end->format('i') - $duration;
        $slots = array();
        for ($minute = $first; $minute <= $last; $minute += $step) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            $at = self::local_datetime($date, $time);
            if (!$at || $at < $now->modify('+' . (int) $settings['notice'] . ' minutes')) continue;
            $finish = $at->modify('+' . $duration . ' minutes');
            if ($finish > $day_end) continue;
            $blocked = false;
            foreach ($breaks as $break) {
                if (!is_array($break) || count($break) !== 2 || !self::valid_time($break[0]) || !self::valid_time($break[1])) continue;
                $bstart = self::local_datetime($date, $break[0]);
                $bend = self::local_datetime($date, $break[1]);
                if ($bstart && $bend && $at < $bend && $finish > $bstart) { $blocked = true; break; }
            }
            if ($blocked) continue;
            $a = $at->getTimestamp();
            $b = $finish->getTimestamp();
            foreach ($bookings as $booking) {
                $occupied_start = strtotime($booking->starts_utc . ' UTC');
                $occupied_end = strtotime($booking->ends_utc . ' UTC');
                if ($a < $occupied_end + $buffer * 60 && $b + $buffer * 60 > $occupied_start) { $blocked = true; break; }
            }
            $slots[] = array('time' => $time, 'status' => $blocked ? 'booked' : 'available');
        }
        return $slots;
    }

    public static function create($data, $admin = false) {
        global $wpdb;
        $service_id = absint($data['service_id'] ?? 0);
        $master_id = absint($data['master_id'] ?? 0);
        $date = sanitize_text_field($data['date'] ?? '');
        $time = sanitize_text_field($data['time'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        if (!self::valid_date($date) || !self::valid_time($time) || mb_strlen($name) < 2 || mb_strlen($name) > 190 || !preg_match('/^\+?[0-9 ()-]{7,25}$/D', $phone)) {
            return new WP_Error('sbk_input', 'Проверьте дату, время, имя и телефон.', array('status' => 400));
        }
        $lock = 'sbk_master_' . $master_id;
        if (!$master_id || '1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) {
            return new WP_Error('sbk_busy', 'Сервис занят. Попробуйте ещё раз.', array('status' => 503));
        }
        try {
            if (!in_array($time, self::slots($service_id, $master_id, $date, $admin), true)) {
                return new WP_Error('sbk_unavailable', 'Это время уже недоступно. Выберите другое.', array('status' => 409));
            }
            $service = $wpdb->get_row($wpdb->prepare('SELECT name,duration,price FROM ' . SBK_Database::table('services') . ' WHERE id=%d', $service_id));
            $master = $wpdb->get_row($wpdb->prepare('SELECT name FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $master_id));
            $start = self::local_datetime($date, $time)->setTimezone(new DateTimeZone('UTC'));
            $now = gmdate('Y-m-d H:i:s');
            $ok = $wpdb->insert(SBK_Database::table('bookings'), array(
                'service_id' => $service_id, 'master_id' => $master_id,
                'service_name' => $service->name, 'master_name' => $master->name, 'price' => $service->price,
                'starts_utc' => $start->format('Y-m-d H:i:s'),
                'ends_utc' => $start->modify('+' . (int) $service->duration . ' minutes')->format('Y-m-d H:i:s'),
                'client_name' => $name, 'phone' => $phone, 'status' => $admin ? 'confirmed' : 'pending',
                'source' => $admin ? 'admin' : 'web', 'created_utc' => $now, 'changed_utc' => $now
            ));
            if (!$ok) return new WP_Error('sbk_database', 'Не удалось сохранить запись.', array('status' => 500));
            $id = (int) $wpdb->insert_id;
            SBK_Telegram::queue($id, 'new');
            return $id;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    public static function update($id, $data) {
        global $wpdb;
        $id = absint($id);
        $current = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id));
        if (!$current || !in_array($current->status, array('pending', 'confirmed'), true)) return new WP_Error('sbk_edit', 'Можно редактировать только действующую запись.');
        $service_id = absint($data['service_id'] ?? 0);
        $master_id = absint($data['master_id'] ?? 0);
        $date = sanitize_text_field($data['date'] ?? '');
        $time = sanitize_text_field($data['time'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        if (!$service_id || !$master_id || !self::valid_date($date) || !self::valid_time($time) || mb_strlen($name) < 2 || mb_strlen($name) > 190 || !preg_match('/^\+?[0-9 ()-]{7,25}$/D', $phone)) {
            return new WP_Error('sbk_input', 'Проверьте услугу, мастера, дату, время, имя и телефон.');
        }
        $locks = array_unique(array((int) $current->master_id, $master_id));
        sort($locks, SORT_NUMERIC);
        $held = array();
        foreach ($locks as $lock_id) {
            $lock = 'sbk_master_' . $lock_id;
            if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) {
                foreach (array_reverse($held) as $held_lock) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $held_lock));
                return new WP_Error('sbk_busy', 'Сервис занят. Попробуйте ещё раз.');
            }
            $held[] = $lock;
        }
        try {
            $fresh = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id));
            if (!$fresh || $fresh->master_id != $current->master_id || !in_array($fresh->status, array('pending', 'confirmed'), true)) return new WP_Error('sbk_edit', 'Запись уже изменилась. Обновите страницу.');
            $start_old = wp_date('Y-m-d H:i', strtotime($fresh->starts_utc . ' UTC'), wp_timezone());
            $schedule_changed = $service_id !== (int) $fresh->service_id || $master_id !== (int) $fresh->master_id || $date . ' ' . $time !== $start_old;
            if (!$schedule_changed) {
                $ok = $wpdb->update(SBK_Database::table('bookings'), array('client_name' => $name, 'phone' => $phone, 'changed_utc' => gmdate('Y-m-d H:i:s')), array('id' => $id));
                if ($ok === false) return new WP_Error('sbk_database', 'Не удалось изменить запись.');
                if ($ok) SBK_Telegram::queue($id, 'edit');
                return true;
            }
            if ($schedule_changed && !in_array($time, self::slots($service_id, $master_id, $date, true, $id), true)) {
                return new WP_Error('sbk_unavailable', 'Это время недоступно для выбранного мастера.');
            }
            $service = $wpdb->get_row($wpdb->prepare('SELECT name,duration,price FROM ' . SBK_Database::table('services') . ' WHERE id=%d', $service_id));
            $master = $wpdb->get_row($wpdb->prepare('SELECT name FROM ' . SBK_Database::table('masters') . ' WHERE id=%d', $master_id));
            if (!$service || !$master) return new WP_Error('sbk_edit', 'Услуга или мастер не найдены.');
            $start = self::local_datetime($date, $time)->setTimezone(new DateTimeZone('UTC'));
            $ok = $wpdb->update(SBK_Database::table('bookings'), array(
                'service_id' => $service_id, 'master_id' => $master_id,
                'service_name' => $service->name, 'master_name' => $master->name, 'price' => $service->price,
                'starts_utc' => $start->format('Y-m-d H:i:s'),
                'ends_utc' => $start->modify('+' . (int) $service->duration . ' minutes')->format('Y-m-d H:i:s'),
                'client_name' => $name, 'phone' => $phone, 'changed_utc' => gmdate('Y-m-d H:i:s')
            ), array('id' => $id));
            if ($ok === false) return new WP_Error('sbk_database', 'Не удалось изменить запись.');
            if ($ok) SBK_Telegram::queue($id, 'edit', (int) $fresh->master_id);
            return true;
        } finally {
            foreach (array_reverse($held) as $held_lock) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $held_lock));
        }
    }

    public static function change_status($id, $status) {
        global $wpdb;
        $id = absint($id);
        $booking = $wpdb->get_row($wpdb->prepare('SELECT master_id,status FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id));
        if (!$booking) return new WP_Error('sbk_missing', 'Запись не найдена.');
        $lock = 'sbk_master_' . (int) $booking->master_id;
        if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock))) return new WP_Error('sbk_busy', 'Сервис занят. Попробуйте ещё раз.');
        try {
            $old = $wpdb->get_var($wpdb->prepare('SELECT status FROM ' . SBK_Database::table('bookings') . ' WHERE id=%d', $id));
            $transitions = array('pending' => array('confirmed', 'cancelled'), 'confirmed' => array('cancelled', 'completed'));
            if (!in_array($status, $transitions[$old] ?? array(), true)) return new WP_Error('sbk_status', 'Этот переход статуса недоступен.');
            $ok = $wpdb->update(SBK_Database::table('bookings'), array('status' => $status, 'changed_utc' => gmdate('Y-m-d H:i:s')), array('id' => $id));
            if ($ok === false) return new WP_Error('sbk_database', 'Не удалось изменить статус.');
            SBK_Telegram::queue($id, 'status');
            return true;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
}
