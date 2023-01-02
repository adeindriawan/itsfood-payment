<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetPayment extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {

      $params = $request->validate([
        'id' => ['required', 'numeric']
      ]);

      $payment = DB::table('payments')
                    ->select([
                      'method',
                      'virtual_account',
                      'amount',
                      'file',
                      'status'
                    ])
                    ->where('id', $params['id'])
                    ->first();

      if (empty($payment))  {
        return response([
          'status' => 'failed',
          'message' => 'Payment record not found'
        ], 404);
      }

      $paymentDetails = DB::table('payment_details')
                          ->select([
                            'id',
                            'order_id',
                            'status'
                          ])
                          ->where('payment_id', $params['id'])
                          ->get();

      return response()->json([
        'status' => 'success',
        'result' => [
          'payment' => $payment,
          'payment_details' => $paymentDetails
        ]
      ]);
    }
}
