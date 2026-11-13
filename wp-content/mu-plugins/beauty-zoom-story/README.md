# Beauty Zoom Story

This is an automatically loaded WordPress MU plugin. Add `[beauty_zoom_story]` to an Elementor **Shortcode** widget. The existing `/beauty-story/` page already contains that widget and uses the Elementor Canvas template.

The plugin loads GSAP 3.15.0 and ScrollTrigger from the active theme's `assets/vendor/gsap/` folder only when the shortcode renders. The original nine PNG photographs were resized and compressed as JPEGs in `images/` (1.24 MiB total). The browser loads no story scripts on the home page.

The final button points to `/#booking`, the existing `[salon_booking]` form on the home page. If the booking location changes, update `$booking_url` in `../beauty-zoom-story.php`.

Camera positions and timeline timing live in `story.js`; desktop and mobile presentation lives in `story.css`. The reduced motion preference displays the last image and booking link without pinning or zooming.
