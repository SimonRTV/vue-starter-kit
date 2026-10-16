<?php

return [
    'disk' => 'media',
    'max_upload_kb' => 10240,
    'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'csv', 'docx', 'xlsx'],
    'image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    'max_image_pixels' => 12000000,
    'thumbnail_size' => 400,
    'webp_quality' => 82,
    'max_tags' => 10,
    'max_tag_length' => 30,
    'per_page_options' => [24, 48, 96],
];
