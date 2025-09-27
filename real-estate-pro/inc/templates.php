<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter('template_include', function($template){
    if ( is_singular('property') ){
        return REP_PATH.'templates/single-property.php';
    }
    if ( is_post_type_archive('property') ){
        return REP_PATH.'templates/archive-property.php';
    }
    return $template;
},20);