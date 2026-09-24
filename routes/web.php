<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Single Page Application (SPA)
|--------------------------------------------------------------------------
|
| All web routes render the Vue 3 application container.
| Vue Router handles client-side routing on the browser.
|
*/

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|up).*$');
