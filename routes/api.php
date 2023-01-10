<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CreateVAController;
use App\Http\Controllers\UploadPaymentConfirmationController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/', function (Request $request) {
    return 'hello';
});
Route::post('/orders/va/create', [CreateVAController::class, 'createVA']);
Route::post('/orders/transfer/confirm', [UploadPaymentConfirmationController::class, 'upload']);
Route::get('/payments/payment', \App\Http\Controllers\GetPayment::class);
Route::get('/payments/count', \App\Http\Controllers\PaymentCount::class);
Route::get('/payments', \App\Http\Controllers\GetPayments::class);
Route::get('/pending-payment', [\App\Http\Controllers\GetPayment::class, 'getPendingPayment']);