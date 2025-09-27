<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Submenú de sincronización
add_action('admin_menu', function(){
    add_submenu_page(
        'edit.php?post_type=property',
        'Mobilia',
        'Mobilia',
        'manage_options',
        'rep-mobilia',
        'rep_mobilia_admin_page'
    );
});

// Página Mobilia con botón de sincronización manual
function rep_mobilia_admin_page(){
    if ( isset($_POST['rep_mobilia_manual']) && check_admin_referer('rep_mobilia_manual','rep_mobilia_manual_nonce') ){
        delete_option('rep_mobilia_batch');
        do_action('rep_mobilia_run_sync');
        echo '<div class="notice notice-success is-dismissible"><p>Sincronización iniciada. Se procesará por lotes automáticamente.</p></div>';
    }

    $last  = get_option('rep_mobilia_last_status', array());
    $batch = get_option('rep_mobilia_batch', array('offset'=>0,'done'=>0,'total'=>0));

    echo '<div class="wrap"><h1>Mobilia – Sincronización</h1>';

    echo '<form method="post" style="margin:1em 0;">';
    wp_nonce_field('rep_mobilia_manual','rep_mobilia_manual_nonce');
    echo '<input type="hidden" name="rep_mobilia_manual" value="1" />';
    submit_button('Iniciar sincronización ahora', 'primary', 'submit', false);
    echo ' <a class="button" href="'. esc_url( add_query_arg('rep_mobilia_refresh','1') ) .'">Actualizar estado</a>';
    echo '</form>';

    $total  = intval($batch['total']);
    $done   = intval($batch['done']);
    echo '<h2>Progreso</h2>';
    echo '<p><strong>Total:</strong> '.esc_html($total).' &nbsp; <strong>Procesados:</strong> '.esc_html($done).'</p>';

    echo '<h2>Último estado</h2>';
    echo '<pre style="max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;">'
        . esc_html( print_r($last,true) ) .'</pre>';

    echo '</div>';
}

// Construir URL con parámetros
function rep_mobilia_build_url(){
    $url = rep_get_setting('mobilia_url','');
    if ( ! $url ) return '';
    $params = rep_get_setting('mobilia_params',array());
    $q = array();
    if ( !empty($params['descripcionesHtml']) ) $q['descripcionesHtml']=1;
    if ( !empty($params['mostrarAlias']) )     $q['mostrarAlias']=1;
    if ( !empty($params['fotosAmpliada']) )    $q['fotosAmpliada']=1;
    if ( isset($params['marcaAgua']) )         $q['marcaAgua']=intval($params['marcaAgua']);
    if ( $q ) $url .= (strpos($url,'?')===false?'?':'&').http_build_query($q);
    return $url;
}

// Descargar XML
function rep_mobilia_fetch_xml(){
    $url = rep_mobilia_build_url();
    if ( ! $url ) return new WP_Error('no_url','No hay URL de Mobilia configurada.');
    $res = wp_remote_get($url,array('timeout'=>30,'redirection'=>3,'user-agent'=>'RealEstatePro/1.3'));
    if ( is_wp_error($res) ) return $res;
    $code = wp_remote_retrieve_response_code($res);
    if ( $code !== 200 ) return new WP_Error('http','HTTP '.$code);
    return wp_remote_retrieve_body($res);
}

// Parse XML
function rep_mobilia_parse_xml($str){
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($str);
    if ( ! $xml ) return new WP_Error('xml','Error parseando XML');
    return $xml;
}

// Guardar un inmueble
function rep_mobilia_map_and_save($node){
    $id   = (string)$node->Id;
    $ref  = (string)$node->Referencia;
    $tit  = (string)$node->Titulo;
    $desc = (string)($node->DescripcionAmpliada ?: $node->Descripcion);
    $grp  = (string)$node->GrupoInmueble; // tipo
    $prov = (string)$node->Provincia;
    $pob  = (string)$node->Poblacion;
    $zona = (string)$node->Zona;
    $lat  = (string)$node->Latitud;
    $lng  = (string)$node->Longitud;

    // Fallback título
    if ( trim($tit)==='' ){
        $parts=array(); if($grp)$parts[]=$grp; if($pob)$parts[]=$pob; if($zona)$parts[]=$zona; if($ref)$parts[]='Ref '.$ref;
        $tit = $parts ? implode(' · ',$parts) : 'Propiedad '.$id;
    }

    // Buscar existente
    $existing = get_posts(array(
        'post_type'=>'property',
        'meta_key'=>'external_id',
        'meta_value'=>$id,
        'posts_per_page'=>1,
        'post_status'=>array('publish','draft','trash')
    ));
    $postarr = array(
        'post_title'   => wp_strip_all_tags($tit),
        'post_content' => wp_kses_post($desc),
        'post_status'  => 'publish',
        'post_type'    => 'property'
    );
    $post_id = $existing ? wp_update_post(array_merge($postarr,array('ID'=>$existing[0]->ID)), true)
                         : wp_insert_post($postarr, true);
    if ( is_wp_error($post_id) ) return $post_id;

    // Operaciones y precios
    $pv=$pa=$pt=0; $ops=array();
    if ( isset($node->Operaciones) ){
        foreach($node->Operaciones->Operacion as $op){
            $tipo=strtolower((string)$op->Tipo);
            $val=floatval((string)$op->Precio);
            if($tipo==='venta'){ $pv=$val; $ops[]='venta'; }
            elseif($tipo==='alquiler'){ $pa=$val; $ops[]='alquiler'; }
            elseif($tipo==='traspaso'){ $pt=$val; $ops[]='traspaso'; }
        }
    }
    $precio = $pv ?: ($pa ?: $pt);
    update_post_meta($post_id,'precio',$precio);
    update_post_meta($post_id,'precio_venta',$pv);
    update_post_meta($post_id,'precio_alquiler',$pa);
    update_post_meta($post_id,'precio_traspaso',$pt);

    // Campos básicos
    update_post_meta($post_id,'external_source','mobilia');
    update_post_meta($post_id,'external_id',$id);
    update_post_meta($post_id,'referencia',$ref);
    if($lat!=='') update_post_meta($post_id,'lat',floatval($lat));
    if($lng!=='') update_post_meta($post_id,'lng',floatval($lng));

    if($pob)  wp_set_object_terms($post_id,$pob,'property_city');
    if($prov) wp_set_object_terms($post_id,$prov,'property_province');
    if($grp)  wp_set_object_terms($post_id,$grp,'property_type');
    if($zona) wp_set_object_terms($post_id,$zona,'property_zone');
    if(!empty($ops)) wp_set_object_terms($post_id,$ops,'property_operation');

    // Eficiencia energética
    $eraw = '';
    if ( isset($node->CertificadoEnergetico) ) $eraw = (string)$node->CertificadoEnergetico;
    elseif ( isset($node->Cert_Energetica) )   $eraw = (string)$node->Cert_Energetica;
    elseif ( isset($node->CertEnergetica) )    $eraw = (string)$node->CertEnergetica;
    $eraw = strtoupper(trim($eraw));
    $rating = '';
    if ( in_array($eraw, array('A','B','C','D','E','F','G'), true ) ) {
      $rating = $eraw;
    } elseif ( in_array($eraw, array('EN TRÁMITE','EN TRAMITE','EN TRAMITE.','PENDIENTE','EN PROCESO'), true) ) {
      $rating = 'EN TRAMITE';
    }
    if ( $rating ) update_post_meta($post_id, 'energy_rating', $rating);

    // Características desde Mobilia (Extras/Extra)
    $feat_map = array(
      'ASCENSOR'=>'ascensor','TERRAZA'=>'terraza','PISCINA'=>'piscina','EXTERIOR'=>'exterior',
      'SOLEADO'=>'soleado','AMUEBLADO'=>'amueblado','GARAJE'=>'garaje','TRASTERO'=>'trastero',
      'BALCÓN'=>'balcon','BALCON'=>'balcon','CALEFACCIÓN'=>'calefaccion','CALEFACCION'=>'calefaccion',
      'AIRE ACONDICIONADO'=>'aire_acondicionado','ARMARIOS EMPOTRADOS'=>'armarios_empotrados',
      'COCINA EQUIPADA'=>'cocina_equipada','JARDÍN'=>'jardin','JARDIN'=>'jardin',
      'MASCOTAS'=>'mascotas','ACCESIBLE'=>'accesible','VISTAS'=>'vistas','ALARMA'=>'alarma',
      'PORTERO'=>'portero','ZONA COMUNITARIA'=>'zona_comunitaria',
      // Etiquetas comerciales detectadas a veces como extra
      'NOVEDAD'=>'__LABEL:novedad','EXCLUSIVA'=>'__LABEL:en_exclusiva','RESERVADO'=>'__LABEL:reservado',
      'OPORTUNIDAD'=>'__LABEL:oportunidad','URGE'=>'__LABEL:urge','REBAJADO'=>'__LABEL:rebajado',
      'PRODUCTO ESTRELLA'=>'__LABEL:producto_estrella'
    );
    $label_set = '';
    if ( isset($node->Extras->Extra) ){
        foreach($node->Extras->Extra as $ex){
            $label = strtoupper( trim( (string)$ex ) );
            if ( isset($feat_map[$label]) ){
                if ( strpos($feat_map[$label],'__LABEL:') === 0 ){
                    $label_set = substr($feat_map[$label], 8);
                } else {
                    update_post_meta($post_id, $feat_map[$label], 1);
                }
            }
        }
    }
    if ($label_set) update_post_meta($post_id,'label',$label_set);

        // Etiqueta desde Mobilia (si existiese)
    // Intentamos leer nodos habituales: <Etiqueta>, <Tag>, <EstadoComercial>
    $label_raw = '';
    if ( isset($node->Etiqueta) ) $label_raw = (string)$node->Etiqueta;
    elseif( isset($node->Tag) ) $label_raw = (string)$node->Tag;
    elseif( isset($node->EstadoComercial) ) $label_raw = (string)$node->EstadoComercial;

    $label_raw = strtolower( sanitize_title($label_raw) );
    $allowed = array('urge','oportunidad','rebajado','ideal-inversores','producto-estrella','alquilado','vendido','reservado','origen-bancario','novedad','en-exclusiva','estudiantes','precio-negociable');
    if ( in_array($label_raw,$allowed,true) ){
        update_post_meta($post_id,'label_tag',$label_raw);
    }

    // Fotos (importar todas; la primera como destacada)
    if ( isset($node->Fotos->Foto) ) {
        $max_photos = 20;
        $ids = array();
        $i = 0;

        $existing_ids = (array) get_post_meta($post_id, 'gallery_ids', true);
        $existing_ids = array_filter(array_map('intval', $existing_ids));
        if ($existing_ids) $ids = $existing_ids;

        foreach ( $node->Fotos->Foto as $foto ) {
            if ( $i >= $max_photos ) break;
            $url = (string)($foto->Url ?: $foto);
            if ( ! $url ) { $i++; continue; }

            // Evitar duplicados por meta _rep_source_url
            $found = false;
            if ( $ids ) {
                foreach ( $ids as $aid ) {
                    $src = get_post_meta($aid, '_rep_source_url', true);
                    if ( $src === $url ) { $found = true; break; }
                }
            }
            if ( $found ) { $i++; continue; }

            $aid = rep_sideload_image_get_id( $url, $post_id, $tit );
            if ( ! is_wp_error($aid) ) {
                update_post_meta($aid, '_rep_source_url', $url);
                $ids[] = $aid;
                if ( ! has_post_thumbnail($post_id) ) {
                    set_post_thumbnail($post_id, $aid);
                }
            }
            $i++;
        }

        if ( $ids ) {
            update_post_meta( $post_id, 'gallery_ids', array_values( array_unique($ids) ) );
        }
    }

    return $post_id;
}

// Procesamiento por lotes
function rep_mobilia_process_batch(){
    $xml_str = rep_mobilia_fetch_xml();
    if ( is_wp_error($xml_str) ){
        update_option('rep_mobilia_last_status', array('error'=>$xml_str->get_error_message()));
        return;
    }
    $xml = rep_mobilia_parse_xml($xml_str);
    if ( is_wp_error($xml) ){
        update_option('rep_mobilia_last_status', array('error'=>$xml->get_error_message()));
        return;
    }

    $items = $xml->xpath('/Inmuebles/Inmueble');
    $total = is_array($items)? count($items) : 0;
    $batch = get_option('rep_mobilia_batch', array('offset'=>0,'done'=>0,'total'=>$total));
    if ( empty($batch['total']) ) $batch['total'] = $total;

    $offset = intval($batch['offset']);
    $limit  = intval(rep_get_setting('mobilia_batch_size',20));
    $end    = min($offset + $limit, $total);

    $imported = 0; $errors=array();
    for($i=$offset; $i<$end; $i++){
        $res = rep_mobilia_map_and_save($items[$i]);
        if ( is_wp_error($res) ) $errors[] = $res->get_error_message(); else $imported++;
    }

    $batch = array('offset'=>$end,'done'=>$end,'total'=>$total);
    update_option('rep_mobilia_batch',$batch,false);

    $status = array('msg'=>"Lote $offset-$end/$total",'imported'=>$imported);
    if ($errors) $status['errors'] = $errors;
    update_option('rep_mobilia_last_status',$status,false);

    if ( $end < $total && ! wp_next_scheduled('rep_mobilia_continue_batch') ){
        wp_schedule_single_event(time()+15,'rep_mobilia_continue_batch');
    }
}

add_action('rep_mobilia_run_sync','rep_mobilia_process_batch');
add_action('rep_mobilia_continue_batch','rep_mobilia_process_batch');