<?php

use Illuminate\Support\Facades\Route;

/**
 * Driver Pricing & Compensation admin routes.
 *
 * Self-contained: this file declares its own namespace, prefix, name prefix and
 * middleware, so integration is a single `require` from routes/admin.php at the
 * top level (NOT inside an existing group — the group is declared here).
 *
 * See COMPENSATION_ADMIN_INTEGRATION_MANIFEST.md.
 */
Route::group([
    'namespace' => 'Admin\UrbanGoodz',
    'prefix' => 'admin/urban-goodz/compensation',
    'as' => 'admin.urban-goodz.compensation.',
    // Defaults to the standard admin stack in production. Overridable so tests can
    // exercise this surface's own permission enforcement without the unrelated
    // 2FA/module gates in the full admin middleware chain.
    'middleware' => config('urban_goodz_compensation.route_middleware', ['admin']),
], function () {

    // Rules overview / published / archived
    Route::get('/', 'UrbanGoodzCompensationController@index')->name('index');
    Route::get('published', 'UrbanGoodzCompensationController@published')->name('published');
    Route::get('archived', 'UrbanGoodzCompensationController@archived')->name('archived');

    // Rule builder
    Route::get('create', 'UrbanGoodzCompensationController@create')->name('create');
    Route::post('/', 'UrbanGoodzCompensationController@store')->name('store');
    Route::get('{id}/edit', 'UrbanGoodzCompensationController@edit')->whereNumber('id')->name('edit');
    Route::put('{id}', 'UrbanGoodzCompensationController@update')->whereNumber('id')->name('update');
    Route::post('{id}/new-version', 'UrbanGoodzCompensationController@newVersion')->whereNumber('id')->name('new-version');

    // Detail and versions
    Route::get('{id}', 'UrbanGoodzCompensationController@show')->whereNumber('id')->name('show');
    Route::get('versions/{ruleKey}', 'UrbanGoodzCompensationController@versions')->name('versions');

    // Publishing controls
    Route::post('{id}/publish', 'UrbanGoodzCompensationController@publish')->whereNumber('id')->name('publish');
    Route::post('{id}/archive', 'UrbanGoodzCompensationController@archive')->whereNumber('id')->name('archive');
    Route::post('{id}/toggle-active', 'UrbanGoodzCompensationController@toggleActive')->whereNumber('id')->name('toggle-active');

    // Simulator
    Route::get('tools/simulator', 'UrbanGoodzCompensationController@simulator')->name('simulator');
    Route::post('tools/simulator', 'UrbanGoodzCompensationController@simulate')->name('simulate');

    // Calculation history and audit
    Route::get('history/calculations', 'UrbanGoodzCompensationController@calculations')->name('calculations');
    Route::get('history/calculations/{id}', 'UrbanGoodzCompensationController@calculation')->whereNumber('id')->name('calculation');
    Route::get('history/audit', 'UrbanGoodzCompensationController@audit')->name('audit');

    // Deficit alerts and split configuration
    Route::get('alerts/deficits', 'UrbanGoodzCompensationController@deficits')->name('deficits');
    Route::get('configuration/splits', 'UrbanGoodzCompensationController@splits')->name('splits');
});
