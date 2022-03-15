<?php

namespace Bramus\Database\Provider;

use Silex\ServiceProviderInterface;
use Silex\Application;

class RepositoryServiceProvider implements ServiceProviderInterface
{
	public function register(Application $app)
	{
	}

	public function boot(Application $app)
	{
		foreach ($app['repositories'] as $label => $class) {
			$app['db.' . $label] = $app->share(function($app) use ($class) {
				return new $class($app);
			});
		}
	}
}
