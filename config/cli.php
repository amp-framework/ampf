<?php

return array(

	'routes' => array(
		'index/index' => array(
			'pattern' => 'index/index',
			'controller' => 'IndexIndexController',
		),
		// generic catch-all, route.
		'help/index' => array(
			'pattern' => '(?P<pathInfo>.*)',
			'controller' => 'HelpIndexController',
		),
	),

	'beans' => array(
		/**
		 * Routing stuff
		 */
		'RouteResolver' => array(
			'class' => '\ampf\router\impl\DefaultRouteResolver',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'Router' => array(
			'class' => '\ampf\router\impl\DefaultCliRouter',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),

		/**
		 * Request stuff
		 */
		'Request' => array(
			'class' => '\ampf\requests\impl\DefaultCli',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'RequestStub' => array(
			'class' => '\ampf\requests\impl\DefaultCli',
			'parent' => 'Request',
			'scope' => 'prototype',
		),

		/**
		 * Controller stuff
		 */
		'AbstractController' => array(
			'abstract' => true,
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'IndexIndexController' => array(
			'class' => '\ampf\controller\cli\index\IndexController',
			'parent' => 'AbstractController',
		),
		'HelpIndexController' => array(
			'class' => '\ampf\controller\cli\help\IndexController',
			'parent' => 'AbstractController',
		),

		/**
		 * View stuff
		 */
		'View' => array(
			'class' => '\ampf\views\impl\DefaultCliView',
			'scope' => 'prototype',
			'parent' => 'AbstractView',
		),
	),

	'viewDirectory' => realpath((realpath(__DIR__) . '/../views/')),
);
