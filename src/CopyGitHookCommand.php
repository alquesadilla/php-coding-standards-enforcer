<?php

namespace Alquesadilla\Enforcer;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CopyGitHookCommand extends Command
{
    protected $signature = 'enforcer:copy';
    protected $description = 'Copy the git hooks scripts to hooks directory';
    protected $config;
    protected $files;

    public function __construct($config, Filesystem $files)
    {
        parent::__construct();
        $this->config = $config;
        $this->files = $files;
    }


    public function handle()
    {
        $environment = $this->config->get('app.env');
        $enforcerEnv = $this->config->get('enforcer.env');

        if ($environment !== $enforcerEnv) {
            return;
        }

        $hooksDir = base_path('.git/hooks');
        if (!$this->files->isDirectory($hooksDir)) {
            $this->files->makeDirectory($hooksDir, 0755);
        }

        $preCommitHook = $hooksDir . '/pre-commit';
        $preCommitContents = '#!/bin/bash'
            . PHP_EOL . $this->config->get('enforcer.precommit_command', 'php artisan enforcer:check --githook');

        $this->files->put($preCommitHook, $preCommitContents);
        $this->files->chmod($preCommitHook, 0755);
    }
}
