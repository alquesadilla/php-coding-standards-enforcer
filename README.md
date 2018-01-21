# php-coding-standards-enforcer
Enforce coding standards on your PHP & JS code base

### Install with composer

```sh
composer require alquesadilla/php-coding-standards-enforcer --dev
```
### Laravel Usage

##### Add the provider to app config
```sh
Alquesadilla\Enforcer\EnforcerServiceProvider::class
```

##### Use artisan to publish the config
```sh
php artisan vendor:publish --provider="Alquesadilla\Enforcer\EnforcerServiceProvider" --tag=config
```

##### Run artisan command to copy the pre-commit hook
```sh
php artisan enforcer:copy
```

If you are working with other developers and you prefer each time that someone makes a clone and runs composer install, the hook is automatically copied, just add the copy command to the composer scripts, anyways it runs only on the defined environment, which by default is local.

```sh
"post-install-cmd": [
    "...laravel commands..."
    "php artisan enforcer:copy"
],
```

### Standalone Usage

To run against your staged code
```sh
./php-coding-standards-enforcer
```

To run against a branch if you are submitting a pull request
```sh
./php-coding-standards-enforcer {$branch}
```
