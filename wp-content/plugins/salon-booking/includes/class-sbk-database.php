<?php
defined('ABSPATH') || exit;

final class SBK_Database {
    public static function table($part) {
        global $wpdb;
        return $wpdb->prefix . 'sbk_' . $part;
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate();
        $tables = array(
            'services' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                description text NOT NULL,
                duration smallint(5) unsigned NOT NULL,
                price decimal(12,2) NOT NULL DEFAULT 0,
                active tinyint(1) NOT NULL DEFAULT 1,
                PRIMARY KEY  (id)",
            'masters' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                description text NOT NULL,
                telegram_chat_id varchar(24) NOT NULL DEFAULT '',
                active tinyint(1) NOT NULL DEFAULT 1,
                PRIMARY KEY  (id)",
            'master_services' => "master_id bigint(20) unsigned NOT NULL,
                service_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY  (master_id,service_id)",
            'schedule' => "master_id bigint(20) unsigned NOT NULL,
                weekday tinyint(1) unsigned NOT NULL,
                is_closed tinyint(1) NOT NULL DEFAULT 1,
                starts time NOT NULL DEFAULT '09:00:00',
                ends time NOT NULL DEFAULT '18:00:00',
                breaks text NOT NULL,
                PRIMARY KEY  (master_id,weekday)",
            'exceptions' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                master_id bigint(20) unsigned NOT NULL,
                on_date date NOT NULL,
                is_closed tinyint(1) NOT NULL DEFAULT 1,
                starts time NOT NULL DEFAULT '09:00:00',
                ends time NOT NULL DEFAULT '18:00:00',
                breaks text NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY master_date (master_id,on_date)",
            'bookings' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                service_id bigint(20) unsigned NOT NULL,
                master_id bigint(20) unsigned NOT NULL,
                service_name varchar(190) NOT NULL,
                master_name varchar(190) NOT NULL,
                price decimal(12,2) NOT NULL DEFAULT 0,
                starts_utc datetime NOT NULL,
                ends_utc datetime NOT NULL,
                client_name varchar(190) NOT NULL,
                phone varchar(40) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                source varchar(20) NOT NULL DEFAULT 'web',
                created_utc datetime NOT NULL,
                changed_utc datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY master_time (master_id,starts_utc),
                KEY status_time (status,starts_utc)",
            'telegram_queue' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) unsigned NOT NULL,
                chat_id varchar(24) NOT NULL DEFAULT '',
                event varchar(30) NOT NULL,
                attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
                next_utc datetime NOT NULL,
                sent_utc datetime NULL,
                PRIMARY KEY  (id),
                KEY pending (sent_utc,next_utc)"
        );
        foreach ($tables as $name => $columns) {
            dbDelta('CREATE TABLE ' . self::table($name) . " (\n$columns\n) ENGINE=InnoDB $collate;");
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table($name)));
            if ('InnoDB' !== $engine) {
                wp_die(esc_html('Для записи требуется база данных с таблицами InnoDB.'));
            }
        }
        if (!get_option('sbk_settings')) {
            add_option('sbk_settings', array('step' => 15, 'notice' => 120, 'horizon' => 60, 'buffer' => 0), '', false);
        }
        if (!wp_next_scheduled('sbk_poll_telegram')) wp_schedule_event(time() + 60, 'sbk_minute', 'sbk_poll_telegram');
        update_option('sbk_schema', '2', false);
    }

    public static function schedules($schedules) {
        $schedules['sbk_minute'] = array('interval' => 60, 'display' => 'Каждую минуту');
        return $schedules;
    }

    public static function maybe_upgrade() {
        if (get_option('sbk_role_version') !== '1') self::ensure_roles();
        if (get_option('sbk_schema') !== '2') {
            self::activate();
        }
    }

    private static function ensure_roles() {
        $admin = get_role('administrator');
        if ($admin) $admin->add_cap('manage_salon_bookings');
        $manager = get_role('salon_manager');
        if (!$manager) $manager = add_role('salon_manager', 'Администратор салона', array('read' => true, 'manage_salon_bookings' => true));
        if ($manager) $manager->add_cap('manage_salon_bookings');
        update_option('sbk_role_version', '1', false);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('sbk_poll_telegram');
        wp_clear_scheduled_hook('sbk_send_telegram');
    }
}
