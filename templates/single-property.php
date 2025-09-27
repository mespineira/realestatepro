<?php
/**
 * Single Property Template (UI con anclas + galería + mapa Leaflet + eficiencia + contacto + simulador)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main class="rep-container single-property">
<?php while( have_posts() ): the_post();
  $pid    = get_the_ID();
  $precio = get_post_meta($pid,'precio',true);
  $ref    = get_post_meta($pid,'referencia',true);
  $m2     = get_post_meta($pid,'superficie_construida',true);
  $hab    = get_post_meta($pid,'habitaciones',true);
  $ban    = get_post_meta($pid,'banos',true);
  $lat    = get_post_meta($pid,'lat', true);
  $lng    = get_post_meta($pid,'lng', true);

  // Datos de Eficiencia Energética
  $rating = strtoupper(trim(get_post_meta($pid,'energy_rating',true)));
  $e_cons = get_post_meta($pid,'energy_consumption',true);
  $e_emis = get_post_meta($pid,'energy_emissions',true);

  // Características (booleans)
  if (!function_exists('rep_get_feature_groups')) {
      require_once REP_PATH . 'inc/utils.php';
  }
  $feature_groups = rep_get_feature_groups();
  $all_feature_labels = array();
  foreach ($feature_groups as $group) {
      $all_feature_labels = array_merge($all_feature_labels, $group['items']);
  }
  $features = array();
  foreach(array_keys($all_feature_labels) as $fk){
    if ( get_post_meta($pid,$fk,true) ) $features[] = $fk;
  }

  // Galería
  $gallery_ids = array_filter( array_map('intval', (array) get_post_meta($pid, 'gallery_ids', true) ) );
  $images = array();
  if ( has_post_thumbnail() ) {
    $images[] = get_post_thumbnail_id();
    $gallery_ids = array_diff($gallery_ids, array(get_post_thumbnail_id()));
  }
  if ( $gallery_ids ) $images = array_merge($images,$gallery_ids);

  $slides = array();
  foreach($images as $aid){
    $full = wp_get_attachment_image_src($aid,'full');
    $med  = wp_get_attachment_image_src($aid,'large');
    $th   = wp_get_attachment_image_src($aid,'thumbnail');
    if($full && $med && $th){
      $slides[] = array('id'=>$aid,'full'=>$full[0],'med'=>$med[0],'thumb'=>$th[0]);
    }
  }
?>
  <article <?php post_class('rep-single rep-card-xl'); ?>>

    <header class="rep-head">
      <div class="rep-head-left">
        <h1 class="rep-title"><?php the_title(); ?></h1>
        <div class="rep-sub">
          <?php if($ref): ?><span class="rep-ref">REF. <?php echo esc_html($ref); ?></span><?php endif; ?>
          <?php if($m2):  ?><span class="rep-chip"><?php echo esc_html($m2); ?> m²</span><?php endif; ?>
          <?php if($hab): ?><span class="rep-chip"><?php echo esc_html($hab); ?> hab</span><?php endif; ?>
          <?php if($ban): ?><span class="rep-chip"><?php echo esc_html($ban); ?> baños</span><?php endif; ?>
        </div>
      </div>
      <div class="rep-head-right">
        <?php if($precio): ?><div class="rep-price-lg"><?php echo esc_html(rep_price_format($precio)); ?></div><?php endif; ?>
      </div>
    </header>

    <!-- Barra de anclas -->
    <nav class="rep-anchorbar">
      <a href="#fotos">Fotos</a>
      <?php if ($lat!=='' && $lng!==''): ?>
        <a href="#mapa" data-rep-goto-map>Mapa</a>
      <?php endif; ?>
      <a href="#ficha">Ficha</a>
      <a href="#contacto" class="rep-cta">Contactar ahora</a>
    </nav>

    <!-- Sección: Fotos -->
    <section id="fotos" class="rep-section">
      <div class="rep-gallery" data-rep-gallery>
        <div class="rep-gallery-main">
          <button class="rep-g-prev" type="button" aria-label="Anterior">&#10094;</button>
          <div class="rep-g-stage">
            <?php if ($slides): ?>
              <img class="rep-g-current" src="<?php echo esc_url($slides[0]['med']); ?>"
                   data-full="<?php echo esc_url($slides[0]['full']); ?>" alt="Imagen de la propiedad"/>
            <?php else: ?>
                <img class="rep-g-current" src="<?php echo esc_url(rep_placeholder_img()); ?>" alt="Imagen de la propiedad"/>
            <?php endif; ?>
          </div>
          <button class="rep-g-next" type="button" aria-label="Siguiente">&#10095;</button>
        </div>
        <?php if (count($slides) > 1): ?>
        <div class="rep-gallery-thumbs">
          <button class="rep-gt-prev" type="button" aria-label="Desplazar miniaturas">&#10094;</button>
          <div class="rep-gt-strip">
            <?php foreach($slides as $idx=>$s): ?>
              <img class="rep-gt-thumb <?php echo $idx===0?'is-active':''; ?>"
                   src="<?php echo esc_url($s['thumb']); ?>"
                   data-med="<?php echo esc_url($s['med']); ?>"
                   data-full="<?php echo esc_url($s['full']); ?>"
                   alt="Miniatura <?php echo esc_attr($idx+1); ?>"/>
            <?php endforeach; ?>
          </div>
          <button class="rep-gt-next" type="button" aria-label="Desplazar miniaturas">&#10095;</button>
        </div>
        <?php endif; ?>
      </div>

      <!-- Lightbox -->
      <div class="rep-lightbox" data-rep-lightbox hidden>
        <button class="rep-lb-close" type="button" aria-label="Cerrar">&times;</button>
        <button class="rep-lb-prev"  type="button" aria-label="Anterior">&#10094;</button>
        <img class="rep-lb-img" src="" alt="Imagen ampliada"/>
        <button class="rep-lb-next"  type="button" aria-label="Siguiente">&#10095;</button>
        <div class="rep-lb-thumbs">
          <?php foreach($slides as $idx=>$s): ?>
            <img class="rep-lb-thumb <?php echo $idx===0?'is-active':''; ?>"
                 src="<?php echo esc_url($s['thumb']); ?>"
                 data-full="<?php echo esc_url($s['full']); ?>"
                 alt="Miniatura lightbox <?php echo esc_attr($idx+1); ?>"/>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Sección: Mapa -->
    <?php if ($lat!=='' && $lng!==''): ?>
    <section id="mapa" class="rep-section">
      <h2>Ubicación</h2>
      <div class="rep-map" data-rep-map
           data-lat="<?php echo esc_attr($lat); ?>"
           data-lng="<?php echo esc_attr($lng); ?>"
           data-title="<?php echo esc_attr(get_the_title()); ?>"></div>
      <small class="rep-note">La dirección del inmueble es aproximada.</small>
    </section>
    <?php endif; ?>

    <!-- Sección: Ficha -->
    <section id="ficha" class="rep-section">
      <div class="rep-two">
        <div class="rep-box">
          <h2>Descripción</h2>
          <div class="rep-content"><?php the_content(); ?></div>
        </div>
        <aside class="rep-box">
          <h2>Características</h2>
          <ul class="rep-features">
            <?php if($m2):  ?><li><strong>Superficie:</strong> <?php echo esc_html($m2); ?> m²</li><?php endif; ?>
            <?php if($hab): ?><li><strong>Habitaciones:</strong> <?php echo esc_html($hab); ?></li><?php endif; ?>
            <?php if($ban): ?><li><strong>Baños:</strong> <?php echo esc_html($ban); ?></li><?php endif; ?>
            <?php if($ref): ?><li><strong>Referencia:</strong> <?php echo esc_html($ref); ?></li><?php endif; ?>
            <?php
            foreach($features as $k){
              if ( isset($all_feature_labels[$k]) ) echo '<li>'.esc_html($all_feature_labels[$k]).'</li>';
            } ?>
          </ul>

          <!-- Eficiencia energética -->
          <?php if($rating): ?>
          <div class="rep-energy-cert">
            <h3>Eficiencia energética</h3>
            <div class="rep-energy-scale-new">
              <div class="rep-energy-header">
                <div>ESCALA DE LA CALIFICACIÓN ENERGÉTICA</div>
                <div class="rep-energy-col-title">Consumo de energía<br><span>kWh/m² año</span></div>
                <div class="rep-energy-col-title">Emisiones<br><span>kg CO₂/m² año</span></div>
              </div>
              <div class="rep-energy-rows">
                <?php
                $letters = array(
                    'A' => array('color' => '#008c4f', 'label' => 'A'),
                    'B' => array('color' => '#50a639', 'label' => 'B'),
                    'C' => array('color' => '#c3d334', 'label' => 'C'),
                    'D' => array('color' => '#fcec00', 'label' => 'D'),
                    'E' => array('color' => '#f9b233', 'label' => 'E'),
                    'F' => array('color' => '#f26d21', 'label' => 'F'),
                    'G' => array('color' => '#ed1c24', 'label' => 'G'),
                );
                $is_tramite = !array_key_exists($rating, $letters);

                foreach($letters as $L => $data):
                ?>
                <div class="rep-energy-row <?php echo ($rating === $L) ? 'is-active' : ''; ?>">
                  <div class="rep-energy-letter" style="background-color:<?php echo $data['color']; ?>; color: <?php echo in_array($L, ['A','B','C', 'F', 'G']) ? '#fff' : '#000'; ?>;">
                    <?php echo $data['label']; ?>
                  </div>
                  <div class="rep-energy-value">
                    <?php if($rating === $L && !$is_tramite && !empty($e_cons)) echo '<span>' . esc_html(number_format_i18n($e_cons, 2)) . '</span>'; ?>
                  </div>
                  <div class="rep-energy-value">
                    <?php if($rating === $L && !$is_tramite && !empty($e_emis)) echo '<span>' . esc_html(number_format_i18n($e_emis, 2)) . '</span>'; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php if($is_tramite): ?>
                <div class="rep-energy-tramite">En trámite</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </aside>
      </div>
    </section>

    <!-- Contacto -->
    <section id="contacto" class="rep-single-contact rep-section">
      <h2>¿Te interesa esta propiedad?</h2>
      <?php echo rep_render_contact_form( $pid ); ?>
    </section>

    <!-- Simulador -->
    <section class="rep-mortgage">
      <h2>Simulador de hipoteca</h2>
      <?php echo do_shortcode('[rep_mortgage price="'.$precio.'"]'); ?>
    </section>

  </article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>

