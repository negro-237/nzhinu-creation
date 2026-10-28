<?php

return [
    'indexable' => (bool) env('SEO_INDEXABLE', env('APP_ENV') === 'production'),
];
