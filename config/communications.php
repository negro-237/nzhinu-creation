<?php

return [
    'order_email_enabled' => (bool) env('ORDER_EMAIL_ENABLED', false),
    'whatsapp_ca' => env('WHATSAPP_CA'),
    'whatsapp_cm' => env('WHATSAPP_CM', '23755278597'),
    'contact_email' => env('SHOP_CONTACT_EMAIL', 'infos@nhinu-creation.com'),
    'social_facebook' => env('SOCIAL_FACEBOOK_URL'),
    'social_tiktok' => env('SOCIAL_TIKTOK_URL'),
    'social_youtube' => env('SOCIAL_YOUTUBE_URL'),
];
