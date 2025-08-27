<?php
// This file is generated. Do not modify it manually.
return array(
	'top-games' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'top-games/top-games',
		'version' => '0.1.0',
		'title' => 'Top Games',
		'category' => 'widgets',
		'icon' => 'smiley',
		'description' => 'Choose up to 2 games to feature; if none, show the latest with an optional offset you set.',
		'textdomain' => 'top-games',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			'selected' => array(
				'type' => 'array',
				'default' => array(
					
				),
				'items' => array(
					'type' => 'integer'
				)
			),
			'items' => array(
				'type' => 'integer',
				'default' => 1
			),
			'offset' => array(
				'type' => 'integer'
			),
			'showThumbs' => array(
				'type' => 'boolean',
				'default' => true
			),
			'variant' => array(
				'type' => 'string',
				'default' => 'standard'
			),
			'titleSize' => array(
				'type' => 'string'
			),
			'headingText' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				'items' => 1,
				'selected' => array(
					
				),
				'variant' => 'featured',
				'headingText' => 'Top Stories'
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	)
);
