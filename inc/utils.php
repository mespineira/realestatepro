<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** =========================
 * Ajustes del plugin
 * ========================= */
function rep_get_setting( $key, $default = '' ) {
    $opts = get_option( 'rep_settings', array() );
    return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
}

function rep_update_setting( $key, $value ) {
    $opts = get_option( 'rep_settings', array() );
    $opts[ $key ] = $value;
    update_option( 'rep_settings', $opts, false );
}

/** =========================
 * Formato de precio
 * ========================= */
function rep_price_format( $amount ) {
    $symbol = rep_get_setting('currency_symbol', '€');
    $pos    = rep_get_setting('currency_position', 'after');
    $dec    = intval( rep_get_setting('currency_decimals', 0) );
    $th     = rep_get_setting('thousands_sep', '.');
    $dp     = rep_get_setting('decimal_sep', ',');

    $amount = floatval($amount);
    $formatted = number_format($amount, $dec, $dp, $th);
    return $pos==='before' ? $symbol.$formatted : $formatted.$symbol;
}

/**
 * Define y devuelve los grupos de características
 */
function rep_get_feature_groups() {
    return array(
        'comun' => array(
            'label' => 'Características Comunes',
            'items' => array(
                'amueblado'           => 'Amueblado',
                'trastero'            => 'Trastero',
                'calefaccion'         => 'Calefacción',
                'aire_acondicionado'  => 'Aire acondicionado',
                'cocina_equipada'     => 'Cocina equipada',
                'armarios_empotrados' => 'Armarios empotrados',
                'exterior'            => 'Exterior',
                'soleado'             => 'Soleado',
                'vistas'              => 'Vistas',
                'accesible'           => 'Accesible',
                'alarma'              => 'Alarma',
                'mascotas'            => 'Admite mascotas',
            )
        ),
        'piso' => array(
            'label' => 'Características de Piso',
            'items' => array(
                'ascensor'         => 'Ascensor',
                'balcon'           => 'Balcón',
                'terraza'          => 'Terraza',
                'portero'          => 'Portero',
                'zona_comunitaria' => 'Zona comunitaria',
            )
        ),
        'casa' => array(
            'label' => 'Características de Casa',
            'items' => array(
                'piscina' => 'Piscina',
                'jardin'  => 'Jardín',
                'garaje'  => 'Garaje',
                'terraza' => 'Terraza'
            )
        ),
        'terreno' => array(
            'label' => 'Características de Terreno/Finca',
            'items' => array(
                'vallado'      => 'Vallado',
                'con_agua'     => 'Conexión de agua',
                'con_luz'      => 'Conexión de luz',
                'edificable'   => 'Edificable',
                'pozo'         => 'Pozo',
                'fosa_septica' => 'Fosa séptica',
            )
        )
    );
}

/** =========================
 * Descarga de imágenes
 * ========================= */
function rep_sideload_featured( $post_id, $url, $desc = '' ) {
    if ( ! function_exists('media_handle_sideload') ) require_once ABSPATH.'wp-admin/includes/media.php';
    if ( ! function_exists('download_url') ) require_once ABSPATH.'wp-admin/includes/file.php';

    $tmp = download_url( $url );
    if ( is_wp_error($tmp) ) return $tmp;

    $file_array = array(
        'name'     => basename( parse_url($url, PHP_URL_PATH) ),
        'tmp_name' => $tmp
    );
    $id = media_handle_sideload( $file_array, $post_id, $desc );
    if ( is_wp_error($id) ) {
        @unlink($tmp);
        return $id;
    }
    set_post_thumbnail( $post_id, $id );
    return $id;
}

function rep_sideload_image_get_id( $url, $post_id = 0, $desc = '' ) {
    if ( ! function_exists('media_handle_sideload') ) require_once ABSPATH.'wp-admin/includes/media.php';
    if ( ! function_exists('download_url') ) require_once ABSPATH.'wp-admin/includes/file.php';

    $tmp = download_url( $url );
    if ( is_wp_error($tmp) ) return $tmp;

    $file_array = array(
        'name'     => basename( parse_url($url, PHP_URL_PATH) ),
        'tmp_name' => $tmp
    );
    $id = media_handle_sideload( $file_array, $post_id, $desc );
    if ( is_wp_error($id) ) {
        @unlink($tmp);
        return $id;
    }
    return $id;
}

/** =========================
 * Helpers multibyte-safe (sin mbstring)
 * ========================= */
if (!function_exists('rep_strlen')) {
    function rep_strlen($s){
        return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    }
}
if (!function_exists('rep_substr')) {
    function rep_substr($s,$start,$len=null){
        if (function_exists('mb_substr')) {
            return mb_substr($s, $start, $len === null ? (function_exists('mb_strlen') ? mb_strlen($s) : strlen($s)) : $len);
        }
        return substr($s, $start, $len === null ? strlen($s) : $len);
    }
}

/**
 * Recorta texto a X caracteres respetando palabra completa.
 * - Limpia HTML
 * - Normaliza espacios
 * - Evita cortar en mitad de palabra
 */
function rep_excerpt_chars($text, $limit = 170){
    $text = wp_strip_all_tags($text);
    $text = preg_replace('/\s+/u', ' ', trim($text));
    if (rep_strlen($text) <= $limit) return $text;

    $cut = rep_substr($text, 0, $limit);
    // intentar cortar por el último espacio
    $pos = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ') : strrpos($cut, ' ');
    if ($pos !== false && $pos > 0) {
        $cut = rep_substr($cut, 0, $pos);
    }
    return $cut . '…';
}

/** Placeholder inline para tarjetas/listados */
function rep_placeholder_img(){
    return "data:image/svg+xml;utf8," . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400">'
        .'<rect width="100%" height="100%" fill="#eee"/>'
        .'<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#999" font-family="sans-serif" font-size="20">Sin imagen</text>'
        .'</svg>'
    );
}

/** =========================
 * Etiquetas (marketing badges)
 * ========================= */
function rep_label_options(){
    // Canon: claves con GUION BAJO (mantiene compatibilidad con lo que ya tenías).
    return array(
        '' => 'Sin etiqueta',
        'urge' => 'Urge',
        'oportunidad' => 'Oportunidad',
        'rebajado' => 'Rebajado',
        'ideal_inversores' => 'Ideal inversores',
        'producto_estrella' => 'Producto estrella',
        'alquilado' => 'Alquilado',
        'vendido' => 'Vendido',
        'reservado' => 'Reservado',
        'origen_bancario' => 'Origen bancario',
        'novedad' => 'Novedad',
        'en_exclusiva' => 'En exclusiva',
        'estudiantes' => 'Estudiantes',
        'precio_negociable' => 'Precio negociable',
    );
}

/** Normaliza variantes con guiones/espacios a la clave canónica con guión bajo */
function rep_normalize_label_slug($slug){
    $slug = strtolower(trim($slug));
    // Normaliza separadores a guión
    $slug = str_replace(array(' ', '_'), '-', $slug);
    // Mapa de variantes → canon con guión bajo
    $map = array(
        'ideal-inversores'   => 'ideal_inversores',
        'producto-estrella'  => 'producto_estrella',
        'origen-bancario'    => 'origen_bancario',
        'en-exclusiva'       => 'en_exclusiva',
        'precio-negociable'  => 'precio_negociable',
        // variantes idénticas
        'urge'               => 'urge',
        'oportunidad'        => 'oportunidad',
        'rebajado'           => 'rebajado',
        'alquilado'          => 'alquilado',
        'vendido'            => 'vendido',
        'reservado'          => 'reservado',
        'novedad'            => 'novedad',
        'estudiantes'        => 'estudiantes',
        ''                   => ''
    );
    return isset($map[$slug]) ? $map[$slug] : $slug; // si no está, devolvemos lo que haya (por si ya viene con _)
}

function rep_label_text($slug){
    $opts = rep_label_options();
    $key  = rep_normalize_label_slug($slug);
    return isset($opts[$key]) ? $opts[$key] : $opts[''];
}

/** =========================
 * Helpers de listado
 * ========================= */

/**
 * Recorte “legacy” que usaban tus listados. Ahora delega en rep_excerpt_chars()
 * para evitar dependencias de mbstring.
 */
function rep_trim_chars($text, $limit = 170){
    return rep_excerpt_chars($text, $limit);
}

/** Devuelve array de URLs de imágenes para tarjetas (máx 5) */
function rep_get_card_images($post_id, $max = 5){
    $ids = array();
    if ( has_post_thumbnail($post_id) ) $ids[] = get_post_thumbnail_id($post_id);
    $g = (array) get_post_meta($post_id,'gallery_ids',true);
    $g = array_filter(array_map('intval',$g));
    foreach($g as $aid){
        if (in_array($aid,$ids,true)) continue;
        $ids[] = $aid;
        if (count($ids) >= $max) break;
    }
    $urls = array();
    foreach($ids as $aid){
        $med = wp_get_attachment_image_src($aid,'large');
        if ($med) $urls[] = $med[0];
    }
    return $urls;
}

