<?php
/**
 * Archive Property Template – tarjeta tipo PDF
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
          <span class="rep-badge"><?php echo esc_html( rep_label_text($label) ); ?></span>
        <?php endif; ?>

        <?php
          if (!function_exists('rep_svg')){
            function rep_svg($name){
              $icons = array(
                'tag'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 10l-8-8H4v8l8 8 8-8z" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
                'm2'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
                'bed'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3 7h18v8H3zM3 7V5M21 7V5M3 15v4M21 15v4" stroke="currentColor" stroke-width="2"/></svg>',
                'bath' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 10h10v7H7zM5 17h14M9 7a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="2"/></svg>'
              );
              return isset($icons[$name]) ? $icons[$name] : '';
            }
          }
        ?>

        <div class="rep-card-slider" data-images="<?php echo esc_attr( htmlspecialchars( wp_json_encode($imgs), ENT_QUOTES, 'UTF-8') ); ?>">
          <button class="rep-cs-prev" type="button" aria-label="Anterior">&#10094;</button>
          <a href="<?php the_permalink(); ?>" class="rep-cs-stage"><img src="<?php echo esc_url($imgs[0]); ?>" alt=""/></a>
          <button class="rep-cs-next" type="button" aria-label="Siguiente">&#10095;</button>
        </div>

        <?php if($precio): ?><div class="rep-price"><?php echo esc_html(rep_price_format($precio)); ?></div><?php endif; ?>

        <ul class="rep-mini-feats">
          <?php
            if($ref) echo '<li>'.rep_svg('tag').esc_html($ref).'</li>';
            if($m2)  echo '<li>'.rep_svg('m2').esc_html($m2).' m²</li>';
            if($hab) echo '<li>'.rep_svg('bed').esc_html($hab).'</li>';
            if($ban) echo '<li>'.rep_svg('bath').esc_html($ban).'</li>';
          ?>
        </ul>

        <h2 class="rep-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <p class="rep-excerpt"><?php echo esc_html($desc); ?></p>
      </article>
    <?php endwhile; else: ?>
      <p>No hay inmuebles.</p>
    <?php endif; ?>
  </div>

  <nav class="rep-pagination"><?php the_posts_pagination(); ?></nav>
</main>
<?php get_footer(); ?>