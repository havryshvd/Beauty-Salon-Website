<?php
defined('ABSPATH') || exit;
$salon_zoom_assets = get_theme_file_uri('assets/salon-zoom/');
?>
<div class="sz-story" data-salon-zoom data-gsap-base="<?php echo esc_url(get_theme_file_uri('assets/vendor/gsap/')); ?>">
  <section class="sz-stage" aria-label="Взгляд, маникюр и педикюр в деталях">
    <div class="sz-media" aria-hidden="true">
      <img class="sz-image sz-wide" src="<?php echo esc_url($salon_zoom_assets . 'salon-wide.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <video class="sz-video" src="<?php echo esc_url($salon_zoom_assets . 'salon-intro.mp4'); ?>" preload="none" muted playsinline webkit-playsinline aria-hidden="true"></video>
      <img class="sz-image sz-close" src="<?php echo esc_url($salon_zoom_assets . 'salon-closeup.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <img class="sz-image sz-eye" src="<?php echo esc_url($salon_zoom_assets . 'salon-eye.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <img class="sz-image sz-hand-face" src="<?php echo esc_url($salon_zoom_assets . 'salon-hand-face.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <img class="sz-image sz-manicure" src="<?php echo esc_url($salon_zoom_assets . 'salon-manicure.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <img class="sz-image sz-hand-foot" src="<?php echo esc_url($salon_zoom_assets . 'salon-hand-foot.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <img class="sz-image sz-pedicure" src="<?php echo esc_url($salon_zoom_assets . 'salon-pedicure.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <img class="sz-image sz-return" src="<?php echo esc_url($salon_zoom_assets . 'salon-wide.jpg'); ?>" alt="" width="1672" height="941" decoding="async" loading="lazy">
      <div class="sz-soften"></div>
      <div class="sz-veil"></div>
    </div>
    <p class="sz-hint" aria-hidden="true">Взгляните ближе <span>↓</span></p>
    <div class="sz-copy sz-copy-brows">
      <p class="sz-copy-index">01 / Взгляд</p><h3>Брови</h3>
      <p>Линия, которая подчёркивает ваше лицо.</p>
      <p class="sz-copy-price"><small>Ориентировочно от</small>20 €</p>
    </div>
    <div class="sz-copy sz-copy-manicure">
      <p class="sz-copy-index">02 / Руки</p><h3>Маникюр</h3>
      <p>Точный уход и деликатный цвет.</p>
      <p class="sz-copy-price"><small>Ориентировочно от</small>35 €</p>
    </div>
    <div class="sz-copy sz-copy-pedicure">
      <p class="sz-copy-index">03 / Лёгкость</p><h3>Педикюр</h3>
      <p>Забота, которую чувствуешь в каждом шаге.</p>
      <p class="sz-copy-price"><small>Ориентировочно от</small>45 €</p>
    </div>
    <div class="sz-copy sz-copy-overview">
      <p class="sz-copy-index">04 / Ваш момент</p><h3>Выберите своё</h3>
      <p>Брови от 20 € · Маникюр от 35 € · Педикюр от 45 €</p>
    </div>
  </section>
  <div class="sz-mobile">
    <article class="sz-card"><img src="<?php echo esc_url($salon_zoom_assets . 'salon-eye.jpg'); ?>" alt="Крупный план глаза и брови" width="1672" height="941" loading="lazy"><div class="sz-card-copy"><p class="sz-copy-index">01 / Взгляд</p><h3>Брови</h3><p>Линия, которая подчёркивает ваше лицо.</p><strong>Ориентировочно от 20 €</strong></div></article>
    <article class="sz-card"><img src="<?php echo esc_url($salon_zoom_assets . 'salon-manicure.jpg'); ?>" alt="Аккуратный маникюр в нежном оттенке" width="1672" height="941" loading="lazy"><div class="sz-card-copy"><p class="sz-copy-index">02 / Руки</p><h3>Маникюр</h3><p>Точный уход и деликатный цвет.</p><strong>Ориентировочно от 35 €</strong></div></article>
    <article class="sz-card"><img src="<?php echo esc_url($salon_zoom_assets . 'salon-pedicure.jpg'); ?>" alt="Педикюр в нежном оттенке" width="1672" height="941" loading="lazy"><div class="sz-card-copy"><p class="sz-copy-index">03 / Лёгкость</p><h3>Педикюр</h3><p>Забота, которую чувствуешь в каждом шаге.</p><strong>Ориентировочно от 45 €</strong></div></article>
    <article class="sz-card"><img src="<?php echo esc_url($salon_zoom_assets . 'salon-wide.jpg'); ?>" alt="Женщина в кресле салона красоты" width="1672" height="941" loading="lazy"><div class="sz-card-copy"><p class="sz-copy-index">04 / Ваш момент</p><h3>Выберите своё</h3><p>Брови от 20 € · Маникюр от 35 € · Педикюр от 45 €</p></div></article>
  </div>
  <section class="sz-next" aria-labelledby="sz-next-title">
    <div class="sz-next-inner">
      <p class="sz-kicker">Внимание к каждой детали</p>
      <h2 id="sz-next-title">Красота в деталях.<br><em>Время для себя.</em></h2>
      <p class="sz-lead">Брови от 20 € · Маникюр от 35 € · Педикюр от 45 €</p>
      <p class="sz-price-note">Цены ориентировочные. Точную стоимость уточните при записи.</p>
      <a class="sz-button" href="#booking">Записаться <span aria-hidden="true">↗</span></a>
    </div>
  </section>
</div>
