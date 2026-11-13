<?php
defined('ABSPATH') || exit;

final class SBK_Public {
    public static function init() {
        add_shortcode('salon_booking', array(__CLASS__, 'shortcode'));
    }

    public static function routes() {
        register_rest_route('salon-booking/v1', '/catalog', array('methods' => 'GET', 'callback' => array(__CLASS__, 'catalog'), 'permission_callback' => '__return_true'));
        register_rest_route('salon-booking/v1', '/slots', array('methods' => 'GET', 'callback' => array(__CLASS__, 'slots'), 'permission_callback' => '__return_true'));
        register_rest_route('salon-booking/v1', '/book', array('methods' => 'POST', 'callback' => array(__CLASS__, 'book'), 'permission_callback' => '__return_true'));
    }

    public static function shortcode() {
        $issued = time();
        $proof = hash_hmac('sha256', (string) $issued, wp_salt('nonce'));
        wp_enqueue_style('sbk-booking', SBK_URL . 'assets/booking.css', array(), SBK_VERSION);
        wp_enqueue_script('sbk-booking', SBK_URL . 'assets/booking.js', array(), SBK_VERSION, true);
        wp_add_inline_script('sbk-booking', 'window.SBK_CONFIG=' . wp_json_encode(array('api' => esc_url_raw(rest_url('salon-booking/v1/')), 'issued' => $issued, 'proof' => $proof)) . ';', 'before');
        ob_start(); ?>
        <form class="sbk-form" novalidate>
            <h2 class="sbk-title">Запись в салон</h2>
            <label class="sbk-field">Услуга<select name="service_id" required><option value="">Выберите услугу</option></select></label>
            <label class="sbk-field">Мастер<select name="master_id" required disabled><option value="">Выберите мастера</option></select></label>
            <label class="sbk-field sbk-date-field">Дата<input type="date" name="date" required disabled></label>
            <label class="sbk-field">Время<select name="time" required disabled><option value="">Выберите время</option></select></label>
            <label class="sbk-field">Имя<input type="text" name="name" autocomplete="name" maxlength="190" required></label>
            <label class="sbk-field">Телефон<input type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="25" required></label>
            <div class="sbk-trap" aria-hidden="true"><label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <button type="submit" class="sbk-submit">Отправить запись</button>
            <p class="sbk-message" role="status" aria-live="polite"></p>
        </form>
        <?php return ob_get_clean();
    }

    public static function catalog() {
        global $wpdb;
        $services = $wpdb->get_results('SELECT id,name,description,duration,price FROM ' . SBK_Database::table('services') . ' WHERE active=1 ORDER BY name', ARRAY_A);
        $masters = $wpdb->get_results('SELECT id,name,description FROM ' . SBK_Database::table('masters') . ' WHERE active=1 ORDER BY name', ARRAY_A);
        $relations = $wpdb->get_results('SELECT ms.master_id,ms.service_id FROM ' . SBK_Database::table('master_services') . ' ms JOIN ' . SBK_Database::table('masters') . ' m ON m.id=ms.master_id AND m.active=1 JOIN ' . SBK_Database::table('services') . ' s ON s.id=ms.service_id AND s.active=1', ARRAY_A);
        return rest_ensure_response(array('services' => $services, 'masters' => $masters, 'relations' => $relations, 'horizon' => (int) SBK_Booking::settings()['horizon'], 'today' => wp_date('Y-m-d')));
    }

    public static function slots($request) {
        $date = sanitize_text_field((string) $request->get_param('date'));
        if (!SBK_Booking::valid_date($date)) return new WP_Error('sbk_date', 'Неверная дата.', array('status' => 400));
        $options = SBK_Booking::slot_options(absint($request->get_param('service_id')), absint($request->get_param('master_id')), $date);
        return rest_ensure_response(array(
            'times' => array_values(array_column(array_filter($options, static fn($slot) => $slot['status'] === 'available'), 'time')),
            'slots' => $options
        ));
    }

    public static function book($request) {
        $data = (array) $request->get_json_params();
        if (!$data) $data = (array) $request->get_body_params();
        $issued = absint($data['issued'] ?? 0);
        $proof = sanitize_text_field($data['proof'] ?? '');
        if (!empty($data['website']) || $issued > time() - 2 || $issued < time() - DAY_IN_SECONDS || !hash_equals(hash_hmac('sha256', (string) $issued, wp_salt('nonce')), $proof)) {
            return new WP_Error('sbk_spam', 'Не удалось проверить форму. Обновите страницу.', array('status' => 400));
        }
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $bucket = 'sbk_rate_' . hash('sha256', $ip . '|' . wp_date('Y-m-d H:i'));
        $attempts = (int) get_transient($bucket);
        if ($attempts >= 5) return new WP_Error('sbk_rate', 'Слишком много попыток. Попробуйте позже.', array('status' => 429));
        set_transient($bucket, $attempts + 1, 2 * MINUTE_IN_SECONDS);
        $result = SBK_Booking::create($data);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(array('message' => 'Запись отправлена. Мы свяжемся с вами для подтверждения.', 'number' => $result));
    }
}
