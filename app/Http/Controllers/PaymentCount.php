<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentCount extends Controller
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
            'customer_id' => ['required', 'numeric'],
            'status' => ['nullable', Rule::in(['Unpaid', 'Paid', 'Cancelled'])],
            'method' => ['nullable', Rule::in(['VA', 'Transfer'])]
        ]);

        $paymentCount = DB::table('payments')
                            ->where('customer_id', $params['customer_id'])
                            ->when(isset($params['status']), function ($q) use ($params) {
                                $q->where('status', $params['status']);
                            })
                            ->when(isset($params['method']), function ($q) use ($params) {
                                $q->where('method', $params['method']);
                            })
                            ->count();

        return response()->json([
            'count' => $paymentCount
        ]);
    }
}
