# Beauty studio WordPress portfolio demo

An anonymized portfolio copy of a custom WordPress theme, booking plugin, and animated story component. It includes no WordPress installation, database, user accounts, booking records, configuration, or Telegram credentials.

## Contents

- `wp-content/themes/atelier-rose`: front page, responsive styling, animation, and visual assets.
- `wp-content/plugins/salon-booking`: service catalog, availability, booking form, administration, and optional Telegram integration.
- `wp-content/mu-plugins`: additional animated story component.

## Run locally

1. Install WordPress 6.4+ with PHP 8.0+ and a MySQL/MariaDB database.
2. Copy the three directories from `wp-content` into your WordPress installation.
3. Activate **Salon Booking** and **Beauty Studio Demo** in WordPress. Add sample services, staff, and schedules in the booking settings.
4. Set the booking form shortcode `[salon_booking]` on the home page as needed. The theme also renders it in its front page template.
5. Configure any studio contact details and Telegram bot token only in your own WordPress admin. Never commit a database dump or `wp-config.php`.

The repository includes only the GSAP core and ScrollTrigger used by the demo; their notices and [standard license](https://gsap.com/standard-license/) remain applicable. Portfolio images and videos should be published only when you have rights to share them. Unsplash images loaded remotely by the front page are credited in the source.
