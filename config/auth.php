<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Super Admins
    |--------------------------------------------------------------------------
    |
    | Users whose email appears in this list bypass every authorization check
    | (via a Gate::before in AppServiceProvider). Managed by environment, not a
    | database column, so super-admin access can't be granted by a DB write and
    | can differ per environment.
    |
    */

    'super_admins' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPER_ADMIN_EMAILS', '')),
    )),

    /*
    |--------------------------------------------------------------------------
    | Platform Permissions Team
    |--------------------------------------------------------------------------
    |
    | Spatie Permission runs in "teams" mode (per-club roles), so every role
    | assignment needs a team id. The /admin panel has no tenant, so admin-staff
    | roles are scoped to this reserved sentinel team id. It must be NON-ZERO
    | (Spatie treats a falsy 0 as "no team" and stores NULL) and must never
    | collide with a real club id (clubs auto-increment from 1). A middleware
    | pins it as the active team on admin requests.
    |
    */

    'platform_team_id' => (int) env('PLATFORM_TEAM_ID', 1000000),

    /*
    |--------------------------------------------------------------------------
    | Seeded Admin Password
    |--------------------------------------------------------------------------
    |
    | Password AdminPasswordSeeder gives the platform admin. In production it
    | must come from the environment — outside it, a convenient default keeps
    | local and dev seeding frictionless. Read through config (never env()
    | directly in a seeder) so a cached config does not resolve it to null.
    |
    */

    'admin_password' => (string) env(
        'ADMIN_PASSWORD',
        env('APP_ENV') === 'production' ? '' : 'password',
    ),

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
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

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
