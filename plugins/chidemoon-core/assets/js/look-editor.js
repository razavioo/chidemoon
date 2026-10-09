(function () {
 'use strict';
 var editor = document.querySelector('.ch-look-editor');
 if (!editor) return;
 var body = editor.querySelector('tbody');
 var canvas = editor.querySelector('.ch-look-editor-canvas');
 var points = editor.querySelector('.ch-look-editor-points');
 var active = null;
 var nextIndex = body.children.length;
 var template = body.lastElementChild.cloneNode(true);
 function input(row, key) { return row.querySelector('[name$="[' + key + ']"]'); }
 function draw() {
  if (!points) return;
  points.replaceChildren();
  Array.from(body.children).forEach(function (row, index) {
   var x = input(row, 'x').value, y = input(row, 'y').value;
   if (x === '' || y === '') return;
   var dot = document.createElement('button');
   dot.type = 'button'; dot.textContent = index + 1;
   dot.dataset.index = index;
   dot.setAttribute('aria-label', (index + 1) + ': ' + input(row, 'productId').value);
   dot.style.cssText = 'position:absolute;transform:translate(-50%,-50%);border:2px solid white;border-radius:50%;width:36px;height:36px;background:#81563c;color:white;pointer-events:auto;touch-action:none;';
   dot.style.left = Number(x) + '%'; dot.style.top = Number(y) + '%';
   dot.addEventListener('click', function () { active = row; input(row, 'x').focus(); });
   dot.addEventListener('pointerdown', function (event) { active = row; dot.setPointerCapture(event.pointerId); });
   dot.addEventListener('pointermove', function (event) { if (dot.hasPointerCapture(event.pointerId)) move(row, event); });
   dot.addEventListener('pointerup', function (event) { if (dot.hasPointerCapture(event.pointerId)) { dot.releasePointerCapture(event.pointerId); draw(); } });
   dot.addEventListener('keydown', function (event) {
    var delta = event.shiftKey ? 5 : 1;
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    var axis = event.key === 'ArrowLeft' || event.key === 'ArrowRight' ? 'x' : 'y';
    input(row, axis).value = Math.max(0, Math.min(100, Number(input(row, axis).value) + (event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -delta : delta)));
    editor.querySelector('[name="chidemoon_confirm_hotspots"]').checked = false;
    dot.style.left = input(row, 'x').value + '%'; dot.style.top = input(row, 'y').value + '%';
   });
   points.appendChild(dot);
  });
 }
 function move(row, event) {
  var image = canvas.querySelector('img'), rect = image.getBoundingClientRect();
  if (!rect.width || !rect.height) return;
  input(row, 'x').value = Math.max(0, Math.min(100, (event.clientX - rect.left) * 100 / rect.width)).toFixed(2);
  input(row, 'y').value = Math.max(0, Math.min(100, (event.clientY - rect.top) * 100 / rect.height)).toFixed(2);
  editor.querySelector('[name="chidemoon_confirm_hotspots"]').checked = false;
  var dot = points.querySelector('[data-index="' + Array.from(body.children).indexOf(row) + '"]');
  if (dot) { dot.style.left = input(row, 'x').value + '%'; dot.style.top = input(row, 'y').value + '%'; }
 }
 body.addEventListener('focusin', function (event) { active = event.target.closest('tr'); });
 body.addEventListener('input', function () { editor.querySelector('[name="chidemoon_confirm_hotspots"]').checked = false; draw(); });
 body.addEventListener('click', function (event) { if (event.target.closest('.ch-look-remove')) { event.target.closest('tr').remove(); active = null; draw(); } });
 if (canvas) canvas.addEventListener('pointerdown', function (event) { if (active && event.target.tagName === 'IMG') { move(active, event); draw(); } });
 function add() {
  var row = template.cloneNode(true);
  row.querySelectorAll('input,select').forEach(function (field) { field.name = field.name.replace(/\[\d+\]/, '[' + nextIndex + ']'); field.value = ''; });
  input(row, 'position').value = body.children.length; nextIndex++; body.appendChild(row); active = row; return row;
 }
 editor.querySelector('.ch-look-add').addEventListener('click', function () { input(add(), 'productId').focus(); });
 var timer, search = editor.querySelector('.ch-look-product-search'), results = editor.querySelector('.ch-look-search-results');
 search.addEventListener('input', function () {
  window.clearTimeout(timer); var query = search.value;
  timer = window.setTimeout(function () {
   wp.apiFetch({ path: '/chidemoon-core/v1/looks/product-search?search=' + encodeURIComponent(query) }).then(function (products) {
    if (query !== search.value) return;
    results.replaceChildren();
    products.forEach(function (product) {
     var li = document.createElement('li'), button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = product.title + ' (#' + product.id + ')';
     button.addEventListener('click', function () { var row = add(); input(row, 'productId').value = product.id; input(row, 'x').focus(); results.replaceChildren(); }); li.appendChild(button); results.appendChild(li);
    });
   }).catch(function () { results.replaceChildren(); });
  }, 250);
 });
 draw();
 var paletteBody = editor.querySelector('.ch-look-palette-editor tbody');
 var paletteTemplate = paletteBody.lastElementChild.cloneNode(true);
 var paletteIndex = paletteBody.children.length;
 editor.querySelector('.ch-look-palette-add').addEventListener('click', function () {
  if (paletteBody.children.length >= 12) return;
  var row = paletteTemplate.cloneNode(true);
  row.querySelectorAll('input,select').forEach(function (field) { field.name = field.name.replace(/\[\d+\]/, '[' + paletteIndex + ']'); field.value = ''; });
  paletteIndex++; paletteBody.appendChild(row); row.querySelector('select').focus();
 });
 paletteBody.addEventListener('click', function (event) { if (event.target.closest('.ch-look-palette-remove')) event.target.closest('tr').remove(); });
})();
