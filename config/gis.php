<?php

return [
    'python' => env('GIS_PYTHON', PHP_OS_FAMILY === 'Windows' ? storage_path('app/gis-python/Scripts/python.exe') : 'python3'),
    'timeout' => 30,
    'max_rows' => 1000,
    'max_upload_kb' => 5120,
    'storage_path' => storage_path('app/gis'),
];
