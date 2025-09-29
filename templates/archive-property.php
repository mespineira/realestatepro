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
        // Incluimos la plantilla de la tarjeta para mantener la consistencia
        include( REP_PATH . 'templates/parts/property-card.php' );
    endwhile; else: ?>
      <p>No hay inmuebles.</p>
    <?php endif; ?>
  </div>

  <nav class="rep-pagination"><?php the_posts_pagination(array(
      'prev_text' => '<i class="fa-solid fa-arrow-left"></i>',
      'next_text' => '<i class="fa-solid fa-arrow-right"></i>',
  )); ?></nav>
</main>
<?php get_footer(); ?>

