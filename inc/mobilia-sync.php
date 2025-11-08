<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Definir los hooks para las tareas cron
define('REP_CRON_HOOK_BATCH', 'rep_mobilia_process_batch_job');
define('REP_CRON_HOOK_CLEANUP', 'rep_mobilia_cleanup_job');

// --- Interfaz de Administración ---

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

function rep_mobilia_admin_page(){
    // Iniciar manualmente
    if ( isset($_POST['rep_mobilia_manual_start']) && check_admin_referer('rep_mobilia_manual_start_nonce') ){
        // Comprobar si ya hay una sincronización en curso
        $sync_status = get_option('rep_mobilia_sync_status', ['status' => 'idle']);
        if ($sync_status['status'] === 'idle' || $sync_status['status'] === 'completed' || $sync_status['status'] === 'error') {
            rep_mobilia_start_sync();
            echo '<div class="notice notice-success is-dismissible"><p>'.__('Sincronización iniciada en segundo plano.', 'real-estate-pro').'</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>'.__('Ya hay una sincronización en curso.', 'real-estate-pro').'</p></div>';
        }
    }
    
    // Forzar cancelación (por si se queda atascado)
     if ( isset($_POST['rep_mobilia_manual_cancel']) && check_admin_referer('rep_mobilia_manual_cancel_nonce') ){
         rep_mobilia_cancel_sync();
         echo '<div class="notice notice-warning is-dismissible"><p>'.__('Intento de cancelación de la sincronización enviado.', 'real-estate-pro').'</p></div>';
     }

    $sync_status = get_option('rep_mobilia_sync_status', ['status' => 'idle']);
    $last_run_log = get_option('rep_mobilia_last_run_log', []);
    $is_running = in_array($sync_status['status'], ['fetching_wp_ids', 'processing_batch', 'cleaning_up']);

    echo '<div class="wrap"><h1>'.__('Mobilia – Sincronización API', 'real-estate-pro').'</h1>';

    // Formulario para Iniciar/Cancelar
    echo '<form method="post" style="margin-bottom: 20px;">';
    if ($is_running) {
        wp_nonce_field('rep_mobilia_manual_cancel_nonce');
        echo '<input type="hidden" name="rep_mobilia_manual_cancel" value="1" />';
        submit_button(__('Cancelar Sincronización', 'real-estate-pro'), 'delete', 'submit', false, ['disabled' => false]); // Siempre permitir cancelar
         echo '<p style="color:orange; font-weight:bold;">'.__('Sincronización en curso...', 'real-estate-pro').'</p>';
    } else {
        wp_nonce_field('rep_mobilia_manual_start_nonce');
        echo '<input type="hidden" name="rep_mobilia_manual_start" value="1" />';
        submit_button(__('Iniciar Sincronización Ahora', 'real-estate-pro'), 'primary', 'submit', false);
        if ($sync_status['status'] === 'completed') {
             echo '<p style="color:green;">'.__('Última sincronización completada.', 'real-estate-pro').'</p>';
        } elseif ($sync_status['status'] === 'error') {
            echo '<p style="color:red;">'.__('La última sincronización terminó con error.', 'real-estate-pro').'</p>';
        }
    }
     echo ' <a class="button" href="'. esc_url( admin_url('edit.php?post_type=property&page=rep-mobilia') ) .'">'.__('Actualizar Estado', 'real-estate-pro').'</a>';
    echo '</form>';
    
    // Mostrar estado detallado
    echo '<h2>'.__('Estado Actual', 'real-estate-pro').'</h2>';
    echo '<p><strong>'.__('Fase', 'real-estate-pro').':</strong> ';
    switch ($sync_status['status']) {
        case 'idle': echo __('Inactivo', 'real-estate-pro'); break;
        case 'fetching_wp_ids': echo __('Obteniendo IDs locales...', 'real-estate-pro'); break;
        case 'processing_batch': 
            echo sprintf(
                __('Procesando lote (Página %d / Aprox. %d total)', 'real-estate-pro'), 
                esc_html($sync_status['current_page'] ?? 0), 
                esc_html($sync_status['total_items'] ?? 0)
            ); 
            break;
        case 'cleaning_up': echo __('Limpiando inmuebles obsoletos...', 'real-estate-pro'); break;
        case 'completed': echo __('Completado', 'real-estate-pro'); break;
        case 'error': echo '<span style="color:red;">'.__('Error', 'real-estate-pro').'</span>'; break;
        default: echo esc_html($sync_status['status']);
    }
    echo '</p>';
    if (isset($sync_status['last_update'])) {
        echo '<p><strong>'.__('Última Actualización', 'real-estate-pro').':</strong> '.esc_html(wp_date( get_option('date_format') . ' ' . get_option('time_format'), $sync_status['last_update'] )).'</p>';
    }
     if (isset($sync_status['message']) && $sync_status['message']) {
        $color = ($sync_status['status'] === 'error') ? 'red' : 'inherit';
        echo '<p><strong>'.__('Mensaje', 'real-estate-pro').':</strong> <span style="color:'.$color.';">'.esc_html($sync_status['message']).'</span></p>';
    }


    echo '<h2>'.__('Registro Última Ejecución', 'real-estate-pro').'</h2>';
    if (!empty($last_run_log)) {
         echo '<pre style="max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;">';
         echo esc_html( print_r($last_run_log, true) );
         echo '</pre>';
    } else {
        echo '<p>'.__('No hay registro disponible.', 'real-estate-pro').'</p>';
    }

    echo '</div>'; // close wrap
}

// --- Lógica de Sincronización en Segundo Plano ---

/**
 * Inicia el proceso de sincronización. Obtiene IDs locales y programa el primer lote.
 */
function rep_mobilia_start_sync() {
    // 1. Marcar el inicio y limpiar datos previos
    update_option('rep_mobilia_sync_status', ['status' => 'fetching_wp_ids', 'last_update' => time()]);
    delete_option('rep_mobilia_processed_ids'); 
    delete_option('rep_mobilia_wp_ids_before_sync');
    delete_option('rep_mobilia_last_run_log'); // Limpiar log anterior
    delete_transient('rep_mobilia_access_token'); // Forzar nuevo token

    // 2. Obtener todos los IDs de posts 'property' gestionados por mobilia ANTES de empezar
    $args = array(
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'trash'), // Incluir todos los estados
        'meta_query' => array(
            array(
                'key' => 'external_source',
                'value' => 'mobilia_api', // Asegúrate que este valor coincida con el guardado en map_and_save
                'compare' => '=',
            ),
        ),
        'fields' => 'ids', // Solo necesitamos los IDs
    );
    $wp_property_ids = get_posts($args);
    update_option('rep_mobilia_wp_ids_before_sync', $wp_property_ids);

    // 3. Inicializar la lista de IDs procesados en esta sincronización
    update_option('rep_mobilia_processed_ids', []);

    // 4. Programar la primera tarea de procesamiento (inmediatamente)
    update_option('rep_mobilia_sync_status', [
        'status' => 'processing_batch', 
        'current_page' => 1, 
        'total_items' => 0, // Aún no lo sabemos
        'last_update' => time()
    ]);
    wp_schedule_single_event(time(), REP_CRON_HOOK_BATCH);
    
    error_log('[Mobilia Sync] Proceso iniciado. '.count($wp_property_ids).' inmuebles locales encontrados. Programada primera tarea.');
}

/**
 * Tarea Cron: Procesa un lote (página) de inmuebles desde la API.
 */
function rep_mobilia_process_batch_job() {
    $sync_status = get_option('rep_mobilia_sync_status');
    $current_page = $sync_status['current_page'] ?? 1;
    $per_page = intval(rep_get_setting('mobilia_batch_size', 5));
    $run_log = ['page' => $current_page, 'processed' => 0, 'errors' => []];

    error_log("[Mobilia Sync] Ejecutando tarea: procesar página $current_page");

    // Obtener datos de la API para la página actual
    $data = rep_mobilia_fetch_properties_from_api($current_page, $per_page);

    if ( is_wp_error($data) ) {
        $error_message = $data->get_error_message();
        error_log("[Mobilia Sync] Error al obtener página $current_page: $error_message");
        update_option('rep_mobilia_sync_status', ['status' => 'error', 'message' => $error_message, 'last_update' => time()]);
        update_option('rep_mobilia_last_run_log', ['error' => $error_message]); // Guardar error en log
        return; // Detener el proceso
    }

    $items = $data['elementos'] ?? [];
    $total_items = $data['totalElementos'] ?? $sync_status['total_items'] ?? 0; // Actualizar total si está disponible
    $processed_ids_current_run = get_option('rep_mobilia_processed_ids', []);

    if (!empty($items)) {
        foreach($items as $property_data) {
            $mobilia_id = (string)($property_data['idInmueble'] ?? null);
            if (!$mobilia_id) continue;

            $res = rep_mobilia_map_and_save($property_data); // Esta función añade o actualiza el post
            
            if (is_wp_error($res)) {
                 $run_log['errors'][] = "Mobilia ID $mobilia_id: " . $res->get_error_message();
            } else {
                 $run_log['processed']++;
                 // Añadir el ID de Mobilia a la lista de procesados en esta sincronización
                 if (!in_array($mobilia_id, $processed_ids_current_run)) {
                      $processed_ids_current_run[] = $mobilia_id;
                 }
            }
        }
        update_option('rep_mobilia_processed_ids', $processed_ids_current_run); // Guardar IDs procesados
        
        // Calcular si hemos terminado (basado en el número de items procesados hasta ahora)
        $total_processed_so_far = count($processed_ids_current_run);
        $next_page = $current_page + 1;
        $approx_total_pages = ($total_items > 0) ? ceil($total_items / $per_page) : $current_page + 1; // Estimación

        // Si la API devuelve menos elementos que el tamaño de página, asumimos que es la última página
        $is_last_page = count($items) < $per_page || $current_page >= $approx_total_pages; 

        if ($is_last_page) {
            // Todos los lotes procesados, programar limpieza
            update_option('rep_mobilia_sync_status', [
                'status' => 'cleaning_up', 
                'total_items' => $total_items, 
                'last_update' => time()
             ]);
             wp_schedule_single_event(time() + 5, REP_CRON_HOOK_CLEANUP);
             error_log("[Mobilia Sync] Último lote procesado (Página $current_page). Programada limpieza.");
        } else {
            // Programar el siguiente lote
            update_option('rep_mobilia_sync_status', [
                'status' => 'processing_batch', 
                'current_page' => $next_page, 
                'total_items' => $total_items, 
                'last_update' => time()
            ]);
            wp_schedule_single_event(time() + 10, REP_CRON_HOOK_BATCH); // Esperar 10 segundos
             error_log("[Mobilia Sync] Lote $current_page procesado. Programado siguiente lote (Página $next_page).");
        }

    } else {
        // No hay más items, pasar a limpieza
        update_option('rep_mobilia_sync_status', [
             'status' => 'cleaning_up', 
             'total_items' => $total_items, 
             'last_update' => time()
        ]);
        wp_schedule_single_event(time() + 5, REP_CRON_HOOK_CLEANUP);
         error_log("[Mobilia Sync] No se encontraron más inmuebles en la API (Página $current_page). Programada limpieza.");
    }
    
     update_option('rep_mobilia_last_run_log', $run_log); // Guardar log de esta ejecución
}

/**
 * Tarea Cron: Compara IDs y elimina/envía a papelera los obsoletos.
 */
function rep_mobilia_cleanup_job() {
    error_log("[Mobilia Sync] Ejecutando tarea: limpieza.");
    
    $wp_ids_before_sync = get_option('rep_mobilia_wp_ids_before_sync', []);
    $processed_mobilia_ids = get_option('rep_mobilia_processed_ids', []); // IDs de Mobilia que SÍ existen
    $run_log = ['deleted_count' => 0, 'errors' => []];

    if (empty($wp_ids_before_sync)) {
         error_log("[Mobilia Sync] Limpieza: No había inmuebles locales para verificar.");
    } else {
        // Convertir los IDs de Mobilia procesados a un mapa para búsqueda rápida
        $processed_mobilia_ids_map = array_flip($processed_mobilia_ids);

        foreach ($wp_ids_before_sync as $wp_post_id) {
            $mobilia_id = get_post_meta($wp_post_id, 'external_id', true);

            // Si el inmueble local no tiene ID externo o si su ID externo no está en la lista de procesados, eliminarlo.
            if (empty($mobilia_id) || !isset($processed_mobilia_ids_map[$mobilia_id])) {
                // Verificar si el post aún existe antes de intentar borrarlo
                if (get_post_status($wp_post_id)) { 
                    $result = wp_trash_post($wp_post_id); // Enviar a papelera en lugar de borrar permanentemente
                    if ($result) {
                        $run_log['deleted_count']++;
                         error_log("[Mobilia Sync] Limpieza: Inmueble obsoleto enviado a papelera (WP ID: $wp_post_id, Mobilia ID: $mobilia_id)");
                    } else {
                         $run_log['errors'][] = "No se pudo enviar a papelera WP ID: $wp_post_id";
                         error_log("[Mobilia Sync] Limpieza ERROR: No se pudo enviar a papelera WP ID: $wp_post_id");
                    }
                }
            }
        }
    }

    // Marcar como completado y limpiar datos temporales
    update_option('rep_mobilia_sync_status', ['status' => 'completed', 'last_update' => time()]);
    delete_option('rep_mobilia_processed_ids');
    delete_option('rep_mobilia_wp_ids_before_sync');
    update_option('rep_mobilia_last_run_log', $run_log); // Guardar log de limpieza
    
    error_log("[Mobilia Sync] Proceso de sincronización completado. ".$run_log['deleted_count']." inmuebles obsoletos enviados a papelera.");
}

/**
* Cancela cualquier tarea de sincronización pendiente.
*/
function rep_mobilia_cancel_sync() {
    wp_clear_scheduled_hook(REP_CRON_HOOK_BATCH);
    wp_clear_scheduled_hook(REP_CRON_HOOK_CLEANUP);
    update_option('rep_mobilia_sync_status', ['status' => 'idle', 'message' => 'Sincronización cancelada manualmente.', 'last_update' => time()]);
    // Considera si quieres borrar las opciones temporales aquí también
    // delete_option('rep_mobilia_processed_ids');
    // delete_option('rep_mobilia_wp_ids_before_sync');
    error_log("[Mobilia Sync] Intento de cancelación manual.");
}

// --- Registro y desregistro de tareas Cron ---

// Asegurarse de limpiar las tareas al desactivar el plugin
register_deactivation_hook(REP_PATH . 'real-estate-pro.php', function(){
    wp_clear_scheduled_hook(REP_CRON_HOOK_BATCH);
    wp_clear_scheduled_hook(REP_CRON_HOOK_CLEANUP);
    // Podrías resetear el estado aquí si lo deseas
    // delete_option('rep_mobilia_sync_status');
});

// Registrar las acciones para los hooks de cron
add_action(REP_CRON_HOOK_BATCH, 'rep_mobilia_process_batch_job');
add_action(REP_CRON_HOOK_CLEANUP, 'rep_mobilia_cleanup_job');


// --- Funciones de soporte (obtener token, mapear datos) ---
// (Estas funciones rep_mobilia_get_access_token y rep_mobilia_map_and_save permanecen igual que en la versión anterior)

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
         error_log("[Mobilia Sync] Error HTTP $code al solicitar token: $response_body");
        return new WP_Error('token_http_error', 'Error HTTP ' . $code . ' al solicitar el token.');
    }

    $data = json_decode($response_body, true);
    
    if ( ! isset($data['access_token']) || ! isset($data['expires_in']) ) {
        error_log("[Mobilia Sync] Respuesta de token inválida: $response_body");
        return new WP_Error('token_invalid_response', 'La respuesta del token no es válida.');
    }

    // 3. Guarda el nuevo token en la caché (transient) con un tiempo de expiración
    $token = $data['access_token'];
    $expires_in = intval($data['expires_in']) - 60; // Restamos 1 minuto por seguridad

    set_transient('rep_mobilia_access_token', $token, $expires_in);
     error_log("[Mobilia Sync] Token de acceso obtenido y cacheado.");

    return $token;
}

/**
 * Obtiene los inmuebles de la API usando el token de acceso.
 */
function rep_mobilia_fetch_properties_from_api($page = 1, $per_page = 20) {
    // Primero, obtenemos el token de acceso
    $access_token = rep_mobilia_get_access_token();
    if ( is_wp_error($access_token) ) {
         error_log("[Mobilia Sync] Fallo al obtener token de acceso: ".$access_token->get_error_message());
        return $access_token; // Propagamos el error
    }

    $api_url = 'https://api.mobiliagestion.es/api/v1/inmuebles';
    
    $args = array(
        'NumeroPagina'      => $page,
        'TamanoPagina'      => $per_page,
        'MarcaAguaImagenes' => 'false', // Enviar como texto
        'DescripcionImagenes' => 'true'  // Enviar como texto
    );

    $url = add_query_arg($args, $api_url);

    $headers = array(
        'Authorization' => 'Bearer ' . $access_token,
        'Accept'        => 'application/json'
    );
    
    $res = wp_remote_get($url, array(
        'timeout' => 45, // Aumentamos un poco el timeout para la petición de datos
        'headers' => $headers
    ));

    if ( is_wp_error($res) ) {
         error_log("[Mobilia Sync] WP Error al obtener inmuebles (página $page): ".$res->get_error_message());
        return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ( $code !== 200 ) {
         error_log("[Mobilia Sync] Error HTTP $code al obtener inmuebles (página $page): $body");
        if ($code === 401) { // Error específico de token inválido/expirado
            error_log("[Mobilia Sync] Error 401: Token probablemente expirado. Eliminando transient.");
            delete_transient('rep_mobilia_access_token');
        }
        return new WP_Error('http_error', 'HTTP ' . $code . ' - ' . $body);
    }
    
    $data = json_decode($body, true);
    if ( json_last_error() !== JSON_ERROR_NONE ) {
         error_log("[Mobilia Sync] Error al parsear JSON de inmuebles (página $page): ".json_last_error_msg());
        return new WP_Error('json_error', 'Error parseando JSON: ' . json_last_error_msg());
    }
    
     error_log("[Mobilia Sync] Inmuebles obtenidos correctamente para página $page (".count($data['elementos'] ?? [])." items).");
    return $data;
}


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

    $existing_posts = get_posts(array(
        'post_type' => 'property',
        'meta_key' => 'external_id',
        'meta_value' => $id,
        'posts_per_page' => 1,
        'post_status' => array('publish', 'draft', 'trash'), // Buscar en todos los estados
         'fields' => 'ids' // Optimización
    ));
    $existing_id = !empty($existing_posts) ? $existing_posts[0] : null;

    $postarr = array(
        'post_title' => wp_strip_all_tags($tit),
        'post_content' => wp_kses_post($desc),
        'post_status' => 'publish', // Siempre publicamos o actualizamos a publicado
        'post_type' => 'property'
    );

    if ($existing_id) {
         // Si existe (incluso en papelera), lo actualizamos y restauramos si es necesario
         $postarr['ID'] = $existing_id;
         // Si estaba en la papelera, wp_update_post lo restaura a 'publish' si le pasamos ese estado
         $post_id = wp_update_post($postarr, true); 
         if (!is_wp_error($post_id)) {
            error_log("[Mobilia Sync] Inmueble actualizado (WP ID: $post_id, Mobilia ID: $id)");
         }
    } else {
        // Si no existe, lo insertamos
        $post_id = wp_insert_post($postarr, true);
         if (!is_wp_error($post_id)) {
            error_log("[Mobilia Sync] Inmueble nuevo insertado (WP ID: $post_id, Mobilia ID: $id)");
         }
    }

    if ( is_wp_error($post_id) ) {
        error_log("[Mobilia Sync] Error al guardar/actualizar post para Mobilia ID $id: " . $post_id->get_error_message());
        return $post_id; // Devolver el error para registrarlo
    }

    // --- Metadatos y Taxonomías ---
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
    else wp_delete_object_term_relationships($post_id, 'property_operation'); // Limpiar si no hay operaciones
    
    // Asignar términos (asegurándose de que existen)
    $taxonomies_to_update = [
        'property_city' => $property_data['poblacion'] ?? null,
        'property_province' => $property_data['provincia'] ?? null,
        'property_zone' => $property_data['nombreZona'] ?? null,
        'property_type' => $property_data['tipoInmueble']['tipoInmueble'] ?? null,
    ];
    foreach ($taxonomies_to_update as $tax => $term_name) {
        if ($term_name) {
            $term = get_term_by('name', $term_name, $tax);
            if (!$term) {
                // Si el término no existe, lo creamos
                $term_info = wp_insert_term($term_name, $tax);
                if (!is_wp_error($term_info)) {
                    $term_id = $term_info['term_id'];
                    wp_set_object_terms($post_id, $term_id, $tax, false); // false para reemplazar
                } else {
                     error_log("[Mobilia Sync] Error creando término '$term_name' en taxonomía '$tax': ".$term_info->get_error_message());
                }
            } else {
                 wp_set_object_terms($post_id, $term->term_id, $tax, false); // false para reemplazar
            }
        } else {
             wp_delete_object_term_relationships($post_id, $tax); // Limpiar si no viene término
        }
    }


    if (!empty($property_data['latitud'])) update_post_meta($post_id, 'lat', floatval(str_replace(',', '.', $property_data['latitud']))); else delete_post_meta($post_id, 'lat');
    if (!empty($property_data['longitud'])) update_post_meta($post_id, 'lng', floatval(str_replace(',', '.', $property_data['longitud']))); else delete_post_meta($post_id, 'lng');

    $chars = $property_data['caracteristicas'] ?? [];
    if (isset($chars['metrosConstruidos'])) update_post_meta($post_id, 'superficie_construida', intval($chars['metrosConstruidos'])); else delete_post_meta($post_id, 'superficie_construida');
    if (isset($chars['habitaciones'])) update_post_meta($post_id, 'habitaciones', intval($chars['habitaciones'])); else delete_post_meta($post_id, 'habitaciones');
    if (isset($chars['banos'])) update_post_meta($post_id, 'banos', intval($chars['banos']) + intval($chars['aseos'] ?? 0)); else delete_post_meta($post_id, 'banos');
    
    // Características booleanas: obtener todas las posibles del sistema y marcarlas/desmarcarlas
     if (!function_exists('rep_get_feature_groups')) require_once REP_PATH . 'inc/utils.php';
     $all_feature_keys = [];
     foreach (rep_get_feature_groups() as $group) {
         $all_feature_keys = array_merge($all_feature_keys, array_keys($group['items']));
     }
     
    $api_features_map = [ // Mapeo de clave API Mobilia -> clave meta WordPress
        'ascensor' => 'ascensor', 'piscinaPrivada' => 'piscina', 'terraza' => 'terraza',
        'exterior' => 'exterior', 'soleado' => 'soleado', 'amueblado' => 'amueblado',
        'garaje' => 'garaje', 'plazasGaraje' => 'garaje', // Mapear ambos a garaje
        'trastero' => 'trastero', 
        'calefaccion' => 'calefaccion', // Necesitaría lógica si queremos guardar el tipo ('idTipoCalefaccion')
        'aireAcondicionado' => 'aire_acondicionado', 
        'armarios' => 'armarios_empotrados',
        'cocinaAmueblada' => 'cocina_equipada', 
        'jardin' => 'jardin', 
        'admiteMascotas' => 'mascotas',
        'adaptado' => 'accesible', // Mapear adaptado a accesible
        'accesoMinusvalidos' => 'accesible', // Mapear también
        'alarmaInterior' => 'alarma', 'alarmaPerimetral' => 'alarma', // Mapear ambas a alarma
        'conserje' => 'portero', // Mapear conserje a portero
        'zonasComunes' => 'zona_comunitaria',
        'vallado' => 'vallado',
        'agua' => 'con_agua',
        'luz' => 'con_luz',
        //'edificable' => ??? // No veo un campo claro en la API, revisar `idTipoCalificacionSuelo` o `calificacionSuelo`
        'pozo' => 'pozo',
        //'fosa_septica' => ??? // No veo un campo claro en la API
        'balcon' => 'balcon' // Asumiendo que `balcon` existe en $chars
    ];

    foreach ($all_feature_keys as $meta_key) {
        $found_in_api = false;
        foreach($api_features_map as $api_key => $wp_key) {
             if ($wp_key === $meta_key && !empty($chars[$api_key])) {
                update_post_meta($post_id, $meta_key, 1);
                $found_in_api = true;
                break; // Marcar y salir si encontramos una coincidencia
             }
        }
        // Si no se encontró ninguna clave API mapeada a esta clave meta, la borramos
        if (!$found_in_api) {
            delete_post_meta($post_id, $meta_key);
        }
    }

    if (!empty($chars['consumo'])) update_post_meta($post_id, 'energy_consumption', floatval($chars['consumo'])); else delete_post_meta($post_id, 'energy_consumption');
    if (!empty($chars['emisiones'])) update_post_meta($post_id, 'energy_emissions', floatval($chars['emisiones'])); else delete_post_meta($post_id, 'energy_emissions');
    
    // Mapeo básico de ID Calificación Energética a Letra (¡AJUSTAR SEGÚN LOS IDs REALES!)
    $energy_rating_map = [ 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'EN TRAMITE' /* ... etc */ ];
    $energy_rating_letter = '';
    if (!empty($chars['idCalificacionEnergetica'])) {
        $rating_id = intval($chars['idCalificacionEnergetica']);
         if (isset($energy_rating_map[$rating_id])) {
             $energy_rating_letter = $energy_rating_map[$rating_id];
         } else {
              error_log("[Mobilia Sync] ID Calificación energética desconocido: $rating_id para WP ID $post_id");
              // Podrías poner 'EN TRAMITE' o '' por defecto
              $energy_rating_letter = ''; 
         }
    }
    update_post_meta($post_id, 'energy_rating', $energy_rating_letter);
    
    
    // --- Gestión de Fotos ---
    $current_gallery_ids = get_post_meta($post_id, 'gallery_ids', true);
    if (!is_array($current_gallery_ids)) $current_gallery_ids = [];
    $current_gallery_ids = array_filter(array_map('intval', $current_gallery_ids)); // Asegurar array de ints
    
    $api_photos = $property_data['fotos'] ?? [];
    $new_gallery_ids = [];
    $featured_image_id = null;
    $has_featured_from_api = false;

    if (!empty($api_photos)) {
        // Ordenar fotos por el campo 'orden' de la API
        usort($api_photos, function($a, $b) {
            return ($a['orden'] ?? 99) <=> ($b['orden'] ?? 99);
        });

        foreach ($api_photos as $foto) {
            $url = $foto['url'] ?? null;
            if (!$url) continue;

            $desc = $foto['descripcion'] ?? $tit;
            
            // Intentar encontrar la imagen por URL de origen (más fiable que attachment_url_to_postid)
             $attachment_id = null;
             $args = [
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 1,
                'meta_query' => [ [ 'key' => '_rep_mobilia_photo_url', 'value' => esc_url_raw($url) ] ]
             ];
             $found_attachments = get_posts($args);
             if ($found_attachments) {
                 $attachment_id = $found_attachments[0]->ID;
                 // Actualizar descripción si ha cambiado? (Opcional)
                 // if ($desc !== $found_attachments[0]->post_content) {
                 //     wp_update_post(['ID' => $attachment_id, 'post_content' => $desc]);
                 // }
             } else {
                // Si no existe, la descargamos
                 error_log("[Mobilia Sync] Descargando nueva imagen: $url para WP ID $post_id");
                $attachment_id = rep_sideload_image_get_id($url, $post_id, $desc);
                if (!is_wp_error($attachment_id)) {
                    // Guardamos la URL original para futuras comprobaciones
                    update_post_meta($attachment_id, '_rep_mobilia_photo_url', esc_url_raw($url));
                } else {
                     error_log("[Mobilia Sync] Error al descargar imagen $url: " . $attachment_id->get_error_message());
                     $attachment_id = null; // Asegurarse de que no se añade si hay error
                }
             }

            if ($attachment_id) {
                $new_gallery_ids[] = $attachment_id;
                if (($foto['destacada'] ?? false) || !$featured_image_id) { // Marcar como destacada si lo indica la API o si es la primera
                    $featured_image_id = $attachment_id;
                    $has_featured_from_api = true;
                }
            }
        }
    }
    
    // Comparar galerías y eliminar adjuntos obsoletos (solo los gestionados por mobilia para este post)
     $ids_to_delete = array_diff($current_gallery_ids, $new_gallery_ids);
     foreach ($ids_to_delete as $attachment_id_to_delete) {
        // Doble chequeo: asegurarnos que esta imagen vino de mobilia y pertenece a este post
        $is_mobilia_photo = get_post_meta($attachment_id_to_delete, '_rep_mobilia_photo_url', true);
        $parent_post = get_post($attachment_id_to_delete);
        if ($is_mobilia_photo && $parent_post && $parent_post->post_parent == $post_id) {
            wp_delete_attachment($attachment_id_to_delete, true); // true = forzar borrado
             error_log("[Mobilia Sync] Imagen obsoleta eliminada (Attachment ID: $attachment_id_to_delete) de WP ID $post_id");
        }
     }

    // Actualizar la galería y la imagen destacada
    update_post_meta( $post_id, 'gallery_ids', $new_gallery_ids );
    if ($featured_image_id) {
         set_post_thumbnail($post_id, $featured_image_id);
    } elseif (!$has_featured_from_api && empty($new_gallery_ids)) {
         // Si la API no envió fotos y antes sí había, eliminamos la destacada
         delete_post_thumbnail($post_id);
    }
    // Si $has_featured_from_api es false pero $new_gallery_ids no está vacío,
    // la primera imagen descargada ($new_gallery_ids[0]) será la destacada por defecto.


    return $post_id;
}


function rep_mobilia_process_batch(){
    $batch = get_option('rep_mobilia_batch', array('page'=>1,'done'=>0,'total'=>0, 'finished' => false));
    if ($batch['finished']) return;

    $page = intval($batch['page']);
    $per_page = intval(rep_get_setting('mobilia_batch_size', 5)); // Usar 5 como default

    $data = rep_mobilia_fetch_properties_from_api($page, $per_page);

    if ( is_wp_error($data) ){
        update_option('rep_mobilia_sync_status', ['status' => 'error', 'message' => $data->get_error_message(), 'last_update' => time()]);
        update_option('rep_mobilia_last_run_log', ['error' => $data->get_error_message()]);
        return; // Detener
    }

    $items = $data['elementos'] ?? [];
    $total = $data['totalElementos'] ?? $batch['total'] ?? 0; // Mantener el total si ya lo teníamos
    $processed_ids_current_run = get_option('rep_mobilia_processed_ids', []);
    
    $run_log = ['page' => $page, 'processed_in_batch' => 0, 'errors' => []];

    if (!empty($items)) {
        foreach($items as $property) {
            $mobilia_id = (string)($property['idInmueble'] ?? null);
            if (!$mobilia_id) continue;

            $res = rep_mobilia_map_and_save($property);
            
            if (is_wp_error($res)) {
                 $run_log['errors'][] = "Mobilia ID $mobilia_id: " . $res->get_error_message();
            } else {
                 $run_log['processed_in_batch']++;
                 if (!in_array($mobilia_id, $processed_ids_current_run)) {
                      $processed_ids_current_run[] = $mobilia_id;
                 }
            }
        }
        update_option('rep_mobilia_processed_ids', $processed_ids_current_run); 
        
        $total_processed_overall = count($processed_ids_current_run); // Recalcular total procesado
        $next_page = $page + 1;
        $is_last_page_from_api = count($items) < $per_page; // Condición más fiable para saber si es la última página

        if ($is_last_page_from_api) {
             update_option('rep_mobilia_sync_status', [
                'status' => 'cleaning_up', 
                'total_items' => $total, // Actualizar total final
                'processed_items' => $total_processed_overall,
                'last_update' => time()
             ]);
             wp_schedule_single_event(time() + 5, REP_CRON_HOOK_CLEANUP);
             error_log("[Mobilia Sync] Último lote procesado (Página $page). Programada limpieza. Total procesado: $total_processed_overall");
        } else {
             update_option('rep_mobilia_sync_status', [
                'status' => 'processing_batch', 
                'current_page' => $next_page, 
                'total_items' => $total, 
                'processed_items' => $total_processed_overall,
                'last_update' => time()
            ]);
            wp_schedule_single_event(time() + 10, REP_CRON_HOOK_BATCH); 
             error_log("[Mobilia Sync] Lote $page procesado. Programado siguiente lote (Página $next_page). Procesados hasta ahora: $total_processed_overall");
        }

    } else {
        // No hay más items, pasar a limpieza
        $total_processed_overall = count($processed_ids_current_run);
        update_option('rep_mobilia_sync_status', [
             'status' => 'cleaning_up', 
             'total_items' => $total, 
             'processed_items' => $total_processed_overall,
             'last_update' => time()
        ]);
        wp_schedule_single_event(time() + 5, REP_CRON_HOOK_CLEANUP);
         error_log("[Mobilia Sync] No se encontraron más inmuebles en la API (Página $page). Programada limpieza. Total procesado: $total_processed_overall");
    }
    
     update_option('rep_mobilia_last_run_log', $run_log); 
}

add_action(REP_CRON_HOOK_BATCH, 'rep_mobilia_process_batch_job');
add_action(REP_CRON_HOOK_CLEANUP, 'rep_mobilia_cleanup_job');