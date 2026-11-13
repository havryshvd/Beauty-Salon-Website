(() => {
  'use strict';

  const root = document.querySelector('[data-hero-film]');
  if (!root) return;

  const stage = root.querySelector('.hero');
  const intro = root.querySelector('.hero-intro');
  const shade = root.querySelector('.hero-film-shade');
  const cue = root.querySelector('.hero-scroll');
  const continueCue = root.querySelector('.hero-continue');
  const bookingWindow = root.querySelector('.hero-booking-window');
  const cards = [root.querySelector('.hero-finale-brows'), root.querySelector('.hero-finale-manicure'), root.querySelector('.hero-finale-pedicure')];
  const stills = [root.querySelector('.hero-freeze-eye'), root.querySelector('.hero-freeze-hands'), root.querySelector('.hero-freeze-pedicure'), root.querySelector('.hero-freeze-room')];
  const films = [
    { forward: root.querySelector('.hero-video-forward'), reverse: root.querySelector('.hero-video-reverse'), end: 5, focusStart: 78, focusEnd: 52, layer: 'eye' },
    { forward: root.querySelector('.hero-video-hands-forward'), reverse: root.querySelector('.hero-video-hands-reverse'), end: 3.92, focusStart: 52, focusEnd: 58, layer: 'hands' },
    { forward: root.querySelector('.hero-video-pedicure-forward'), reverse: root.querySelector('.hero-video-pedicure-reverse'), end: 3.92, focusStart: 58, focusEnd: 61, layer: 'pedicure' },
    { forward: root.querySelector('.hero-video-room-forward'), reverse: root.querySelector('.hero-video-room-reverse'), end: 3.92, focusStart: 61, focusEnd: 72, layer: 'room' }
  ];
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (reducedMotion.matches) {
    cards.forEach(card => {
      card.setAttribute('aria-hidden', 'false');
      card.querySelector('a').tabIndex = 0;
    });
    bookingWindow.inert = false;
    bookingWindow.setAttribute('aria-hidden', 'false');
    return;
  }
  if (!window.gsap || !window.ScrollTrigger || !films[0].forward.canPlayType('video/mp4')) return;

  const gsap = window.gsap;
  const ScrollTrigger = window.ScrollTrigger;
  const sceneDuration = 1.5;
  const sceneSpeed = (film) => film.end / sceneDuration;
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const mobile = () => window.matchMedia('(max-width: 760px)').matches;
  gsap.registerPlugin(ScrollTrigger);

  for (const film of films) {
    for (const video of [film.forward, film.reverse]) {
      video.muted = true;
      video.playsInline = true;
      video.playbackRate = sceneSpeed(film);
    }
  }

  let phase = 'intro';
  let activeFilm = 0;
  let activeVideo = films[0].forward;
  let visualTime = 0;
  let generation = 0;
  let shownCard = -1;
  let introShown = true;
  let holdAt = 0;
  let lastWheelAt = 0;
  let trigger;

  const revealVideo = () => root.classList.add('hero-film-ready');
  if (films[0].forward.readyState >= 2) revealVideo();
  else films[0].forward.addEventListener('loadeddata', revealVideo, { once: true });

  function prime(index) {
    const film = films[index];
    if (film.primed) return;
    film.primed = true;
    for (const video of [film.forward, film.reverse]) {
      video.preload = 'auto';
      video.load();
    }
  }
  function setLayer(index, direction) {
    root.dataset.filmLayer = films[index].layer + '-' + direction;
  }

  function showIntro(visible) {
    if (introShown === visible) return;
    introShown = visible;
    gsap.to(intro, { autoAlpha: visible ? 1 : 0, y: visible ? 0 : -28, duration: 0.55, ease: 'power2.inOut', overwrite: true });
    gsap.to(cue, { autoAlpha: visible ? 1 : 0, duration: 0.3, overwrite: true });
  }

  function showCard(index) {
    if (shownCard === index) return;
    shownCard = index;
    cards.forEach((card, cardIndex) => {
      const visible = cardIndex === index;
      card.setAttribute('aria-hidden', String(!visible));
      card.querySelector('a').tabIndex = visible ? 0 : -1;
      gsap.to(card, { autoAlpha: visible ? 1 : 0, y: visible ? 0 : 25, duration: visible ? 0.75 : 0.3, ease: 'power2.out', overwrite: true });
    });
    const bookingVisible = index === films.length - 1;
    bookingWindow.inert = !bookingVisible;
    bookingWindow.setAttribute('aria-hidden', String(!bookingVisible));
    if (bookingVisible) {
      gsap.fromTo(bookingWindow,
        { autoAlpha: 0, x: mobile() ? 0 : -42, y: mobile() ? 42 : 0 },
        { autoAlpha: 1, x: 0, y: 0, duration: 0.85, delay: 0.18, ease: 'power3.out', overwrite: true });
    } else {
      if (bookingWindow.contains(document.activeElement)) document.activeElement.blur();
      gsap.to(bookingWindow, { autoAlpha: 0, x: mobile() ? 0 : -42, y: mobile() ? 42 : 0, duration: 0.28, overwrite: true });
    }
    gsap.to(continueCue, { autoAlpha: index >= 0 && index < films.length - 1 ? 1 : 0, duration: 0.35, overwrite: true });
  }

  function focusAt(index, time) {
    if (!mobile()) return;
    const film = films[index];
    const percent = film.focusStart + (film.focusEnd - film.focusStart) * clamp(time / film.end, 0, 1);
    root.style.setProperty('--hero-focus-x', percent + '%');
  }

  function moveFocus(index, from, to) {
    if (!mobile()) return;
    const film = films[index];
    gsap.killTweensOf(root);
    focusAt(index, from);
    const percent = film.focusStart + (film.focusEnd - film.focusStart) * to / film.end;
    gsap.to(root, { '--hero-focus-x': percent + '%', duration: Math.abs(to - from) / sceneSpeed(film), ease: 'none' });
  }

  function shownTime() {
    const film = films[activeFilm];
    if (activeVideo === film.reverse) return clamp(film.reverse.duration - activeVideo.currentTime, 0, film.end);
    return clamp(activeVideo.currentTime, 0, film.end);
  }

  function pauseAll() {
    films.forEach(film => { film.forward.pause(); film.reverse.pause(); });
  }

  function showFreeze(index) {
    root.dataset.filmStill = films[index].layer;
    stills.forEach((still, stillIndex) => {
      gsap.killTweensOf(still);
      gsap.set(still, { autoAlpha: stillIndex === index ? 1 : 0 });
    });
  }

  function hideFreeze() {
    stills.forEach(still => gsap.to(still, { autoAlpha: 0, duration: 0.16, overwrite: true }));
  }

  function resetIntro() {
    if (phase === 'intro') return;
    generation++;
    pauseAll();
    phase = 'intro';
    activeFilm = 0;
    activeVideo = films[0].forward;
    films[0].forward.currentTime = 0;
    visualTime = 0;
    setLayer(0, 'forward');
    delete root.dataset.filmStill;
    delete root.dataset.hold;
    hideFreeze();
    gsap.killTweensOf(root);
    focusAt(0, 0);
    showCard(-1);
    showIntro(true);
    gsap.to(shade, { opacity: 1, duration: 0.45, overwrite: true });
  }

  function hold(index) {
    if (phase === 'hold-' + index) return;
    generation++;
    showFreeze(index);
    pauseAll();
    const film = films[index];
    phase = 'hold-' + index;
    root.dataset.hold = String(index);
    activeFilm = index;
    activeVideo = film.forward;
    visualTime = film.end;
    setLayer(index, 'forward');
    gsap.killTweensOf(root);
    focusAt(index, film.end);
    showIntro(false);
    showCard(index);
    gsap.to(shade, { opacity: 0.72, duration: 0.65, overwrite: true });
    holdAt = performance.now();
  }

  function metadata(video) {
    if (video.readyState >= 1) return Promise.resolve();
    return new Promise((resolve, reject) => {
      video.addEventListener('loadedmetadata', resolve, { once: true });
      video.addEventListener('error', reject, { once: true });
      video.load();
    });
  }

  function seek(video, time) {
    if (Math.abs(video.currentTime - time) < 0.04 && video.readyState >= 2) return Promise.resolve();
    return new Promise(resolve => {
      const done = () => { clearTimeout(timeout); video.removeEventListener('seeked', done); resolve(); };
      const timeout = setTimeout(done, 1800);
      video.addEventListener('seeked', done, { once: true });
      video.currentTime = time;
    });
  }

  function decodedFrame(video) {
    return new Promise(resolve => {
      let settled = false;
      const done = () => {
        if (settled) return;
        settled = true;
        clearTimeout(timeout);
        resolve();
      };
      const timeout = setTimeout(done, 500);
      if (typeof video.requestVideoFrameCallback === 'function') video.requestVideoFrameCallback(done);
      else if (video.readyState >= 2) requestAnimationFrame(() => requestAnimationFrame(done));
      else video.addEventListener('loadeddata', done, { once: true });
    });
  }

  async function play(index, direction) {
    const nextPhase = 'play-' + index + '-' + direction;
    if (phase === nextPhase) return;
    const film = films[index];
    const time = activeFilm === index ? shownTime() : (direction === 'forward' ? 0 : film.end);
    const outgoing = activeVideo;
    const incoming = film[direction];
    const token = ++generation;
    phase = nextPhase;
    outgoing.pause();
    prime(index);
    if (direction === 'forward' && index + 1 < films.length) prime(index + 1);

    try {
      await metadata(incoming);
      if (token !== generation) return;
      const target = direction === 'forward'
        ? clamp(time, 0, Math.min(film.end, incoming.duration - 0.04))
        : clamp(incoming.duration - time, 0, incoming.duration - 0.04);
      await seek(incoming, target);
      if (token !== generation) return;
      incoming.playbackRate = sceneSpeed(film);
      const playback = incoming.play();
      if (playback && typeof playback.then === 'function') await playback;
      if (token !== generation) { incoming.pause(); return; }
      activeFilm = index;
      activeVideo = incoming;
      await decodedFrame(incoming);
      if (token !== generation) { incoming.pause(); return; }

      visualTime = shownTime();
      setLayer(index, direction);
      delete root.dataset.hold;
      hideFreeze();
      showCard(-1);
      if (direction === 'forward' || index > 0 || visualTime > 0.7) showIntro(false);
      moveFocus(index, visualTime, direction === 'forward' ? film.end : 0);
      gsap.to(shade, { opacity: 0.38, duration: 0.5, overwrite: true });
    } catch (_) {
      if (token !== generation) return;
      if (direction === 'forward') hold(index);
      else if (index === 0) resetIntro();
      else hold(index - 1);
    }
  }

  films.forEach((film, index) => {
    film.forward.addEventListener('timeupdate', () => {
      if (phase !== 'play-' + index + '-forward' || activeVideo !== film.forward) return;
      visualTime = shownTime();
      if (visualTime >= film.end - 0.05) hold(index);
    });
    film.reverse.addEventListener('timeupdate', () => {
      if (phase !== 'play-' + index + '-reverse' || activeVideo !== film.reverse) return;
      visualTime = shownTime();
      if (index === 0 && visualTime < 0.7) showIntro(true);
      if (visualTime <= 0.06) index === 0 ? resetIntro() : hold(index - 1);
    });
    film.forward.addEventListener('ended', () => { if (phase === 'play-' + index + '-forward') hold(index); });
    film.reverse.addEventListener('ended', () => { if (phase === 'play-' + index + '-reverse') index === 0 ? resetIntro() : hold(index - 1); });
  });

  function transitionFromHold(direction) {
    const bounds = stage.getBoundingClientRect();
    if (!trigger || (!trigger.isActive && (bounds.bottom <= 0 || bounds.top >= window.innerHeight))) return;
    const match = /^hold-(\d+)$/.exec(phase);
    if (!match) return;
    const index = Number(match[1]);
    if (direction > 0 && index < films.length - 1) play(index + 1, 'forward');
    else if (direction > 0) document.getElementById('services')?.scrollIntoView({ behavior: 'smooth' });
    else if (direction < 0) play(index, 'reverse');
  }

  window.addEventListener('wheel', event => {
    if (bookingWindow.contains(event.target)) return;
    const now = performance.now();
    const gap = now - lastWheelAt;
    lastWheelAt = now;
    if (now - holdAt < 250 || gap < 180) return;
    transitionFromHold(Math.sign(event.deltaY));
  }, { passive: true });

  let touchStartY = 0;
  let touchPhase = '';
  let touchEligible = false;
  let touchHandled = false;
  window.addEventListener('touchstart', event => {
    touchStartY = event.touches[0]?.clientY || 0;
    touchPhase = phase;
    const bounds = stage.getBoundingClientRect();
    touchEligible = stage.contains(event.target) && !bookingWindow.contains(event.target) && bounds.bottom > 0 && bounds.top < window.innerHeight;
    touchHandled = false;
  }, { passive: true });
  window.addEventListener('touchmove', event => {
    if (!touchEligible || !event.touches.length) return;
    event.preventDefault();
    if (touchHandled || phase !== touchPhase) return;
    const movement = touchStartY - event.touches[0].clientY;
    if (Math.abs(movement) < 18) return;
    touchHandled = true;
    const direction = Math.sign(movement);
    if (phase === 'intro' && direction > 0) play(0, 'forward');
    else if (phase.startsWith('hold-') && performance.now() - holdAt > 150) {
      transitionFromHold(direction);
    } else {
      const match = /^play-(\d+)-(forward|reverse)$/.exec(phase);
      if (match && direction < 0 && match[2] === 'forward') play(Number(match[1]), 'reverse');
      else if (match && direction > 0 && match[2] === 'reverse') play(Number(match[1]), 'forward');
    }
  }, { passive: false });

  window.addEventListener('keydown', event => {
    if (performance.now() - holdAt < 150 || event.target.closest('input,textarea,select,.hero-booking-window')) return;
    if (['ArrowDown', 'PageDown', ' '].includes(event.key)) transitionFromHold(1);
    if (['ArrowUp', 'PageUp'].includes(event.key)) transitionFromHold(-1);
  });

  trigger = ScrollTrigger.create({
    trigger: root,
    start: 'top top',
    end: () => '+=' + Math.round(window.innerHeight * (window.innerWidth <= 760 ? 4.4 : 4.7)),
    pin: stage,
    anticipatePin: 1,
    invalidateOnRefresh: true,
    onUpdate(self) {
      if (phase === 'intro' && self.direction > 0 && self.progress > 0.018) { play(0, 'forward'); return; }
      const match = /^play-(\d)-(forward|reverse)$/.exec(phase);
      if (!match) return;
      const index = Number(match[1]);
      if (self.direction < 0 && match[2] === 'forward') play(index, 'reverse');
      else if (self.direction > 0 && match[2] === 'reverse') play(index, 'forward');
    }
  });
  ScrollTrigger.refresh();
})();
