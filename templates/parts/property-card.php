<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Variables de la propiedad
$pid    = get_the_ID();
$precio = get_post_meta($pid,'precio',true);
$ref    = get_post_meta($pid,'referencia',true);
$m2     = get_post_meta($pid,'superficie_construida',true);
$hab    = get_post_meta($pid,'habitaciones',true);
$ban    = get_post_meta($pid,'banos',true);
$label  = get_post_meta($pid,'label_tag',true);

// Galería de imágenes
$imgs = rep_get_card_images($pid, 5);
if(!$imgs) $imgs[] = rep_placeholder_img();

// Descripción
$raw  = get_the_excerpt() ? get_the_excerpt() : get_the_content(null,false);
$desc = rep_excerpt_chars($raw, 170);
?>
<article <?php post_class('rep-card rep-card-line'); ?>>
    <?php if($label): ?>
      <span class="rep-badge" data-label-slug="<?php echo esc_attr($label); ?>"><?php echo esc_html( rep_label_text($label) ); ?></span>
    <?php endif; ?>

    <div class="rep-card-slider" data-images="<?php echo esc_attr( htmlspecialchars( wp_json_encode($imgs), ENT_QUOTES, 'UTF-8') ); ?>">
      <a href="<?php the_permalink(); ?>" class="rep-cs-stage"><img src="<?php echo esc_url($imgs[0]); ?>" alt="<?php the_title_attribute(); ?>"/></a>
      <button class="rep-cs-prev" type="button" aria-label="Anterior">&#10094;</button>
      <button class="rep-cs-next" type="button" aria-label="Siguiente">&#10095;</button>
    </div>
    
    <div class="rep-card-content">
        <?php if($precio): ?><div class="rep-price"><?php echo esc_html(rep_price_format($precio)); ?></div><?php endif; ?>

        <h3 class="rep-title-card"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

        <ul class="rep-mini-feats">
          <?php
            if($ref) echo '<li title="Referencia"><i class="fa-solid fa-tag"></i> '.esc_html($ref).'</li>';
            if($m2)  echo '<li title="Superficie"><i class="fa-solid fa-ruler-combined"></i> '.esc_html($m2).' m²</li>';
            if($hab) echo '<li title="Habitaciones"><i class="fa-solid fa-bed"></i> '.esc_html($hab).'</li>';
            if($ban) echo '<li title="Baños"><i class="fa-solid fa-bath"></i> '.esc_html($ban).'</li>';
          ?>
        </ul>
        
        <p class="rep-excerpt"><?php echo esc_html($desc); ?></p>
    </div>
</article>
