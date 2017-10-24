<?php

namespace Alquesadilla\Enforcer;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;


class EnforcerCheckCommand extends Command
{

    protected $signature = 'enforcer:check {--githook}';
    protected $description = 'Enforce coding standards on PHP & Javascript code using PHP_CodeSniffer and ESLint. Now with Swagger support.';
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
        $isGitHook = $this->option('githook');
        $projectGitRoot = trim(shell_exec("git rev-parse --show-toplevel"));

        if (!$projectGitRoot) {
            $this->error('Not detecting GIT.');
            exit(1);
        }

        if ($environment !== $enforcerEnv) {
            return;
        }

        $phpcsBin = $this->config->get('enforcer.phpcs_bin');
        $phpcbfBin = $this->config->get('enforcer.phpcbf_bin');
        $eslintBin = $this->config->get('enforcer.eslint_bin');
        $eslintConfig = $this->config->get('enforcer.eslint_config');
        $eslintIgnorePath = $this->config->get('enforcer.eslint_ignore_path');
        $swaggerBin = $this->config->get('enforcer.swagger_bin');
        $swaggerOutputPath = $this->config->get('enforcer.swagger_output_path');

        if (!empty($phpcsBin)) {
            if (!$this->files->exists($phpcsBin)) {
                $this->error('PHP CodeSniffer not found');
                exit(1);
            }
        }

        //ESLint
        if (!empty($eslintBin)) {
            if (!$this->files->exists($eslintBin)) {
                $this->error('ESLint not found');
                exit(1);
            } elseif (!$this->files->exists($eslintConfig)) {
                $this->error('ESLint configuration file not found');
                exit(1);
            }

            if (!empty($eslintIgnorePath)) {
                if (!$this->files->exists($eslintIgnorePath)) {
                    $this->error('ESLint ignore file not found');
                    exit(1);
                }
            }
        }

        //Swagger
        if (!empty($swaggerBin)) {
            if (!$this->files->exists($swaggerBin)) {
                $this->error('Swagger not found');
                exit(1);
            }
        }

        //one of them needs to be active
        if (empty($phpcsBin) && empty($eslintBin) && empty($swaggerBin)) {
            $this->error('Phpcs/ESlint/Swagger bins are not configured');
            exit(1);
        }

        //https://stackoverflow.com/questions/9765453/is-gits-semi-secret-empty-tree-object-reliable-and-why-is-there-not-a-symbolic
        $revision = trim(shell_exec('git rev-parse --verify HEAD'));
        $against = "4b825dc642cb6eb9a060e54bf8d69288fbee4904";
        if (!empty($revision)) {
            $against = 'HEAD';
        }


        $files = trim(shell_exec("git diff-index --name-only --cached --diff-filter=ACMR {$against} --"));
        if (empty($files)) {
            exit(0);
        }

        $tempStaging = $this->config->get('enforcer.temp');
        //create temporary copy of staging area
        if ($this->files->exists($tempStaging)) {
            $this->files->deleteDirectory($tempStaging);
        }

        $fileList = explode("\n", $files);
        //based on config values
        $validPhpExtensions = $this->config->get('enforcer.phpcs_extensions');
        $validEslintExtensions = $this->config->get('enforcer.eslint_extensions');
        $validFiles = [];

        foreach ($fileList as $l) {
            if (!empty($phpcsBin)) {
                if (in_array($this->files->extension($l), $validPhpExtensions)) {
                    $validFiles[] = $l;
                }
            }

            if (!empty($eslintBin)) {
                if (in_array($this->files->extension($l), $validEslintExtensions)) {
                    $validFiles[] = $l;
                }
            }
        }

        if (empty($validFiles)) {
            exit(0);
        }

        $this->files->makeDirectory($tempStaging);
        $phpStaged = [];
        $eslintStaged = [];


        //to address the Git root folder not being the same as laravel root
        $pathDiff = null;
        if ($projectGitRoot !== base_path()){
            $pathDiff = str_replace($projectGitRoot, '', base_path());
            $pathDiff = substr($pathDiff, 1)."/";
        }



        foreach ($validFiles as $f) {
            $validFile = $f;
            if ($pathDiff !== null) {
                if (substr($f, 0, strlen($pathDiff)) === $pathDiff) {
                    $validFile = substr($f, strlen($pathDiff));
                }
            }

            $id = shell_exec("git diff-index --cached {$against} \"{$validFile}\" | cut -d \" \" -f4");
            if (!$this->files->exists($tempStaging . '/' . $this->files->dirname($validFile))) {
                $this->files->makeDirectory($tempStaging . '/' . $this->files->dirname($validFile), 0755, true);
            }
            $output = shell_exec("git cat-file blob {$id}");
            $this->files->put($tempStaging . '/' . $validFile, $output);

            if (!empty($phpcsBin)) {
                if (in_array($this->files->extension($validFile), $validPhpExtensions)) {
                    $phpStaged[] = '"' . $tempStaging . '/' . $validFile . '"';
                }
            }

            if (!empty($eslintBin)) {
                if (in_array($this->files->extension($validFile), $validEslintExtensions)) {
                    $eslintStaged[] = '"' . $tempStaging . '/' . $validFile . '"';
                }
            }
        }

        $eslintOutput = null;
        $phpcsOutput = null;

        if (!empty($phpcsBin) && !empty($phpStaged)) {
            $standard = $this->config->get('enforcer.standard');
            $encoding = $this->config->get('enforcer.encoding');
            $ignoreFiles = $this->config->get('enforcer.phpcs_ignore');
            $phpcsExtensions = implode(',', $validPhpExtensions);
            $sniffFiles = implode(' ', $phpStaged);

            $phpcsIgnore = null;
            if (!empty($ignoreFiles)) {
                $phpcsIgnore = ' --ignore=' . implode(',', $ignoreFiles);
            }
            $phpcsOutput = shell_exec("\"{$phpcsBin}\" -s --standard={$standard} --encoding={$encoding} --extensions={$phpcsExtensions}{$phpcsIgnore} {$sniffFiles} -n");
        }

        if (!empty($eslintBin) && !empty($eslintStaged)) {
            $eslintFiles = implode(' ', $eslintStaged);
            $eslintIgnore = ' --no-ignore';

            if (!empty($eslintIgnorePath)) {
                $eslintIgnore = ' --ignore-path "' . $eslintIgnorePath . '"';
            }

            $eslintOutput = shell_exec("\"{$eslintBin}\" -c \"{$eslintConfig}\"{$eslintIgnore} --quiet  {$eslintFiles}");
        }

        //only if a php file is modified, will the documentation be generated
        if (!empty($swaggerBin) && !empty($phpStaged)) {

            if (!$this->files->exists($this->files->dirname($swaggerOutputPath))) {
                $this->files->makeDirectory($this->files->dirname($swaggerOutputPath), 0755, true);
            }
            $swaggerOutput = shell_exec("\"{$swaggerBin}\" app/ -o {$swaggerOutputPath} > /dev/null 2>&1");
        }
        $this->files->deleteDirectory($tempStaging);

        if (empty($phpcsOutput) && empty($eslintOutput)) {
            exit(0);
        } else {
            if (!empty($phpcsOutput)) {

                if (!$isGitHook)
                    $this->warn($phpcsOutput);
                $this->error("PHP Coding Style Violations Found!");
                $this->info("Please run 'php artisan enforcer:check' to view and interactively fix these violations automatically.");

                if (!$isGitHook) {
                    $fixViolations = $this->confirm('Attempt to fix PHP Coding Style violations automatically on a per file basis?');
                    if ($fixViolations) {
                        $laundryBucket = [];
                        foreach ($phpStaged as $phpFile) {
                            if (strpos($phpcsOutput, str_replace('"', '', $phpFile)) != false) {
                                $phpFile = str_replace($tempStaging . '/', '', $phpFile);
                                $laundryBucket[] = $phpFile;
                            }
                        }
                        if (count($laundryBucket) > 0) {
                            foreach ($laundryBucket as $dirtyFile) {

                                $fixThisFile = $this->confirm('Fix PHP Coding Style violations automatically on: ' . $dirtyFile . '?');

                                if ($fixThisFile) {
                                    $phpcbfOutput = shell_exec("\"{$phpcbfBin}\" -s --standard={$standard} --encoding={$encoding} -n $dirtyFile");
                                    $this->info($phpcbfOutput);
                                } else {
                                    $this->info("Skipped...");
                                }
                            }
                        }
                    }
                }

            }
            if (!empty($eslintOutput)) {
                $this->error($eslintOutput);
            }
            exit(1);
        }
    }
}
