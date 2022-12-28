<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Utils\BniEnc;
use App\Utils\BniCallback;

class CreateVAController extends Controller
{
  public function __invoke(Request $request)
  {
    // query amount of orders and accumulate them
    // query costs and discounts of order details that related to those orders and aggregate them
    // create virtual account
    $orders = \explode(':', $request->orders);
    $validOrders = [];

    // make sure that the orders inputted hasn't been paid yet
    $paidOrderExists = DB::table('payment_details')->whereIn('order_id', $orders)->count();
    if ($paidOrderExists > 0) {
      return response()->json([
        "status" => "failed",
        "result" => null,
        "errors" => "Ada " . $paidOrderExists . " order yang sudah terbayar sebelumnya.",
        "description" => "Pembayaran tidak bisa dilakukan karena ada order yang berstatus sudah dibayar."
      ]);
    }

    $ordersData = DB::table('orders')
                    ->select('orders.id', 'orders.ordered_by', 'orders.status', 'users.name', 'users.id as user_id')
                    ->join('customers', 'customers.id', '=', 'orders.ordered_by')
                    ->join('users', 'users.id', '=', 'customers.user_id')
                    ->whereIn('orders.id', $orders)->get();
    $customerId = $ordersData[0]->ordered_by;
    $userName = $ordersData[0]->name;

    // only take valid orders (not "Cancelled")
    foreach ($ordersData as $key => $value) {
      if ($value->status != "Cancelled") {
        $validOrders[] = $value->id;
      }
    }

    $ordersAmount = $this->getTotalOrdersAmount($validOrders);
    $costsAndDiscountsAmount = $this->getTotalCostsAndDiscounts($validOrders);
    $totalAmount = $ordersAmount + $costsAndDiscountsAmount;
    $transactionId = \implode(":", $validOrders);
    $bniResponse = $this->createVirtualAccount($transactionId, $totalAmount, $customerId);
    if ($bniResponse["status"] !== "000") {
      return response()->json([
        "status" => "failed",
        "result" => [
          "orders" => $ordersData,
          "trxId" => $transactionId
        ],
        "errors" => $bniResponse,
        "description" => "Gagal memproses pembuatan VA untuk order ini."
      ]);
    }

    $clientId = env('BNI_CLIENT_ID');
    $secretKey = env('BNI_SECRET_KEY');
    $responseData = BniEnc::decrypt($bniResponse["data"], $clientId, $secretKey);
    $virtualAccount = $responseData["virtual_account"];

    // after VA creation is success
    $newPaymentId = DB::table('payments')->insertGetId([
      "transaction_id" => \implode(",", $validOrders),
      "customer_id" => $customerId,
      "method" => "VA",
      "virtual_account" => $virtualAccount,
      "amount" => $totalAmount,
      "status" => "Unpaid",
      "created_at" => date('Y-m-d H:i:s'),
      "created_by" => $userName
    ]);

    foreach ($validOrders as $key => $value) {
      DB::table('payment_details')->insert([
        "payment_id" => $newPaymentId,
        "order_id" => $value,
        "status" => "Unpaid",
        "created_at" => date('Y-m-d H:i:s'),
        "created_by" => $userName
      ]);
    }

    $result = [
      "status" => "success",
      "result" => [
        'totalAmount' => $ordersAmount,
        'totalCostsAndDiscounts' => $costsAndDiscountsAmount,
        'BniResponse' => $responseData
      ],
      "errors" => null,
      "description" => "Berhasil membuat virtual account untuk order yang telah diinput."
    ];

    return response()->json($result);
  }

  private function getTotalOrdersAmount(array $orders)
  {
    $totalAmount = 0;

    $ordersData = DB::table('orders')->whereIn('id', $orders)->get();
    foreach ($ordersData as $key => $value) {
      $totalAmount += $value->amount;
    }

    return $totalAmount;
  }

  private function getTotalCostsAndDiscounts(array $orders)
  {
    $totalCosts = 0;
    $totalDiscounts = 0;

    $orderDetailsData = DB::table('orders')->whereIn('id', $orders)->get();
    foreach ($orderDetailsData as $key => $value) {
      if ($value->status != "Cancelled") {
        // accumulate costs
        $costsData = DB::table('costs')->where('order_detail_id', $value->id)->get();
        foreach ($costsData as $key => $value) {
          if ($value->status == "Unpaid") {
            $totalCosts += $value->amount;
          }
        }

        // accumulate discounts
        $discountsData = DB::table('discounts')->where('order_detail_id', $value->id)->get();
        foreach ($discountsData as $key => $value) {
          if ($value->status == "Unpaid") {
            $totalDiscounts += $value->amount;
          }
        }
      }
    }

    $totalCostsAndDiscounts = $totalCosts - $totalDiscounts;

    return $totalCostsAndDiscounts;
  }

  private function createVirtualAccount($transactionId, $totalAmount, $customerId)
  {
    $clientId = env('BNI_CLIENT_ID');
    $secretKey = env('BNI_SECRET_KEY');

    $userQuery = DB::table('customers')->join('users', 'users.id', '=', 'customers.user_id')->where('customers.id', $customerId)->get();

    $VAcreatedAt = new \DateTime();
    $VAcreatedAt->modify('+1 month');
    $VAexpiredAt = $VAcreatedAt->format('Y-m-d H:i:s');
    
    $data['type'] = 'createbilling';
    $data['client_id'] = $clientId;
    $data['trx_id'] = $transactionId;
    $data['trx_amount'] = $totalAmount;
    $data['billingtype'] = 'c';
    $data['customer_name'] = $userQuery[0]->name;
    $data['customer_email'] = $userQuery[0]->email;
    $data['customer_phone'] = $userQuery[0]->phone;
    $data['virtual_account'] = '';
    $data['datetime_expired'] = $VAexpiredAt;
    $data['description'] = '';

    $encryptedRequest = BniEnc::encrypt($data, $clientId, $secretKey);
    $sentRequest = array('client_id' => $clientId, 'data' => $encryptedRequest);

    $BNIServerResponse = BniCallback::getContent(json_encode($sentRequest));
    $responseJSON = json_decode($BNIServerResponse, TRUE);

    return $responseJSON;
  }
}
