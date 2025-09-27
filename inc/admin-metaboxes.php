<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Metaboxes de edición para post type "property":
 * - Detalles (precio, m2, hab, baños, referencia, energía, etiqueta, características)
 * - Galería (múltiples imágenes, drag & drop con asa y compat Safari)
 * - Ubicación (Leaflet + lat/lng)
 */

add_action('add_meta_boxes', function(){
    add_meta_box('rep_mb_details', __('Detalles de la propiedad','real-estate-pro'), 'rep_mb_render_details', 'property','normal','high');
    add_meta_box('rep_mb_gallery', __('Galería de imágenes','real-estate-pro'), 'rep_mb_render_gallery', 'property','normal','default');
    add_meta_box('rep_mb_location', __('Ubicación (mapa)','real-estate-pro'), 'rep_mb_render_location', 'property','side','default');
});

/** Render: Detalles */
function rep_mb_render_details( $post ){
    wp_nonce_field('rep_mb_details','rep_mb_details_nonce');

    $precio  = get_post_meta($post->ID,'precio',true);
    $pv      = get_post_meta($post->ID,'precio_venta',true);
    $pa      = get_post_meta($post->ID,'precio_alquiler',true);
    $pt      = get_post_meta($post->ID,'precio_traspaso',true);
    $m2      = get_post_meta($post->ID,'superficie_construida',true);
    $hab     = get_post_meta($post->ID,'habitaciones',true);
    $ban     = get_post_meta($post->ID,'banos',true);
    $ref     = get_post_meta($post->ID,'referencia',true);
    $energy  = strtoupper(trim(get_post_meta($post->ID,'energy_rating',true)));
    $label   = get_post_meta($post->ID,'label_tag',true);
    $extsrc  = get_post_meta($post->ID,'external_source',true);
    $extid   = get_post_meta($post->ID,'external_id',true);

    $energy_opts = array('' => __('— Selecciona —','real-estate-pro'),
        'A'=>'A','B'=>'B','C'=>'C','D'=>'D','E'=>'E','F'=>'F','G'=>'G','EN TRAMITE'=>__('En trámite','real-estate-pro'));

    $label_opts = array(
        ''=>'Sin etiqueta','urge'=>'Urge','oportunidad'=>'Oportunidad','rebajado'=>'Rebajado',
        'ideal-inversores'=>'Ideal inversores','producto-estrella'=>'Producto estrella',
        'alquilado'=>'Alquilado','vendido'=>'Vendido','reservado'=>'Reservado',
        'origen-bancario'=>'Origen bancario','novedad'=>'Novedad','en-exclusiva'=>'En exclusiva',
        'estudiantes'=>'Estudiantes','precio-negociable'=>'Precio negociable'
    );

    $feat_labels = array(
      'ascensor'=>'Ascensor','terraza'=>'Terraza','piscina'=>'Piscina','exterior'=>'Exterior',
      'soleado'=>'Soleado','amueblado'=>'Amueblado','garaje'=>'Garaje','trastero'=>'Trastero',
      'balcon'=>'Balcón','calefaccion'=>'Calefacción','aire_acondicionado'=>'Aire acondicionado',
      'armarios_empotrados'=>'Armarios empotrados','cocina_equipada'=>'Cocina equipada',
      'jardin'=>'Jardín','mascotas'=>'Admite mascotas','accesible'=>'Accesible',
      'vistas'=>'Vistas','alarma'=>'Alarma','portero'=>'Portero','zona_comunitaria'=>'Zona comunitaria'
    );
    ?>
    <style>
      .rep-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
      .rep-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px}
      .rep-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:12px}
      .rep-mb-field label{display:block;font-weight:600;margin-bottom:4px}
      .rep-mb-field input,.rep-mb-field select{width:100%}
      .rep-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
      .rep-feat-grid label{display:flex;gap:8px;align-items:center;border:1px solid #e6eaef;border-radius:8px;padding:8px;background:#fff}
      @media (max-width: 1100px){
        .rep-grid-4{grid-template-columns:repeat(2,1fr)}
        .rep-grid-3{grid-template-columns:repeat(2,1fr)}
        .rep-feat-grid{grid-template-columns:repeat(2,1fr)}
      }
    </style>

    <div class="rep-grid-4">
      <div class="rep-mb-field"><label><?php _e('Precio principal (€)','real-estate-pro'); ?></label><input type="number" step="500" name="rep_mb[precio]" value="<?php echo esc_attr($precio); ?>"/></div>
      <div class="rep-mb-field"><label><?php _e('Precio venta (€)','real-estate-pro'); ?></label><input type="number" step="500" name="rep_mb[precio_venta]" value="<?php echo esc_attr($pv); ?>"/></div>
      <div class="rep-mb-field"><label><?php _e('Precio alquiler (€)','real-estate-pro'); ?></label><input type="number" step="50" name="rep_mb[precio_alquiler]" value="<?php echo esc_attr($pa); ?>"/></div>
      <div class="rep-mb-field"><label><?php _e('Precio traspaso (€)','real-estate-pro'); ?></label><input type="number" step="500" name="rep_mb[precio_traspaso]" value="<?php echo esc_attr($pt); ?>"/></div>
    </div>

    <div class="rep-grid-3">
      <div class="rep-mb-field"><label><?php _e('Superficie construida (m²)','real-estate-pro'); ?></label><input type="number" step="1" name="rep_mb[superficie_construida]" value="<?php echo esc_attr($m2); ?>"/></div>
      <div class="rep-mb-field"><label><?php _e('Habitaciones','real-estate-pro'); ?></label><input type="number" step="1" name="rep_mb[habitaciones]" value="<?php echo esc_attr($hab); ?>"/></div>
      <div class="rep-mb-field"><label><?php _e('Baños','real-estate-pro'); ?></label><input type="number" step="1" name="rep_mb[banos]" value="<?php echo esc_attr($ban); ?>"/></div>
    </div>

    <div class="rep-grid-2">
      <div class="rep-mb-field"><label><?php _e('Referencia','real-estate-pro'); ?></label><input type="text" name="rep_mb[referencia]" value="<?php echo esc_attr($ref); ?>"/></div>
      <div class="rep-mb-field"><label><?php _e('Eficiencia energética','real-estate-pro'); ?></label>
        <select name="rep_mb[energy_rating]">
          <?php foreach($energy_opts as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($energy,$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="rep-grid-2">
      <div class="rep-mb-field"><label><?php _e('Etiqueta','real-estate-pro'); ?></label>
        <select name="rep_mb[label_tag]">
          <?php foreach($label_opts as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($label,$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3 style="margin-top:16px;"><?php _e('Características','real-estate-pro'); ?></h3>
    <div class="rep-feat-grid">
      <?php foreach($feat_labels as $key=>$lbl): $checked = get_post_meta($post->ID,$key,true) ? 'checked' : ''; ?>
        <label><input type="checkbox" name="rep_mb_feat[<?php echo esc_attr($key); ?>]" value="1" <?php echo $checked; ?>/> <?php echo esc_html($lbl); ?></label>
      <?php endforeach; ?>
    </div>

    <?php if($extsrc || $extid): ?>
      <p style="margin-top:12px;opacity:.8">
        <?php if($extsrc): ?><strong>Origen:</strong> <?php echo esc_html($extsrc); ?> &nbsp;<?php endif; ?>
        <?php if($extid): ?><strong>ID externo:</strong> <?php echo esc_html($extid); ?><?php endif; ?>
      </p>
    <?php endif;
}

/** Guardado de detalles + características */
add_action('save_post_property', function($post_id){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! isset($_POST['rep_mb_details_nonce']) || ! wp_verify_nonce($_POST['rep_mb_details_nonce'],'rep_mb_details') ) return;
    if ( ! current_user_can('edit_post',$post_id) ) return;

    $in = isset($_POST['rep_mb']) && is_array($_POST['rep_mb']) ? $_POST['rep_mb'] : array();

    $map = array(
        'precio' => 'floatval',
        'precio_venta' => 'floatval',
        'precio_alquiler' => 'floatval',
        'precio_traspaso' => 'floatval',
        'superficie_construida' => 'floatval',
        'habitaciones' => 'intval',
        'banos' => 'intval',
        'referencia' => 'sanitize_text_field',
        'energy_rating' => 'rep_sanitize_energy',
        'label_tag' => 'sanitize_text_field',
        'lat' => 'floatval',
        'lng' => 'floatval'
    );
    foreach($map as $key=>$fn){
        if ( isset($in[$key]) ){
            $val = call_user_func($fn, $in[$key]);
            update_post_meta($post_id, $key, $val);
        }
    }

    // Características (booleans)
    $feat_keys = array('ascensor','terraza','piscina','exterior','soleado','amueblado','garaje','trastero','balcon','calefaccion','aire_acondicionado','armarios_empotrados','cocina_equipada','jardin','mascotas','accesible','vistas','alarma','portero','zona_comunitaria');
    $in_feat = isset($_POST['rep_mb_feat']) && is_array($_POST['rep_mb_feat']) ? $_POST['rep_mb_feat'] : array();
    foreach($feat_keys as $k){
        if ( isset($in_feat[$k]) ) update_post_meta($post_id,$k,1);
        else delete_post_meta($post_id,$k);
    }

    // Si no hay precio principal, derivarlo
    $precio = get_post_meta($post_id,'precio',true);
    if ($precio==='' || $precio===null){
        $pv = get_post_meta($post_id,'precio_venta',true);
        $pa = get_post_meta($post_id,'precio_alquiler',true);
        $pt = get_post_meta($post_id,'precio_traspaso',true);
        $precio = $pv ?: ($pa ?: $pt);
        if($precio) update_post_meta($post_id,'precio',$precio);
    }
}, 10);

function rep_sanitize_energy($val){
    $val = strtoupper(trim($val));
    $allowed = array('A','B','C','D','E','F','G','EN TRAMITE','');
    return in_array($val,$allowed,true) ? $val : '';
}

/** Render: Galería */
function rep_mb_render_gallery( $post ){
    wp_nonce_field('rep_mb_gallery','rep_mb_gallery_nonce');

    $ids = get_post_meta($post->ID,'gallery_ids',true);
    $ids = is_array($ids) ? array_filter(array_map('intval',$ids)) : array();
    ?>
    <style>
      .rep-gal-wrap{display:flex;gap:10px;flex-wrap:wrap;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
      .rep-gal-item{position:relative;width:120px}
      .rep-gal-item img{display:block;width:120px;height:120px;object-fit:cover;border-radius:6px;border:1px solid #e3e6ea}
      .rep-gal-remove{position:absolute;top:4px;right:4px;background:#000a;color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer}
      .rep-gal-drag{position:absolute;left:4px;top:4px;background:#000a;color:#fff;border:none;border-radius:4px;width:24px;height:24px;cursor:grab;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1}
      .rep-gal-drag:active{cursor:grabbing}
      .rep-gal-actions{margin-top:10px}
      .rep-gal-sort-hint{opacity:.7;font-size:.9em;margin-left:8px}
      .rep-gal-placeholder{width:120px;height:120px;border:2px dashed #c7ced6;border-radius:6px;background:#f6f8fa}
    </style>

    <div id="rep-gal" class="rep-gal-wrap" data-rep-gal>
      <?php foreach($ids as $id):
        $src = wp_get_attachment_image_src($id,'thumbnail');
        if(!$src) continue; ?>
        <div class="rep-gal-item" data-id="<?php echo esc_attr($id); ?>">
          <button type="button" class="rep-gal-drag" title="<?php esc_attr_e('Arrastrar','real-estate-pro'); ?>">⋮⋮</button>
          <img src="<?php echo esc_url($src[0]); ?>" alt=""/>
          <button type="button" class="rep-gal-remove" aria-label="<?php esc_attr_e('Eliminar','real-estate-pro'); ?>">&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="rep_mb_gallery_ids" id="rep_mb_gallery_ids" value="<?php echo esc_attr( implode(',',$ids) ); ?>"/>

    <div class="rep-gal-actions">
      <button type="button" class="button button-primary" id="rep-gal-add"><?php _e('Añadir imágenes','real-estate-pro'); ?></button>
      <span class="rep-gal-sort-hint"><?php _e('Usa el asa ⋮⋮ para reordenar. (Compat. Safari)','real-estate-pro'); ?></span>
    </div>
    <?php
}

/** Guardado: Galería */
add_action('save_post_property', function($post_id){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! isset($_POST['rep_mb_gallery_nonce']) || ! wp_verify_nonce($_POST['rep_mb_gallery_nonce'],'rep_mb_gallery') ) return;
    if ( ! current_user_can('edit_post',$post_id) ) return;

    $val = isset($_POST['rep_mb_gallery_ids']) ? trim($_POST['rep_mb_gallery_ids']) : '';
    $ids = array();
    if($val!==''){
        foreach( explode(',',$val) as $p ){
            $p = intval($p);
            if($p>0) $ids[] = $p;
        }
    }
    update_post_meta($post_id,'gallery_ids',$ids);
}, 11);

/** Render: Ubicación (mapa) */
function rep_mb_render_location( $post ){
    wp_nonce_field('rep_mb_location','rep_mb_location_nonce');

    $lat = get_post_meta($post->ID,'lat',true);
    $lng = get_post_meta($post->ID,'lng',true);
    ?>
    <style>
      #rep-admin-map{width:100%;height:230px;border-radius:8px;overflow:hidden;margin:8px 0;border:1px solid #e3e6ea}
      .rep-loc-fields input{width:100%}
      .rep-loc-url-group{display:flex;gap:8px;margin-bottom:10px}
      .rep-loc-url-group input{flex:1}
    </style>

    <div class="rep-loc-url-group">
      <input type="url" id="rep_mb_gmaps_url" placeholder="<?php esc_attr_e('Pegar URL de Google Maps aquí','real-estate-pro'); ?>"/>
      <button type="button" class="button" id="rep_mb_gmaps_extract"><?php _e('Extraer','real-estate-pro'); ?></button>
    </div>

    <div class="rep-loc-fields">
      <label><?php _e('Latitud','real-estate-pro'); ?></label>
      <input type="number" step="0.000001" name="rep_mb[lat]" id="rep_mb_lat" value="<?php echo esc_attr($lat); ?>"/>
      <label style="margin-top:8px;"><?php _e('Longitud','real-estate-pro'); ?></label>
      <input type="number" step="0.000001" name="rep_mb[lng]" id="rep_mb_lng" value="<?php echo esc_attr($lng); ?>"/>
    </div>
    <div id="rep-admin-map" data-lat="<?php echo esc_attr($lat); ?>" data-lng="<?php echo esc_attr($lng); ?>"></div>
    <p><small><?php _e('Haz clic en el mapa para fijar las coordenadas.','real-estate-pro'); ?></small></p>
    <?php
}


/** Guardado: Ubicación */
add_action('save_post_property', function($post_id){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! isset($_POST['rep_mb_location_nonce']) || ! wp_verify_nonce($_POST['rep_mb_location_nonce'],'rep_mb_location') ) return;
    if ( ! current_user_can('edit_post',$post_id) ) return;

    if ( isset($_POST['rep_mb']['lat']) ) update_post_meta($post_id,'lat', floatval($_POST['rep_mb']['lat']) );
    if ( isset($_POST['rep_mb']['lng']) ) update_post_meta($post_id,'lng', floatval($_POST['rep_mb']['lng']) );
}, 12);
