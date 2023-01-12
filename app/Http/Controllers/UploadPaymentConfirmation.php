<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class UploadPaymentConfirmation extends Controller
{
  public function __invoke(Request $request)
  {
    // query amount of orders and accumulate them
    // query costs and discounts of order details that related to those orders and aggregate them
    $orderIds = \explode(':', $request->orders);

    // make sure that the orders inputted hasn't been paid yet
    $paidOrderExists = DB::table('payment_details')->whereIn('order_id', $orderIds)->count();
    if ($paidOrderExists > 0) {
      return response()->json([
        "status" => "failed",
        "result" => null,
        "errors" => "Ada " . $paidOrderExists . " order yang sudah terbayar sebelumnya.",
        "description" => "Pembayaran tidak bisa dilakukan karena ada order yang berstatus sudah dibayar."
      ]);
    }

    $orders = DB::table('orders')
                    ->select([
                      'orders.id', 
                      'orders.ordered_by', 
                      'orders.status', 
                      'orders.amount', 
                      'users.name', 
                      'users.id as user_id'
                    ])
                    ->join('customers', 'customers.id', '=', 'orders.ordered_by')
                    ->join('users', 'users.id', '=', 'customers.user_id')
                    ->whereIn('orders.id', $orderIds)->get();
    $customerId = $orders[0]->ordered_by;
    $userName = $orders[0]->name;

    $validOrderIds = [];
    $validOrders = [];
    // only take valid orders (not "Cancelled")
    foreach ($orders as $order) {
      if ($order->status != "Cancelled") {
        $validOrders[] = $order;
        $validOrderIds[] = $order->id;        
      }
    }

    $ordersAmount = $this->getTotalOrdersAmount($validOrders);
    $costsAndDiscountsAmount = $this->getTotalCostsAndDiscounts($validOrderIds);
    $totalAmount = $ordersAmount + $costsAndDiscountsAmount;
    $transactionId = bin2hex(random_bytes(15));
    
    $newFile = Storage::putFileAs('/bukti-transfer', $request->file('file'), $transactionId.".".$request->file('file')->getClientOriginalExtension());

    $newPayment = [
      "transaction_id" => $transactionId,
      "customer_id" => $customerId,
      "method" => "Transfer",
      "virtual_account" => null,
      "file" => basename($newFile),
      "amount" => $totalAmount,
      "status" => "Unpaid",
      "created_at" => date('Y-m-d H:i:s'),
      "created_by" => $userName
    ];

    $newPaymentId = DB::table('payments')->insertGetId($newPayment);

    $newPaymentDetails = [];
    foreach ($validOrderIds as $id) {
        $newPaymentDetails[] = [
          "payment_id" => $newPaymentId,
          "order_id" => $id,
          "status" => "Unpaid",
          "created_at" => date('Y-m-d H:i:s'),
          "created_by" => $userName
        ];
    }

    DB::table('payment_details')->insert($newPaymentDetails);

    $result = [
      "status" => "success",
      "result" => [
        "totalAmount" => $ordersAmount,
        "totalCostsAndDiscounts" => $costsAndDiscountsAmount,
        "payment" => array_merge([ "id" => $newPaymentId ], $newPayment) 
      ],
      "errors" => null,
      "description" => "Berhasil mengupload bukti transfer untuk order yang telah diinput."
    ];

    return response()->json($result);
  }

  private function getTotalOrdersAmount(array $orders)
  {
    $totalAmount = 0;

    foreach ($orders as $order) {
      $totalAmount += $order->amount;
    }

    return $totalAmount;
  }

  private function getTotalCostsAndDiscounts(array $orderIds)
  {
    $totalCosts = 0;
    $totalDiscounts = 0;

    $orderDetails = DB::table('orders')->whereIn('id', $orderIds)->get();

    $validOrderDetailIds = [];

    foreach ($orderDetails as $orderDetail) {
      if ($orderDetail->status != "Cancelled") {
        // accumulate costs
        $validOrderDetailIds[] = $orderDetail->id;
      }
    }

    $costs = DB::table('costs')->whereIn('order_detail_id', $validOrderDetailIds)->get();
    foreach ($costs as $cost) {
      if ($cost->status == "Unpaid") {
        $totalCosts += $cost->amount;
      }
    }

    $discounts = DB::table('discounts')->whereIn('order_detail_id', $validOrderDetailIds)->get();
    foreach ($discounts as $discount) {
      if ($discount->status == "Unpaid") {
        $totalDiscounts += $discount->amount;
      }
    }

        // accumulate discounts
        

    $totalCostsAndDiscounts = $totalCosts - $totalDiscounts;

    return $totalCostsAndDiscounts;
  }
}