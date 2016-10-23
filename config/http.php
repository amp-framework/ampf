<?php

return array(

	'routes' => array(
	),

	'beans' => array(
		/**
		 * Routing stuff
		 */
		'RouteResolver' => array(
			'class' => '\ampf\router\impl\DefaultRouteResolver',
			'properties' => array(
				'Config' => 'config',
			),
		),
		'Router' => array(
			'class' => '\ampf\router\impl\DefaultHttpRouter',
		),

		/**
		 * Request stuff
		 */
		'Request' => array(
			'class' => '\ampf\requests\impl\DefaultHttp',
		),
		'RequestStub' => array(
			'class' => '\ampf\requests\impl\DefaultHttp',
			'parent' => 'Request',
			'scope' => 'prototype',
		),

		/**
		 * View stuff
		 */
		'View' => array(
			'class' => '\ampf\views\impl\DefaultHttpView',
			'scope' => 'prototype',
		),
	),

	'viewDirectory' => null,
);
