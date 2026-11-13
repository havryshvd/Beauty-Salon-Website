<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<header class="site-header" id="top">
  <div class="header-inner wrap">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(atelier_brand()); ?> — главная"><?php echo esc_html(atelier_brand()); ?><span>beauty studio</span></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-controls="primary-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav id="primary-nav" class="primary-nav" aria-label="Главное меню">
      <a href="<?php echo esc_url(home_url('/#main')); ?>">Главная</a>
      <a href="<?php echo esc_url(home_url('/#services')); ?>">Услуги</a>
      <a href="<?php echo esc_url(home_url('/#portfolio')); ?>">Галерея</a>
      <a class="nav-book" href="<?php echo esc_url(home_url('/#booking')); ?>">Запись <span aria-hidden="true">↗</span></a>
      <a href="<?php echo esc_url(home_url('/#contacts')); ?>">Контакты</a>
    </nav>
  </div>
</header>
