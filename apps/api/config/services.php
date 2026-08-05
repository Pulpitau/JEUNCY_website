<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM_EMAIL', 'no-reply@jeuncy.com'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Prix fixe (en centimes) de publication d'une offre seule, different
        // selon qu'elle est publiee par une entreprise ou par un CFA (voir
        // JobOfferService::priceCentsFor) — ne donne plus acces aux
        // candidatures depuis le nouveau modele economique du 2026-08-05
        // (voir applications_unlock_price_cents et les abonnements
        // ci-dessous), d'ou la baisse du tarif entreprise et la hausse du
        // tarif CFA par rapport aux montants precedents (9,99€/4,99€).
        'company_offer_price_cents' => (int) env('STRIPE_COMPANY_OFFER_PRICE_CENTS', 800),
        'cfa_offer_price_cents' => (int) env('STRIPE_CFA_OFFER_PRICE_CENTS', 1000),
        // Deblocage ponctuel de l'acces aux candidatures d'UNE offre precise
        // (voir PaymentService::createApplicationsUnlockCheckoutSession) —
        // meme tarif entreprise/CFA, decision produit du 2026-08-05.
        'applications_unlock_price_cents' => (int) env('STRIPE_APPLICATIONS_UNLOCK_PRICE_CENTS', 5000),
        // Abonnement mensuel (voir SubscriptionService::priceCentsFor) :
        // publication illimitee + acces aux candidatures de toutes les offres
        // inclus, decision produit du 2026-08-05.
        'company_subscription_price_cents' => (int) env('STRIPE_COMPANY_SUBSCRIPTION_PRICE_CENTS', 7900),
        'cfa_subscription_price_cents' => (int) env('STRIPE_CFA_SUBSCRIPTION_PRICE_CENTS', 9900),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
