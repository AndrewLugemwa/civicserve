/* CivicServe report form: map pin, client-side validation, photo preview, submit state.
   The form works without JavaScript; this only improves the experience. */
(function () {
  'use strict';
  var form = document.querySelector('[data-report-form]');
  if (!form) return;

  var MAX_BYTES = parseInt((form.querySelector('[name=max_file_size]') || {}).value || '5242880', 10);
  var latInput = document.getElementById('latitude');
  var lngInput = document.getElementById('longitude');
  var mapEl = document.getElementById('incident-map');
  var statusEl = document.getElementById('map-status');
  var clearBtn = document.getElementById('clear-pin');
  var locateBtn = document.getElementById('use-location');
  var map = null, marker = null;

  /* ---------- Errors ---------- */
  function setError(key, message) {
    var el = document.getElementById('err-' + key);
    if (!el) return;
    el.textContent = message || '';
    var field = el.closest('.field');
    if (field) field.classList.toggle('has-error', !!message);
  }

  /* ---------- Pin handling ---------- */
  function validCoords(lat, lng) {
    return isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
  }
  function setPin(lat, lng, pan) {
    lat = Number(lat); lng = Number(lng);
    if (!validCoords(lat, lng)) { setError('coordinates', 'Enter a latitude between -90 and 90 and a longitude between -180 and 180.'); return false; }
    latInput.value = lat.toFixed(7);
    lngInput.value = lng.toFixed(7);
    setError('coordinates', '');
    statusEl.textContent = 'Pin placed at ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '.';
    clearBtn.hidden = false;
    if (map && window.L) {
      var point = L.latLng(lat, lng);
      if (marker) marker.setLatLng(point); else marker = L.marker(point, { keyboard: false }).addTo(map);
      if (pan) map.setView(point, Math.max(map.getZoom(), 16));
    }
    return true;
  }
  function clearPin() {
    latInput.value = ''; lngInput.value = '';
    if (marker && map) { map.removeLayer(marker); marker = null; }
    clearBtn.hidden = true;
    statusEl.textContent = 'Pin removed. Tap the map to place a new one.';
  }
  clearBtn.addEventListener('click', clearPin);

  document.getElementById('apply-coords').addEventListener('click', function () {
    var lat = parseFloat(document.getElementById('manual-lat').value.replace(',', '.'));
    var lng = parseFloat(document.getElementById('manual-lng').value.replace(',', '.'));
    setPin(lat, lng, true);
  });

  locateBtn.addEventListener('click', function () {
    if (!navigator.geolocation) { statusEl.textContent = 'Location is not available in this browser. Tap the map instead.'; return; }
    locateBtn.disabled = true;
    statusEl.textContent = 'Finding your location…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      locateBtn.disabled = false;
      setPin(pos.coords.latitude, pos.coords.longitude, true);
    }, function () {
      locateBtn.disabled = false;
      statusEl.textContent = 'We could not get your location. Tap the map or enter coordinates instead.';
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
  });

  function mapUnavailable(reason) {
    if (map) { map.remove(); map = null; marker = null; }
    mapEl.innerHTML = '<div class="map-unavailable"><strong>The map could not load.</strong><span></span></div>';
    mapEl.querySelector('span').textContent = reason + ' You can still submit your report, or enter coordinates below.';
    document.getElementById('manual-details').open = true;
    statusEl.textContent = 'Map unavailable.';
  }

  /* ---------- Map ---------- */
  window.civicLoadLeaflet().then(function (Leaflet) {
    var view = [parseFloat(mapEl.dataset.defaultLat) || 0.3136, parseFloat(mapEl.dataset.defaultLng) || 32.5811];
    map = Leaflet.map(mapEl, { scrollWheelZoom: false, tap: true }).setView(view, parseInt(mapEl.dataset.defaultZoom, 10) || 13);
    window.civicTileLayer(map, function () {
      mapUnavailable('Map images cannot be reached from this connection.');
    });
    map.on('click', function (ev) { setPin(ev.latlng.lat, ev.latlng.lng, false); });
    var startLat = parseFloat(mapEl.dataset.lat), startLng = parseFloat(mapEl.dataset.lng);
    if (validCoords(startLat, startLng)) setPin(startLat, startLng, true);
    setTimeout(function () { if (map) map.invalidateSize(); }, 250);
  }).catch(function () {
    mapUnavailable('The map component did not load.');
  });

  /* ---------- Character counter ---------- */
  Array.prototype.forEach.call(form.querySelectorAll('[data-count]'), function (el) {
    var out = form.querySelector('[data-count-for="' + el.id + '"]');
    function update() { out.textContent = el.value.length + ' / ' + el.maxLength; }
    el.addEventListener('input', update); update();
  });

  /* ---------- Photo preview + size check ---------- */
  var photo = document.getElementById('f-photo');
  var preview = document.getElementById('photo-preview');
  photo.addEventListener('change', function () {
    setError('photo', '');
    preview.hidden = true;
    var file = photo.files && photo.files[0];
    if (!file) return;
    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
      setError('photo', 'Please choose a JPG, PNG or WebP photo.'); photo.value = ''; return;
    }
    if (file.size > MAX_BYTES) {
      setError('photo', 'This photo is ' + (file.size / 1048576).toFixed(1) + ' MB. The limit is ' + Math.round(MAX_BYTES / 1048576) + ' MB.'); photo.value = ''; return;
    }
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
  });

  /* ---------- Validation on submit ---------- */
  var phoneRe = /^(?:\+?256|0)[1-9]\d{8}$/;
  function check() {
    var errors = {};
    if (!form.querySelector('input[name=category]:checked')) errors.category = 'Choose the category that best describes the issue.';
    if (form.description.value.trim().length < 15) errors.description = 'Describe the problem in at least 15 characters.';
    if (!form.division.value) errors.division = 'Choose the area where the issue is.';
    if (form.location.value.trim().length < 3) errors.location = 'Enter a street, village or landmark so staff can find the issue.';
    if (form.citizen_name.value.trim().length < 2) errors.name = 'Enter the full name of the person reporting.';
    if (!phoneRe.test(form.phone.value.replace(/[\s\-().]/g, ''))) errors.phone = 'Enter a valid Ugandan phone number, e.g. 0772 123 456.';
    var email = form.citizen_email.value.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Enter a valid email address, or leave it blank.';
    if (!form.consent.checked) errors.consent = 'Please confirm the details and agree to the Council using them for this report.';
    return errors;
  }
  ['category', 'description', 'division', 'location', 'name', 'phone', 'email', 'consent'].forEach(function (key) {
    var target = key === 'name' ? form.citizen_name : key === 'email' ? form.citizen_email : form[key];
    if (!target) return;
    var events = (target.length && !target.tagName) ? Array.prototype.slice.call(target) : [target];
    // Clear an error as soon as the value becomes valid (while typing), never on blur:
    // removing a message on blur shifts the layout under the pointer and makes the next click miss.
    events.forEach(function (el) {
      ['input', 'change'].forEach(function (type) {
        el.addEventListener(type, function () {
          var msg = document.getElementById('err-' + key);
          if (msg && msg.textContent && !check()[key]) setError(key, '');
        });
      });
    });
  });

  form.addEventListener('submit', function (ev) {
    var errors = check();
    ['category', 'description', 'division', 'location', 'name', 'phone', 'email', 'consent'].forEach(function (k) { setError(k, errors[k] || ''); });
    var keys = Object.keys(errors);
    if (keys.length) {
      ev.preventDefault();
      var first = document.getElementById('f-' + keys[0]);
      if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus({ preventScroll: true }); }
      return;
    }
    var button = form.querySelector('button[type=submit]');
    button.disabled = true;
    button.classList.add('is-loading');
    button.textContent = button.getAttribute('data-submit-label') || 'Submitting…';
  });

  var summary = document.getElementById('form-errors');
  if (summary) summary.focus();
})();
