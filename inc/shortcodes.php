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
    // --- INICIO CAMBIO ZONAS DINÁMICAS ---
    $all_zones = get_terms(array('taxonomy'=>'property_zone','hide_empty'=>false));
    // --- FIN CAMBIO ZONAS DINÁMICAS ---
    $operations = get_terms(array('taxonomy'=>'property_operation', 'hide_empty' => false));

    // Valores actuales (GET)
    $get  = wp_unslash($_GET);
    $s    = isset($get['s']) ? esc_attr($get['s']) : '';
    $op_get = isset($get['operation'])? sanitize_text_field($get['operation']) : '';
    $type = isset($get['type'])? sanitize_text_field($get['type']) : '';
    $city = isset($get['city'])? sanitize_text_field($get['city']) : '';
    // --- INICIO CAMBIO ZONAS DINÁMICAS ---
    $zone = isset($get['zone'])? sanitize_text_field($get['zone']) : '';
    // --- FIN CAMBIO ZONAS DINÁMICAS ---

    ob_start();

    // Estilo HERO (simplificado) - Sin cambios aquí, ya que no incluye el filtro de zona
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
        $ref      = isset($get['ref']) ? esc_attr($get['ref']) : '';
        $min_price= isset($get['min_price']) ? intval($get['min_price']) : '';
        $max_price= isset($get['max_price']) ? intval($get['max_price']) : '';
        $min_m2   = isset($get['min_m2']) ? intval($get['min_m2']) : '';
        $max_m2   = isset($get['max_m2']) ? intval($get['max_m2']) : '';
        $hab      = isset($get['hab']) ? intval($get['hab']) : '';
        $ban      = isset($get['ban']) ? intval($get['ban']) : '';

        // --- INICIO CAMBIO ZONAS DINÁMICAS ---
        // Crear estructura de datos: zonas agrupadas por slug de ciudad
        $zones_by_city = [];
        foreach ($all_zones as $z) {
            // Asumimos que cada zona tiene propiedades asignadas a UNA ciudad.
            // Buscamos las propiedades que tienen esta zona
            $props_in_zone = get_posts([
                'post_type' => 'property',
                'posts_per_page' => 1, // Solo necesitamos una para saber la ciudad
                'tax_query' => [
                    [
                        'taxonomy' => 'property_zone',
                        'field' => 'term_id',
                        'terms' => $z->term_id,
                    ],
                ],
                'fields' => 'ids' // Optimización: solo necesitamos el ID
            ]);

            if ($props_in_zone) {
                $prop_id = $props_in_zone[0];
                // Obtenemos los términos de ciudad para esa propiedad
                $city_terms = wp_get_post_terms($prop_id, 'property_city');
                if ($city_terms && !is_wp_error($city_terms)) {
                    // Usamos la primera ciudad encontrada (asumimos una por propiedad)
                    $city_slug = $city_terms[0]->slug;
                    if (!isset($zones_by_city[$city_slug])) {
                        $zones_by_city[$city_slug] = [];
                    }
                    $zones_by_city[$city_slug][] = ['slug' => $z->slug, 'name' => $z->name];
                }
            }
        }
        // --- FIN CAMBIO ZONAS DINÁMICAS ---
    ?>
    
    <form class="rep-filters" method="get" action="<?php echo $action; ?>"
          data-zones="<?php echo esc_attr(wp_json_encode($zones_by_city)); // Pasar datos a JS ?>">
      <?php if($a['operation']): ?>
        <input type="hidden" name="operation" value="<?php echo esc_attr($a['operation']); ?>"/>
      <?php endif; ?>

      <input type="text" name="ref" placeholder="Referencia" value="<?php echo $ref; ?>"/>

      <select name="type">
        <option value="">Tipo</option>
        <?php foreach($types as $t): ?>
          <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($type,$t->slug); ?>><?php echo esc_html($t->name); ?></option>
        <?php endforeach; ?>
      </select>

      <select name="city" id="rep-filter-city">
        <option value="">Ciudad</option>
        <?php foreach($cities as $c): ?>
          <option value="<?php echo esc_attr($c->slug); ?>" <?php selected($city,$c->slug); ?>><?php echo esc_html($c->name); ?></option>
        <?php endforeach; ?>
      </select>
      
      <select name="zone" id="rep-filter-zone">
        <option value="">Zona</option>
        <?php
        // --- INICIO CAMBIO ZONAS DINÁMICAS ---
        // Si hay una ciudad seleccionada al cargar la página, mostramos solo sus zonas
        if ($city && isset($zones_by_city[$city])) {
            foreach($zones_by_city[$city] as $z_data) {
                echo '<option value="' . esc_attr($z_data['slug']) . '" ' . selected($zone, $z_data['slug'], false) . '>' . esc_html($z_data['name']) . '</option>';
            }
        } elseif (!$city) {
            // Si no hay ciudad seleccionada, podemos mostrar todas o ninguna (opcional)
             // foreach($all_zones as $z) {
             //    echo '<option value="'.esc_attr($z->slug).'" '.selected($zone,$z->slug, false).'>'.esc_html($z->name).'</option>';
             // }
        }
        // --- FIN CAMBIO ZONAS DINÁMICAS ---
        ?>
      </select>

      <input type="number" name="min_price" placeholder="Precio mín." value="<?php echo $min_price; ?>" step="500">
      <input type="number" name="max_price" placeholder="Precio máx." value="<?php echo $max_price; ?>" step="500">
      
      <input type="number" name="min_m2" placeholder="Superficie mín." value="<?php echo $min_m2; ?>" step="1">
      <input type="number" name="max_m2" placeholder="Superficie máx." value="<?php echo $max_m2; ?>" step="1">

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

      <button type="submit" class="rep-btn">Buscar</button>
    </form>
    <?php
    endif;
    return ob_get_clean();
});

// El resto del archivo ([rep_list], [rep_mortgage]) no necesita cambios
// ... (código existente de rep_list y rep_mortgage) ...

/**
 * Listado – [rep_list per_page="12" operation="venta" pagination="true"]
 */
add_shortcode('rep_list', function($atts){
    $a = shortcode_atts(array(
        'per_page'   => 12,
        'operation'  => '',
        'pagination' => 'true'
    ),$atts);

    $paged = max(1, get_query_var('paged') ? get_query_var('paged') : ( isset($_GET['pg']) ? intval($_GET['pg']) : 1 ));
    
    $args = array(
        'post_type'      => 'property',
        'posts_per_page' => intval($a['per_page']),
        'paged'          => $paged
    );

    $tax_query = array('relation'=>'AND');
    $meta_query = array('relation'=>'AND');

    // Forzar operación si viene del shortcode
    $operation_slug = $a['operation'] ? $a['operation'] : (isset($_GET['operation']) ? sanitize_text_field($_GET['operation']) : '');
    if ($operation_slug) {
        $tax_query[] = array('taxonomy'=>'property_operation','field'=>'slug','terms'=>array($operation_slug));
    }
    
    // Taxonomías
    if (!empty($_GET['type'])) $tax_query[] = array('taxonomy'=>'property_type','field'=>'slug','terms'=>array(sanitize_text_field($_GET['type'])));
    if (!empty($_GET['city'])) $tax_query[] = array('taxonomy'=>'property_city','field'=>'slug','terms'=>array(sanitize_text_field($_GET['city'])));
    if (!empty($_GET['zone'])) $tax_query[] = array('taxonomy'=>'property_zone','field'=>'slug','terms'=>array(sanitize_text_field($_GET['zone'])));
    
    // Metas
    if (!empty($_GET['ref'])) $meta_query[] = array('key'=>'referencia','value'=> sanitize_text_field($_GET['ref']),'compare'=>'=');
    if (!empty($_GET['min_price'])) $meta_query[] = array('key'=>'precio','value'=>intval($_GET['min_price']),'compare'=>'>=','type'=>'NUMERIC');
    if (!empty($_GET['max_price'])) $meta_query[] = array('key'=>'precio','value'=>intval($_GET['max_price']),'compare'=>'<=','type'=>'NUMERIC');
    if (!empty($_GET['min_m2'])) $meta_query[] = array('key'=>'superficie_construida','value'=>intval($_GET['min_m2']),'compare'=>'>=','type'=>'NUMERIC');
    if (!empty($_GET['max_m2'])) $meta_query[] = array('key'=>'superficie_construida','value'=>intval($_GET['max_m2']),'compare'=>'<=','type'=>'NUMERIC');
    if (!empty($_GET['hab'])) $meta_query[] = array('key'=>'habitaciones','value'=>intval($_GET['hab']),'compare'=>'>=','type'=>'NUMERIC');
    if (!empty($_GET['ban'])) $meta_query[] = array('key'=>'banos','value'=>intval($_GET['ban']),'compare'=>'>=','type'=>'NUMERIC');
    
    // Búsqueda general (portada)
    if (!empty($_GET['s'])) {
         $args['s'] = sanitize_text_field($_GET['s']);
    }

    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;
    if (count($meta_query) > 1) $args['meta_query'] = $meta_query;

    $q = new WP_Query($args);

    ob_start();
    echo '<div class="rep-grid rep-grid-list">';
    if($q->have_posts()):
        while($q->have_posts()): $q->the_post();
            include( REP_PATH . 'templates/parts/property-card.php' );
        endwhile; wp_reset_postdata();
    else:
        echo '<p>No hay inmuebles que coincidan con tu búsqueda.</p>';
    endif;
    echo '</div>';

    if ( $a['pagination'] === 'true' && $q->max_num_pages > 1 ) {
        $links = paginate_links(array(
            'total'     => $q->max_num_pages,
            'current'   => $paged,
            'type'      => 'list',
            'prev_text' => '<i class="fas fa-chevron-left"></i>',
            'next_text' => '<i class="fas fa-chevron-right"></i>',
        ));
        if($links) echo '<nav class="rep-pagination">'.$links.'</nav>';
    }

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