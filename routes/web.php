<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Let\'s Watch Together API',
        'version' => '1.0.0',
        'status' => 'active'
    ]);
});
