<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixed Plan
    |--------------------------------------------------------------------------
    |
    | The platform offers a single plan. Its Stripe price id and display price
    | come from configuration so the platform administrator can change them
    | without touching code. There is no overage billing in V1.
    |
    */

    'name' => env('PLAN_NAME', 'ChatKnowledge'),

    'price_id' => env('PLAN_PRICE_ID'),

    'price' => [
        'amount' => (int) env('PLAN_PRICE_AMOUNT', 1800), // cents (US$18.00)
        'currency' => env('PLAN_PRICE_CURRENCY', 'usd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Limits (admin-configurable)
    |--------------------------------------------------------------------------
    |
    | Consumption limits for the single plan. These are measured (Phase 2.5.3)
    | but NOT billed for overage in V1. A null value means "unlimited".
    |
    */

    'limits' => [
        'users' => env('PLAN_LIMIT_USERS', 10),
        'agents' => env('PLAN_LIMIT_AGENTS', 5),
        'questions' => env('PLAN_LIMIT_QUESTIONS', 1000),
        'documents' => env('PLAN_LIMIT_DOCUMENTS', 100),
        'storage_mb' => env('PLAN_LIMIT_STORAGE_MB', 1024),
    ],

];
