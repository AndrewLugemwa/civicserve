/* CivicServe staff workspace helpers (no inline scripts, CSP-friendly). */
(function () {
  'use strict';

  // Show / hide password fields
  Array.prototype.forEach.call(document.querySelectorAll('[data-toggle-password]'), function (btn) {
    var input = document.getElementById(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });

  // Print case sheet
  Array.prototype.forEach.call(document.querySelectorAll('[data-print]'), function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // Confirm destructive actions
  Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });

  // Prevent double submission
  Array.prototype.forEach.call(document.querySelectorAll('form[method=post]'), function (form) {
    form.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) return;
      var b = form.querySelector('button[type=submit]');
      if (b) { setTimeout(function () { b.disabled = true; b.classList.add('is-loading'); }, 0); }
    });
  });

  // Read-only location map on the case page
  var caseMap = document.getElementById('case-map');
  if (caseMap && window.civicLoadLeaflet) {
    var lat = parseFloat(caseMap.dataset.lat), lng = parseFloat(caseMap.dataset.lng);
    window.civicLoadLeaflet().then(function (L) {
      var map = L.map(caseMap, { scrollWheelZoom: false, dragging: !L.Browser.mobile }).setView([lat, lng], 16);
      window.civicTileLayer(map, function () {
        map.remove();
        caseMap.innerHTML = '<div class="map-unavailable"><strong>Map images unavailable.</strong><span>Use the “Open in map” link above.</span></div>';
      });
      L.marker([lat, lng], { keyboard: false }).addTo(map);
      setTimeout(function () { map.invalidateSize(); }, 200);
    }).catch(function () {
      caseMap.innerHTML = '<div class="map-unavailable"><strong>Map unavailable.</strong><span>Use the “Open in map” link above.</span></div>';
    });
  }
})();
