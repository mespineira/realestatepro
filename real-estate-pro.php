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
// NUEVO: metaboxes de edición en admin
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