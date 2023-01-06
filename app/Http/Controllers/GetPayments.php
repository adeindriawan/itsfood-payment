<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GetPayments extends Controller
{
  public function __invoke(Request $request)
  {
    $params = $request->validate([
      'customer_id' => ['required','numeric'],
      'page' => ['required', 'numeric'],
      'length' => ['required', 'numeric'],
      'status' => ['nullable', Rule::in(['Paid','Unpaid','Cancelled'])],
      'method' => ['nullable', Rule::in(['VA', 'Transfer'])]
    ]);

    DB::statement("SET SQL_MODE=''");

    $offset = (int) ($params['page'] - 1) * $params['length'];
    $limit = $params['length'];

    $paymentsQuery = DB::table('payments')
                  ->join('payment_details', 'payments.id', '=', 'payment_details.payment_id')
                  ->select([
                    'payments.id',
                    'payments.method',
                    'payments.virtual_account',
                    'payments.amount',
                    'payments.status',
                    'payments.created_at',
                    DB::raw('group_concat(payment_details.order_id) as order_ids')
                  ])
                  ->where('payments.customer_id', $params['customer_id'])
                  ->when(isset($params['status']), function ($q) use($params) {
                    $q->where('status', $params['status']);
                  })
                  ->when(isset($params['method']), function ($q) use ($params) {
                    $q->where('method', $params['method']);
                  })
                  ->groupBy('payments.id');

    $paymentsCount = DB::table('payments')
                        ->where('payments.customer_id', $params['customer_id'])
                        ->when(isset($params['status']), function ($q) use($params) {
                          $q->where('status', $params['status']);
                        })
                        ->when(isset($params['method']), function ($q) use ($params) {
                          $q->where('method', $params['method']);
                        })
                        ->count();

    $payments = (clone $paymentsQuery)
                  ->limit($limit)
                  ->offset($offset)
                  ->get();

    return response()->json([
      'status' => 'success',
      'result' => [
        'payments' => $payments,
        'total_rows' => $paymentsCount,
        'query' => $paymentsQuery->toSql()
      ]
    ]);
  }
}