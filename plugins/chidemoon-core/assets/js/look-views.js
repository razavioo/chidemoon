(() => {
 'use strict';
 const endpoint = window.chLookViewsEndpoint;
 if (!endpoint || !('IntersectionObserver' in window)) return;
 const locale = new Intl.NumberFormat('fa-IR');
 const counts = new Map(), counted = new Set(), pending = new Set(), timers = new Map();
 let visitor;
 try { visitor = localStorage.getItem('ch-look-visitor'); if (!visitor) { visitor = crypto.randomUUID(); localStorage.setItem('ch-look-visitor', visitor); } } catch (_) { visitor = crypto.randomUUID(); }
 const update = data => {
  Object.entries(data.counts || {}).forEach(([id, count]) => counts.set(id, Number(count)));
  document.querySelectorAll('.ch-look-view-count').forEach(badge => {
   const count = counts.get(badge.dataset.id);
   if (count === undefined) return;
   const description = `شمارنده: ${locale.format(100 + count)}؛ شروع از ۱۰۰ + ${locale.format(count)} بازدید ثبت‌شده`;
   badge.querySelector('span').textContent = locale.format(100 + count);
   badge.title = description; badge.setAttribute('aria-label', description);
  });
 };
 const record = async id => {
  if (counted.has(id) || pending.has(id) || document.hidden) return;
  pending.add(id);
  try {
   const response = await fetch(endpoint, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:Number(id),visitor})});
   if (!response.ok) return;
   update(await response.json()); counted.add(id);
  } catch (_) {} finally { pending.delete(id); }
 };
 const observer = new IntersectionObserver(entries => entries.forEach(entry => {
  const target = entry.target, id = target.dataset.viewLook;
  clearTimeout(timers.get(target));
  if (!entry.isIntersecting || entry.intersectionRatio < .5 || document.hidden) return;
  timers.set(target, setTimeout(() => { const image=target.querySelector('img'); if (image?.complete && image.naturalWidth && target.getBoundingClientRect().height && !target.closest('[hidden]')) record(id); }, 1000));
 }), {threshold:[0,.5]});
 const init = () => {
  const ids = new Set();
  document.querySelectorAll('[data-look-id]').forEach(card => {
   if (card.closest('.ch-home-look-mosaic')) return;
   const id = card.dataset.lookId;
   const canvas = card.querySelector('.ch-scene-canvas, .chidemoon-shop-the-look__canvas, .chidemoon-shop-the-look');
   if (!canvas || canvas.dataset.viewLook) return;
   canvas.dataset.viewLook = id;
   const badge = document.createElement('span'); badge.className = 'ch-look-view-count'; badge.dataset.id = id;
   badge.title = 'شمارنده از ۱۰۰ آغاز شده؛ بازدیدهای ثبت‌شده به آن اضافه می‌شوند';
   badge.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>۱۰۰</span>';
   badge.setAttribute('aria-label', badge.title); canvas.append(badge); observer.observe(canvas); ids.add(id);
  });
  if (ids.size) update({});
  if (ids.size) fetch(`${endpoint}?ids=${[...ids].join(',')}`, {cache:'no-store'}).then(r => r.ok ? r.json() : null).then(data => {if(data) update(data);}).catch(() => {});
 };
 document.addEventListener('visibilitychange', () => {
  timers.forEach(clearTimeout); timers.clear();
  if (!document.hidden) document.querySelectorAll('[data-view-look]').forEach(el => {observer.unobserve(el);observer.observe(el);});
 });
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init); else init();
 new MutationObserver(init).observe(document.body,{childList:true,subtree:true});
})();
