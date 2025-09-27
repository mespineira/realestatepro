(function(){
  var inited = false;
  var map;

  function initMap() {
    if (inited) {
      if (map) setTimeout(function(){ map.invalidateSize(); }, 60);
      return;
    }
    var el = document.querySelector('[data-rep-map]');
    if(!el) return;

    var lat = parseFloat(el.getAttribute('data-lat'));
    var lng = parseFloat(el.getAttribute('data-lng'));
    if(isNaN(lat) || isNaN(lng)) return;

    map = L.map(el).setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    var title = el.getAttribute('data-title') || '';
    var marker = L.marker([lat, lng]).addTo(map);
    if(title) marker.bindPopup(title);

    setTimeout(function(){ map.invalidateSize(); }, 60);
    inited = true;
  }

  document.addEventListener('rep:open-map', initMap);

  document.addEventListener('DOMContentLoaded', function(){
    // Si por lo que sea el mapa ya está visible al cargar
    var sec = document.querySelector('#mapa');
    if (sec && sec.offsetParent !== null) initMap();
  });
})();