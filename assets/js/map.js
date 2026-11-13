/* CivicServe map helpers. Leaflet is served from this site (assets/vendor/leaflet),
   so only the map images (tiles) need an internet connection. */
(function () {
  'use strict';
  var base = (document.currentScript && document.currentScript.src) ? document.currentScript.src.replace(/assets\/js\/map\.js.*$/, '') : '';

  window.civicLoadLeaflet = function () {
    if (window.L && typeof window.L.map === 'function') return Promise.resolve(window.L);
    if (window.__civicLeaflet) return window.__civicLeaflet;
    window.__civicLeaflet = new Promise(function (resolve, reject) {
      var css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = base + 'assets/vendor/leaflet/leaflet.css';
      document.head.appendChild(css);
      var js = document.createElement('script');
      js.src = base + 'assets/vendor/leaflet/leaflet.js';
      js.onload = function () { (window.L && window.L.map) ? resolve(window.L) : reject(new Error('Leaflet missing')); };
      js.onerror = function () { reject(new Error('Leaflet failed to load')); };
      document.head.appendChild(js);
    });
    return window.__civicLeaflet;
  };

  /* Adds a tile layer, switching provider after repeated failures; calls onFail if all fail. */
  window.civicTileLayer = function (map, onFail) {
    var providers = [
      { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attribution: '&copy; OpenStreetMap contributors', subdomains: 'abc' },
      { url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', attribution: '&copy; OpenStreetMap contributors &copy; CARTO', subdomains: 'abcd' },
      { url: 'https://tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', attribution: '&copy; OpenStreetMap France', subdomains: 'abc' }
    ];
    var index = 0, layer = null, errors = 0, loaded = 0, failed = false;
    function use() {
      if (layer) map.removeLayer(layer);
      var p = providers[index];
      errors = 0; loaded = 0;
      layer = L.tileLayer(p.url, { maxZoom: 19, attribution: p.attribution, subdomains: p.subdomains }).addTo(map);
      layer.on('tileload', function () { loaded++; });
      layer.on('tileerror', function () {
        errors++;
        if (errors >= 4 && loaded === 0) {
          if (index < providers.length - 1) { index++; use(); }
          else if (!failed) { failed = true; if (onFail) onFail(); }
        }
      });
    }
    use();
    return function () { return layer; };
  };
})();
