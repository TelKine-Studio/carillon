<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carillon
    |--------------------------------------------------------------------------
    |
    | Almost every option of Carillon is configured from the administration
    | UI and stored in the database (per project for push keys). The values
    | below only describe the runtime environment.
    |
    */

    'version' => env('CARILLON_VERSION', '0.1.0'),

    'publisher' => 'Telkine',

    'publisher_url' => 'https://github.com/TelKine-Studio',

    'repository' => env('CARILLON_REPOSITORY', 'https://github.com/TelKine-Studio/carillon'),

];
