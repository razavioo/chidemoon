/* Three-level navigation: links navigate; adjacent controls expand groups. */
(() => {
 'use strict';
 const illustrations = {
  appliance: '<rect x="22" y="14" width="52" height="68" rx="9"/><circle cx="48" cy="51" r="17"/><path d="M32 25h8m15 0h8M32 82v4m32-4v4"/>',
  blender: '<path d="M31 17h34l-5 39H36ZM37 56h22l7 24H30ZM65 24h9v24H62M42 30h10m-11 9h10"/><circle cx="48" cy="70" r="3"/>',
  coffee: '<path d="M27 17h42v21H27ZM31 38v29h34V38M23 78h50M38 51h17v15H38Zm17 2h7v10h-7M39 25h18"/>',
  kettle: '<path d="M32 35h28l8 40H24ZM38 35V24h16v11M62 43h13L66 60M28 42H17v23h9M36 75v5m21-5v5"/>',
  heater: '<rect x="19" y="27" width="58" height="49" rx="7"/><path d="M29 38v26m12-26v26m13-26v26m12-26v26M29 76v7m38-7v7M35 18v-6m13 6v-6m13 6v-6"/>',
  plate: '<circle cx="51" cy="49" r="30"/><circle cx="51" cy="49" r="20"/><path d="M11 19v22m-5-22v15q0 7 10 7V19m-5 22v39M89 19v61m0-61q-14 13 0 29"/>',
  bowl: '<path d="M15 43h66q-4 30-33 30T15 43ZM35 74v6h26v-6M22 36h52"/>',
  tray: '<rect x="18" y="31" width="60" height="38" rx="8"/><path d="M18 43H9v14h9m60-14h9v14h-9M27 40h42v20H27"/>',
  cutlery: '<path d="M22 16v24m-8-24v17q0 9 16 9V16M22 42v38M54 16q-18 20-5 32h6v32m0-64v32M77 16v64m0-64q-15 10 0 30"/>',
  cup: '<path d="M23 35h42v29q0 13-21 13T23 64ZM65 40h12q13 22-12 22M17 82h57M33 25v-9m13 9V12m13 13v-9"/>',
  jar: '<rect x="25" y="29" width="46" height="51" rx="8"/><path d="M29 29V18h38v11M25 42h46M36 54h24v15H36M36 18v11m12-11v11m12-11v11"/>',
  storage: '<rect x="18" y="20" width="60" height="61" rx="5"/><path d="M18 49h60M47 20v61M29 32h8m20 0h9M29 62h8m20 0h9"/>',
  bed: '<path d="M14 77V32q0-8 8-8h52q8 0 8 8v45M14 49h68M14 66h68M24 49V35h19v14m10 0V35h19v14M20 77v7m56-7v7"/>',
  linen: '<path d="M22 22h52v56H22ZM22 37h52M33 37v41m30-41v41M22 65h52M31 22v15m34-15v15"/>',
  rug: '<path d="M23 20h50v58H23ZM31 28h34v42H31ZM48 36l13 13-13 13-13-13ZM23 13v7m12-7v7m13-7v7m13-7v7m12-7v7M23 78v7m12-7v7m13-7v7m13-7v7m12-7v7"/>',
 };
 const iconFor = link => {
  const slug = new URL(link.href).pathname.split('/').filter(Boolean).pop();
  const groups = {
   appliance: ['home-appliance'], blender: ['chopper-food-processor','food-grinder'],
   coffee: ['espresso-maker','coffee-maker'], kettle: ['drink-maker','tea-maker'],
   heater: ['heater','gas-heater','electric-room-heaters','electric-heater'],
   plate: ['kitchen-appliances','dinnerware-sets','porcelain-tableware','arcopal-tableware','crystalware','ceramicware','melamine-tableware'],
   bowl: ['serving','bowl','condiment-and-appetizer-dishes'], tray: ['tray'], cutlery: ['spoon-fork-knife'],
   cup: ['tea-sets','mug-jug'], jar: ['canisters','sauce-dish'], storage: ['kitchen-organizers','drawer-cabinet-organizer'],
   bed: ['bedroom-essentials','bedding'], linen: ['bedspread'], rug: ['carpets','rugs-floor-mats','machine-made-carpet'],
  };
  const key = Object.keys(groups).find(key => groups[key].includes(slug)) || 'storage';
  const icon = document.createElement('span'); icon.className = `ch-category-icon ch-category-icon-${key}`; icon.setAttribute('aria-hidden','true');
  icon.innerHTML = `<svg viewBox="0 0 96 96" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="48" cy="48" r="42" fill="#edf2e8" stroke="none"/>${illustrations[key]}</svg>`;
  return icon;
 };
 const initMenu = () => {
  document.querySelectorAll('.ch-header .e-n-menu-item:has(.ch-category-tree)').forEach(item => {
   if (item.dataset.chMenuReady) return;
   item.dataset.chMenuReady = 'true'; item.classList.add('ch-category-menu');
   const title = item.querySelector('.e-n-menu-title');
   const toggle = title.querySelector('.e-n-menu-dropdown-icon');
   const content = item.querySelector('.e-n-menu-content');
   const setOpen = open => {
    item.classList.toggle('ch-category-menu-open',open);
    item.classList.toggle('ch-mobile-expanded',open);
    toggle.setAttribute('aria-expanded',String(open));
    content.inert = !open;
    item.closest('.e-n-menu-wrapper').classList.toggle('ch-category-view-open',open);
   };
   setOpen(false);
   title.addEventListener('click',event => {
    event.preventDefault(); event.stopImmediatePropagation();
    setOpen(!item.classList.contains('ch-category-menu-open'));
   },true);
   ['mouseover','mouseenter','mouseout','mouseleave','pointerover','pointerenter','pointerout','pointerleave'].forEach(type => {
    item.addEventListener(type,event => event.stopImmediatePropagation());
   });
   document.addEventListener('click',event => { if (!event.composedPath().includes(item)) setOpen(false); });
   item.addEventListener('keydown',event => {
    if (event.key === 'Escape' && item.classList.contains('ch-category-menu-open')) {
     event.preventDefault(); event.stopImmediatePropagation(); setOpen(false); title.querySelector('a').focus();
    }
   },true);
   matchMedia('(max-width: 1024px)').addEventListener('change',() => setOpen(false));
  });
 };
 const init = () => document.querySelectorAll('.ch-category-tree').forEach((tree, treeIndex) => {
  if (tree.dataset.chTreeReady) return;
  tree.dataset.chTreeReady = 'true';
  tree.querySelectorAll('li > a').forEach(link => link.prepend(iconFor(link)));
  // A compact level-by-level view fits the mobile drawer without nested scrolling.
  if (tree.closest('.ch-header')) {
   const mobile = document.createElement('div'); mobile.className = 'ch-category-mobile'; tree.append(mobile);
   const rootsList = tree.querySelector(':scope > ul');
   const renderLevel = (list, parent = null) => {
    mobile.replaceChildren();
    if (parent) {
     const back = document.createElement('button'); back.type = 'button'; back.className = 'ch-category-back'; back.textContent = 'بازگشت به دسته‌ها';
     back.addEventListener('click',() => {
      const upper = parent.parentElement.closest('li');
      renderLevel(parent.parentElement,upper && rootsList.contains(upper) ? upper : null); mobile.querySelector('button, a')?.focus();
     }); mobile.append(back);
     const source = parent.querySelector(':scope > a');
     const all = document.createElement('a'); all.href = source.href; all.className = 'ch-category-view-all'; all.textContent = `همهٔ محصولات ${source.textContent.trim()}`; mobile.append(all);
    }
    const grid = document.createElement('div'); grid.className = 'ch-category-mobile-grid';
    [...list.children].filter(item => item.matches('li')).forEach(item => {
     const source = item.querySelector(':scope > a'); if (!source) return;
     const children = item.querySelector(':scope > ul');
     const entry = document.createElement(children ? 'button' : 'a');
     if (children) {
      entry.type = 'button'; entry.setAttribute('aria-label',`زیرگروه‌های ${source.textContent.trim()}`);
      entry.addEventListener('click',() => { renderLevel(children,item); mobile.querySelector('button, a')?.focus(); });
     } else { entry.href = source.href; }
     entry.className = 'ch-category-mobile-entry';
     const label = document.createElement('span'); label.textContent = source.textContent.trim(); entry.append(iconFor(source),label); grid.append(entry);
    });
    mobile.append(grid);
   };
   renderLevel(rootsList);
  }
  const narrow = matchMedia('(max-width: 1099px)');
  if (!tree.classList.contains('ch-category-browser')) {
   tree.classList.add('ch-category-browser');
   const panel = document.createElement('div'); panel.className = 'ch-category-detail'; tree.append(panel);
   const roots = [...tree.querySelectorAll(':scope > ul > li')];
   const content = item => {
    const fragment = document.createDocumentFragment();
    const link = item.querySelector(':scope > a').cloneNode(true); link.hidden = false;
    link.className = 'ch-category-view-all'; link.textContent = `همه محصولات ${link.textContent}`; fragment.append(link);
    const children = item.querySelector(':scope > ul'); if (children) fragment.append(children.cloneNode(true));
    return fragment;
   };
   const show = item => { roots.forEach(root => root.classList.toggle('is-active',root === item)); panel.replaceChildren(content(item)); };
   roots.forEach((item,index) => {
    const link = item.querySelector(':scope > a');
    const button = document.createElement('button'); button.type = 'button'; button.className = 'ch-category-toggle';
    const label = document.createElement('span'); label.textContent = link.textContent;
    button.append(link.querySelector('.ch-category-icon').cloneNode(true),label);
    link.hidden = true; link.after(button);
    const section = document.createElement('div'); section.className = 'ch-category-accordion'; section.id = `ch-category-${treeIndex}-${index}`; section.append(content(item)); item.append(section);
    button.setAttribute('aria-expanded','false'); button.setAttribute('aria-controls',section.id);
    button.addEventListener('click', () => { const open = !item.classList.contains('is-open'); roots.forEach(root=>root.classList.remove('is-open')); item.classList.toggle('is-open',open); show(item); });
    button.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse' && !narrow.matches) show(item); });
    button.addEventListener('focus', () => { if (!narrow.matches) show(item); });
    button.addEventListener('keydown', event => { if (event.key === 'ArrowLeft' && !narrow.matches) { event.preventDefault(); panel.querySelector('a')?.focus(); } });
   });
   panel.addEventListener('keydown', event => { if (event.key === 'Escape' || event.key === 'ArrowRight') { event.preventDefault(); tree.querySelector('li.is-active > .ch-category-toggle')?.focus(); } });
   if (roots.length) show(roots.find(item => item.querySelector(':scope > a').href.includes('/home-appliance/')) || roots[0]);
  }
  let sequence = 0;
  const enhance = () => {
   tree.querySelectorAll('.ch-category-accordion > ul > li, .ch-category-detail > ul > li').forEach(item => {
    const children = item.querySelector(':scope > ul');
    if (!children || item.querySelector(':scope > .ch-subcategory-toggle')) return;
    children.id = `ch-subcategories-${treeIndex}-${++sequence}`;
    const link = item.querySelector(':scope > a');
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'ch-subcategory-toggle';
    button.setAttribute('aria-label', `زیرگروه‌های ${link.textContent.trim()}`);
    button.setAttribute('aria-controls', children.id);
    button.setAttribute('aria-expanded', 'false'); button.textContent = '+';
    link.after(button); item.classList.add('ch-category-branch');
   });
   tree.querySelectorAll(':scope > ul > li').forEach(item => {
    const button = item.querySelector(':scope > .ch-category-toggle');
    if (!button) return;
    const expanded = String(item.classList.contains(narrow.matches ? 'is-open' : 'is-active'));
    if (button.getAttribute('aria-expanded') !== expanded) button.setAttribute('aria-expanded', expanded);
    if (!narrow.matches) {
     const panel = tree.querySelector(':scope > .ch-category-detail');
     if (panel) { panel.id = `ch-category-detail-${treeIndex}`; button.setAttribute('aria-controls', panel.id); }
    } else {
     button.setAttribute('aria-controls', item.querySelector(':scope > .ch-category-accordion').id);
    }
   });
  };
  tree.addEventListener('click', event => {
   const button = event.target.closest('.ch-subcategory-toggle');
   if (!button) return;
   const expanded = button.getAttribute('aria-expanded') !== 'true';
   button.setAttribute('aria-expanded', String(expanded)); button.textContent = expanded ? '−' : '+';
   button.parentElement.classList.toggle('is-branch-open', expanded);
  });
  tree.addEventListener('keydown', event => {
   if (event.key !== 'Escape' || !narrow.matches) return;
   const branch = event.target.closest('.is-branch-open');
   if (branch) { const button = branch.querySelector(':scope > .ch-subcategory-toggle'); button.click(); button.focus(); event.preventDefault(); event.stopPropagation(); return; }
   const root = event.target.closest('li.is-open');
   if (root) { const button = root.querySelector(':scope > .ch-category-toggle'); button.click(); button.focus(); event.preventDefault(); event.stopPropagation(); }
  });
  new MutationObserver(enhance).observe(tree, {childList:true, subtree:true, attributes:true, attributeFilter:['class']});
  narrow.addEventListener('change', enhance); enhance();
 });
 const start = () => { init(); initMenu(); };
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
