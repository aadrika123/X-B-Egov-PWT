<?php

use App\Http\Controllers\Property\Akola\WhatsappReceiptController;
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
    return view('welcome');
});
#---------------------------- document read ------------------------------
Route::get('/getImageLink', function () {
    return view('getImageLink');
});
Route::get('/whatsappTest', [\App\Http\Controllers\Notice\NoticeController::class, 'openNoticiList']);
// Laravel Logging
Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

Route::get('/water-pdf', [\App\Http\Controllers\Water\WaterPaymentController::class, 'whatsAppSend']);
Route::get('/water-payment/checkout', [\App\Http\Controllers\Water\WaterPaymentController::class, 'waterPaymentResponse']);

// Route::get('property/payment-receipt', function () {
//     return view('property_payment_reciept');
// });

Route::get('property/saf/jahirnama-receipt', function () {
        return view('prop_jahirnama');
    });
// Route::get('property/payment-receipt',WhatsappReceiptController::class)
Route::controller(WhatsappReceiptController::class)->group(function () {
    Route::get('property/payment-receipt/{tranId}', 'sendPaymentReceipt');
});
