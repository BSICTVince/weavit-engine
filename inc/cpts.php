<?php
/**
 * Custom post types for the homepage/site content model.
 * Native `post` stays as Blog. No ACF, no builder plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	register_post_type( 'service', array(
		'labels'       => array(
			'name'          => 'Services',
			'singular_name' => 'Service',
			'add_new_item'  => 'Add New Service',
			'edit_item'     => 'Edit Service',
		),
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => 'services',
		'rewrite'      => array( 'slug' => 'services' ),
		'menu_icon'    => 'dashicons-portfolio',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	) );

	register_post_type( 'integration', array(
		'labels'       => array(
			'name'          => 'Integrations',
			'singular_name' => 'Integration',
			'add_new_item'  => 'Add New Integration',
			'edit_item'     => 'Edit Integration',
		),
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => 'partners',
		'rewrite'      => array( 'slug' => 'partners' ),
		'menu_icon'    => 'dashicons-admin-plugins',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	) );

	register_post_type( 'testimonial', array(
		'labels'       => array(
			'name'          => 'Testimonials',
			'singular_name' => 'Testimonial',
			'add_new_item'  => 'Add New Testimonial',
			'edit_item'     => 'Edit Testimonial',
		),
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => 'testimonials',
		'rewrite'      => array( 'slug' => 'testimonials' ),
		'menu_icon'    => 'dashicons-format-quote',
		'supports'     => array( 'title' ),
	) );

	register_post_type( 'team_member', array(
		'labels'       => array(
			'name'          => 'Team Members',
			'singular_name' => 'Team Member',
			'add_new_item'  => 'Add New Team Member',
			'edit_item'     => 'Edit Team Member',
		),
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => 'team',
		'rewrite'      => array( 'slug' => 'team' ),
		'menu_icon'    => 'dashicons-groups',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	) );

	register_post_type( 'guide', array(
		'labels'       => array(
			'name'          => 'Guides',
			'singular_name' => 'Guide',
			'add_new_item'  => 'Add New Guide',
			'edit_item'     => 'Edit Guide',
		),
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => 'guides',
		'rewrite'      => array( 'slug' => 'guides' ),
		'menu_icon'    => 'dashicons-book-alt',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
	) );
} );
