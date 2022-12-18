<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('index');
});

Route::get('/account', function () {
    return view('account');
});

Route::get('/login', function () {
    return view('login');
});

Route::get('/forgottenPassword', function () {
    return view('forgotten_password');
});

Route::get('/firstLogin', function () {
    return view('first_login');
});

Route::get('/sapReports', function () {
    return view('sap_reports');
});
