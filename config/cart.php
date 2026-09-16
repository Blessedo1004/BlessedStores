<?php

return [
    'cache_store' => env('CART_CACHE_STORE', 'redis'),
    'cache_ttl' => 60 * 60 * 24 * 7,
];
