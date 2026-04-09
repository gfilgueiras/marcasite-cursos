<?php

$stripeMode = strtolower((string) env('STRIPE_MODE', 'sandbox'));
if (! in_array($stripeMode, ['sandbox', 'live'], true)) {
    $stripeMode = 'sandbox';
}

$stripeSandboxPublishable = env('STRIPE_SANDBOX_PUBLISHABLE_KEY') ?: env('STRIPE_KEY');
$stripeSandboxSecret = env('STRIPE_SANDBOX_SECRET_KEY') ?: env('STRIPE_SECRET');
$stripeSandboxWebhook = env('STRIPE_SANDBOX_WEBHOOK_SECRET') ?: env('STRIPE_WEBHOOK_SECRET');

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional place to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    |
    | STRIPE_MODE=sandbox → usa STRIPE_SANDBOX_* (ou legado STRIPE_KEY / STRIPE_SECRET / STRIPE_WEBHOOK_SECRET).
    | STRIPE_MODE=live    → usa STRIPE_LIVE_* (produção; não misture com chaves de teste).
    |
    */
    'stripe' => [
        'mode' => $stripeMode,

        'sandbox' => [
            'publishable_key' => $stripeSandboxPublishable,
            'secret_key' => $stripeSandboxSecret,
            'webhook_secret' => $stripeSandboxWebhook,
        ],

        'live' => [
            'publishable_key' => env('STRIPE_LIVE_PUBLISHABLE_KEY'),
            'secret_key' => env('STRIPE_LIVE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
        ],

        'key' => $stripeMode === 'live'
            ? (string) env('STRIPE_LIVE_PUBLISHABLE_KEY', '')
            : (string) ($stripeSandboxPublishable ?? ''),

        'secret' => $stripeMode === 'live'
            ? (string) env('STRIPE_LIVE_SECRET_KEY', '')
            : (string) ($stripeSandboxSecret ?? ''),

        'webhook_secret' => $stripeMode === 'live'
            ? (string) env('STRIPE_LIVE_WEBHOOK_SECRET', '')
            : (string) ($stripeSandboxWebhook ?? ''),
    ],

];
