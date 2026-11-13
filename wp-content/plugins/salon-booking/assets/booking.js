(function () {
  'use strict';
  const cfg = window.SBK_CONFIG;
  if (!cfg) return;
  const request = async (path, options) => {
    const response = await fetch(cfg.api + path, options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Не удалось выполнить запрос.');
    return data;
  };
  const option = (value, label) => new Option(label, String(value));
  document.querySelectorAll('.sbk-form').forEach(async (form) => {
    const service = form.elements.service_id;
    const master = form.elements.master_id;
    const date = form.elements.date;
    const time = form.elements.time;
    const message = form.querySelector('.sbk-message');
    let slotsRequest = 0;
    const openDatePicker = () => {
      if (date.disabled || typeof date.showPicker !== 'function') return;
      try { date.showPicker(); } catch (_) { date.focus(); }
    };
    date.addEventListener('click', openDatePicker);
    date.addEventListener('keydown', (event) => {
      if ((event.key === 'Enter' || event.key === ' ') && typeof date.showPicker === 'function' && !date.disabled) {
        event.preventDefault();
        openDatePicker();
      }
    });
    const reset = (select, label) => { select.replaceChildren(option('', label)); select.disabled = true; };
    const tell = (value, bad) => { message.textContent = value; message.classList.toggle('sbk-error', !!bad); };
    let catalog;
    try {
      catalog = await request('catalog');
      catalog.services.forEach((s) => service.add(option(s.id, `${s.name} — ${s.duration} мин., ${Number(s.price) > 0 ? 'цена ' + s.price : 'цена уточняется'}`)));
      date.min = catalog.today;
      const max = new Date(catalog.today + 'T12:00:00');
      max.setDate(max.getDate() + catalog.horizon);
      date.max = [max.getFullYear(), String(max.getMonth() + 1).padStart(2, '0'), String(max.getDate()).padStart(2, '0')].join('-');
    } catch (error) { tell(error.message, true); return; }
    service.addEventListener('change', () => {
      slotsRequest++;
      reset(master, 'Выберите мастера'); reset(time, 'Выберите время'); date.value = ''; date.disabled = true;
      if (!service.value) return;
      const ids = new Set(catalog.relations.filter((r) => String(r.service_id) === service.value).map((r) => String(r.master_id)));
      catalog.masters.filter((m) => ids.has(String(m.id))).forEach((m) => master.add(option(m.id, m.name)));
      master.disabled = false;
    });
    master.addEventListener('change', () => { slotsRequest++; date.value = ''; date.disabled = !master.value; reset(time, 'Выберите время'); });
    date.addEventListener('change', async () => {
      const requestId = ++slotsRequest;
      reset(time, 'Выберите время');
      if (!date.value || !master.value) return;
      try {
        const data = await request(`slots?service_id=${encodeURIComponent(service.value)}&master_id=${encodeURIComponent(master.value)}&date=${encodeURIComponent(date.value)}`);
        if (requestId !== slotsRequest) return;
        const slots = data.slots || data.times.map((t) => ({ time: t, status: 'available' }));
        slots.forEach((slot) => {
          const booked = slot.status === 'booked';
          const item = option(slot.time, booked ? `${slot.time} — Забронировано` : slot.time);
          item.disabled = booked;
          if (booked) item.className = 'sbk-booked-option';
          time.add(item);
        });
        time.disabled = !slots.length;
        const freeCount = slots.filter((slot) => slot.status === 'available').length;
        tell(freeCount ? (slots.length > freeCount ? 'Занятое время отмечено как «Забронировано».' : '') : (slots.length ? 'Все варианты на эту дату забронированы.' : 'На эту дату свободного времени нет.'), false);
      } catch (error) { if (requestId === slotsRequest) tell(error.message, true); }
    });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const button = form.querySelector('.sbk-submit');
      button.disabled = true; tell('Отправляем запись…', false);
      const payload = Object.fromEntries(new FormData(form).entries());
      payload.issued = cfg.issued; payload.proof = cfg.proof;
      try {
        const result = await request('book', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        form.reset(); reset(master, 'Выберите мастера'); reset(time, 'Выберите время'); date.disabled = true;
        tell(`${result.message} Номер записи: ${result.number}.`, false);
      } catch (error) {
        tell(error.message, true);
        if (error.message.includes('недоступно')) date.dispatchEvent(new Event('change'));
      } finally { button.disabled = false; }
    });
  });
}());
