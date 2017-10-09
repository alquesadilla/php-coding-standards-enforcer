<?php

namespace Alquesadilla\Enforcer;

use Illuminate\Support\ServiceProvider;

class EnforcerServiceProvider extends ServiceProvider
{
    protected $defer = true;


    public function boot()
    {
        $configPath = __DIR__ . '/../config/enforcer.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('enforcer.php');
        } else {
            $publishPath = base_path('config/enforcer.php');
        }

        $this->publishes([$configPath => $publishPath], 'config');
    }


    public function register()
    {
        $configPath = __DIR__ . '/../config/enforcer.php';
        $this->mergeConfigFrom($configPath, 'enforcer');

        $this->app->singleton('command.enforcer.copy', function ($app) {
            return new CopyGitHookCommand($app['config'], $app['files']);
        });

        $this->app->singleton('command.enforcer.check', function ($app) {
            return new EnforcerCheckCommand($app['config'], $app['files']);
        });

        $this->commands('command.enforcer.copy', 'command.enforcer.check');
    }


    public function provides()
    {
        return array('command.enforcer.copy', 'command.enforcer.check');
    }
}
