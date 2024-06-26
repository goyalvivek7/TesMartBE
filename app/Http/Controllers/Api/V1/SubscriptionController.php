<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Model\BusinessSetting;
use App\Model\DMReview;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrderHistory;
use App\Model\SubscriptionOrders;
use App\Model\OrderType;
use App\Model\Product;
use App\Model\Review;
use App\Model\CartFinal;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;
use Redirect;
use Session;

class SubscriptionController extends Controller
{  

    public function user_update_subscription(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'order_id' => 'required',
            'update_date' => 'required',
            'update_quantity' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        if($request['user_id'] != ""){
            $userId = $request['user_id'];
            $orderId = $request['order_id'];
            $updateDate = $request['update_date'];
            $updateQuantity = $request['update_quantity'];
            $todayDate = date("Y-m-d");

            if(strtotime($updateDate) <= strtotime($todayDate)){
                $response['status'] = 'fail';
                $response['message'] = 'You can not update today data, today order in processing';
                $response['data'] = []; 
                return response()->json($response, 200);
            } else {
                $checkOrder = DB::table('subscription_orders')->where([ 'order_id' => $orderId, 'user_id' => $userId ])->first();
                $daysFormation = json_decode($checkOrder->days_formation);

                if(isset($checkOrder) && !empty($checkOrder)){
                    //echo '<pre />'; print_r($checkOrder);
                    $orderHistory = json_decode($checkOrder->order_history);
                    //echo '<pre />'; print_r($orderHistory);
                    $updateCounter = 0; $productPrice = 0;
                    foreach($orderHistory as $dailyData){
                        $productPrice = $dailyData->price;
                        if($dailyData->date == $updateDate){
                            //echo "find: ".$dailyData->date;
                            $dailyData->quantity = $updateQuantity;
                            $updateCounter++;
                        }
                    }
                    if($updateCounter==0){
                        $newArray['date'] = $updateDate;
                        $newArray['payment_status'] = 'pending';
                        $newArray['delivery_status'] = 'pending';
                        $newArray['delivery_man'] = '';
                        $newArray['price'] = $productPrice;
                        $newArray['quantity'] = $updateQuantity;

                        array_push($orderHistory, $newArray);

                        $daysFormation[] = $updateDate;

                        $updateCounter++;
                    }

                    if($updateCounter>0){

                        DB::table('subscription_orders')->where(['order_id' => $orderId, 'user_id' => $userId])->update([
                            'order_history' => json_encode($orderHistory),
                            'days_formation' => json_encode($daysFormation)
                        ]);

                        $checkOrder = DB::table('subscription_orders')->where([ 'order_id' => $orderId, 'user_id' => $userId ])->first();

                        $response['status'] = 'success';
                        $response['message'] = 'Subscription Order Updated';
                        $response['data'][] = $checkOrder;
                        return response()->json($response, 200);

                    } else {

                        $response['status'] = 'fail';
                        $response['message'] = 'Order not found.';
                        $response['data'] = []; 
                        return response()->json($response, 200);
                    }

                    //echo '<pre />'; print_r($orderHistory);
                    //echo $orderHistory;
                } else {
                    $response['status'] = 'fail';
                    $response['message'] = 'Order not found.';
                    $response['data'] = []; 
                    return response()->json($response, 200);
                }
            }
            // echo $updateDate." - ".strtotime($updateDate).'<br />';
            // echo $todayDate." - ".strtotime($todayDate).'<br />';
            // echo $userId." - ".$orderId." - ".$updateDate." - ".$updateQuantity;
            
        }
    }

    public function cancel_subscription(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'order_id' => 'required',
            'cancel_issue_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }


        if($request['user_id'] != ""){
            $userId = $request['user_id'];
            $orderId = $request['order_id'];
            $cancelIssueId = $request['cancel_issue_id'];

            if(isset($request['cancel_reason']) && !empty($request['cancel_reason'])){
                $cancelReason = $request['cancel_reason'];
            } else {
                $cancelReason = NULL;
            }

            $checkOrder = DB::table('subscription_orders')->where([ 'order_id' => $orderId, 'user_id' => $userId ])->first();

            if(isset($checkOrder) && !empty($checkOrder)){

                DB::table('subscription_orders')->where(['order_id' => $orderId, 'user_id' => $userId])->update([
                    'order_status'  => 'canceled',
                    'cancel_issue_id' => $cancelIssueId,
                    'cancel_reason' => $cancelReason
                ]);

                $checkOrder = DB::table('subscription_orders')->where([ 'order_id' => $orderId, 'user_id' => $userId ])->first();

                $response['status'] = 'success';
                $response['message'] = 'Subscription Order Canceled';
                $response['data'][] = $checkOrder;
                return response()->json($response, 200);
                
            } else {

                $response['status'] = 'fail';
                $response['message'] = 'Order Not Found';
                $response['data'] = [];
                return response()->json($response, 200);

            }
            
        }
    }

    public function pause_subscription(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'order_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }


        if($request['user_id'] != ""){
            $userId = $request['user_id'];
            $orderId = $request['order_id'];

            $checkOrder = DB::table('subscription_orders')->where([ 'order_id' => $orderId, 'user_id' => $userId ])->first();

            if(isset($checkOrder) && !empty($checkOrder)){

                DB::table('subscription_orders')->where(['order_id' => $orderId, 'user_id' => $userId])->update([
                    'order_status'  => 'pause'
                ]);

                $checkOrder = DB::table('subscription_orders')->where([ 'order_id' => $orderId, 'user_id' => $userId ])->first();

                $response['status'] = 'success';
                $response['message'] = 'Subscription Order Paused';
                $response['data'][] = $checkOrder;
                return response()->json($response, 200);
                
            } else {

                $response['status'] = 'fail';
                $response['message'] = 'Order Not Found';
                $response['data'] = [];
                return response()->json($response, 200);

            }
            
        }
    }

    public function cancel_issues(Request $request){
        $cancelIssues = DB::table('subs_cancel_issues')->where('status', 1)->get();
        $response['status'] = 'success';
        $response['message'] = 'Cancel Issues Found';
        $response['data'] = $cancelIssues;
        return response()->json($response, 200);
    }

    public function monthly_subscription_date_wise(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'order_month' => 'required',
            'order_year' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }


        if($request['user_id'] != ""){
            $userId = $request['user_id'];
            $orderMonth = $request['order_month'];
            $orderYear = $request['order_year'];

            $dataData = array();
            
            if($orderMonth < 10){
                $orderMonth = "0".$orderMonth;
            }
            $orderStartDate = $orderYear."-".$orderMonth."-01";
            $orderEndDate = $orderYear."-".$orderMonth."-31";
            
            $userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.user_id', $userId)->where( function ($query) use ($orderStartDate, $orderEndDate){
                $query->whereBetween('subscription_orders.start_date', [$orderStartDate, $orderEndDate])
                ->orWhereBetween('subscription_orders.end_date', [$orderStartDate, $orderEndDate]);
            })->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->get();
            //echo $userOrders->toSql();
            if(isset($userOrders) && !empty($userOrders[0])){

                foreach($userOrders as $order){
                    // echo '<pre />'; print_r($order);
                    //$orderHistory = $order['order_history'];
                    $image = "";
                    $orderHistory = json_decode($order->order_history);
                    
                    if(isset($order->image) && $order->image != "" && $order->image != []){
                        $imageArray = json_decode($order->image);
                        $image = $imageArray[0];
                    }
                    

                    foreach($orderHistory as $subOrder){
                        $newArray = [];
                        $subOrderDate = $subOrder->date;
                        $newArray['payment_status'] = $subOrder->payment_status;
                        $newArray['delivery_status'] = $subOrder->delivery_status;
                        $newArray['product_name'] = $order->name;
                        $newArray['quantity'] = $subOrder->quantity;
                        $newArray['product_id'] = $order->product_id;
                        $newArray['order_id'] = $order->order_id;
                        $newArray['image'] = $image;
                        $dataData[$subOrderDate][] = $newArray;
                        //echo $subOrderDate.'<pre />'; print_r($subOrder);
                    }
                    //$deliveryDate = $orderHistory->date;
                    //echo '<pre />'; print_r($orderHistory);
                }

                $response['status'] = 'success';
                $response['message'] = 'Calander Orders Found';
                // $response['data'] = $userOrders;
                $response['data'][] = $dataData;
                return response()->json($response, 200);
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'Calander Orders Not Found';
                $response['data'] = [];
                return response()->json($response, 200);
            }
            
        }
    }


    public function current_month_orders(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'order_month' => 'required',
            'order_year' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }


        if($request['user_id'] != ""){
            $userId = $request['user_id'];
            $orderMonth = $request['order_month'];
            $orderYear = $request['order_year'];
            
            if($orderMonth < 10){
                $orderMonth = "0".$orderMonth;
            }
            $orderStartDate = $orderYear."-".$orderMonth."-01";
            $orderEndDate = $orderYear."-".$orderMonth."-31";
            //$userOrders = DB::table('subscription_orders')->where('user_id', $userId)->get();
            //$userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.user_id', $userId)->where('subscription_orders.start_date', '>=', $orderStartDate)->orWhere('subscription_orders.end_date', '<=', $orderEndDate)->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->toSql();
            // $userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.user_id', $userId)->where( function ($query) use ($orderStartDate, $orderEndDate){
            //     $query->where('subscription_orders.start_date', '>=', $orderStartDate)
            //     ->orWhere('subscription_orders.end_date', '<=', $orderEndDate);
            // })->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->toSql();
            $userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.user_id', $userId)->where( function ($query) use ($orderStartDate, $orderEndDate){
                $query->whereBetween('subscription_orders.start_date', [$orderStartDate, $orderEndDate])
                ->orWhereBetween('subscription_orders.end_date', [$orderStartDate, $orderEndDate]);
            })->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->get();
            //echo $userOrders->toSql();
            if(isset($userOrders) && !empty($userOrders[0])){
                $response['status'] = 'success';
                $response['message'] = 'Monthely Subscription Orders';
                $response['data'] = $userOrders;
                return response()->json($response, 200);
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'No Monthely Subscription Orders';
                $response['data'] = [];
                return response()->json($response, 200);
            }
            
        }
    }

    public function subscription_detail(Request $request){
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        if($request['order_id'] != ""){
            $orderId = $request['order_id'];
            //$userOrders = DB::table('subscription_orders')->where('user_id', $userId)->get();
            $userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.order_id', $orderId)->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->get();
            $walletHistories = DB::table('wallet_histories')->where('wallet_histories.order_id', $orderId)->orderBy('wallet_histories.id','DESC')->get();

            $response['status'] = 'success';
            $response['message'] = 'Subscription List';
            $response['data'] = $userOrders;
            $response['wallet_histories'] = $walletHistories;
            return response()->json($response, 200);
        }
    }

    public function subscription_list(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        if($request['user_id'] != ""){
            $userId = $request['user_id'];
            //$userOrders = DB::table('subscription_orders')->where('user_id', $userId)->get();
            $userOrders = DB::table('subscription_orders')->join('products', 'products.id', '=', 'subscription_orders.product_id')->where('subscription_orders.user_id', $userId)->select('subscription_orders.*', 'products.name', 'products.image', 'products.price')->orderBy('subscription_orders.id','DESC')->get();

            $response['status'] = 'success';
            $response['message'] = 'Subscription List';
            $response['data'] = $userOrders;
            return response()->json($response, 200);
        }
    }

    public function create_subscription(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'product_id' => 'required',
            'order_type' => 'required',
            'days_formation' => 'required',
            'quantity_array' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'delivery_address_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        if($request['user_id'] != ""){

            $userId = $request['user_id'];
            $productId = $request['product_id'];
            $orderType = $request['order_type'];
            $daysFormation = $request['days_formation'];
            $quantityArray = $request['quantity_array'];

            $productData = Product::where('id', $productId)->first();
            $productPrice = $productData['price'];

            $subsArray = array();
            $i=0;
            foreach($daysFormation as $days){
                $subsArray[$i]['date'] = $days;
                $subsArray[$i]['payment_status'] = 'pending';
                $subsArray[$i]['delivery_status'] = 'pending';
                $subsArray[$i]['delivery_man'] = '';
                $subsArray[$i]['price'] = $productPrice;
                $i++;
            }

            $i=0;
            foreach($quantityArray as $quantity){
                $subsArray[$i]['quantity'] = $quantity;
                $i++;
            }

            if(isset($request['coupon_code']) && $request['coupon_code'] != ""){
                $couponCode = $request['coupon_code'];
            } else {
                $couponCode = NULL;
            }

            $lastOrder = DB::table('subscription_orders')->whereNotNull('order_id')->limit(1)->orderBy('id', 'DESC')->get();
            if(count($lastOrder) > 0){
                $orderId = (($lastOrder[0]->order_id) + 1);
            } else {
                $orderId = 10000;
            }

            $wallet = DB::table('wallet')->where('user_id', $userId)->first();
            if(isset($wallet) &&  !empty($wallet)){
                $userBalance = $wallet->balance;
            } else {
                $userBalance = 0;
            }
            

            $subscriptionOrders = [
                'user_id' => $userId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'order_type' => $orderType,
                'days_formation' => json_encode($daysFormation),
                'order_history' => json_encode($subsArray),
                'start_date' => $request['start_date'],
                'end_date' => $request['end_date'],
                'coupon_code' => $couponCode,
                'delivery_address_id' => $request['delivery_address_id'],
                'user_balance' => $userBalance,
            ];
            
            DB::table('subscription_orders')->insert($subscriptionOrders);

            $createdOrder = DB::table('subscription_orders')->where('order_id', $orderId)->get();

            $response['status'] = 'success';
            $response['message'] = 'Subscription Created';
            $response['data'] = $createdOrder;
            return response()->json($response, 200);
        }
    }

    public function daily_needs(){
        $allProduct = Product::where(['daily_needs' => 1, 'status' => 1])->get();
        $response['status'] = 'success';
        $response['message'] = 'Daily Products';
        $response['data'] = $allProduct;
        return response()->json($response, 200);
    }

    public function daily_needs_categories(){
        $allProduct = Product::where(['daily_needs' => 1, 'status' => 1])->get();
        $catArray = []; $allCatData = [];
        foreach($allProduct as $product){
            $catArray[] = $product->cat_id;
        }
        $catArray = array_unique($catArray);
        $allCatData = DB::table('categories')->whereIn('id', $catArray)->get();
        $response['status'] = 'success';
        $response['message'] = 'Daily Products Categories';
        $response['data'] = $allCatData;
        return response()->json($response, 200);
    }





    public function id_list(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        $userId = $request['user_id'];
        //echo $userId;
        $cartIdArray = DB::table('cart')->where('user_id', $userId)->where('status', 'pending')->select('product_id')->get();
        //echo '<pre />'; print_r($cartIdArray);
        if(!empty($cartIdArray) && isset($cartIdArray[0])){
            $idArray = array();
            foreach($cartIdArray as $cartArray){
                $idArray[] = $cartArray->product_id;
            }

            $response['status'] = 'success';
            $response['message'] = 'Cart List';
            $response['data'] = $idArray;
            return response()->json($response, 200);
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Cart Not Found';
            $response['items'] = [];
            return response()->json($response, 200);
        }
        
    }
  
  	public function create_membership_order(Request $request){

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'amount' => 'required',
            'package_id' => 'required',
            'valid_days' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = [];

            return response()->json($response, 200);
        }
        
        if($request['user_id'] != ""){

            $userId = $request['user_id'];
            $amount = $request['amount'];
            $packageId = $request['package_id'];
            $validDays = $request['valid_days'];

            $lastWallet = DB::table('memberships')->whereNotNull('receipt_id')->limit(1)->orderBy('id', 'DESC')->get();
            if(count($lastWallet) > 0){
                $receiptId = (($lastWallet[0]->receipt_id) + 1);
            } else {
                $receiptId = 1;
            }

          	$amount = ($amount * 100);
          
            $api = new Api(config('razor.razor_key'), config('razor.razor_secret'));
            $orderData = $api->order->create(
                array(
                    'receipt' => $receiptId, 
                    'amount' => $amount, 
                    'currency' => 'INR', 
                    'notes'=> array(
                        'user_id'=> $userId,
                        'order_type'=> "membership"
                    )
                )
            );
            
            if(isset($orderData) && $orderData['status'] == "created"){

                $orderId = $orderData['id'];
                $orderAmount = ($orderData['amount']/100);
                $orderReceipt = $orderData['receipt'];
                $orderNotes = $orderData['notes'];
                $orderUserId = $orderNotes->user_id;
                $orderOrderType = $orderNotes->order_type;
                $validDays = $request['valid_days'];
                
                
                $walletOrders = [
                    'user_id' => $orderUserId,
                    'order_id' => $orderId,
                    'package_id' => $packageId,
                    'amount' => $orderAmount,
                    'receipt_id' => $orderReceipt,
                    'order_status' => $orderData['status'],
                    'valid_days' => $validDays
                ];
                
                DB::table('memberships')->insert($walletOrders);

                $orderAray['order_id'] = $orderId;
                $orderAray['user_id'] = $orderUserId;
                $orderAray['amount'] = $orderAmount;
                $orderAray['receipt_id'] = $orderReceipt;
                $orderAray['status'] = $orderData['status'];
                $orderAray['order_type'] = $orderOrderType;

                $response['status'] = 'success';
                $response['message'] = 'Order Created';
                $response['data'][] = $orderAray;
                return response()->json($response, 200);

            } else {

                $response['status'] = 'fail';
                $response['message'] = 'Getting some error in generating order';
                $response['data'] = [];
                return response()->json($response, 200);

            }
        }
    }
  
  

    public function final_cart(Request $request){
        if(isset($request['order_type']) && $request['order_type'] != 4){
            $validator = Validator::make($request->all(), [
                'user_id' => 'required',
                'is_wallet' => 'required',
                'delivery_address_id' => 'required',
                'time_slot_id' => 'required',
                'same_day_delievery' => 'required',
                'order_type' => 'required'
            ]);

            if ($validator->fails()) {
                $response['status'] = 'fail';
                $response['message'] = 'Please send all required fields.';
                $response['data'] = []; 
                return response()->json($response, 200);
            }
        } elseif($request['order_type'] == 4){
            $validator = Validator::make($request->all(), [
                'user_id' => 'required',
                'is_wallet' => 'required'
            ]);

            if ($validator->fails()) {
                $response['status'] = 'fail';
                $response['message'] = 'Please send all required fields.';
                $response['data'] = []; 
                return response()->json($response, 200);
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        $userId = $request['user_id'];
        $couponDiscount = 0;

        $cartOrder = DB::table('cart')->where('user_id', $userId)->where('status', 'pending')->get();

        if(!empty($cartOrder) && isset($cartOrder[0])){

            $basicCart['total_items'] = count($cartOrder);
            $totalPrice = 0.00; $basicPrice = 0.00; $taxAmount = 0.00; $catName = ""; $remainingBalance = 0.00;
            $cartArray = array(); $productArray = array();
            foreach($cartOrder as $cart){
                $productId = $cart->product_id;
                $cartArray[] = $cart->id;
                $productArray[] = $productId;

                $productData = DB::table('products')->where('id', $productId)->get();
                $deliveryManagement = DB::table('business_settings')->where('key', 'delivery_management')->get();
                $deliveryCharge = DB::table('business_settings')->where('key', 'delivery_charge')->get();

                //$totalPrice += ($cart->quantity * $cart->total_price);
                $totalPrice += $cart->total_price;
                $basicPrice += ($cart->quantity * $cart->product_price);

                if(isset($productData) && !empty($productData[0])){
                    $tax = $productData[0]->tax;
                    $taxType = $productData[0]->tax_type;
                    $categoryArray = json_decode($productData[0]->category_ids);
                    foreach($categoryArray as $category){
                        $catPosition = $category->position;
                        $catId = $category->id;

                        if($catPosition == 1){
                            $catArray = DB::table('categories')->where('id', $catId)->get();
                            //echo '<pre />'; print_r($catArray[0]->name);
                            if(isset($catArray)){
                                $catName = $catArray[0]->name;
                            }
                        }

                    }
                    //echo '<pre />'; print_r($categoryArray);

                    //echo $productData[0]->name.'----'.$taxType.'---'.$tax.'<br />';
                    if($taxType == "percent"){
                        //$taxAmount += ($cart->quantity * (($tax * $cart->total_price)/100));
                        $taxAmount += ($cart->quantity * (($tax * $cart->product_price)/100));
                        //echo "percent--".$taxAmount.'<br />';
                    }
                    if($taxType == "amount"){
                        $taxAmount += ($cart->quantity * $tax);
                        //echo "amount--".$taxAmount.'<br />';
                    }
                }

                //echo '<pre />'; print_r(json_decode($deliveryManagement->value));
                $deliveryArray = json_decode($deliveryManagement[0]->value);
                $deliveryStatus = $deliveryArray->status;

                if($deliveryStatus == 0){
                    $delCharge = $deliveryCharge[0]->value;
                } else {
                    $delCharge = $deliveryArray->min_shipping_charge;
                }

                $cart->category_name = $catName;

                //echo '<pre />'; print_r($deliveryArray);
                //echo '<pre />'; print_r($deliveryCharge);
            }
            
            if($request['order_type'] != 1){ 
                $delCharge = 0; 
            }

            $userData = User::where('id', $userId)->first();
            if($userData->prime_member==1){
                $memberValidity = $userData->member_validity;
                $todayStr = strtotime(date('Y-m-d h:i:s'));
                if($todayStr<strtotime($memberValidity)){
                    $delCharge = 0;
                }
            }

            if(isset($request['coupon_code']) && $request['coupon_code'] != ""){
                $couponCode = $request['coupon_code'];

                $coupon = Coupon::active()->where(['code' => $couponCode])->first();
                
                if (isset($coupon)) {
                    if ($coupon['limit'] != null) {
                        $total = Order::where(['user_id' => $request['user_id'], 'coupon_code' => $couponCode])->count();
                        if ($total < $coupon['limit']) {
                            
                            $cMinPurchase = $coupon['min_purchase'];
                            $cMaxDiscount = (float) $coupon['max_discount'];
                            $cDiscount = $coupon['discount'];
                            $cDiscountType = $coupon['discount_type'];
                            //echo "In Final Cart"."!!!!";
                            if($cDiscountType == "amount"){
                                //echo $totalPrice."@@@@@";
                                if($totalPrice >= $cMinPurchase){
                                    $totalPrice = $totalPrice - $cDiscount;
                                    if($totalPrice<0){
                                        $totalPrice = 0;
                                    }
                                    $couponDiscount = $cDiscount;
                                }
                            }

                            if($cDiscountType == "percent"){
                              
                                if($totalPrice >= $cMinPurchase){
                                    $couponDiscount = (($cDiscount * $totalPrice)/100);
                                    if($couponDiscount <= $cMaxDiscount){
                                        $totalPrice = $totalPrice - $couponDiscount;
                                    } else {
                                        $totalPrice = $totalPrice - $coupon['max_discount'];
                                      	$couponDiscount = $coupon['max_discount'];
                                    }
                                  	
                                }
                            }
                            
                        }
                    }

                }
            } else {
                $couponCode = "";
            }

            $basicCart['total_amount'] = $totalPrice;
            $basicCart['basic_amount'] = $basicPrice;

            $fDiscount = ($basicPrice - $totalPrice);

            $basicCart['total_discount'] = ($basicPrice - $totalPrice);
            $basicCart['tax_amount'] = $taxAmount;
            $basicCart['delivery_charge'] = $delCharge;
            $basicCart['coupon_discount'] = $couponDiscount;

            $sTotal = round(($totalPrice + $taxAmount + $delCharge), 2);

            $basicCart['sub_total'] =  round(($totalPrice + $taxAmount + $delCharge), 2);
            $basicCart['wallet_balance'] =  0;
          	$basicCart['wallet_remaining'] = 0;

            $remainingBalance = $basicCart['sub_total'];

            if($request['is_wallet'] == 1){
                $wallet = DB::table('wallet')->where('user_id', $request['user_id'])->get();
                if(isset($wallet) && isset($wallet[0]) && $wallet[0]->balance){
                    $walletBalance = $wallet[0]->balance;
                    
                  	if($basicCart['sub_total']<=$walletBalance){
                      	$remainingBalance = 0;
                      	$basicCart['wallet_balance'] = $basicCart['sub_total'];
                      	$basicCart['wallet_remaining'] = $walletBalance - $basicCart['sub_total'];
                    } else {
                      	$remainingBalance = $basicCart['sub_total'] - $walletBalance;
                      	$basicCart['wallet_balance'] = $walletBalance;
                      	$basicCart['wallet_remaining'] = 0;
                    }
                }
            }

            $walletBalance = $basicCart['wallet_balance'];
            $walletRemaining = $basicCart['wallet_remaining'];


            $basicCart['remaining_sub_total'] =  round($remainingBalance, 2);

            $finalCart = CartFinal::where('user_id', $userId)->where('cart_status', 'pending')->get();
            if(!empty($finalCart) && isset($finalCart[0])){

                CartFinal::where(['user_id' => $userId, 'cart_status' => 'pending'])->update([
                    'cart_list'  => json_encode($cartArray),
                    'product_list'  => json_encode($productArray),
                    'total_amount'  => $totalPrice,
                    'basic_amount'  => $basicPrice,
                    'total_discount'  => $fDiscount,
                    'coupon_code' => $couponCode,
                    'tax_amount'  => $taxAmount,
                    'delivery_charge'  => $delCharge,
                    'coupon_discount'  => $couponDiscount,
                    'sub_total'  => $sTotal,
                    'wallet_balance'  => $walletBalance,
                    'wallet_remaining'  => $walletRemaining,
                    'remaining_sub_total' => round($remainingBalance, 2),
                    'final_amount' => round($remainingBalance),
                    'delivery_address_id' => $request['delivery_address_id'],
                    'time_slot_id' => $request['time_slot_id'],
                    'same_day_delievery' => $request['same_day_delievery'],
                    'order_type' => $request['order_type']
                ]);

            } else {

                $cartFinal = new CartFinal();
                $cartFinal->user_id = $userId;
                $cartFinal->cart_list  = json_encode($cartArray);
                $cartFinal->product_list  = json_encode($productArray);
                $cartFinal->total_amount  = $totalPrice;
                $cartFinal->basic_amount  = $basicPrice;
                $cartFinal->total_discount  = $fDiscount;
                $cartFinal->coupon_code = $couponCode;
                $cartFinal->tax_amount  = $taxAmount;
                $cartFinal->delivery_charge  = $delCharge;
                $cartFinal->coupon_discount  = $couponDiscount;
                $cartFinal->sub_total  = $sTotal;
                $cartFinal->wallet_balance  = $walletBalance;
                $cartFinal->wallet_remaining  = $walletRemaining;
                $cartFinal->remaining_sub_total = round($remainingBalance, 2);
                $cartFinal->cart_status = 'pending';
                $cartFinal->final_amount = round($remainingBalance);
                $cartFinal->delivery_address_id = $request['delivery_address_id'];
                $cartFinal->time_slot_id = $request['time_slot_id'];
                $cartFinal->same_day_delievery = (string)$request['same_day_delievery'];
                $cartFinal->order_type = $request['order_type'];
                $cartFinal->save();

            }


            //$tax = $cart->total_price
            $response['status'] = 'success';
            $response['message'] = 'Cart List';
            $response['details'][] = $basicCart;
            $response['items'] = $cartOrder;
            return response()->json($response, 200);

        } else {

            $response['status'] = 'fail';
            $response['message'] = 'Cart Not Found';
            $response['items'] = [];
            return response()->json($response, 200);

        }
        
    }


    public function add_to_cart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'product_id' => 'required',
            'quantity' => 'required',
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        $userId = $request['user_id'];
        $productId = $request['product_id'];
        $quantity = $request['quantity'];
        
      	if(isset($request['variations-type']) && $request['variations-type'] != ""){
            $variationsType = $request['variations-type'];
        } else {
            $variationsType = "";
        }
        
        if($quantity <= 0){
          	//echo $quantity."---".$userId."---".$productId;
          	//echo DB::table('cart')->where('user_id', $userId)->where('product_id', $productId)->where('status', 'pending')->delete();
          	DB::table('cart')->where('user_id', $userId)->where('product_id', $productId)->where('status', 'pending')->delete();
          	$response['status'] = 'success';
            $response['message'] = 'Cart Updated';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        $cartOrder = DB::table('cart')->where('user_id', $userId)->where('product_id', $productId)->where('status', 'pending')->get();

        if(!empty($cartOrder) && isset($cartOrder[0])){
            foreach($cartOrder as $cart){

                $cProductId = $cart->product_id;
                $cartId = $cart->id;
                if($cProductId == $productId){

                    $productData = DB::table('products')->where('id', $productId)->get();

                    if(isset($productData) && !empty($productData[0])){

                        $productPrice = $productData[0]->price;
                        $productOrgPrice = $productData[0]->price;
                        $discount = $productData[0]->discount;
                        $discountType = $productData[0]->discount_type;
                        $totalStock = $productData[0]->total_stock;
                        $variations = $productData[0]->variations;
                        $productName = $productData[0]->name;
                        $productImage = "";
                        $productImages = $productData[0]->image;
                        $productCode = $productData[0]->sku;
                        $totalCartPrice = 0;

                        if($variationsType != ""){
                            if($variations != '[]'){
                                $variationArray = json_decode($variations);
                                foreach($variationArray as $variation){
                                    if($variation->type == $variationsType){
                                        $productPrice = $variation->price;
                                    }
                                }
                                $totalCartPrice = $productPrice * $quantity;
                            }
                        } else {
                            if($quantity > $totalStock){
                                $response['status'] = 'fail';
                                $response['message'] = 'This product quantity is out of stock.';
                                $response['data'] = [];
                                return response()->json($response, 200);
                            } else {
                                $totalCartPrice = $productPrice * $quantity;
                            }
                        }

                        if($discount != 0){
                            if($discountType == "amount"){
                                //$productPrice = ($productPrice-$discount);
                                $totalCartPrice = ($totalCartPrice-($discount * $quantity));
                            }
                            if($discountType == "percent"){
                                //$productPrice = (($productPrice*$discount)/100);
                                $totalCartPrice = ($totalCartPrice - (($totalCartPrice*$discount)/100));
                            }
                        }

                        if($productImages != "[]" && $productImages != ""){
                            $imageArray = json_decode($productImages);
                            $productImage = $imageArray[0];
                        }


                        Cart::where(['user_id' => $request['user_id'], 'product_id' => $productId])->update([
                            'product_price'  => $productPrice,
                            'product_image'  => $productImage,
                            'quantity'  => $quantity,
                            'variations'  => $variationsType,
                            'discount'  => $discount,
                            'discount_type'  => $discountType,
                            'total_price'  => $totalCartPrice,
                            'product_code'  => $productCode,
                        ]);

                    }

                }
            }

        } else {
            $productData = DB::table('products')->where('id', $productId)->get();

            if(isset($productData) && !empty($productData[0])){

                $productPrice = $productData[0]->price;
                $productOrgPrice = $productData[0]->price;
                $discount = $productData[0]->discount;
                $discountType = $productData[0]->discount_type;
                $totalStock = $productData[0]->total_stock;
                $variations = $productData[0]->variations;
                $productName = $productData[0]->name;
                $productImage = "";
                $productImages = $productData[0]->image;
                $productCode = $productData[0]->sku;
                $totalCartPrice = 0;

                if($variationsType != ""){
                    if($variations != '[]'){
                        $variationArray = json_decode($variations);
                        foreach($variationArray as $variation){
                            if($variation->type == $variationsType){
                                $productPrice = $variation->price;
                            }
                        }
                        $totalCartPrice = $productPrice * $quantity;
                    }
                } else {
                    if($quantity > $totalStock){
                        $response['status'] = 'fail';
                        $response['message'] = 'This product quantity is out of stock.';
                        $response['data'] = [];
                        return response()->json($response, 200);
                    } else {
                        $totalCartPrice = $productPrice * $quantity;
                    }
                }
				
              	//echo $totalCartPrice.'---';
                if($discount != 0){
                    if($discountType == "amount"){
                        //$productPrice = ($productPrice-$discount);
                        $totalCartPrice = ($totalCartPrice-($discount * $quantity));
                    }
                    if($discountType == "percent"){
                        //$productPrice = (($productPrice*$discount)/100);
                        $totalCartPrice = ($totalCartPrice - (($totalCartPrice*$discount)/100));
                    }
                }

                if($productImages != "[]" && $productImages != ""){
                    $imageArray = json_decode($productImages);
                    $productImage = $imageArray[0];
                }

                $cart = new Cart();
                $cart->user_id = $request['user_id'];
                $cart->product_id = $request['product_id'];
                $cart->product_name = $productName;
                $cart->product_price = $productPrice;
                $cart->product_image = $productImage;
                $cart->quantity = $quantity;
                $cart->variations = $variationsType;
                $cart->discount = $discount;
                $cart->discount_type = $discountType;
                $cart->total_price = $totalCartPrice;
                $cart->product_code = $productCode;
                $cart->save();
                

            } else {
                $response['status'] = 'fail';
                $response['message'] = 'This Product Not Found.';
                $response['data'] = [];
                return response()->json($response, 200);
            }
        }

        $cartOrder = DB::table('cart')->where('user_id', $userId)->where('status', 'pending')->get();

        $basicCart['total_items'] = count($cartOrder);
        $totalPrice = 0;
        foreach($cartOrder as $cart){
            //$totalPrice += ($cart->quantity * $cart->total_price);
            $totalPrice += $cart->total_price;
        }
        $basicCart['total_amount'] = $totalPrice;


        $response['status'] = 'success';
        $response['message'] = 'Cart Updated';
        $response['details'][] = $basicCart;
        $response['items'] = $cartOrder;
        return response()->json($response, 200);
        
    }


    public function list(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response['status'] = 'fail';
            $response['message'] = 'Please send all required fields.';
            $response['data'] = []; 
            return response()->json($response, 200);
        }

        $userId = $request['user_id'];

        $cartOrder = DB::table('cart')->where('user_id', $userId)->where('status', 'pending')->get();

        if(!empty($cartOrder) && isset($cartOrder[0])){

            $basicCart['total_items'] = count($cartOrder);
            $totalPrice = 0; $basicPrice = 0; $taxAmount = 0; $catName = "";
            foreach($cartOrder as $cart){
                $productId = $cart->product_id;

                $productData = DB::table('products')->where('id', $productId)->get();
                $deliveryManagement = DB::table('business_settings')->where('key', 'delivery_management')->get();
                $deliveryCharge = DB::table('business_settings')->where('key', 'delivery_charge')->get();
				
              	$totalPrice += $cart->total_price;
                //$totalPrice += ($cart->quantity * $cart->total_price);
                $basicPrice += ($cart->quantity * $cart->product_price);

                if(isset($productData) && !empty($productData[0])){
                    $tax = $productData[0]->tax;
                    $taxType = $productData[0]->tax_type;
                    $categoryArray = json_decode($productData[0]->category_ids);
                    foreach($categoryArray as $category){
                        $catPosition = $category->position;
                        $catId = $category->id;

                        if($catPosition == 1){
                            $catArray = DB::table('categories')->where('id', $catId)->get();
                            //echo '<pre />'; print_r($catArray[0]->name);
                            if(isset($catArray)){
                                $catName = $catArray[0]->name;
                            }
                        }

                    }
                    //echo '<pre />'; print_r($categoryArray);


                    if($taxType == "percent"){
                        $taxAmount += ($cart->quantity * (($tax * $cart->total_price)/100));
                    }
                    if($taxType == "amount"){
                        $taxAmount += ($cart->quantity * $tax);
                    }
                }

                //echo '<pre />'; print_r(json_decode($deliveryManagement->value));
                $deliveryArray = json_decode($deliveryManagement[0]->value);
                $deliveryStatus = $deliveryArray->status;

                if($deliveryStatus == 0){
                    $delCharge = $deliveryCharge[0]->value;
                } else {
                    $delCharge = $deliveryArray->min_shipping_charge;
                }

                $cart->category_name = $catName;

                //echo '<pre />'; print_r($deliveryArray);
                //echo '<pre />'; print_r($deliveryCharge);
            }
            $basicCart['total_amount'] = $totalPrice;
            $basicCart['basic_amount'] = $basicPrice;
            $basicCart['total_discount'] = ($basicPrice - $totalPrice);
            $basicCart['tax_amount'] = $taxAmount;
            $basicCart['delivery_charge'] = $delCharge;
            //$basicCart['sub_total'] =  round(($totalPrice + $taxAmount + $delCharge), 2);
          	$basicCart['sub_total'] =  round(($totalPrice), 2);

            //$tax = $cart->total_price



            $response['status'] = 'success';
            $response['message'] = 'Cart List';
            $response['details'][] = $basicCart;
            $response['items'] = $cartOrder;
            return response()->json($response, 200);

        } else {

            $response['status'] = 'fail';
            $response['message'] = 'Cart Not Found';
            $response['items'] = [];
            return response()->json($response, 200);

        }
        
    }
  
  
  	public function membership_package(){

        $membershipPackages = DB::table('membership_package')->where('status', 1)->get();
        $membershipFeatures = DB::table('membership_features')->where('status', 1)->orderBy('priorty', 'ASC')->get();

        if(!empty($membershipPackages) && isset($membershipPackages[0])){

            $response['status'] = 'success';
            $response['message'] = 'Membership Package List';
            $response['data'] = $membershipPackages;
            $response['features'] = $membershipFeatures;
            return response()->json($response, 200);

        } else {

            $response['status'] = 'fail';
            $response['message'] = 'Membership Package Not Found';
            $response['data'] = [];
            return response()->json($response, 200);

        }
        
    }
}
