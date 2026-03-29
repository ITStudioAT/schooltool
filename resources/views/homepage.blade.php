<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/storage/images/favicon.ico">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=roboto:100,300,400,500,700,900" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <title>Schooltool</title>
    @if (!app()->runningUnitTests())
    @vite('resources/js/apps/homepage.js')
    @endif
    {!! CookieConsent::styles() !!}
    <style>
        .cookie-consent-root {
            --cc-button-bg-color-rgb: 34, 137, 121;
            --cc-button-text-color: #ffffff;
            --cc-button-bg-opacity: 1;
        }
        .cookie-preferences-modal {
            --cc-button-bg-color-rgb: 34, 137, 121;
            --cc-button-text-color: #ffffff;
            --cc-button-bg-opacity: 1;
        }
        .cookie-consent-button-container button.cookie-consent-reject {
            --cc-button-bg-color-rgb: 214, 83, 63;
            --cc-button-text-color: #ffffff;
        }
        .cookie-preferences-modal-footer .cookie-consent-reject.primary-button {
            --cc-button-bg-color-rgb: 214, 83, 63;
            --cc-button-text-color: #ffffff;
        }
        .cookie-preferences-modal-footer .cookie-consent-accept.primary-button,
        .cookie-preferences-modal-footer .cookie-preferences-save.primary-button {
            --cc-button-bg-color-rgb: 34, 137, 121;
            --cc-button-text-color: #ffffff;
        }
        .cookie-consent-button-container button.preferences-btn {
            --cc-button-bg-color-rgb: 243, 146, 0;
            --cc-button-text-color: #ffffff;
        }
    </style>
</head>

<body class="antialiased">

    <div id="app">

    </div>

    {!! CookieConsent::scripts(options: [
    'cookie_lifetime' => config('laravel-cookie-consent.cookie_lifetime', 7),
    'reject_lifetime' => config('laravel-cookie-consent.reject_lifetime', 1),
    'disable_page_interaction' => config('laravel-cookie-consent.disable_page_interaction', true),
    'preferences_modal_enabled' => config('laravel-cookie-consent.preferences_modal_enabled', true),
    'consent_modal_layout' => config('laravel-cookie-consent.consent_modal_layout', 'bar-inline'),
    'flip_button' => config('laravel-cookie-consent.flip_button', true),
    'theme' => config('laravel-cookie-consent.theme', 'default'),
    'cookie_prefix' => config('laravel-cookie-consent.cookie_prefix', 'Laravel_App'),
    'policy_links' => [
    ['text' => CookieConsent::translate('Datenschutz'), 'link' => url('privacy-policy')],
    ['text' => CookieConsent::translate('AGB'), 'link' => url('terms-and-conditions')],
    ],
    'cookie_categories' => [
    'necessary' => [
    'enabled' => true,
    'locked' => true,
    'title' => CookieConsent::translate('Notwendige Cookies'),
    'description' => CookieConsent::translate('Diese Cookies sind erforderlich, damit die Website funktioniert.'),
    ],
    'preferences' => [
    'enabled' => env('COOKIE_CONSENT_PREFERENCES', false),
    'locked' => false,
    'js_action' => 'loadPreferencesFunc',
    'title' => CookieConsent::translate('Praeferenz-Cookies'),
    'description' => CookieConsent::translate('Diese Cookies merken sich Ihre Einstellungen.'),
    ],
    ],
    'cookie_title' => CookieConsent::translate('Cookie-Hinweis'),
    'cookie_description' => CookieConsent::translate('Diese Website verwendet nur technisch notwendige Cookies.'),
    'cookie_modal_title' => CookieConsent::translate('Cookie-Einstellungen'),
    'cookie_modal_intro' => CookieConsent::translate('Hier koennen Sie Ihre Cookie-Einstellungen anpassen.'),
    'cookie_accept_btn_text' => CookieConsent::translate('Alle akzeptieren'),
    'cookie_reject_btn_text' => CookieConsent::translate('Alle ablehnen'),
    'cookie_preferences_btn_text' => CookieConsent::translate('Einstellungen verwalten'),
    'cookie_preferences_save_text' => CookieConsent::translate('Einstellungen speichern'),
    ]) !!}
</body>

</html>
