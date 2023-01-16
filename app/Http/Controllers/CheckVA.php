<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Utils\BniEnc;
use App\Http\Utils\BniCallback;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class CheckVA extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
      $clientId = env('BNI_CLIENT_ID');
      $secretKey = env('BNI_SECRET_KEY');
  
      $params = $request->validate([
        'id' => ['required', 'numeric']
      ]);

      $payment = Payment::where('id', $params['id'])->first();

     if (empty($payment)) {
        return response([
          'status' => 'failed',
          'description' => 'Payment Data tidak ada'
        ], 404);
      }

      if ($payment->method !== 'VA') {
        return response([
          'status' => 'failed',
          'description' => 'Metode pembayaran yang dipilih bukan melalui VA'
        ], 400);
      }

      if ($payment->status === 'Paid') {
        return response([
          'status' => 'success',
          'result' => [
            'payment_status' => 'Paid',
            'paid_amount' => $payment->amount
          ],
          'description' => 'VA telah dibayar.'
        ]);
      }

      $data['type'] = 'inquirybilling';
      $data['client_id'] = $clientId;
      $data['trx_id'] = $payment->transaction_id;

      $encryptedRequest = BniEnc::encrypt($data, $clientId, $secretKey);
      $sentRequest = ['client_id' => $clientId, 'data' => $encryptedRequest];

      $BNIServerResponse = BniCallback::getContent(json_encode($sentRequest));
      $bniResponse = json_decode($BNIServerResponse, TRUE);
      $responseData = BniEnc::decrypt($bniResponse["data"], $clientId, $secretKey);

      Log::info(json_encode($bniResponse));

      if ($bniResponse["status"] !== "000") {
        return response([
          'status' => 'failed',
          'description' => 'Terjadi kesalahan saat meminta data VA'
        ], 400);
      }

      $result = [];

      if (empty($responseData["payment_amount"]) && (int) $responseData["payment_amount"] <= (int) $payment->amount ) {
        $result = [
          'payment_status' => 'Unpaid',
          'paid_amount' => (int) $responseData["payment_amount"]
        ];
      } else if ($responseData["payment_amount"] >= $payment->amount) {
        $payment->status = 'Paid';
        $payment->save();
        $result = [
          'payment_status' => 'Paid',
          'paid_amount' => (int) $responseData["payment_amount"]
        ];
      }

      return response([
        'status' => 'success',
        'result' => $result
      ]);

    }
}

