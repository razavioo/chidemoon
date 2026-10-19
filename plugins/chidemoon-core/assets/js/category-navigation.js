/* Three-level navigation: links navigate; adjacent controls expand groups. */
(() => {
 'use strict';
 const init = () => document.querySelectorAll('.ch-category-tree').forEach((tree, treeIndex) => {
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
    const button = document.createElement('button'); button.type = 'button'; button.className = 'ch-category-toggle'; button.textContent = link.textContent;
    link.hidden = true; link.after(button);
    const section = document.createElement('div'); section.className = 'ch-category-accordion'; section.id = `ch-category-${treeIndex}-${index}`; section.append(content(item)); item.append(section);
    button.setAttribute('aria-expanded','false'); button.setAttribute('aria-controls',section.id);
    button.addEventListener('click', () => { const open = !item.classList.contains('is-open'); roots.forEach(root=>root.classList.remove('is-open')); item.classList.toggle('is-open',open); show(item); });
    button.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse' && !narrow.matches) show(item); });
    button.addEventListener('focus', () => { if (!narrow.matches) show(item); });
    button.addEventListener('keydown', event => { if (event.key === 'ArrowLeft' && !narrow.matches) { event.preventDefault(); panel.querySelector('a')?.focus(); } });
   });
   panel.addEventListener('keydown', event => { if (event.key === 'Escape' || event.key === 'ArrowRight') { event.preventDefault(); tree.querySelector('li.is-active > .ch-category-toggle')?.focus(); } });
   if (roots.length) show(roots[0]);
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
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
