# Laraval-Spa

[![Latest Version on Packagist](https://img.shields.io/packagist/v/itstudioat/spa.svg?style=flat-square)](https://packagist.org/packages/itstudioat/spa)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/itstudioat/spa/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/itstudioat/spa/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/itstudioat/spa.svg?style=flat-square)](https://packagist.org/packages/itstudioat/spa)


## What is Laravel-Spa
Laravel-Spa installs all necessary items for a fresh Laravel Single Page Application.

The installation uses next to laravel:
- Vue with Vue-Router as Javascript-Framework and routing
- Pinia as state store
- Vuetify as css-framework
- Sanctum as authentication-system for single-page-applications
- Vite for asset bundling

It provides:
- Login page together with a 'Unkown password' page and a Register page
- The login use a 2-factor-authentification (password an email), if the user has set 2-fa option

For the authenticated admins it supports
- Dashboard
- Users page, where all users are listed, may be added, changed or deleted
- Profile update with password change
- Logout

It integrates the Spatie roles and you can manage the role-based access to all web- and api-routes.

After installing this package, you can easily start to develop your own single page application with all necessary requirements.

## Installation

### Install a new laravel project

```bash
laravel new new-laravel-app
```

### Make all necessary configurations
- .env
    - APP_URL
    - DB-configuration
    - MAIL-configuration
    - SESSION_DOMAIN='.localhost'
    - LOCALE-configuration
    
- config/app.php
    - timezone
   

### Create an empty database correspondig to the .env configuration

### Install this package and run the install-commands

```bash
composer require itstudioat/spa
```

```bash
php artisan spa:install
php artisan spa:complete
```

Here you can enter your super_admin User.

### Make important entries in some files
<!--
**Put this line in composer.json**
```bash
    "autoload": {
        "psr-4": {
            ...
            "Itstudioat\\Spa\\": "app/"
        }
    },
```

**Run composer dump-autoload**
```bash
composer dump-autoload
```
-->

**Change the config/cors.php file**
```bash
 'supports_credentials' => true,
```


### You may inividualize your logo for mails
```bash
php artisan vendor:publish --tag=laravel-mail
```

**Change the file resources/views/mail/html/header.blade.php to:**
```bash
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('storage/images/logo.png') }}" class="logo" alt="Your Logo">
</a>
</td>
</tr>
```


### Install your mailing system
If you use Postmark for mailing (i use it):
```bash
composer require symfony/postmark-mailer
composer require symfony/http-client
```


## Permissions for routes ##
### Web-Routes ###
Under routes/meta/web there is for each route.js-file a php-file.
Here you may define, which route needs which (spatie-)roles.
if the array is empty, no permission is needed.

With a simple command you can synchronize these files.
The php-file is made actual with the route-js-file as basis:
```bash
    php artisan routes:sync
```

### Api-Routes ###

Api-Routes may secured with the 'api-allowed'-Middleware followed from the allowed roles.
In this example all users with the role admin may pass the apis
```bash
    Route::middleware(['auth:sanctum', 'api-allowed:admin'])->group(function () {
        ...
```

## Usage
```bash
   php artisan serve
   npm run dev
   php artisan queue:work
```


### Sending E-Mails ###
That everything works fine, sending E-Mails must be configured.
I use Postmark for Mailing and everything is fine.
You can find more infos under Laravel/Notifications.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Guenther Kron](https://github.com/itstudioat)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
