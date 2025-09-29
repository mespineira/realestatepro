<?php
/**
 * Plugin Name: Real Estate Pro
 * Description: Gestión inmobiliaria con Mobilia Sync, galería, mapa Leaflet (OSM), eficiencia energética, formulario de contacto RGPD y simulador de hipoteca.
 * Version: 1.3.0
 * Requires PHP: 7.3
 * Author: Manel Espiñeira
 * Text Domain: real-estate-pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'REP_VERSION', '1.3.0' );
define( 'REP_PATH', plugin_dir_path( __FILE__ ) );
define( 'REP_URL', plugin_dir_url( __FILE__ ) );

// Carga de módulos
require_once REP_PATH . 'inc/utils.php';
require_once REP_PATH . 'inc/cpt-property.php';
require_once REP_PATH . 'inc/taxonomies.php';
require_once REP_PATH . 'inc/meta.php';
require_once REP_PATH . 'inc/settings.php';
require_once REP_PATH . 'inc/templates.php';
require_once REP_PATH . 'inc/shortcodes.php';
require_once REP_PATH . 'inc/leads.php';
require_once REP_PATH . 'inc/mobilia-sync.php';
require_once REP_PATH . 'inc/enqueue.php';
require_once REP_PATH . 'inc/admin-metaboxes.php';

// Activación / desactivación
register_activation_hook( __FILE__, function(){
    rep_register_cpt_property();
    rep_register_taxonomies();
    rep_register_meta();
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function(){
    wp_clear_scheduled_hook('rep_mobilia_continue_batch');
    flush_rewrite_rules();
});

// --- INICIO DE MODIFICACIONES ---
/**
 * Modifica la query principal en el archivo de propiedades para que funcionen los filtros.
 */
function rep_pre_get_properties( $query ) {
    // Aplicar solo en el frontend, en la query principal y en el archivo del CPT 'property'
    if ( is_admin() || ! $query->is_main_query() || ! is_post_type_archive('property') ) {
        return;
    }

    $meta_query = $query->get('meta_query') ?: array();
    if (!is_array($meta_query)) $meta_query = array();
    $meta_query['relation'] = 'AND';
    
    $tax_query = $query->get('tax_query') ?: array();
    if (!is_array($tax_query)) $tax_query = array();
    $tax_query['relation'] = 'AND';
    
    // Filtros de Meta (campos personalizados)
    $meta_filters = array(
        'ref'       => array('key' => 'referencia', 'compare' => '='),
        'min_price' => array('key' => 'precio', 'compare' => '>=', 'type' => 'NUMERIC'),
        'max_price' => array('key' => 'precio', 'compare' => '<=', 'type' => 'NUMERIC'),
        'min_m2'    => array('key' => 'superficie_construida', 'compare' => '>=', 'type' => 'NUMERIC'),
        'max_m2'    => array('key' => 'superficie_construida', 'compare' => '<=', 'type' => 'NUMERIC'),
        'hab'       => array('key' => 'habitaciones', 'compare' => '>=', 'type' => 'NUMERIC'),
        'ban'       => array('key' => 'banos', 'compare' => '>=', 'type' => 'NUMERIC'),
    );

    foreach ($meta_filters as $key => $data) {
        if ( ! empty($_GET[$key]) ) {
            $meta_query[] = array(
                'key'     => $data['key'],
                'value'   => sanitize_text_field($_GET[$key]),
                'compare' => $data['compare'],
                'type'    => $data['type'] ?? 'CHAR',
            );
        }
    }

    // Filtros de Taxonomía
    $tax_filters = array('type', 'city', 'zone', 'operation');
    foreach ($tax_filters as $tax) {
        if ( ! empty($_GET[$tax]) ) {
            $tax_query[] = array(
                'taxonomy' => 'property_' . $tax,
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET[$tax]),
            );
        }
    }

    if ( count($meta_query) > 1 ) {
        $query->set('meta_query', $meta_query);
    }
    if ( count($tax_query) > 1 ) {
        $query->set('tax_query', $tax_query);
    }
}
add_action('pre_get_posts', 'rep_pre_get_properties');
// --- FIN DE MODIFICACIONES ---
