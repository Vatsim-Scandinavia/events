<?php

return [

    /*
    |--------------------------------------------------------------------------
    | vACC Identity
    |--------------------------------------------------------------------------
    |
    | Each installation of the event hub serves a single VATSIM vACC. Admins
    | normally set its name, division and website on the settings page (FIRs
    | are set up on the FIRs admin page).
    | Setting one of these here instead locks it: the app then shows it as
    | set by the server and admins can't change it. Leave them empty to
    | manage everything in the app.
    |
    */

    'name' => env('VACC_NAME') ?: null,

    'division' => env('VACC_DIVISION') ?: null,

    // The VATSIM subdivision code, e.g. SCA. Members rated S1 or above can get the
    // Controller role automatically (switched on in the app).
    'subdivision' => filled(env('VACC_SUBDIVISION')) ? strtoupper(trim((string) env('VACC_SUBDIVISION'))) : null,

    'website' => env('VACC_WEBSITE') ?: null,

];
