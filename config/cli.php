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
		),
		'Router' => array(
			'class' => '\ampf\router\impl\DefaultCliRouter',
		),

		/**
		 * Request stuff
		 */
		'Request' => array(
			'class' => '\ampf\requests\impl\DefaultCli',
		),
		'RequestStub' => array(
			'class' => '\ampf\requests\impl\DefaultCli',
			'parent' => 'Request',
			'scope' => 'prototype',
		),

		/**
		 * View stuff
		 */
		'View' => array(
			'class' => '\ampf\views\impl\DefaultCliView',
			'scope' => 'prototype',
		),
	),

	'viewDirectory' => null,
);
