(function($){
  // ========= Instancias y Utils =========
  var map = null; // Instancia del mapa Leaflet para evitar reinicialización
  var marker = null; // Instancia del marcador del mapa

  function refreshHidden(){
    var ids = [];
    $('#rep-gal .rep-gal-item').each(function(){ ids.push( $(this).attr('data-id') ); });
    $('#rep_mb_gallery_ids').val(ids.join(','));
  }

  function initSortable(){
    var $wrap = $('#rep-gal');
    if(!$wrap.length) return;

    // Destruir previa si existiera (Gutenberg re-render)
    try{ if ($wrap.data('ui-sortable')) $wrap.sortable('destroy'); }catch(e){}

    $wrap.sortable({
      items: '.rep-gal-item',
      handle: '.rep-gal-drag',           // asa de arrastre
      placeholder: 'rep-gal-placeholder',
      forcePlaceholderSize: true,
      helper: 'clone',                    // importante para Safari
      tolerance: 'pointer',
      opacity: 0.9,
      delay: 100,                         // evita selecciones accidentales en WebKit
      cancel: '.rep-gal-remove, input, button',
      start: function(e, ui){
        ui.placeholder.height(ui.helper.outerHeight());
        ui.placeholder.width(ui.helper.outerWidth());
      },
      update: refreshHidden
    });
  }

  function ensureMediaFrame(){
    if (!window.wp || !wp.media) { alert('Cargador de medios no disponible.'); return null; }
    return wp.media({ frame:'select', title:'Selecciona imágenes', button:{text:'Añadir a la galería'}, multiple:true, library:{type:'image'} });
  }

  function bindGalleryUI(){
    $(document).off('click.rep-gal', '#rep-gal-add').on('click.rep-gal', '#rep-gal-add', function(e){
      e.preventDefault();
      var frame = ensureMediaFrame(); if(!frame) return;

      frame.on('select', function(){
        frame.state().get('selection').each(function(attachment){
          var a = attachment.toJSON();
          var id = a.id;
          var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
          var $item = $('<div class="rep-gal-item" data-id="'+id+'">'+
                        '<button type="button" class="rep-gal-drag" title="Arrastrar">⋮⋮</button>'+
                        '<img src="'+url+'"/>'+
                        '<button type="button" class="rep-gal-remove" aria-label="Eliminar">&times;</button>'+
                        '</div>');
          $('#rep-gal').append($item);
        });
        initSortable();
        refreshHidden();
      });

      frame.open();
    });

    $(document).off('click.rep-gal','.rep-gal-remove').on('click.rep-gal','.rep-gal-remove', function(){
      $(this).closest('.rep-gal-item').remove();
      refreshHidden();
    });
  }

  function initAdminMap(){
    var $mapContainer = $('#rep-admin-map');
    if(!$mapContainer.length || typeof L === 'undefined') return;

    // **CORRECCIÓN**: Evitar reinicialización del mapa si ya existe
    if (map) {
      // Si el mapa ya está creado, solo nos aseguramos de que su tamaño sea correcto
      setTimeout(function(){ map.invalidateSize(); }, 100);
      return;
    }

    var lat = parseFloat($mapContainer.data('lat')) || 42.0;
    var lng = parseFloat($mapContainer.data('lng')) || -8.5;
    
    map = L.map('rep-admin-map', { scrollWheelZoom: false }).setView([lat, lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
    marker = L.marker([lat, lng], {draggable:true}).addTo(map);

    function updateInputs(latlng){
      $('#rep_mb_lat').val(latlng.lat.toFixed(6));
      $('#rep_mb_lng').val(latlng.lng.toFixed(6));
    }

    marker.on('dragend', function(){ updateInputs(marker.getLatLng()); });
    map.on('click', function(e){ marker.setLatLng(e.latlng); updateInputs(e.latlng); });

    $('#rep_mb_lat, #rep_mb_lng').on('change', function(){
      if (!map || !marker) return;
      var la = parseFloat($('#rep_mb_lat').val());
      var lo = parseFloat($('#rep_mb_lng').val());
      if(!isNaN(la) && !isNaN(lo)){
        var ll = L.latLng(la, lo);
        marker.setLatLng(ll); 
        map.setView(ll);
      }
    });

    // **ACTUALIZADO CON DEPURACIÓN**: Extractor de coordenadas
    $('#rep_mb_gmaps_extract').on('click', function() {
      console.log('[REP] Botón de extraer pulsado.');
      var url = $('#rep_mb_gmaps_url').val();
      if (!url) {
        console.log('[REP] No hay URL para procesar.');
        return;
      }
      console.log('[REP] Procesando URL:', url);

      var lat, lng;

      var match1 = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
      if (match1 && match1.length >= 3) {
        lat = parseFloat(match1[1]);
        lng = parseFloat(match1[2]);
        console.log('[REP] Coordenadas encontradas con el patrón 1 (place):', {lat: lat, lng: lng});
      }

      if (lat === undefined || lng === undefined) {
        var match2 = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
        if (match2 && match2.length >= 3) {
          lat = parseFloat(match2[1]);
          lng = parseFloat(match2[2]);
          console.log('[REP] Coordenadas encontradas con el patrón 2 (@):', {lat: lat, lng: lng});
        }
      }
      
      if (lat === undefined || lng === undefined) {
          var match3 = url.match(/ll=(-?\d+\.\d+),(-?\d+\.\d+)/);
          if (match3 && match3.length >= 3) {
              lat = parseFloat(match3[1]);
              lng = parseFloat(match3[2]);
              console.log('[REP] Coordenadas encontradas con el patrón 3 (ll=):', {lat: lat, lng: lng});
          }
      }

      if (lat !== undefined && lng !== undefined) {
        console.log('[REP] Actualizando campos de lat/lng y mapa.');
        $('#rep_mb_lat').val(lat.toFixed(6)).trigger('change');
        $('#rep_mb_lng').val(lng.toFixed(6)).trigger('change');
      } else {
        console.error('[REP] No se encontraron coordenadas en la URL.');
        alert('No se pudieron encontrar coordenadas en la URL. Asegúrate de que la URL de Google Maps sea correcta y corresponda a un punto concreto.');
      }
    });

    setTimeout(function(){ if (map) map.invalidateSize(); }, 200);
  }

  function initAll(){
    bindGalleryUI();
    initSortable();
    initAdminMap();
  }

  $(function(){ initAll(); });

  if (window.MutationObserver){
    var obs = new MutationObserver(function(mutations){
      var need=false;
      mutations.forEach(function(m){ if ($(m.addedNodes).find('#rep-gal, #rep-gal-add, #rep-admin-map').length){ need=true; } });
      if(need) initAll();
    });
    obs.observe(document.body, { childList:true, subtree:true });
  }

  $(document).on('postbox-toggled', function(){ initAll(); });

})(jQuery);

