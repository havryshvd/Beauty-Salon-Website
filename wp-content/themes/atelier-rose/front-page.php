<?php
get_header();
$assets = get_theme_file_uri('assets/');
$services = atelier_services();
$kinds = array(
    'manicure' => array('Маникюр', 'MANICURE', 'manicure.webp'),
    'pedicure' => array('Педикюр', 'PEDICURE', 'pedicure.webp'),
    'brows' => array('Брови', 'BROWS', 'brows.webp'),
);
$hero_service_ids = array();
foreach ($services as $service) {
    $kind = atelier_service_kind($service->name);
    if (!isset($hero_service_ids[$kind])) {
        $hero_service_ids[$kind] = $service->id;
    }
}
?>
<main id="main">
  <div class="hero-stage hero-film" data-hero-film data-film-layer="eye-forward"><section class="hero" aria-labelledby="hero-title">
    <div class="hero-image" aria-hidden="true"></div>
    <video class="hero-video hero-video-forward" src="<?php echo esc_url($assets . 'hero-eye-intro.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-eye-poster.jpg'); ?>" preload="auto" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-reverse" src="<?php echo esc_url($assets . 'hero-eye-reverse.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-eye-end.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-hands-forward" src="<?php echo esc_url($assets . 'hero-hands-forward.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-hands-start.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-hands-reverse" src="<?php echo esc_url($assets . 'hero-hands-reverse.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-hands-end.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-pedicure-forward" src="<?php echo esc_url($assets . 'hero-pedicure-forward.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-pedicure-start.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-pedicure-reverse" src="<?php echo esc_url($assets . 'hero-pedicure-reverse.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-pedicure-end.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-room-forward" src="<?php echo esc_url($assets . 'hero-room-forward.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-room-start.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <video class="hero-video hero-video-room-reverse" src="<?php echo esc_url($assets . 'hero-room-reverse.mp4'); ?>" poster="<?php echo esc_url($assets . 'hero-room-end.jpg'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
    <img class="hero-freeze hero-freeze-eye" src="<?php echo esc_url($assets . 'hero-eye-end.jpg'); ?>" alt="" aria-hidden="true" loading="eager" decoding="sync">
    <img class="hero-freeze hero-freeze-hands" src="<?php echo esc_url($assets . 'hero-hands-end.jpg'); ?>" alt="" aria-hidden="true" loading="eager" decoding="sync">
    <img class="hero-freeze hero-freeze-pedicure" src="<?php echo esc_url($assets . 'hero-pedicure-end.jpg'); ?>" alt="" aria-hidden="true" loading="eager" decoding="sync">
    <img class="hero-freeze hero-freeze-room" src="<?php echo esc_url($assets . 'hero-room-end.jpg'); ?>" alt="" aria-hidden="true" loading="eager" decoding="sync">
    <div class="hero-film-shade" aria-hidden="true"></div>
    <div class="wrap hero-content hero-intro"><div class="hero-intro-panel">
      <h1 id="hero-title"><?php echo esc_html(atelier_brand()); ?></h1>
      <p class="hero-lead">Маникюр · Педикюр · Брови и ресницы</p>
    </div></div>
    <div class="wrap hero-finale hero-finale-brows" aria-hidden="true">
      <h2>Бровки<br><em>и реснички</em></h2>
      <p class="hero-finale-price"><span>Ориентировочно от</span><strong>20 €</strong></p>
      <a class="button button-dark" href="#booking" <?php if (isset($hero_service_ids['brows'])) : ?>data-service-id="<?php echo esc_attr($hero_service_ids['brows']); ?>"<?php endif; ?> tabindex="-1">Выбрать время <span aria-hidden="true">↗</span></a>
    </div>
    <div class="wrap hero-finale hero-finale-manicure" aria-hidden="true">
      <h2>Маникюр</h2>
      <p class="hero-finale-price"><span>Ориентировочно от</span><strong>35 €</strong></p>
      <a class="button button-dark" href="#booking" <?php if (isset($hero_service_ids['manicure'])) : ?>data-service-id="<?php echo esc_attr($hero_service_ids['manicure']); ?>"<?php endif; ?> tabindex="-1">Выбрать время <span aria-hidden="true">↗</span></a>
    </div>
    <div class="wrap hero-finale hero-finale-pedicure" aria-hidden="true">
      <h2>Педикюр</h2>
      <p class="hero-finale-price"><span>Ориентировочно от</span><strong>45 €</strong></p>
      <a class="button button-dark" href="#booking" <?php if (isset($hero_service_ids['pedicure'])) : ?>data-service-id="<?php echo esc_attr($hero_service_ids['pedicure']); ?>"<?php endif; ?> tabindex="-1">Выбрать время <span aria-hidden="true">↗</span></a>
    </div>
    <aside class="hero-booking-window" aria-labelledby="hero-booking-title" aria-hidden="true" inert>
      <h2 id="hero-booking-title">Теперь <em>ваше время.</em></h2>
      <div class="hero-booking-form"><?php if (shortcode_exists('salon_booking')) : echo do_shortcode('[salon_booking]'); else : ?><p>Форма записи временно недоступна.</p><?php endif; ?></div>
      <a class="hero-booking-more" href="#services">Посмотреть услуги <span aria-hidden="true">↗</span></a>
    </aside>
    <p class="hero-scroll">Листайте вниз <span aria-hidden="true">↓</span></p>
    <p class="hero-continue" aria-hidden="true">Листайте дальше <span>↓</span></p>
  </section></div>

  <section class="section services-section" id="services" aria-labelledby="services-title">
    <div class="wrap">
      <div class="section-heading"><div><p class="eyebrow">01 / Услуги</p><h2 id="services-title">Искусство<br><em>заботы о себе.</em></h2></div></div>
      <div class="service-grid">
        <?php foreach ($kinds as $kind => $details) :
            $related = array_values(array_filter($services, function ($service) use ($kind) { return atelier_service_kind($service->name) === $kind; }));
        ?>
        <article class="service-card">
          <div class="service-image"><img src="<?php echo esc_url($assets . $details[2]); ?>" alt="Визуальная концепция направления <?php echo esc_attr($details[0]); ?>" loading="lazy" width="900" height="1100"><span><?php echo esc_html($details[1]); ?></span></div>
          <div class="service-content"><div class="service-heading"><h3><?php echo esc_html($details[0]); ?></h3><span aria-hidden="true">↗</span></div>
            <?php if ($related) : ?><ul class="price-list">
              <?php foreach ($related as $service) : ?><li><span><?php echo esc_html($service->name); ?><small><?php echo esc_html((int) $service->duration); ?> мин.</small></span><span><?php echo (float) $service->price > 0 ? esc_html(number_format_i18n((float) $service->price, 2)) : 'Стоимость уточняется'; ?></span></li><?php endforeach; ?>
            </ul><a class="text-link" href="#booking" data-service-id="<?php echo esc_attr($related[0]->id); ?>">Выбрать время <span aria-hidden="true">↗</span></a>
            <?php else : ?><p class="service-unavailable">Услуги скоро появятся в записи.</p><?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section portfolio-section" id="portfolio" aria-labelledby="portfolio-title">
    <div class="wrap"><div class="section-heading"><div><p class="eyebrow">02 / Галерея</p><h2 id="portfolio-title">Детали, которые<br><em>вдохновляют.</em></h2></div><p>Три направления. Нажмите на фото, чтобы посмотреть примеры работ.</p></div>
      <?php
      $portfolio = array(
          array('manicure', 'Маникюр', 'Нежный маникюр', $assets . 'portfolio-manicure.webp', $assets . 'portfolio-manicure.webp', '', ''),
          array('manicure', 'Маникюр', 'Розовый маникюр', 'https://images.unsplash.com/photo-1754799670380-17640d939e32?w=640&q=78&fit=crop', 'https://images.unsplash.com/photo-1754799670380-17640d939e32?w=1600&q=85&fit=max', 'Ari Kurniawan', 'https://unsplash.com/photos/woman-with-manicured-nails-and-rings-q9nfBLcMi9g'),
          array('manicure', 'Маникюр', 'Маникюр в работе', 'https://images.unsplash.com/photo-1676926606566-58f2e00b592b?w=640&q=78&fit=crop', 'https://images.unsplash.com/photo-1676926606566-58f2e00b592b?w=1600&q=85&fit=max', 'Behnaz Kh', 'https://unsplash.com/photos/a-woman-is-doing-a-manicure-on-her-nails-c9zCtwGsJAA'),
          array('pedicure', 'Педикюр', 'Нежный педикюр', $assets . 'portfolio-pedicure.webp', $assets . 'portfolio-pedicure.webp', '', ''),
          array('pedicure', 'Педикюр', 'Педикюр с цветным покрытием', 'https://images.unsplash.com/photo-1664643411326-6c589531be3c?w=640&q=78&fit=crop', 'https://images.unsplash.com/photo-1664643411326-6c589531be3c?w=1600&q=85&fit=max', 'Konstantin Shmatov', 'https://unsplash.com/photos/a-pair-of-feet-with-painted-toenails-WBuwkanEPiM'),
          array('pedicure', 'Педикюр', 'Педикюр крупным планом', 'https://images.unsplash.com/photo-1765014683290-9af39ff6217e?w=640&q=78&fit=crop', 'https://images.unsplash.com/photo-1765014683290-9af39ff6217e?w=1600&q=85&fit=max', 'Sujana Rahman', 'https://unsplash.com/photos/close-up-of-a-womans-painted-toenails-in-shadow-ZJ16Y4kAgGw'),
          array('brows', 'Реснички', 'Ресницы крупным планом', $assets . 'portfolio-brows.webp', $assets . 'portfolio-brows.webp', '', ''),
          array('brows', 'Брови', 'Процедура для ресниц', 'https://images.unsplash.com/photo-1674049406179-d7bf2c263e71?w=640&q=78&fit=crop', 'https://images.unsplash.com/photo-1674049406179-d7bf2c263e71?w=1600&q=85&fit=max', 'Bermix Studio', 'https://unsplash.com/photos/a-woman-getting-her-eyebrows-done-by-a-professional-hair-stylist-CqEGy4zAmbI'),
          array('brows', 'Брови', 'Брови и ресницы крупным планом', 'https://images.unsplash.com/photo-1516220362602-dba5272034e7?w=640&q=78&fit=crop', 'https://images.unsplash.com/photo-1516220362602-dba5272034e7?w=1600&q=85&fit=max', 'Amanda Dalbjörn', 'https://unsplash.com/photos/close-up-photo-of-persons-eye-fvInY-Gh7sc'),
      );
      ?>
      <div class="portfolio-grid">
        <?php foreach (array(0, 3, 6) as $cover_index) : $cover = $portfolio[$cover_index]; ?>
          <figure>
            <button class="portfolio-open" type="button" data-portfolio-open="<?php echo esc_attr($cover[0]); ?>" aria-label="Посмотреть примеры работ: <?php echo esc_attr($cover[1]); ?>">
              <img src="<?php echo esc_url($cover[3]); ?>" alt="<?php echo esc_attr($cover[2]); ?>" loading="lazy" decoding="async" width="1024" height="1024"><span class="portfolio-zoom" aria-hidden="true">↗</span>
            </button>
            <figcaption><?php echo esc_html($cover[1]); ?><span>Смотреть примеры работ ↗</span></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="portfolio-items" hidden aria-hidden="true">
        <?php foreach ($portfolio as $item) : ?>
          <span data-portfolio-item data-category="<?php echo esc_attr($item[0]); ?>" data-full="<?php echo esc_url($item[4]); ?>" data-title="<?php echo esc_attr($item[2]); ?>" data-credit="<?php echo esc_attr($item[5]); ?>" data-source="<?php echo esc_url($item[6]); ?>"></span>
        <?php endforeach; ?>
      </div>
      <dialog class="portfolio-lightbox" aria-label="Просмотр портфолио">
        <button class="portfolio-close" type="button" aria-label="Закрыть портфолио">×</button>
        <button class="portfolio-prev" type="button" aria-label="Предыдущее фото">‹</button>
        <div class="portfolio-lightbox-content"><img alt=""><p class="portfolio-lightbox-title"></p><a class="portfolio-lightbox-credit" target="_blank" rel="noopener noreferrer"></a></div>
        <button class="portfolio-next" type="button" aria-label="Следующее фото">›</button>
      </dialog>
      <?php $instagram = esc_url(get_theme_mod('atelier_instagram')); if ($instagram) : ?><a class="text-link gallery-instagram" href="<?php echo $instagram; ?>" target="_blank" rel="noopener noreferrer">Больше в Instagram <span aria-hidden="true">↗</span></a><?php endif; ?>
    </div>
  </section>

  <section class="section booking-section" id="booking" aria-label="Запись в салон"><div class="wrap booking-grid booking-grid-solo"><div class="booking-panel"><?php if (shortcode_exists('salon_booking')) : echo do_shortcode('[salon_booking]'); else : ?><p>Форма записи временно недоступна.</p><?php endif; ?></div></div></section>
</main>
<?php get_footer(); ?>
