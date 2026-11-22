<?php

return [

    /*
    | When true, the registry serves the cached configuration built by
    | `php artisan forms:cache`. Local development leaves this false so a
    | new file in app/Modules/Forms/Configurations is picked up immediately.
    */
    'cache' => env('FORM_REGISTRY_CACHE', env('APP_ENV') === 'production'),

    'cache_key' => 'forms.registry',

];
