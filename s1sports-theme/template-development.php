<?php
/**
 * Template Name: Development (sport)
 * Template Post Type: page
 *
 * A Development Golf / Development Tennis page: hero, events for the chosen
 * sport with a details dialog, and a closing call to action.
 */
defined('ABSPATH') || exit;
if (s1_render_development_page()) return;
get_header(); ?>
<main><?php while (have_posts()): the_post(); ?><section class="page-hero"><div class="wrap"><h1><?php the_title(); ?></h1></div></section><div class="wrap page-content"><?php the_content(); ?></div><?php endwhile; ?></main>
<?php get_footer(); ?>
