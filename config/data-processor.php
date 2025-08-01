<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    */
    'storage_disk' => env('DATA_PROCESSOR_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    */
    'temp_path' => 'temp/data-processor',

    /*
    |--------------------------------------------------------------------------
    | Default Chunk Settings
    |--------------------------------------------------------------------------
    */
    'chunk_rows' => env('DATA_PROCESSOR_CHUNK_ROWS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => env('DATA_PROCESSOR_QUEUE', 'data-processor'),

    /*
    |--------------------------------------------------------------------------
    | Use Cloud Temp Storage
    |--------------------------------------------------------------------------
    |
    | When true, chunks will be uploaded to the configured storage disk
    | instead of kept on local filesystem, allowing multi-server
    | environments (e.g. Kubernetes) to share temporary files.
    |
    */
    'use_cloud_temp' => env('DATA_PROCESSOR_CLOUD_TEMP', false),

];
