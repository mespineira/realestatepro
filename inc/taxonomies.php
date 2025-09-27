<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rep_register_taxonomies(){
    register_taxonomy('property_type','property', array(
        'label'        => __('Tipo de propiedad','real-estate-pro'),
        'hierarchical' => true,
        'show_in_rest' => true
    ));

    register_taxonomy('property_operation','property', array(
        'label'        => __('Operación','real-estate-pro'),
        'hierarchical' => false,
        'show_in_rest' => true
    ));

    register_taxonomy('property_city','property', array(
        'label'        => __('Ciudad','real-estate-pro'),
        'hierarchical' => false,
        'show_in_rest' => true
    ));

    register_taxonomy('property_province','property', array(
        'label'        => __('Provincia','real-estate-pro'),
        'hierarchical' => false,
        'show_in_rest' => true
    ));

    register_taxonomy('property_zone','property', array(
        'label'        => __('Zona','real-estate-pro'),
        'hierarchical' => false,
        'show_in_rest' => true
    ));
}
add_action('init','rep_register_taxonomies');