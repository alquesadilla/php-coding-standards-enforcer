<?php

return array(
    // run the commands only in this environment
    'env' => 'local',

    // pre-commit command
    'precommit_command' => 'php artisan enforcer:check --githook',


    //############## PHPCS & PHPCBF
    'phpcs_bin' => './vendor/bin/phpcs',
    'phpcbf_bin' => './vendor/bin/phpcbf',

    // code standard
    'standard' => 'PSR2',

    // file encoding
    'encoding' => 'utf-8',

    // valid file extensions for processing on phpcs
    'phpcs_extensions' => [
        'php'
    ],

    // phpcs ignore list
    'phpcs_ignore' => [
        //laravel view blade templates
        './resources/views/*'
    ],

    // temp dir to staged files
    'temp' => '.tmp_staging',



    //############### Eslint

    'eslint_bin' => '',

    'eslint_config' => '',

    // valid file extensions for processing on eslint
    'eslint_extensions' => [
        'js'
    ],

    // eslint ignore list
    // add the value of your temp folder to properly ignore files
    // ex: !.tmp_staging (on the first line of you ignore file)
    'eslint_ignore_path' => '',


    //################# Swagger

    'swagger_bin' => './vendor/bin/swagger',
    'swagger_output_path' => 'storage/docs/api-docs.json',
);
