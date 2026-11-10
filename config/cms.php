<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First administrator
    |--------------------------------------------------------------------------
    |
    | Used by DatabaseSeeder to create (or update) the initial admin account.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Uploads are converted to WebP. The original file is NOT kept: the main
    | file is fitted inside max_size x max_size, and smaller copies are made
    | for each variant (only when the image is larger than that size).
    | Sizes are measured on the long side.
    |
    */

    'images' => [
        'disk' => 'uploads',
        'max_size' => 1920,
        'quality' => 82,
        'max_upload_kb' => 15 * 1024,
        'variants' => [
            'large' => 1600,
            'medium' => 800,
            'thumb' => 400,
        ],

        // Shown as hints in the admin; a warning appears when a photo is smaller.
        'recommended' => [
            'slider' => ['label' => 'Slajder', 'width' => 1920, 'height' => 1080, 'min_width' => 1600, 'min_height' => 900],
            'featured' => ['label' => 'Naslovna slika', 'width' => 1600, 'height' => 900, 'min_width' => 1200, 'min_height' => 675],
            'gallery' => ['label' => 'Galerija', 'width' => 1600, 'height' => null, 'min_width' => 800, 'min_height' => null],
            'alumni' => ['label' => 'Fotografija bivšeg učenika', 'width' => 400, 'height' => 400, 'min_width' => 200, 'min_height' => 200],
        ],
    ],

];
