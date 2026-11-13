<?php
defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));
});

add_action('wp_head', function () {
    if (!is_front_page()) return;

    $description = 'Маникюр, педикюр и оформление бровей в студии ' . atelier_brand() . '. Выберите услугу, мастера и удобное время для записи онлайн.';
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
}, 5);

add_action('wp_enqueue_scripts', function () {
    $version = wp_get_theme()->get('Version');
    $gsap_version = '3.15.0';
    $gsap_path = 'assets/vendor/gsap/';

    wp_register_script('gsap', get_theme_file_uri($gsap_path . 'gsap.min.js'), array(), $gsap_version, true);

    wp_register_script('gsap-scroll-trigger', get_theme_file_uri($gsap_path . 'ScrollTrigger.min.js'), array('gsap'), $gsap_version, true);

    if (is_front_page() && defined('SBK_URL') && defined('SBK_VERSION')) {
        wp_enqueue_style('sbk-booking', SBK_URL . 'assets/booking.css', array(), SBK_VERSION);
    }
    wp_enqueue_style('atelier-rose', get_theme_file_uri('assets/theme.css'), array(), $version);
    wp_enqueue_script('atelier-rose', get_theme_file_uri('assets/theme.js'), array(), $version, true);

    if (is_front_page()) {
        wp_enqueue_script('atelier-hero-film', get_theme_file_uri('assets/hero-film.js'), array('gsap-scroll-trigger'), $version, true);
        wp_enqueue_script('gsap');
        wp_enqueue_script('gsap-scroll-trigger');
    }
});

add_action('customize_register', function ($customizer) {
    $customizer->add_section('atelier_contacts', array(
        'title' => 'Контакты студии',
        'priority' => 30,
    ));
    $fields = array(
        'atelier_brand' => array('Название студии', 'text', 'sanitize_text_field'),
        'atelier_tagline' => array('Короткий слоган', 'text', 'sanitize_text_field'),
        'atelier_phone' => array('Телефон', 'text', 'sanitize_text_field'),
        'atelier_address' => array('Адрес', 'text', 'sanitize_text_field'),
        'atelier_instagram' => array('Ссылка на Instagram', 'url', 'esc_url_raw'),
        'atelier_map' => array('Ссылка на карту', 'url', 'esc_url_raw'),
    );
    foreach ($fields as $key => $field) {
        $customizer->add_setting($key, array('default' => '', 'sanitize_callback' => $field[2]));
        $customizer->add_control($key, array(
            'label' => $field[0], 'section' => 'atelier_contacts', 'type' => $field[1],
        ));
    }
});

function atelier_brand() {
    return get_theme_mod('atelier_brand') ?: 'beauty studio';
}

function atelier_services() {
    if (!class_exists('SBK_Database')) return array();
    global $wpdb;
    return $wpdb->get_results('SELECT id,name,description,duration,price FROM ' . SBK_Database::table('services') . ' WHERE active=1 ORDER BY id ASC');
}

function atelier_service_kind($name) {
    $value = mb_strtolower($name);
    if (str_contains($value, 'педикюр')) return 'pedicure';
    if (str_contains($value, 'бров')) return 'brows';
    return 'manicure';
}
