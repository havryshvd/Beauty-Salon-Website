<?php
$phone = trim((string) get_theme_mod('atelier_phone'));
$address = trim((string) get_theme_mod('atelier_address'));
$instagram = esc_url(get_theme_mod('atelier_instagram'));
$map = esc_url(get_theme_mod('atelier_map'));
$privacy = get_privacy_policy_url();
$impressum = get_page_by_path('impressum');
?>
<footer class="site-footer" id="contacts">
  <div class="wrap footer-grid">
    <div class="footer-intro"><p class="eyebrow">Связаться с нами</p><h2>До встречи<br><em>в студии.</em></h2><a class="button button-light" href="#booking">Выбрать время <span aria-hidden="true">↗</span></a></div>
    <div class="footer-column"><h3>Контакты</h3>
      <?php if ($phone) : ?><p><a href="<?php echo esc_url('tel:' . preg_replace('/[^+0-9]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p><?php else : ?><p>Телефон будет добавлен</p><?php endif; ?>
      <?php if ($address) : ?><p><?php echo esc_html($address); ?></p><?php if ($map) : ?><p><a href="<?php echo $map; ?>" target="_blank" rel="noopener noreferrer">Открыть карту ↗</a></p><?php endif; ?><?php else : ?><p>Адрес будет добавлен</p><?php endif; ?>
      <?php if ($instagram) : ?><p><a href="<?php echo $instagram; ?>" target="_blank" rel="noopener noreferrer">Instagram ↗</a></p><?php endif; ?>
    </div>
    <div class="footer-column"><h3>Часы записи</h3><p>Пн — Пт: 10:00–18:00</p><p>Сб: 10:00–16:00</p><p>Вс: выходной</p><small>Точное время для каждого мастера показано в форме записи.</small></div>
  </div>
  <div class="wrap footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html(atelier_brand()); ?></span><span>Маникюр · Педикюр · Брови</span><span><?php if ($impressum && $impressum->post_status === 'publish') : ?><a href="<?php echo esc_url(get_permalink($impressum)); ?>">Impressum</a>　<?php endif; ?><?php if ($privacy) : ?><a href="<?php echo esc_url($privacy); ?>">Datenschutz</a><?php endif; ?></span></div>
</footer>
<?php wp_footer(); ?>
</body></html>
