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
      handle: '.rep-gal-drag',
      placeholder: 'rep-gal-placeholder',
      forcePlaceholderSize: true,
      helper: 'clone',
      tolerance: 'pointer',
      opacity: 0.9,
      delay: 100,
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

  function manageFeatureVisibility() {
      var $metabox = $('.editor-post-taxonomies__hierarchical-terms-list[aria-label="Tipo de propiedad"]');
      
      if (!$metabox.length) {
          console.warn('[REP] No se encontró el panel de "Tipo de propiedad".');
          return;
      }

      var $selectedItems = $metabox.find('input[type="checkbox"]:checked');
      var selectedTypeSlugs = [];

      if ($selectedItems.length) {
          $selectedItems.each(function(){
              // **CORRECCIÓN**: Buscar la etiqueta usando el atributo 'for' que coincide con el ID del input.
              var inputId = $(this).attr('id');
              var labelText = $('label[for="' + inputId + '"]').text().trim().toLowerCase();
              
              if (labelText) {
                var normalized = labelText.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                var slug = normalized.replace(/ /g, '-').replace(/[^a-z0-9-]/g, '');
                if (slug) {
                    selectedTypeSlugs.push(slug);
                }
              }
          });
      }

      $('#rep-feature-groups-wrapper .rep-feat-group[data-property-type]').hide();
      $('#rep-feature-groups-wrapper .rep-feat-group[data-property-type="comun"]').show();

      if (selectedTypeSlugs.length > 0) {
          console.log('[REP] Tipos seleccionados (slugs):', selectedTypeSlugs);
      } else {
          console.log('[REP] Ningún tipo de propiedad seleccionado.');
      }

      var typeToGroupMap = {
        'casa': ['casa'], 'casas': ['casa'], 'chalet': ['casa'], 'chalets': ['casa'],
        'adosado': ['casa'], 'adosados': ['casa'], 'independiente': ['casa'], 'chalets-independientes': ['casa'],
        'piso': ['piso'], 'pisos': ['piso'], 'atico': ['piso'], 'aticos': ['piso'],
        'duplex': ['piso'], 'bajo': ['piso'], 'bajos': ['piso'], 'plantas-bajas':['piso'], 'apartamento': ['piso'],
        'apartamentos': ['piso'],
        'terreno': ['terreno'], 'terrenos': ['terreno'], 'finca': ['terreno'],
        'fincas': ['terreno'], 'solar': ['terreno'], 'solares': ['terreno'],
        'locales': ['piso'], 'locales-comerciales': ['piso'], 'restaurantes': ['piso'],
      };
      
      selectedTypeSlugs.forEach(function(slug){
          if (typeToGroupMap[slug]) {
              typeToGroupMap[slug].forEach(function(groupKey){
                  console.log('[REP] Mostrando grupo:', groupKey, 'para slug:', slug);
                  $('[data-property-type="' + groupKey + '"]').show();
              });
          } else {
              console.log('[REP] No se encontró mapeo para el slug:', slug);
          }
      });
  }

  function initAdminMap(){
    var $mapContainer = $('#rep-admin-map');
    if(!$mapContainer.length || typeof L === 'undefined') return;

    if (map) {
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

    $('#rep_mb_gmaps_extract').on('click', function() {
      console.log('[REP] Botón de extraer pulsado.');
      var url = $('#rep_mb_gmaps_url').val();
      if (!url) { console.log('[REP] No hay URL para procesar.'); return; }
      console.log('[REP] Procesando URL:', url);

      var lat, lng;
      var match1 = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
      var match2 = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
      var match3 = url.match(/ll=(-?\d+\.\d+),(-?\d+\.\d+)/);

      if (match1) {
        lat = parseFloat(match1[1]); lng = parseFloat(match1[2]);
      } else if (match2) {
        lat = parseFloat(match2[1]); lng = parseFloat(match2[2]);
      } else if (match3) {
        lat = parseFloat(match3[1]); lng = parseFloat(match3[2]);
      }

      if (lat !== undefined && lng !== undefined) {
        $('#rep_mb_lat').val(lat.toFixed(6)).trigger('change');
        $('#rep_mb_lng').val(lng.toFixed(6)).trigger('change');
      } else {
        alert('No se pudieron encontrar coordenadas en la URL.');
      }
    });

    setTimeout(function(){ if (map) map.invalidateSize(); }, 200);
  }

  function initFeatureVisibilityLogic() {
      var metaboxSelector = '.editor-post-taxonomies__hierarchical-terms-list[aria-label="Tipo de propiedad"]';
      
      var observer = new MutationObserver(function(mutations, me) {
          var $foundMetabox = $(metaboxSelector);
          if ($foundMetabox.length) {
              console.log('[REP] Panel de "Tipo de propiedad" encontrado.');
              
              manageFeatureVisibility();
              
              $foundMetabox.on('change', 'input[type="checkbox"]', function(){
                  console.log('[REP] Cambio en checkbox de tipo de propiedad detectado.');
                  manageFeatureVisibility();
              });

              me.disconnect();
          }
      });
      
      observer.observe(document.body, { childList: true, subtree: true });
  }

  $(function(){
    bindGalleryUI();
    initSortable();
    initAdminMap();
    initFeatureVisibilityLogic();
  });

})(jQuery);

