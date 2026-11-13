(() => {
  'use strict';

  const gsap = window.gsap;
  const ScrollTrigger = window.ScrollTrigger;
  if (!gsap || !ScrollTrigger) return;
  gsap.registerPlugin(ScrollTrigger);

  // objectPosition is the camera target inside each actual photograph.
  // Frame 02 moves from eyebrow to lashes without replacing that image.
  const shots = [
    { desktop: [['54% 48%', 1.02], ['63% 48%', 1.16]], mobile: [['65% 47%', 1.02], ['69% 45%', 1.12]] },
    { desktop: [['58% 48%', 1.06], ['68% 47%', 1.18]], mobile: [['66% 47%', 1.07], ['73% 47%', 1.17]] },
    { desktop: [['66% 37%', 1.16], ['77% 54%', 1.34]], mobile: [['74% 34%', 1.08], ['83% 56%', 1.2]] },
    { desktop: [['76% 42%', 1.09], ['61% 58%', 1.22]], mobile: [['77% 43%', 1.05], ['67% 61%', 1.15]] },
    { desktop: [['46% 38%', 1.02], ['70% 63%', 1.16]], mobile: [['38% 35%', 1.04], ['69% 64%', 1.15]] },
    { desktop: [['69% 50%', 1.04], ['70% 55%', 1.18]], mobile: [['68% 49%', 1.04], ['72% 58%', 1.15]] },
    { desktop: [['55% 34%', 1.04], ['63% 74%', 1.17]], mobile: [['56% 28%', 1.03], ['62% 79%', 1.14]] },
    { desktop: [['65% 55%', 1.07], ['68% 56%', 1.18]], mobile: [['65% 52%', 1.04], ['68% 58%', 1.15]] },
    { desktop: [['74% 48%', 1.19], ['50% 50%', 1]], mobile: [['78% 49%', 1.2], ['73% 50%', 1]] },
  ];

  document.querySelectorAll('[data-beauty-zoom-story]').forEach((root) => {
    const fitViewport = () => {
      root.style.left = '0';
      root.style.marginLeft = '0';
      root.style.width = `${document.documentElement.clientWidth}px`;
      root.style.marginLeft = `${-root.getBoundingClientRect().left}px`;
    };
    fitViewport();
    window.addEventListener('resize', fitViewport);

    const stage = root.querySelector('.bzs-stage');
    const frames = [...root.querySelectorAll('.bzs-frame')];
    const copies = [...root.querySelectorAll('.bzs-copy')];
    const progress = root.querySelector('.bzs-progress span');
    const cta = root.querySelector('.bzs-cta');
    const editor = window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode();

    if (editor || frames.length !== shots.length) {
      root.classList.add('bzs-static');
      return;
    }

    const mm = gsap.matchMedia();
    mm.add({
      desktop: '(min-width: 701px) and (prefers-reduced-motion: no-preference)',
      mobile: '(max-width: 700px) and (prefers-reduced-motion: no-preference)',
      reduced: '(prefers-reduced-motion: reduce)',
    }, (context) => {
      if (context.conditions.reduced) {
        root.classList.add('bzs-static');
        cta.removeAttribute('tabindex');
        return () => root.classList.remove('bzs-static');
      }

      const mobile = context.conditions.mobile;
      root.classList.remove('bzs-static');
      gsap.set(frames, { autoAlpha: 0 });
      gsap.set(frames[0], { autoAlpha: 1 });
      gsap.set(copies, { autoAlpha: 0 });
      gsap.set(copies[0], { autoAlpha: 1 });
      cta.tabIndex = -1;

      const timeline = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
          trigger: root,
          start: 'top top',
          end: () => '+=' + Math.round(window.innerHeight * (mobile ? 7.5 : 7.2)),
          pin: stage,
          pinSpacing: true,
          scrub: 0.6,
          anticipatePin: 1,
          invalidateOnRefresh: true,
          onUpdate: (self) => {
            progress.style.transform = `scaleX(${self.progress})`;
            cta.tabIndex = self.progress >= 0.92 ? 0 : -1;
            root.classList.toggle('bzs-at-end', self.progress >= 0.9);
          },
        },
      });

      frames.forEach((frame, index) => {
        const image = frame.querySelector('img');
        const [from, to] = mobile ? shots[index].mobile : shots[index].desktop;
        gsap.set(image, { objectPosition: from[0], scale: from[1] });
        timeline.to(image, { objectPosition: to[0], scale: to[1], duration: 1 }, index);
        if (index > 0) {
          timeline.to(frame, { autoAlpha: 1, duration: 0.22 }, index - 0.22);
          timeline.set(frames[index - 1], { autoAlpha: 0 }, index + 0.02);
        }
      });

      timeline.to(copies[0], { autoAlpha: 0, duration: 0.2 }, 0.62);
      [
        [1, 2.08, 2.72],
        [2, 5.08, 5.7],
        [3, 7.08, 7.7],
      ].forEach(([copyIndex, show, hide]) => {
        timeline.to(copies[copyIndex], { autoAlpha: 1, duration: 0.18 }, show);
        timeline.to(copies[copyIndex], { autoAlpha: 0, duration: 0.18 }, hide);
      });
      timeline.to(copies[4], { autoAlpha: 1, duration: 0.34 }, 8.25);
      timeline.to({}, { duration: 0.42 }, 8.58);

      return () => {
        timeline.scrollTrigger.kill();
        timeline.kill();
        gsap.set([...frames, ...copies, ...frames.map((frame) => frame.querySelector('img'))], { clearProps: 'all' });
        progress.style.transform = '';
        root.classList.remove('bzs-at-end');
        cta.removeAttribute('tabindex');
      };
    });

    window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
  });
})();
