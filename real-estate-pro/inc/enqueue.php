<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('rep-frontend', REP_URL.'assets/css/frontend.css', array(), REP_VERSION);
    wp_enqueue_style('rep-gallery',  REP_URL.'assets/css/gallery.css',  array(), REP_VERSION);
    wp_enqueue_script('rep-gallery', REP_URL.'assets/js/gallery.js',    array(), REP_VERSION, true);

    wp_enqueue_style('leaflet','https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',array(), '1.9.4');
    wp_enqueue_script('leaflet','https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',array(), '1.9.4', true);

    wp_enqueue_style('rep-map', REP_URL.'assets/css/map.css', array(), REP_VERSION);
    wp_enqueue_script('rep-map', REP_URL.'assets/js/map.js', array('leaflet'), REP_VERSION, true);

    wp_enqueue_script('rep-frontend-js', REP_URL.'assets/js/frontend.js', array(), REP_VERSION, true);

    // NUEVO: mini-slider para cards del listado
    wp_enqueue_script('rep-cards', REP_URL.'assets/js/cards.js', array(), REP_VERSION, true);
});

// Admin enqueue se mantiene igual que el último que te pasé
add_action('admin_enqueue_scripts', function($hook){
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'property' ) return;
    wp_enqueue_style('rep-admin', REP_URL.'assets/css/admin.css', array(), REP_VERSION);
    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('leaflet','https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',array(), '1.9.4');
    wp_enqueue_script('leaflet','https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',array(), '1.9.4', true);
    wp_enqueue_script('rep-admin-js', REP_URL.'assets/js/admin.js', array('jquery','jquery-ui-sortable','leaflet'), REP_VERSION, true);
});