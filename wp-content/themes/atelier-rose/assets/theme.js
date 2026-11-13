(() => {
  'use strict';
  const menu = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.primary-nav');
  if (menu && nav) {
    menu.addEventListener('click', () => {
      const open = menu.getAttribute('aria-expanded') !== 'true';
      menu.setAttribute('aria-expanded', String(open));
      menu.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
      nav.classList.toggle('is-open', open);
    });
    nav.addEventListener('click', (event) => {
      if (event.target.closest('a')) {
        nav.classList.remove('is-open');
        menu.setAttribute('aria-expanded', 'false');
        menu.setAttribute('aria-label', 'Открыть меню');
      }
    });
  }
  if (nav) {
    const sectionIds = ['main', 'services', 'portfolio', 'booking', 'contacts'];
    const sections = sectionIds.map((id) => document.getElementById(id));
    if (sections.every(Boolean)) {
      const links = [...nav.querySelectorAll('a[href*="#"]')];
      let scheduled = false;
      const updateCurrent = () => {
        scheduled = false;
        let current = 'main';
        for (const section of sections) {
          if (section.getBoundingClientRect().top <= 120) current = section.id;
        }
        for (const link of links) {
          if (new URL(link.href).hash === `#${current}`) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        }
      };
      const scheduleUpdate = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(updateCurrent);
      };
      updateCurrent();
      window.addEventListener('scroll', scheduleUpdate, { passive: true });
      window.addEventListener('resize', scheduleUpdate);
    }
  }
  const lightbox = document.querySelector('.portfolio-lightbox');
  if (lightbox) {
    const image = lightbox.querySelector('.portfolio-lightbox-content img');
    const title = lightbox.querySelector('.portfolio-lightbox-title');
    const credit = lightbox.querySelector('.portfolio-lightbox-credit');
    const items = [...document.querySelectorAll('[data-portfolio-item]')];
    let category = '';
    let current = 0;
    const visible = () => items.filter((item) => item.dataset.category === category);
    const show = (item) => {
      image.src = item.dataset.full;
      image.alt = item.dataset.title;
      title.textContent = item.dataset.title;
      credit.textContent = item.dataset.credit ? `Фото: ${item.dataset.credit} / Unsplash` : '';
      credit.href = item.dataset.source || '#';
      credit.hidden = !item.dataset.source;
    };
    const move = (step) => {
      const gallery = visible();
      if (!gallery.length) return;
      current = (current + step + gallery.length) % gallery.length;
      show(gallery[current]);
    };
    document.querySelectorAll('[data-portfolio-open]').forEach((cover) => cover.addEventListener('click', () => {
      category = cover.dataset.portfolioOpen;
      const gallery = visible();
      if (!gallery.length) return;
      current = 0;
      show(gallery[current]);
      lightbox.showModal();
    }));
    lightbox.querySelector('.portfolio-close').addEventListener('click', () => lightbox.close());
    lightbox.querySelector('.portfolio-prev').addEventListener('click', () => move(-1));
    lightbox.querySelector('.portfolio-next').addEventListener('click', () => move(1));
    lightbox.addEventListener('click', (event) => { if (event.target === lightbox) lightbox.close(); });
    lightbox.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); move(-1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); move(1); }
    });
    lightbox.addEventListener('close', () => { image.removeAttribute('src'); });
  }
  document.querySelectorAll('[data-service-id]').forEach((link) => {
    link.addEventListener('click', () => {
      const id = link.dataset.serviceId;
      const select = document.querySelector('.booking-section .sbk-form select[name="service_id"]');
      if (!select) return;
      let attempts = 0;
      const choose = () => {
        if ([...select.options].some((option) => option.value === id)) {
          select.value = id;
          select.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (attempts++ < 30) {
          setTimeout(choose, 100);
        }
      };
      choose();
    });
  });
})();
