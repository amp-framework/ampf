<?php

return array(

	'routes' => array(
		'index' => array(
			'pattern' => 'index',
			'controller' => 'IndexController',
		),
		'mapper/test/index' => array(
			'pattern' => 'mapper/test/index',
			'controller' => 'MapperTestIndexController',
		),
		// generic catch-all, route.
		'index/redirect' => array(
			'pattern' => '(?P<pathInfo>.*)',
			'controller' => 'IndexRedirectController',
		),
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
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),

		/**
		 * Request stuff
		 */
		'Request' => array(
			'class' => '\ampf\requests\impl\DefaultHttp',
			'properties' => array(
				'BeanFactory' => 'beanFactory',
			),
		),
		'RequestStub' => array(
			'class' => '\ampf\requests\impl\DefaultHttp',
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
		'IndexController' => array(
			'class' => '\ampf\controller\http\IndexController',
			'parent' => 'AbstractController',
		),
		'IndexRedirectController' => array(
			'class' => '\ampf\controller\http\IndexRedirectController',
			'parent' => 'AbstractController',
		),
		'SubrouteExampleController' => array(
			'class' => '\ampf\controller\http\subroute\ExampleController',
			'parent' => 'AbstractController',
		),
		'MapperTestIndexController' => array(
			'class' => '\ampf\controller\http\mapper\test\IndexController',
			'parent' => 'AbstractController',
		),

		/**
		 * View stuff
		 */
		'View' => array(
			'class' => '\ampf\views\impl\DefaultHttpView',
			'scope' => 'prototype',
			'parent' => 'AbstractView',
		),
	),

	'viewDirectory' => realpath((realpath(__DIR__) . '/../views/')),
);
