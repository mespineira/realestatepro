<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rep_register_meta(){
    $fields = array(
        'precio'          => array('type'=>'number','single'=>true),
        'precio_venta'    => array('type'=>'number','single'=>true),
        'precio_alquiler' => array('type'=>'number','single'=>true),
        'precio_traspaso' => array('type'=>'number','single'=>true),

        'superficie_construida' => array('type'=>'number','single'=>true),
        'habitaciones'    => array('type'=>'integer','single'=>true),
        'banos'           => array('type'=>'integer','single'=>true),
        'lat'             => array('type'=>'number','single'=>true),
        'lng'             => array('type'=>'number','single'=>true),
        'referencia'      => array('type'=>'string','single'=>true),
        'estado'          => array('type'=>'string','single'=>true),
        'external_source' => array('type'=>'string','single'=>true),
        'external_id'     => array('type'=>'string','single'=>true),

        // Eficiencia energética (A..G o EN TRAMITE)
        'energy_rating'   => array('type'=>'string','single'=>true),

        // Galería: array de IDs
        'gallery_ids'     => array('type'=>'array','single'=>true),

        // NUEVO: Etiqueta de marketing (select)
        'label_tag'       => array('type'=>'string','single'=>true),

        // Características booleanas (lista expandida)
        'ascensor' => array('type'=>'boolean','single'=>true),
        'terraza'  => array('type'=>'boolean','single'=>true),
        'piscina'  => array('type'=>'boolean','single'=>true),
        'exterior' => array('type'=>'boolean','single'=>true),
        'soleado'  => array('type'=>'boolean','single'=>true),
        'amueblado'=> array('type'=>'boolean','single'=>true),
        'garaje'   => array('type'=>'boolean','single'=>true),
        'trastero' => array('type'=>'boolean','single'=>true),
        'balcon'   => array('type'=>'boolean','single'=>true),
        'calefaccion'=>array('type'=>'boolean','single'=>true),
        'aire_acondicionado'=>array('type'=>'boolean','single'=>true),
        'armarios_empotrados'=>array('type'=>'boolean','single'=>true),
        'cocina_equipada'=>array('type'=>'boolean','single'=>true),
        'jardin'   => array('type'=>'boolean','single'=>true),
        'mascotas' => array('type'=>'boolean','single'=>true),
        'accesible'=> array('type'=>'boolean','single'=>true),
        'vistas'   => array('type'=>'boolean','single'=>true),
        'alarma'   => array('type'=>'boolean','single'=>true),
        'portero'  => array('type'=>'boolean','single'=>true),
        'zona_comunitaria'=>array('type'=>'boolean','single'=>true),
        // Nuevas características para terrenos
        'vallado'      => array('type'=>'boolean','single'=>true),
        'con_agua'     => array('type'=>'boolean','single'=>true),
        'con_luz'      => array('type'=>'boolean','single'=>true),
        'edificable'   => array('type'=>'boolean','single'=>true),
        'pozo'         => array('type'=>'boolean','single'=>true),
        'fosa_septica' => array('type'=>'boolean','single'=>true),
    );
    foreach( $fields as $key=>$schema ){
        register_post_meta('property',$key, array_merge(array(
            'show_in_rest'=>true,
            'auth_callback'=>function(){ return current_user_can('edit_posts'); }
        ),$schema));
    }
}
add_action('init','rep_register_meta');

// Validación: imagen destacada obligatoria (si está activa en ajustes)
add_action('save_post_property', function($post_id,$post){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || ! is_admin() ) return;
    $require = rep_get_setting('require_featured','0') === '1';
    if ( ! $require ) return;
    if ( $post->post_status==='publish' && ! has_post_thumbnail($post_id) ){
        remove_action('save_post_property', __FUNCTION__, 10);
        wp_update_post(array('ID'=>$post_id,'post_status'=>'draft'));
        add_filter('redirect_post_location',function($loc){ return add_query_arg('rep_need_featured','1',$loc); });
    }
},10,2);

add_action('admin_notices', function(){
    if ( isset($_GET['rep_need_featured']) ){
        echo '<div class="notice notice-error is-dismissible"><p>'
           . __('Debes establecer una imagen destacada antes de publicar la propiedad. Se ha guardado como borrador.','real-estate-pro')
           .'</p></div>';
    }
});

