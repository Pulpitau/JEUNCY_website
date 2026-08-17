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
        // JobOfferService::priceCentsFor). Couvre la publication et RIEN
        // d'autre : ni l'acces aux candidatures, ni la CVtheque, qui passent
        // desormais exclusivement par l'abonnement (decision du 2026-08-17,
        // qui a supprime le deblocage a l'offre existant depuis le 2026-08-05).
        'company_offer_price_cents' => (int) env('STRIPE_COMPANY_OFFER_PRICE_CENTS', 999),
        'cfa_offer_price_cents' => (int) env('STRIPE_CFA_OFFER_PRICE_CENTS', 599),
        // Abonnement mensuel (voir SubscriptionService::priceCentsFor) : tout
        // illimite — publication d'offres, acces aux candidatures de toutes les
        // offres, et acces a la CVtheque. Meme tarif entreprise et CFA depuis le
        // 2026-08-17 ("pour le moment", les deux grilles restent separees pour
        // pouvoir diverger a nouveau sans migration).
        'company_subscription_price_cents' => (int) env('STRIPE_COMPANY_SUBSCRIPTION_PRICE_CENTS', 49900),
        'cfa_subscription_price_cents' => (int) env('STRIPE_CFA_SUBSCRIPTION_PRICE_CENTS', 49900),
        // Tarif d'ouverture reserve aux 50 premiers abonnes, entreprises et CFA
        // confondus, verrouille a vie tant que l'abonnement continue (voir
        // SubscriptionService::founderSeatsRemaining et la migration
        // add_founder_rate_to_subscriptions_table).
        'founder_subscription_price_cents' => (int) env('STRIPE_FOUNDER_SUBSCRIPTION_PRICE_CENTS', 29900),
        'founder_seats_total' => (int) env('STRIPE_FOUNDER_SEATS_TOTAL', 50),
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
