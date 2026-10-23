<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Offline evaluation
    |--------------------------------------------------------------------------
    |
    | The evaluation suite runs against a disposable in-memory SQLite database
    | and the deterministic fake provider. It never uses production data, real
    | provider credentials, or network access. Thresholds are versioned in the
    | baseline file under evaluation/baselines.
    |
    */

    'suite_version' => 'offline-v1',

    'baseline_path' => base_path('evaluation/baselines/offline-v1.json'),

    'report_path' => storage_path('app/evaluations/offline-latest.json'),

    'thresholds' => [
        'minimum_pass_rate' => 1.0,
        'maximum_failed_checks' => 0,
        'minimum_checks' => 30,
        'maximum_critical_failures' => 0,
    ],

];
