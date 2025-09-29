<?php
/**
 * Archive Property Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main class="rep-container archive-property">
  <header><h1><?php post_type_archive_title(); ?></h1></header>

  <?php echo do_shortcode('[rep_filters]'); ?>

  <div class="rep-grid rep-grid-list">
    <?php if(have_posts()): while(have_posts()): the_post();
      $pid = get_the_ID();
      $precio = get_post_meta($pid,'precio',true);
      $ref    = get_post_meta($pid,'referencia',true);
      $m2     = get_post_meta($pid,'superficie_construida',true);
      $hab    = get_post_meta($pid,'habitaciones',true);
      $ban    = get_post_meta($pid,'banos',true);
      $label  = get_post_meta($pid,'label_tag',true);

      // Galería (destacada + primeras 5)
      $gids = (array) get_post_meta($pid,'gallery_ids',true);
      $gids = array_filter(array_map('intval',$gids));
      $imgs = array();
      if ( has_post_thumbnail($pid) ){
          $th = wp_get_attachment_image_src(get_post_thumbnail_id($pid),'large');
          if($th) $imgs[] = $th[0];
      }
      foreach($gids as $gid){
          if (count($imgs)>=5) break;
          $src = wp_get_attachment_image_src($gid,'large');
          if($src) $imgs[] = $src[0];
      }
      if(!$imgs) $imgs[] = rep_placeholder_img();

      // Descripción segura
      $raw  = get_the_excerpt();
      if(!$raw) $raw = get_the_content(null,false);
      $desc = rep_excerpt_chars($raw, 170);
      ?>
      <article <?php post_class('rep-card rep-card-line'); ?>>
        <?php if($label): ?>
          <span class="rep-badge" data-label-slug="<?php echo esc_attr($label); ?>"><?php echo esc_html( rep_label_text($label) ); ?></span>
        <?php endif; ?>

        <div class="rep-card-slider" data-images="<?php echo esc_attr( htmlspecialchars( wp_json_encode($imgs), ENT_QUOTES, 'UTF-8') ); ?>">
          <button class="rep-cs-prev" type="button" aria-label="Anterior">&#10094;</button>
          <a href="<?php the_permalink(); ?>" class="rep-cs-stage"><img src="<?php echo esc_url($imgs[0]); ?>" alt="<?php the_title_attribute(); ?>"/></a>
          <button class="rep-cs-next" type="button" aria-label="Siguiente">&#10095;</button>
        </div>
        
        <div class="rep-card-content">
            <?php if($precio): ?><div class="rep-price"><?php echo esc_html(rep_price_format($precio)); ?></div><?php endif; ?>

            <h2 class="rep-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

            <ul class="rep-mini-feats">
              <?php
                if($ref) echo '<li title="Referencia"><i class="fa-solid fa-tag"></i>'.esc_html($ref).'</li>';
                if($m2)  echo '<li title="Superficie"><i class="fa-solid fa-ruler-combined"></i>'.esc_html($m2).' m²</li>';
                if($hab) echo '<li title="Habitaciones"><i class="fa-solid fa-bed"></i>'.esc_html($hab).'</li>';
                if($ban) echo '<li title="Baños"><i class="fa-solid fa-bath"></i>'.esc_html($ban).'</li>';
              ?>
            </ul>
            
            <p class="rep-excerpt"><?php echo esc_html($desc); ?></p>
        </div>

      </article>
    <?php endwhile; else: ?>
      <p>No hay inmuebles.</p>
    <?php endif; ?>
  </div>

  <nav class="rep-pagination"><?php the_posts_pagination(array(
      'prev_text' => '<i class="fa-solid fa-arrow-left"></i>',
      'next_text' => '<i class="fa-solid fa-arrow-right"></i>',
  )); ?></nav>
</main>
<?php get_footer(); ?>
