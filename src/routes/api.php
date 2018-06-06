<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/
Route::prefix('v1')->group(function () {
    Route::get('search', "SearchController@index");
    Route::post('click/log','SearchController@searchClickLog');
    Route::get('autocomplete', "SearchController@autoComplete");

    Route::get('docs', function () {
        $path = storage_path('docs/swagger.json');
        if (!File::exists($path)) {
            throw new Exception("Document is not available");
        }

        $file = File::get($path);
        $swagger = json_decode($file, true);
        $swagger['host'] = request()->getHttpHost();
        $swagger['schemes'] = [request()->getScheme()];
        return response()->json($swagger);

    });
});


