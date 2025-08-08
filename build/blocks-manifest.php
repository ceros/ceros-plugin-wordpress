<?php
// This file is generated. Do not modify it manually.
return array(
	'ceros' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/ceros',
		'version' => '0.1.0',
		'title' => 'Ceros',
		'category' => 'widgets',
		'icon' => 'smiley',
		'description' => 'Add Ceros experience to your site.',
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'ceros',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'fullHeightEmbedCode' => array(
				'type' => 'string',
				'default' => ''
			),
			'scrollableEmbedCode' => array(
				'type' => 'string',
				'default' => ''
			),
			'selectedOption' => array(
				'type' => 'string',
				'default' => 'full'
			),
			'experienceName' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js'
	)
);
