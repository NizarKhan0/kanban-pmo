<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PMO Timezone
    |--------------------------------------------------------------------------
    |
    | Used for business-date calculations such as "overdue" detection.
    |
    */

    'timezone' => env('PMO_TIMEZONE', 'Asia/Kuala_Lumpur'),

    /*
    |--------------------------------------------------------------------------
    | Team Roster (Module 01)
    |--------------------------------------------------------------------------
    |
    | A PIC is flagged as "Overloaded" when their open task count reaches
    | this threshold.
    |
    */

    'roster' => [
        'overload_threshold' => (int) env('PMO_PIC_OVERLOAD_THRESHOLD', 5),
    ],

];
