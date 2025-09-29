<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Filtros – [rep_filters style="full|hero"]
 */
add_shortcode('rep_filters', function($atts){
    $a = shortcode_atts(array('operation'=>'','action'=>'','style'=>'full'), $atts);
    $action = $a['action'] ? esc_url($a['action']) : esc_url( get_post_type_archive_link('property') );

    // Taxonomías para selects
    $types = get_terms(array('taxonomy'=>'property_type','hide_empty'=>false));
    $cities= get_terms(array('taxonomy'=>'property_city','hide_empty'=>false));
    $operations = get_terms(array('taxonomy'=>'property_operation', 'hide_empty' => false));

    // Valores actuales (GET)
    $get  = wp_unslash($_GET);
    $s    = isset($get['s']) ? esc_attr($get['s']) : '';
    $op_get = isset($get['operation'])? sanitize_text_field($get['operation']) : '';
    $type = isset($get['type'])? sanitize_text_field($get['type']) : '';
    $city = isset($get['city'])? sanitize_text_field($get['city']) : '';

    ob_start(); 
    
    // Estilo HERO (simplificado)
    if ($a['style'] === 'hero') : ?>
    <form class="rep-filters rep-filters--hero" method="get" action="<?php echo $action; ?>">
        <input type="search" name="s" placeholder="Buscar por referencia..." value="<?php echo $s; ?>"/>

        <select name="operation">
            <option value="">Comprar o Alquilar</option>
            <?php foreach($operations as $op): ?>
                <option value="<?php echo esc_attr($op->slug); ?>" <?php selected($op_get, $op->slug); ?>><?php echo esc_html($op->name); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="type">
            <option value="">Tipo de propiedad</option>
            <?php foreach($types as $t): ?>
            <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type,$t->slug); ?>><?php echo esc_html($t->name); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="city">
            <option value="">Municipio</option>
            <?php foreach($cities as $c): ?>
            <option value="<?php echo esc_attr($c->slug); ?>" <?php selected($city,$c->slug); ?>><?php echo esc_html($c->name); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="rep-btn"><i class="fas fa-search"></i> Buscar</button>
    </form>
    <?php 
    // Estilo FULL (por defecto)
    else: 
        $min  = isset($get['min']) ? intval($get['min']) : '';
        $max  = isset($get['max']) ? intval($get['max']) : '';
        $hab  = isset($get['hab']) ? intval($get['hab']) : '';
        $ban  = isset($get['ban']) ? intval($get['ban']) : '';
        $label= isset($get['label'])? sanitize_text_field($get['label']) : '';
        if (!function_exists('rep_label_options')) require_once REP_PATH.'inc/utils.php';
        $label_opts = rep_label_options();
    ?>
    <form class="rep-filters" method="get" action="<?php echo $action; ?>">
      <?php if($a['operation']): ?>
        <input type="hidden" name="operation" value="<?php echo esc_attr($a['operation']); ?>"/>
      <?php endif; ?>

      <input type="search" name="s" placeholder="Buscar..." value="<?php echo $s; ?>"/>

      <select name="type">
        <option value="">Tipo</option>
        <?php foreach($types as $t): ?>
          <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type,$t->slug); ?>><?php echo esc_html($t->name); ?></option>
        <?php endforeach; ?>
      </select>

      <select name="city">
        <option value="">Ciudad</option>
        <?php foreach($cities as $c): ?>
          <option value="<?php echo esc_attr($c->slug); ?>" <?php selected($city,$c->slug); ?>><?php echo esc_html($c->name); ?></option>
        <?php endforeach; ?>
      </select>

      <input type="number" name="min" placeholder="Precio mín." value="<?php echo $min; ?>" step="500">
      <input type="number" name="max" placeholder="Precio máx." value="<?php echo $max; ?>" step="500">

      <select name="hab">
        <option value="">Hab.</option>
        <?php for($i=1;$i<=6;$i++): ?>
          <option value="<?php echo $i; ?>" <?php selected($hab,$i); ?>><?php echo $i; ?>+</option>
        <?php endfor; ?>
      </select>

      <select name="ban">
        <option value="">Baños</option>
        <?php for($i=1;$i<=4;$i++): ?>
          <option value="<?php echo $i; ?>" <?php selected($ban,$i); ?>><?php echo $i; ?>+</option>
        <?php endfor; ?>
      </select>

      <select name="label">
        <option value="">Etiqueta</option>
        <?php foreach($label_opts as $k=>$v): if($k==='') continue; ?>
          <option value="<?php echo esc_attr($k); ?>" <?php selected($label,$k); ?>><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="rep-btn">Buscar</button>
    </form>
    <?php endif;
    return ob_get_clean();
});

/**
 * Listado – [rep_list operation="venta|alquiler" per_page="12"]
 */
add_shortcode('rep_list', function($atts){
    $a = shortcode_atts(array('per_page'=>12,'operation'=>''),$atts);
    $paged = max(1, get_query_var('paged') ? get_query_var('paged') : ( isset($_GET['pg']) ? intval($_GET['pg']) : 1 ));
    $args = array(
        'post_type'=>'property',
        'posts_per_page'=>intval($a['per_page']),
        'paged'=>$paged
    );

    // Tax query opcional
    $tax = array('relation'=>'AND');
    
    // Unificamos el parámetro de operación
    $operation_slug = $a['operation'] ? $a['operation'] : (isset($_GET['operation']) ? sanitize_text_field($_GET['operation']) : '');
    if ($operation_slug) {
        $tax[] = array('taxonomy'=>'property_operation','field'=>'slug','terms'=>array($operation_slug));
    }
    
    if (!empty($_GET['type'])) $tax[] = array('taxonomy'=>'property_type','field'=>'slug','terms'=>array(sanitize_text_field($_GET['type'])));
    if (!empty($_GET['city'])) $tax[] = array('taxonomy'=>'property_city','field'=>'slug','terms'=>array(sanitize_text_field($_GET['city'])));
    if (count($tax)>1) $args['tax_query']=$tax;

    // Meta query
    $meta = array('relation'=>'AND');
    if (!empty($_GET['min'])) $meta[] = array('key'=>'precio','value'=>intval($_GET['min']),'compare'=>'>=','type'=>'NUMERIC');
    if (!empty($_GET['max'])) $meta[] = array('key'=>'precio','value'=>intval($_GET['max']),'compare'=>'<=','type'=>'NUMERIC');
    if (!empty($_GET['hab'])) $meta[] = array('key'=>'habitaciones','value'=>intval($_GET['hab']),'compare'=>'>=','type'=>'NUMERIC');
    if (!empty($_GET['ban'])) $meta[] = array('key'=>'banos','value'=>intval($_GET['ban']),'compare'=>'>=','type'=>'NUMERIC');

    if (!function_exists('rep_normalize_label_slug')) require_once REP_PATH.'inc/utils.php';
    if (!empty($_GET['label'])) {
        $label_slug = rep_normalize_label_slug( sanitize_text_field($_GET['label']) );
        $meta[] = array('key'=>'label_tag','value'=>$label_slug);
    }
    
    if (count($meta)>1) $args['meta_query']=$meta;

    if (!empty($_GET['s'])) $args['s'] = sanitize_text_field($_GET['s']);

    $q = new WP_Query($args);

    ob_start();
    echo '<div class="rep-grid rep-grid-list">';
    if($q->have_posts()):
        while($q->have_posts()): $q->the_post();
            $pid = get_the_ID();
            $precio = get_post_meta($pid,'precio',true);
            $ref    = get_post_meta($pid,'referencia',true);
            $m2     = get_post_meta($pid,'superficie_construida',true);
            $hab    = get_post_meta($pid,'habitaciones',true);
            $ban    = get_post_meta($pid,'banos',true);
            $label  = get_post_meta($pid,'label_tag',true);

            $imgs = rep_get_card_images($pid, 5);
            if(!$imgs) $imgs[] = rep_placeholder_img();

            $raw  = get_the_excerpt() ? get_the_excerpt() : get_the_content(null,false);
            $desc = rep_excerpt_chars($raw, 170);

            echo '<article class="rep-card rep-card-line">';
            if($label){
              echo '<span class="rep-badge" data-label-slug="'.esc_attr($label).'">'.esc_html( rep_label_text($label) ).'</span>';
            }

            $json_imgs = htmlspecialchars( wp_json_encode($imgs), ENT_QUOTES, 'UTF-8' );
            echo '<div class="rep-card-slider" data-images="'.$json_imgs.'">';
            echo '  <button class="rep-cs-prev" type="button" aria-label="Anterior">&#10094;</button>';
            echo '  <a href="'.esc_url(get_permalink()).'" class="rep-cs-stage"><img src="'.esc_url($imgs[0]).'" alt="'.esc_attr(get_the_title()).'"/></a>';
            echo '  <button class="rep-cs-next" type="button" aria-label="Siguiente">&#10095;</button>';
            echo '</div>';

            if($precio) echo '<div class="rep-price">'.esc_html(rep_price_format($precio)).'</div>';

            echo '<ul class="rep-mini-feats">';
            if($ref) echo '<li title="Referencia"><i class="fas fa-tag"></i> '.esc_html($ref).'</li>';
            if($m2)  echo '<li title="Superficie"><i class="fas fa-ruler-combined"></i> '.esc_html($m2).' m²</li>';
            if($hab) echo '<li title="Habitaciones"><i class="fas fa-bed"></i> '.esc_html($hab).'</li>';
            if($ban) echo '<li title="Baños"><i class="fas fa-bath"></i> '.esc_html($ban).'</li>';
            echo '</ul>';

            echo '<h3 class="rep-title-card"><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3>';
            echo '<p class="rep-excerpt">'.esc_html($desc).'</p>';

            echo '</article>';
        endwhile; wp_reset_postdata();
    else:
        echo '<p>No hay inmuebles que coincidan con tu búsqueda.</p>';
    endif;
    echo '</div>';

    $links=paginate_links(array(
        'total'=>$q->max_num_pages,
        'current'=>$paged,
        'type'=>'list',
        'prev_text' => '<i class="fas fa-chevron-left"></i>',
        'next_text' => '<i class="fas fa-chevron-right"></i>',
    ));
    if($links) echo '<nav class="rep-pagination">'.$links.'</nav>';
    return ob_get_clean();
});

/**
 * Simulador – [rep_mortgage price="270000"]
 */
add_shortcode('rep_mortgage', function($atts){
    $a = shortcode_atts(array('price'=>0), $atts);
    $price = floatval($a['price']);
    ob_start(); ?>
    <div class="rep-mortgage" data-rep-mortgage>
      <div class="rep-m-rows">
        <div class="rep-m-row">
          <label>Precio (€)</label>
          <input type="number" data-m="price" value="<?php echo esc_attr($price); ?>" step="500">
        </div>
        <div class="rep-m-row">
          <label>Entrada (€)</label>
          <input type="number" data-m="down" value="0" step="500">
        </div>
        <div class="rep-m-row">
          <label>Años</label>
          <input type="number" data-m="years" value="30" step="1" min="1" max="40">
        </div>
        <div class="rep-m-row">
          <label>Interés anual (%)</label>
          <input type="number" data-m="rate" value="2.5" step="0.1" min="0">
        </div>
      </div>
      <div class="rep-m-out">
        <div class="rep-m-badge">Impuestos aprox.: <span>€ <span data-m="taxes">0</span></span></div>
        <div class="rep-m-badge">Préstamo aprox.: <span>€ <span data-m="loan">0</span></span></div>
        <div class="rep-m-badge">Cuota mensual: <span>€ <span data-m="quota">0</span></span></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

