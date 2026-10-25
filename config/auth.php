<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard". Sign-in is
    | through VATSIM Connect or Handover only, so there are no passwords, password
    | resets or password confirmation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Sign-in Provider
    |--------------------------------------------------------------------------
    |
    | Where people sign in: "vatsim" for VATSIM Connect, or "handover" for
    | VATSIM Scandinavia's Handover. Each is set up in config/services.php.
    |
    */

    'sign_in_provider' => env('AUTH_PROVIDER', 'vatsim'),

    /*
    |--------------------------------------------------------------------------
    | Development Sign-in
    |--------------------------------------------------------------------------
    |
    | Until VATSIM Connect is wired up, local installs can sign in as any user
    | from /login. It must be switched on explicitly, and only works when
    | APP_ENV=local, so a staging or production server can't turn it on.
    |
    */

    'dev_login' => (bool) env('AUTH_DEV_LOGIN', false),

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

];
