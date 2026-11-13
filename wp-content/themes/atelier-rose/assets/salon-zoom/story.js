(() => {
  'use strict';

  const selector = '[data-salon-zoom]';
  const motion = '(min-width: 768px) and (prefers-reduced-motion: no-preference)';

  function script(url, test) {
    if (test()) return Promise.resolve();
    const address = new URL(url, location.href);
    let tag = [...document.scripts].find(item => item.src && new URL(item.src).pathname === address.pathname);
    if (!tag) {
      tag = document.createElement('script');
      tag.src = url;
      tag.async = true;
      document.head.appendChild(tag);
    }
    return new Promise((resolve, reject) => {
      if (test()) return resolve();
      tag.addEventListener('load', resolve, { once: true });
      tag.addEventListener('error', reject, { once: true });
    });
  }

  function gsapReady(root) {
    if (window.gsap && window.ScrollTrigger) return Promise.resolve();
    if (!window.__salonZoomGsapPromise) {
      const base = root.dataset.gsapBase;
      window.__salonZoomGsapPromise = script(base + 'gsap.min.js', () => !!window.gsap)
        .then(() => script(base + 'ScrollTrigger.min.js', () => !!window.ScrollTrigger));
    }
    return window.__salonZoomGsapPromise;
  }

  // Converts a landmark in the original 1672 × 941 image to a CSS object-fit:cover point.
  function landmark(image, px, py, box) {
    const fit = Math.max(box.width / image.naturalWidth, box.height / image.naturalHeight);
    return {
      x: px * fit - (image.naturalWidth * fit - box.width) / 2,
      y: py * fit - (image.naturalHeight * fit - box.height) / 2
    };
  }

  function pose(image, px, py, scale, targetX, targetY, box) {
    const point = landmark(image, px, py, box);
    return { scale, x: targetX * box.width - point.x * scale, y: targetY * box.height - point.y * scale };
  }

  function videoReady(video) {
    if (!video || !video.canPlayType('video/mp4')) return Promise.resolve(false);
    if (video.readyState >= 1) return Promise.resolve(true);
    return new Promise(resolve => {
      let done = false;
      const finish = ready => {
        if (done) return;
        done = true;
        clearTimeout(timeout);
        video.removeEventListener('loadedmetadata', loaded);
        video.removeEventListener('error', failed);
        resolve(ready);
      };
      const loaded = () => finish(true);
      const failed = () => finish(false);
      const timeout = setTimeout(() => finish(false), 5000);
      video.addEventListener('loadedmetadata', loaded, { once: true });
      video.addEventListener('error', failed, { once: true });
      video.preload = 'auto';
      video.load();
    });
  }

  function init(root) {
    if (root._salonZoomReady) return;
    root._salonZoomReady = true;
    const stage = root.querySelector('.sz-stage');
    const wide = root.querySelector('.sz-wide');
    const video = root.querySelector('.sz-video');
    const close = root.querySelector('.sz-close');
    const eye = root.querySelector('.sz-eye');
    const handFace = root.querySelector('.sz-hand-face');
    const manicure = root.querySelector('.sz-manicure');
    const handFoot = root.querySelector('.sz-hand-foot');
    const pedicure = root.querySelector('.sz-pedicure');
    const returnShot = root.querySelector('.sz-return');
    const soften = root.querySelector('.sz-soften');
    const veil = root.querySelector('.sz-veil');
    const hint = root.querySelector('.sz-hint');
    const browsCopy = root.querySelector('.sz-copy-brows');
    const manicureCopy = root.querySelector('.sz-copy-manicure');
    const pedicureCopy = root.querySelector('.sz-copy-pedicure');
    const overviewCopy = root.querySelector('.sz-copy-overview');
    const copies = [browsCopy, manicureCopy, pedicureCopy, overviewCopy];
    const images = [wide, close, eye, handFace, manicure, handFoot, pedicure, returnShot];
    const editor = window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode();
    if (editor || !window.matchMedia(motion).matches) {
      root.classList.add('sz-static');
      if (!editor) window.matchMedia(motion).addEventListener('change', () => {
        root._salonZoomReady = false;
        init(root);
      }, { once: true });
      return;
    }

    const loadAndInit = () => {
      // Hidden layers may never be fetched while loading="lazy". Fetch them
      // shortly before the scene enters view, then create the pinned timeline.
      images.forEach(img => { img.loading = 'eager'; });
      Promise.all([
        Promise.all(images.map(img => img.decode ? img.decode().catch(() => {}) : Promise.resolve())),
        videoReady(video)
      ])
      .then(([, useVideo]) => gsapReady(root).then(() => useVideo))
      .then(useVideo => {
        if (!root.isConnected || !window.matchMedia(motion).matches) return;
        const gsap = window.gsap;
        const ScrollTrigger = window.ScrollTrigger;
        gsap.registerPlugin(ScrollTrigger);
        const mm = gsap.matchMedia();
        root._salonZoomMedia = mm;
        mm.add(motion, () => {
          root.classList.remove('sz-static');
          const box = { width: stage.clientWidth, height: stage.clientHeight };
          const wideFace = pose(wide, 1298, 117, useVideo ? 2.15 : 2.1, useVideo ? .73 : .67, useVideo ? .25 : .24, box);
          const closeFace = pose(close, 1124, 251, useVideo ? 1.4 : 1.08, useVideo ? .475 : .67, useVideo ? .33 : .24, box);
          const closeEye = pose(close, 1124, 251, 4.2, .5, .46, box);
          const eyeDetail = pose(eye, 1005, 505, 1.34, .5, .46, box);
          const handFaceStart = pose(handFace, 1300, 220, 1.5, .72, .4, box);
          const manicureStart = pose(manicure, 1000, 640, 1.18, .69, .7, box);
          const manicureEnd = pose(manicure, 1000, 640, 1.4, .68, .67, box);
          const handFootStart = pose(handFoot, 1150, 220, 1.2, .68, .24, box);
          const handFootEnd = pose(handFoot, 1070, 790, 1.4, .68, .83, box);
          const pedicureStart = pose(pedicure, 1120, 790, 1.4, .68, .83, box);
          const pedicureEnd = pose(pedicure, 1120, 790, 1.55, .68, .8, box);
          const returnStart = pose(returnShot, 1150, 830, 1.45, .68, .85, box);

          gsap.set(wide, { autoAlpha: 1, scale: 1, x: 0, y: 0 });
          gsap.set(video, { opacity: 0 });
          if (useVideo) video.pause();
          gsap.set(close, { ...closeFace, autoAlpha: 0 });
          gsap.set(eye, { ...eyeDetail, autoAlpha: 0 });
          gsap.set(handFace, { ...handFaceStart, autoAlpha: 0 });
          gsap.set(manicure, { ...manicureStart, autoAlpha: 0 });
          gsap.set(handFoot, { ...handFootStart, autoAlpha: 0 });
          gsap.set(pedicure, { ...pedicureStart, autoAlpha: 0 });
          gsap.set(returnShot, { ...returnStart, autoAlpha: 0 });
          gsap.set(copies, { autoAlpha: 0 });
          gsap.set(soften, { opacity: 0 });
          gsap.set(veil, { opacity: 0 });
          gsap.set(hint, { opacity: 1 });

          const timeline = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
              trigger: stage,
              start: 'top top',
              end: () => '+=' + Math.round(window.innerHeight * 7),
              pin: stage,
              pinSpacing: true,
              scrub: 0.7,
              anticipatePin: 1,
              invalidateOnRefresh: true
            }
          });
          timeline.to(wide, { ...wideFace, duration: 1.05 }, 0)
            .to(hint, { opacity: 0, duration: .3 }, .2);
          if (useVideo) {
            const playhead = { time: 0 };
            timeline.to(soften, { opacity: .58, duration: .22 }, .84)
              .to(video, { opacity: 1, duration: .24 }, .98)
              .to(wide, { autoAlpha: 0, duration: .24 }, .98)
              .to(soften, { opacity: 0, duration: .28 }, 1.16)
              .to(playhead, { time: 6.1, duration: 1.42, onUpdate: () => {
                const time = Math.min(playhead.time, video.duration - .04);
                if (Math.abs(video.currentTime - time) > .025) video.currentTime = time;
              } }, 1.07);
          }
          timeline.to(soften, { opacity: .65, duration: .22 }, 2.4)
            .to(close, { autoAlpha: 1, duration: .23 }, 2.53)
            .to(useVideo ? video : wide, { [useVideo ? 'opacity' : 'autoAlpha']: 0, duration: .23 }, 2.53)
            .to(soften, { opacity: 0, duration: .26 }, 2.78)
            .to(close, { ...closeEye, duration: 1.55 }, 2.9)
            .to(soften, { opacity: .6, duration: .2 }, 4.07)
            .to(eye, { autoAlpha: 1, duration: .24 }, 4.17)
            .to(close, { autoAlpha: 0, duration: .24 }, 4.17)
            .to(soften, { opacity: 0, duration: .24 }, 4.41)
            .to(eye, { scale: 1.47, x: eyeDetail.x - .065 * box.width, y: eyeDetail.y - .06 * box.height, duration: .5 }, 4.65)
            .to(browsCopy, { autoAlpha: 1, duration: .24 }, 4.71)
            .to(browsCopy, { autoAlpha: 0, duration: .2 }, 5.28)
            .to(soften, { opacity: .88, duration: .25 }, 5.3)
            .to(handFace, { autoAlpha: 1, duration: .22 }, 5.46)
            .to(eye, { autoAlpha: 0, duration: .22 }, 5.46)
            .to(soften, { opacity: 0, duration: .28 }, 5.7)
            .to(handFace, { scale: 1, x: 0, y: 0, duration: 1 }, 5.7)
            .to(soften, { opacity: .62, duration: .18 }, 6.63)
            .to(manicure, { autoAlpha: 1, duration: .22 }, 6.72)
            .to(handFace, { autoAlpha: 0, duration: .22 }, 6.72)
            .to(soften, { opacity: 0, duration: .25 }, 6.98)
            .to(manicure, { ...manicureEnd, duration: .83 }, 6.96)
            .to(manicureCopy, { autoAlpha: 1, duration: .23 }, 7.04)
            .to(manicureCopy, { autoAlpha: 0, duration: .2 }, 7.83)
            .to(soften, { opacity: .72, duration: .2 }, 7.86)
            .to(handFoot, { autoAlpha: 1, duration: .22 }, 7.98)
            .to(manicure, { autoAlpha: 0, duration: .22 }, 7.98)
            .to(soften, { opacity: 0, duration: .25 }, 8.23)
            .to(handFoot, { ...handFootEnd, duration: .8 }, 8.21)
            .to(soften, { opacity: .66, duration: .2 }, 9)
            .to(pedicure, { autoAlpha: 1, duration: .22 }, 9.14)
            .to(handFoot, { autoAlpha: 0, duration: .22 }, 9.14)
            .to(soften, { opacity: 0, duration: .25 }, 9.38)
            .to(pedicure, { ...pedicureEnd, duration: .8 }, 9.36)
            .to(pedicureCopy, { autoAlpha: 1, duration: .23 }, 9.4)
            .to(pedicureCopy, { autoAlpha: 0, duration: .2 }, 10.22)
            .to(soften, { opacity: .78, duration: .25 }, 10.24)
            .to(returnShot, { autoAlpha: 1, duration: .24 }, 10.36)
            .to(pedicure, { autoAlpha: 0, duration: .24 }, 10.36)
            .to(soften, { opacity: 0, duration: .28 }, 10.68)
            .to(returnShot, { scale: 1, x: 0, y: 0, duration: .95 }, 10.68)
            .to(overviewCopy, { autoAlpha: 1, duration: .25 }, 11.04)
            .to({}, { duration: .17 }, 11.63)
            .to(overviewCopy, { autoAlpha: 0, duration: .2 }, 11.65)
            .to(veil, { opacity: 1, duration: .55 }, 11.8)
            .to({}, { duration: .12 }, 12.35);

          return () => {
            timeline.scrollTrigger.kill();
            timeline.kill();
            video.pause();
            gsap.set([...images, video, ...copies, soften, veil, hint], { clearProps: 'all' });
          };
        });
        window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
        ScrollTrigger.refresh();
      })
      .catch(() => root.classList.add('sz-static'));
    };
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        if (!entries[0].isIntersecting) return;
        observer.disconnect();
        loadAndInit();
      }, { rootMargin: '1200px 0px' });
      observer.observe(root);
    } else {
      loadAndInit();
    }
  }

  function scan() { document.querySelectorAll(selector).forEach(init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan, { once: true });
  else scan();
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/html.default', scan);
  }
  document.addEventListener('elementor/frontend/init', scan);
})();
