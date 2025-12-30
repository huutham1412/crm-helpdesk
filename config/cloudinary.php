<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration cho Cloudinary SDK để upload file đính kèm
    |
    */

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Folder Structure
    |--------------------------------------------------------------------------
    |
    | Folder structure trên Cloudinary
    | Mặc định: attachments/{ticket_id}
    |
    */
    'folder' => env('CLOUDINARY_FOLDER', 'crm-helpdesk/attachments'),

    /*
    |--------------------------------------------------------------------------
    | Resource Types
    |--------------------------------------------------------------------------
    |
    | Mapping file types to Cloudinary resource types
    |
    */
    'resource_types' => [
        'image' => 'image',
        'raw' => 'raw', // For non-image files (pdf, doc, etc.)
    ],
];
