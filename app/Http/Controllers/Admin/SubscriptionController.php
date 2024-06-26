<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrderHistory;
use App\Model\BusinessSetting;
use App\Model\Product;
use App\Model\SubscriptionOrders;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;

class SubscriptionController extends Controller{

    
    public function tomorrow_orders(Request $request){

        $query_param = [];
        $search = $request['search'];
        $todayDate = date("Y-m-d");
        $nextDate = date('Y-m-d', strtotime(' +1 day'));
        //$todayDate = "2022-08-25";
        //$nextDate = "2022-08-26";
        $orders = [];
        //echo $todayDate; die;

        //$userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.user_id', $userId)->where('subscription_orders.start_date', '<=', $todayDate)->where('subscription_orders.end_date', '>=', $todayDate)->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->get();
        $query = SubscriptionOrders::with(['customer'])->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.start_date', '<=', $todayDate)->where('subscription_orders.end_date', '>=', $todayDate)->select('subscription_orders.*', 'products.name', 'products.image', 'products.price');

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }

        $tomOrders = $query->latest()->paginate(Helpers::getPagination())->appends($query_param);

        foreach($tomOrders as $order){
            $orderHistory = json_decode($order->order_history);
            
            foreach($orderHistory as $subOrder){
                $subOrderDate = $subOrder->date;
                if($subOrderDate == $nextDate){
                    $orders[] = $order;
                }
            }
        }

        //echo '<pre />'; dd($tomOrders); die;
        return view('admin-views.subscription.tomorrow-list', compact('orders', 'search', 'nextDate',));
    }


    public function add_delivery_man($order_id, $sub_date, $delivery_man_id)
    {
        $orderId = $order_id;
        $subsDate = date('Y-m-d', $sub_date);
        $deliveryManId = $delivery_man_id;
        $todayDate = date("Y-m-d");
        if ($deliveryManId == 0) {
            Toastr::warning('Please Assign Deliveryman');
        }
        if($todayDate == $subsDate){
            $order = SubscriptionOrders::find($orderId);
            $orderHistory = $order['order_history'];
            if($orderHistory != ""){
                $historyArray = json_decode($orderHistory);
                foreach($historyArray as $history){
                    if($history->date == $subsDate){
                        $history->delivery_man = $deliveryManId;
                      
                      	$orderGeneratedId = $order['order_id'];
                        $checkFirstDeliver = DB::table('delivery_histories')->where('order_id', $orderGeneratedId)->where('order_date', $subsDate)->first();
                        if(isset($checkFirstDeliver) && !empty($checkFirstDeliver)){
                            $deliveryId = $checkFirstDeliver->id;
                            DB::table('delivery_histories')->where('id', $deliveryId)->delete();
                        }
                        $deliveryHistories = [
                            'order_id' => $orderGeneratedId,
                            'deliveryman_id' => $deliveryManId,
                            'order_type' => 'subscription',
                            'order_date' => $subsDate,
                            'delivery_status' => 'pending'
                        ];
                        DB::table('delivery_histories')->insert($deliveryHistories);	
                      
                    }
                }
                $subsOrderUpdate = DB::table('subscription_orders')->where('id', $orderId)->update([
                    'order_history' => json_encode($historyArray)
                ]);

                Toastr::success('Deliverman assigned to subscription.');
            } else {
                Toastr::warning('No order Details Found.');
            }
        } else {
            Toastr::warning('Delivery man can assign only for order date');
        }
        return response()->json(['status' => true], 200);
    }

    public function payment_status(Request $request){
        $orderStatus = $request->order_status;
        $subsDate = $request->subs_date;
        $todayDate = date("Y-m-d");
        if($todayDate == $subsDate){
            $order = SubscriptionOrders::find($request->id);
            $userId = $order['user_id'];
            $wallet = DB::table('wallet')->where('user_id', $userId)->first();
            $userBalance = $wallet->balance;
            
            if($userBalance>0){
                $orderHistory = $order['order_history'];
                if($orderHistory != ""){
                    $historyArray = json_decode($orderHistory);
                    foreach($historyArray as $history){
                        if($history->date == $subsDate){
                            if($history->payment_status == "pending"){
                                $subsPrice = ($history->price * $history->quantity);
                                if($subsPrice<$userBalance){
                                    
                                    $walletHistoryArray = [
                                        'user_id' => $userId,
                                        'amount' => $subsPrice,
                                        'status' => 'debit',
                                        'order_id' => $order['order_id'],
                                        'subscription_date' => $subsDate
                                    ];
                                    DB::table('wallet_histories')->insert($walletHistoryArray);
                                    
                                    $newAmount = $userBalance - $subsPrice;
                                    $walletUpdate = DB::table('wallet')->where('user_id', $userId)->update([
                                        'balance' => $newAmount
                                    ]);

                                    $history->payment_status = "completed";

                                } else {
                                    Toastr::warning("User don't have sufficient balance to deduct money.");
                                    return back();
                                }
                            } else {
                                Toastr::warning("Payment already deducted");
                                return back();
                            }
                        }
                        //echo '<pre />'; print_r($history);
                    }

                    $subsOrderUpdate = DB::table('subscription_orders')->where('id', $request->id)->update([
                        'order_history' => json_encode($historyArray)
                    ]);

                    Toastr::success('Subscription amount deducted from wallet.');
                    return back();
                }
            } else {
                Toastr::warning("Customer Don't have sufficient balance.");
                return back();
            }
            //echo $userBalance.'<pre />'; print_r($wallet);
        } else {
            Toastr::warning('Balance can deduct only for order date');
            return back();
        }
        
        if ($request->order_status == 'out_for_delivery' && $order['delivery_man_id'] == null && $order['order_type'] != 'self_pickup') {
            
        }

        $order->order_status = $request->order_status;
        $order->save();

        Toastr::success('Order status updated!');
        return back();
    }

    public function status(Request $request){
        $order = SubscriptionOrders::find($request->id);
        if ($request->order_status == 'out_for_delivery' && $order['delivery_man_id'] == null && $order['order_type'] != 'self_pickup') {
            Toastr::warning('Please assign delivery man first!');
            return back();
        }

        $order->order_status = $request->order_status;
        $order->save();

        Toastr::success('Order status updated!');
        return back();
    }

    public function details($id)
    {
        
        $order = SubscriptionOrders::with(['customer', 'delivery_address'])->where(['id' => $id])->first();
        $subs_cancel_issues = DB::table('subs_cancel_issues')->get();
        $cancelIssues = [];
        foreach($subs_cancel_issues  as $subCancelIssues){
            $issueId = $subCancelIssues->id;
            $issueTitle = $subCancelIssues->title;
            $cancelIssues[$issueId] = $issueTitle;
        }

        $delivery_men = DB::table('delivery_men')->get();
        $deliveryMenList = [];
        foreach($delivery_men  as $deliveryMen){
            $deliveryMenId = $deliveryMen->id;
            $fName = $deliveryMen->f_name;
            $lLame = $deliveryMen->l_name;
            $deliveryMenList[$deliveryMenId] = $fName." ".$lLame;
        }
        
        if (isset($order)) {
            $orderId = $order['order_id'];
            $productId = $order['product_id'];
            $userId = $order['user_id'];

            $product = Product::where(['id' => $productId])->first();
            $walletHistories = DB::table('wallet_histories')->where('order_id', $orderId)->whereNotNull('subscription_date')->limit(1)->orderBy('id', 'ASC')->get();
            $deliveryHistories = DB::table('delivery_histories')->where('order_id', $orderId)->get();
            $wallet = DB::table('wallet')->where('user_id', $userId)->first();
            return view('admin-views.subscription.order-view', compact('order', 'product', 'walletHistories', 'wallet', 'cancelIssues', 'deliveryHistories', 'deliveryMenList'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }
    
    public function list(Request $request, $status)
    {
        //dd($request['timeSlot']);
        $query_param = [];
        $search = $request['search'];
        $date = $request['date'];
        
        
        if ($status != 'all') {
            $query = SubscriptionOrders::with(['customer'])->where(['order_status' => $status]);
        } else {
            $query = SubscriptionOrders::with(['customer']);
        }

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }

        $orders = $query->latest()->paginate(Helpers::getPagination())->appends($query_param);
        //echo '<pre />'; print_r($orders); die;
        
        return view('admin-views.subscription.list', compact('orders', 'status', 'search', 'date',));
    }

}
