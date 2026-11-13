<?php
/**
 * Plugin Name: Beauty Zoom Story
 * Description: Scroll driven salon story for the Elementor Shortcode widget.
 * Version: 1.0.4
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('BZS_VERSION', '1.0.4');
define('BZS_URL', plugin_dir_url(__FILE__) . 'beauty-zoom-story/');

add_action('wp_enqueue_scripts', function () {
    $gsap_dir = get_theme_file_path('assets/vendor/gsap/');
    if (!is_file($gsap_dir . 'gsap.min.js') || !is_file($gsap_dir . 'ScrollTrigger.min.js')) {
        return;
    }

    $gsap_url = get_theme_file_uri('assets/vendor/gsap/');
    wp_register_script('bzs-gsap', $gsap_url . 'gsap.min.js', array(), '3.15.0', true);
    wp_register_script('bzs-scroll-trigger', $gsap_url . 'ScrollTrigger.min.js', array('bzs-gsap'), '3.15.0', true);
    wp_register_script('bzs-story', BZS_URL . 'story.js', array('bzs-scroll-trigger'), BZS_VERSION, true);
});

add_shortcode('beauty_zoom_story', function () {
    static $instance = 0;
    $instance++;

    $gsap_dir = get_theme_file_path('assets/vendor/gsap/');
    $assets_ready = is_file($gsap_dir . 'gsap.min.js') && is_file($gsap_dir . 'ScrollTrigger.min.js');
    if ($assets_ready) {
        wp_enqueue_script('bzs-story');
    }

    $shots = array(
        array('01-mirror-opening', 'Героиня у зеркала'),
        array('transition-01-mirror-to-face', 'Камера приближается к лицу'),
        array('02-brows-lashes', 'Брови и ресницы крупным планом'),
        array('03-eye-to-manicure', 'Рука появляется рядом с лицом'),
        array('transition-02-face-to-hand', 'Камера опускается к руке'),
        array('04-manicure', 'Крупный план маникюра'),
        array('transition-03-hand-to-foot', 'Рука и педикюр в одном кадре'),
        array('05-pedicure', 'Крупный план педикюра'),
        array('transition-04-wide-booking', 'Общий кадр у зеркала'),
    );
    $booking_url = home_url('/#booking');

    ob_start();
    if ($instance === 1) : ?>
        <link rel="stylesheet" href="<?php echo esc_url(BZS_URL . 'story.css?ver=' . BZS_VERSION); ?>">
    <?php endif; ?>
    <section class="bzs<?php echo $assets_ready ? '' : ' bzs-static'; ?>" data-beauty-zoom-story aria-label="История салона красоты">
        <div class="bzs-stage">
            <div class="bzs-media" aria-hidden="true">
                <?php foreach ($shots as $index => $shot) : ?>
                    <div class="bzs-frame" data-shot="<?php echo esc_attr($index); ?>">
                        <img src="<?php echo esc_url(BZS_URL . 'images/' . $shot[0] . '.jpg'); ?>"
                             alt=""
                             width="1600" height="900"
                             decoding="async" <?php echo $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bzs-tint" aria-hidden="true"></div>
            <div class="bzs-topline" aria-hidden="true"><span>BEAUTY STUDIO</span><span>ИСТОРИЯ О ВРЕМЕНИ ДЛЯ СЕБЯ</span></div>
            <div class="bzs-copy bzs-copy-opening" data-copy="0" aria-hidden="true">
                <p class="bzs-kicker">01 / Момент для себя</p>
                <h2>Всё начинается<br><em>с вас.</em></h2>
                <p>Остановиться. Выдохнуть. Увидеть себя по-новому.</p>
            </div>
            <div class="bzs-copy" data-copy="2" aria-hidden="true">
                <p class="bzs-kicker">02 / Взгляд</p>
                <h2>Красота<br><em>в деталях.</em></h2>
                <p>Линия бровей. Лёгкий взмах ресниц.</p>
            </div>
            <div class="bzs-copy" data-copy="5" aria-hidden="true">
                <p class="bzs-kicker">03 / Прикосновение</p>
                <h2>Время<br><em>для рук.</em></h2>
                <p>Маникюр, который хочется рассматривать.</p>
            </div>
            <div class="bzs-copy" data-copy="7" aria-hidden="true">
                <p class="bzs-kicker">04 / Лёгкость</p>
                <h2>И для каждого<br><em>шага.</em></h2>
                <p>Педикюр — ещё один повод замедлиться.</p>
            </div>
            <div class="bzs-copy bzs-copy-finale" data-copy="8">
                <p class="bzs-kicker">05 / Ваш момент</p>
                <h2>Немного времени для себя.<br><em>И совсем другое настроение.</em></h2>
                <a class="bzs-cta" href="<?php echo esc_url($booking_url); ?>">Записаться <span aria-hidden="true">↗</span></a>
            </div>
            <div class="bzs-progress" aria-hidden="true"><span></span></div>
            <div class="bzs-scroll-cue" aria-hidden="true">Листайте историю <span>↓</span></div>
        </div>
        <p class="bzs-visually-hidden">История салона: от взгляда и оформления бровей и ресниц к маникюру и педикюру. В конце можно перейти к онлайн-записи.</p>
        <noscript><p class="bzs-no-script"><a href="<?php echo esc_url($booking_url); ?>">Записаться</a></p></noscript>
        <?php if (!$assets_ready) : ?>
            <p class="bzs-asset-warning">Для анимации нужны gsap.min.js и ScrollTrigger.min.js в assets/vendor/gsap/ активной темы.</p>
        <?php endif; ?>
    </section>
    <?php return ob_get_clean();
});
