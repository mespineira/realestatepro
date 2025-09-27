<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rep_register_cpt_property(){
    register_post_type('property', array(
        'labels' => array(
            'name'          => __('Propiedades','real-estate-pro'),
            'singular_name' => __('Propiedad','real-estate-pro')
        ),
        'public'        => true,
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-admin-multisite',
        'supports'      => array('title','editor','excerpt','thumbnail','revisions'),
        'has_archive'   => true,
        'rewrite'       => array('slug' => 'propiedades','with_front'=>false),
    ));
}
add_action('init','rep_register_cpt_property');