<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Submenú de sincronización
add_action('admin_menu', function(){
    add_submenu_page(
        'edit.php?post_type=property',
        'Mobilia API Sync',
        'Mobilia API',
        'manage_options',
        'rep-mobilia',
        'rep_mobilia_admin_page'
    );
});

// Página de administración
function rep_mobilia_admin_page(){
    if ( isset($_POST['rep_mobilia_manual']) && check_admin_referer('rep_mobilia_manual','rep_mobilia_manual_nonce') ){
        delete_option('rep_mobilia_batch');
        delete_transient('rep_mobilia_access_token'); // Limpiamos el token viejo al iniciar
        do_action('rep_mobilia_run_sync');
        echo '<div class="notice notice-success is-dismissible"><p>Sincronización iniciada. Se procesará por lotes automáticamente.</p></div>';
    }

    $last  = get_option('rep_mobilia_last_status', array());
    $batch = get_option('rep_mobilia_batch', array('page'=>1,'done'=>0,'total'=>0, 'finished' => false));

    echo '<div class="wrap"><h1>Mobilia – Sincronización API</h1>';

    echo '<form method="post" style="margin:1em 0;">';
    wp_nonce_field('rep_mobilia_manual','rep_mobilia_manual_nonce');
    echo '<input type="hidden" name="rep_mobilia_manual" value="1" />';
    submit_button('Iniciar sincronización ahora', 'primary', 'submit', false);
    echo ' <a class="button" href="'. esc_url( add_query_arg('rep_mobilia_refresh','1') ) .'">Actualizar estado</a>';
    echo '</form>';

    $total  = intval($batch['total']);
    $done   = intval($batch['done']);
    $finished = $batch['finished'] ?? false;
    echo '<h2>Progreso</h2>';
    if ($finished) echo '<p><strong>Sincronización completada.</strong></p>';
    echo '<p><strong>Total de inmuebles en Mobilia:</strong> '.esc_html($total).' &nbsp; <strong>Procesados hasta ahora:</strong> '.esc_html($done).'</p>';

    echo '<h2>Último estado</h2>';
    echo '<pre style="max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;">'
        . esc_html( print_r($last,true) ) .'</pre>';

    echo '</div>';
}

/**
 * Obtiene un token de acceso de la API de Mobilia, lo cachea en un transient.
 */
function rep_mobilia_get_access_token() {
    // 1. Revisa si ya tenemos un token válido cacheado
    $token = get_transient('rep_mobilia_access_token');
    if ( $token ) {
        return $token;
    }
    
    // 2. Si no hay token, lo solicita
    $client_id = rep_get_setting('mobilia_client_id', '');
    $client_secret = rep_get_setting('mobilia_client_secret', '');

    if ( ! $client_id || ! $client_secret ) {
        return new WP_Error('no_credentials', 'Faltan Client ID o Client Secret en los ajustes.');
    }
    
    $token_url = 'https://api.mobiliagestion.es/api/v1/token'; 
    
    $body = array(
        'grant_type'    => 'client_credentials',
        'client_id'     => trim($client_id),
        'client_secret' => trim($client_secret)
    );

    $res = wp_remote_post($token_url, array(
        'timeout' => 20,
        'body'    => $body,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        )
    ));

    if ( is_wp_error($res) ) {
        return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $response_body = wp_remote_retrieve_body($res);

    if ( $code !== 200 ) {
        return new WP_Error('token_http_error', 'Error HTTP ' . $code . ' al solicitar el token.');
    }

    $data = json_decode($response_body, true);
    
    if ( ! isset($data['access_token']) || ! isset($data['expires_in']) ) {
        return new WP_Error('token_invalid_response', 'La respuesta del token no es válida.');
    }

    // 3. Guarda el nuevo token en la caché (transient) con un tiempo de expiración
    $token = $data['access_token'];
    $expires_in = intval($data['expires_in']) - 60; // Restamos 1 minuto por seguridad

    set_transient('rep_mobilia_access_token', $token, $expires_in);

    return $token;
}


/**
 * Obtiene los inmuebles de la API usando el token de acceso.
 */
function rep_mobilia_fetch_properties_from_api($page = 1, $per_page = 20) {
    // Primero, obtenemos el token de acceso
    $access_token = rep_mobilia_get_access_token();
    if ( is_wp_error($access_token) ) {
        return $access_token; // Propagamos el error
    }

    $api_url = 'https://api.mobiliagestion.es/api/v1/inmuebles';
    
    // --- INICIO DE LA CORRECCIÓN ---
    $args = array(
        'NumeroPagina'      => $page,
        'TamanoPagina'      => $per_page,
        'MarcaAguaImagenes' => 'false', // Enviar como texto
        'DescripcionImagenes' => 'true'  // Enviar como texto
    );
    // --- FIN DE LA CORRECCIÓN ---

    $url = add_query_arg($args, $api_url);

    $headers = array(
        'Authorization' => 'Bearer ' . $access_token,
        'Accept'        => 'application/json'
    );
    
    $res = wp_remote_get($url, array(
        'timeout' => 30,
        'headers' => $headers
    ));

    if ( is_wp_error($res) ) {
        return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ( $code !== 200 ) {
        if ($code === 401) {
            delete_transient('rep_mobilia_access_token');
        }
        return new WP_Error('http_error', 'HTTP ' . $code . ' - ' . $body);
    }
    
    $data = json_decode($body, true);
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_Error('json_error', 'Error parseando JSON: ' . json_last_error_msg());
    }

    return $data;
}

// --- El resto del archivo no necesita cambios ---
function rep_mobilia_map_and_save($property_data){
    $id = (string)$property_data['idInmueble'];
    $ref = (string)$property_data['referencia'];
    
    $tit = !empty($property_data['tituloWeb']['txtTituloWeb']) ? $property_data['tituloWeb']['txtTituloWeb'] : '';
    if ( empty($tit) ) {
        $type = $property_data['tipoInmueble']['tipoInmueble'] ?? '';
        $city = $property_data['poblacion'] ?? '';
        $parts = array_filter([$type, 'en', $city, 'Ref ' . $ref]);
        $tit = implode(' ', $parts);
    }

    $desc = !empty($property_data['descripcionWeb']['txtDescripcionWeb']) ? $property_data['descripcionWeb']['txtDescripcionWeb'] : '';

    $existing = get_posts(array(
        'post_type' => 'property',
        'meta_key' => 'external_id',
        'meta_value' => $id,
        'posts_per_page' => 1,
        'post_status' => array('publish', 'draft', 'trash')
    ));

    $postarr = array(
        'post_title' => wp_strip_all_tags($tit),
        'post_content' => wp_kses_post($desc),
        'post_status' => 'publish',
        'post_type' => 'property'
    );

    $post_id = $existing ? wp_update_post(array_merge($postarr, array('ID' => $existing[0]->ID)), true) : wp_insert_post($postarr, true);

    if ( is_wp_error($post_id) ) {
        return $post_id;
    }

    update_post_meta($post_id, 'external_source', 'mobilia_api');
    update_post_meta($post_id, 'external_id', $id);
    update_post_meta($post_id, 'referencia', $ref);

    $ops = array();
    $pv = $pa = $pt = 0;
    if ($property_data['venta']) { $ops[] = 'venta'; $pv = floatval($property_data['precioVenta']); }
    if ($property_data['alquiler']) { $ops[] = 'alquiler'; $pa = floatval($property_data['precioAlquiler']); }
    if ($property_data['traspaso']) { $ops[] = 'traspaso'; $pt = floatval($property_data['precioTraspaso']); }
    
    $precio = $pv ?: ($pa ?: $pt);
    update_post_meta($post_id, 'precio', $precio);
    update_post_meta($post_id, 'precio_venta', $pv);
    update_post_meta($post_id, 'precio_alquiler', $pa);
    update_post_meta($post_id, 'precio_traspaso', $pt);
    if (!empty($ops)) wp_set_object_terms($post_id, $ops, 'property_operation');
    
    if (!empty($property_data['poblacion'])) wp_set_object_terms($post_id, $property_data['poblacion'], 'property_city');
    if (!empty($property_data['provincia'])) wp_set_object_terms($post_id, $property_data['provincia'], 'property_province');
    if (!empty($property_data['nombreZona'])) wp_set_object_terms($post_id, $property_data['nombreZona'], 'property_zone');
    if (!empty($property_data['tipoInmueble']['tipoInmueble'])) wp_set_object_terms($post_id, $property_data['tipoInmueble']['tipoInmueble'], 'property_type');

    if (!empty($property_data['latitud'])) update_post_meta($post_id, 'lat', floatval(str_replace(',', '.', $property_data['latitud'])));
    if (!empty($property_data['longitud'])) update_post_meta($post_id, 'lng', floatval(str_replace(',', '.', $property_data['longitud'])));

    $chars = $property_data['caracteristicas'] ?? [];
    if (isset($chars['metrosConstruidos'])) update_post_meta($post_id, 'superficie_construida', intval($chars['metrosConstruidos']));
    if (isset($chars['habitaciones'])) update_post_meta($post_id, 'habitaciones', intval($chars['habitaciones']));
    if (isset($chars['banos'])) update_post_meta($post_id, 'banos', intval($chars['banos']) + intval($chars['aseos'] ?? 0));
    
    $features_map = [
        'ascensor' => 'ascensor', 'piscinaPrivada' => 'piscina', 'terraza' => 'terraza',
        'exterior' => 'exterior', 'soleado' => 'soleado', 'amueblado' => 'amueblado',
        'garaje' => 'garaje', 'trastero' => 'trastero', 'calefaccion' => 'calefaccion',
        'aireAcondicionado' => 'aire_acondicionado', 'armarios' => 'armarios_empotrados',
        'cocinaAmueblada' => 'cocina_equipada', 'jardin' => 'jardin', 'admiteMascotas' => 'mascotas'
    ];
    foreach($features_map as $api_key => $meta_key) {
        if (!empty($chars[$api_key])) {
            update_post_meta($post_id, $meta_key, 1);
        } else {
            delete_post_meta($post_id, $meta_key);
        }
    }

    if (!empty($chars['consumo'])) update_post_meta($post_id, 'energy_consumption', floatval($chars['consumo']));
    if (!empty($chars['emisiones'])) update_post_meta($post_id, 'energy_emissions', floatval($chars['emisiones']));
    if (!empty($chars['idCalificacionEnergetica'])) {
        // Aquí necesitarías una función que traduzca el ID a la letra.
    }
    
    if (!empty($property_data['fotos']) && is_array($property_data['fotos'])) {
        $gallery_ids = [];
        $has_featured = has_post_thumbnail($post_id);

        usort($property_data['fotos'], function($a, $b) {
            return ($a['orden'] ?? 99) <=> ($b['orden'] ?? 99);
        });

        foreach ($property_data['fotos'] as $foto) {
            $url = $foto['url'] ?? null;
            if (!$url) continue;

            $attachment_id = attachment_url_to_postid(esc_url_raw($url));
            if (!$attachment_id) {
                $desc = $foto['descripcion'] ?? $tit;
                $attachment_id = rep_sideload_image_get_id($url, $post_id, $desc);
            }

            if (!is_wp_error($attachment_id)) {
                $gallery_ids[] = $attachment_id;
                if (!$has_featured || ($foto['destacada'] ?? false)) {
                    set_post_thumbnail($post_id, $attachment_id);
                    $has_featured = true;
                }
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($post_id, 'gallery_ids', $gallery_ids);
        }
    }

    return $post_id;
}


function rep_mobilia_process_batch(){
    $batch = get_option('rep_mobilia_batch', array('page'=>1,'done'=>0,'total'=>0, 'finished' => false));
    if ($batch['finished']) return;

    $page = intval($batch['page']);
    $per_page = intval(rep_get_setting('mobilia_batch_size', 20));

    $data = rep_mobilia_fetch_properties_from_api($page, $per_page);

    if ( is_wp_error($data) ){
        update_option('rep_mobilia_last_status', array('error'=>$data->get_error_message()));
        return;
    }

    $items = $data['elementos'] ?? [];
    $total = $data['totalElementos'] ?? 0;
    
    $imported = 0; $errors = [];
    if (!empty($items)) {
        foreach($items as $property) {
            $res = rep_mobilia_map_and_save($property);
            if (is_wp_error($res)) {
                $errors[] = $res->get_error_message();
            } else {
                $imported++;
            }
        }
    }

    $new_done_count = $batch['done'] + $imported;
    $next_page = $page + 1;
    $is_finished = ($new_done_count >= $total) || empty($items);

    $batch_update = array(
        'page' => $next_page,
        'done' => $new_done_count,
        'total' => $total,
        'finished' => $is_finished,
    );
    update_option('rep_mobilia_batch', $batch_update, false);

    $status = array('msg' => "Lote página $page procesado", 'imported' => $imported, 'total_api' => $total);
    if ($errors) $status['errors'] = $errors;
    update_option('rep_mobilia_last_status', $status, false);

    if ( ! $is_finished && ! wp_next_scheduled('rep_mobilia_continue_batch') ){
        wp_schedule_single_event(time() + 15, 'rep_mobilia_continue_batch');
    }
}

add_action('rep_mobilia_run_sync', 'rep_mobilia_process_batch');
add_action('rep_mobilia_continue_batch', 'rep_mobilia_process_batch');