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
                      'id',
                      'method',
                      'virtual_account',
                      'amount',
                      'file',
                      'status',
                      'created_at'
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

    public function getPendingPayment (Request $request) {
      $params = $request->validate([
        'customer_id' => ['required', 'numeric']
      ]);

      $pendingPayment = DB::table('payments')
                            ->select([
                              'id',
                              'method',
                              'virtual_account',
                              'amount',
                              'file',
                              'status',
                              'created_at'
                            ])
                            ->where('status', 'Unpaid')
                            ->where('customer_id', $params['customer_id'])
                            ->first();

      if (empty($pendingPayment)) {
        return response([
          'status' => 'failed',
          'description' => 'No pending payment found',
          'result' => null
        ], 404);
      }
                      
      $paymentDetails = DB::table('payment_details')
                            ->select([
                              'id',
                              'order_id',
                              'status'
                            ])
                            ->where('payment_id', $pendingPayment->id)
                            ->get();

      return response()->json([
        'status' => 'success',
        'result' => [
          'payment' => $pendingPayment,
          'payment_details' => $paymentDetails
        ]
      ]);
    }
}

