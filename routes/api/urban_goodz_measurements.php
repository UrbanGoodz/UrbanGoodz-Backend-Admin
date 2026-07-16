<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'urban-goodz/fashion/measurements', 'middleware' => ['auth:api']], function () {
    Route::get('profile', 'Api\V1\UrbanGoodzFashionMeasurementController@profile');
    Route::post('profile', 'Api\V1\UrbanGoodzFashionMeasurementController@saveProfile');
    Route::post('request', 'Api\V1\UrbanGoodzFashionMeasurementController@createRequest');
    Route::post('photos', 'Api\V1\UrbanGoodzFashionMeasurementController@photos');
    Route::get('{id}', 'Api\V1\UrbanGoodzFashionMeasurementController@show');
});

Route::group(['prefix' => 'vendor/urban-goodz/fashion/measurements', 'middleware' => ['vendor.api']], function () {
    Route::get('/', 'Api\V1\Vendor\UrbanGoodzFashionMeasurementController@index');
    Route::get('{id}', 'Api\V1\Vendor\UrbanGoodzFashionMeasurementController@show');
    Route::post('{id}/review', 'Api\V1\Vendor\UrbanGoodzFashionMeasurementController@review');
});

Route::post('vendor/urban-goodz/fashion/measurement-settings', 'Api\V1\Vendor\UrbanGoodzFashionMeasurementController@settings')
    ->middleware('vendor.api');

Route::group(['prefix' => 'admin/urban-goodz/fashion', 'middleware' => ['auth:admin']], function () {
    Route::get('measurements', 'Api\V1\Admin\UrbanGoodzFashionMeasurementController@index');
    Route::get('measurements/{id}', 'Api\V1\Admin\UrbanGoodzFashionMeasurementController@show');
    Route::post('measurement-settings', 'Api\V1\Admin\UrbanGoodzFashionMeasurementController@settings');
    Route::post('measurements/{id}/privacy-status', 'Api\V1\Admin\UrbanGoodzFashionMeasurementController@privacyStatus');
    Route::post('measurements/{id}/status', 'Api\V1\Admin\UrbanGoodzFashionMeasurementController@status');
});
