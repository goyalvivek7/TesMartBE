<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrderHistory;
use App\Model\BusinessSetting;
use App\Model\Product;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;

class OrderController extends Controller
{
    public function list(Request $request, $status)
    {
        //dd($request['timeSlot']);
        $query_param = [];
        $search = $request['search'];
        $date = $request['date'];
        $time = $request['time'];

        if (session()->has('branch_filter') == false) {
            session()->put('branch_filter', 0);
        }
        Order::where(['checked' => 0])->update(['checked' => 1]);
        if (session('branch_filter') == 0) {
            if ($status != 'all') {
                $query = Order::with(['customer', 'branch', 'final_cart'])->where(['order_status' => $status]);
            } else {
                $query = Order::with(['customer', 'branch', 'final_cart']);
            }
        } else {
            if ($status != 'all') {
                $query = Order::with(['customer', 'branch', 'final_cart'])->where(['order_status' => $status, 'branch_id' => session('branch_filter')]);
            } else {
                $query = Order::with(['customer', 'branch', 'final_cart'])->where(['branch_id' => session('branch_filter')]);
            }
        }
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }

        if ($request->has('date') && $date != null) {
            $query = $query->where('delivery_date', $date);
            $query_param = ['date' => $request['date']];
        }
        if ($request->has('time') && $time != 0) {
            $query = $query->where('time_slot_id', $time);
            $query_param = ['time' => $request['time']];
        }

        $orders = $query->where('order_type', '!=', 'pos')->where('order_status', '!=', 'created')->latest()->paginate(Helpers::getPagination())->appends($query_param);
        //echo '<pre />'; print_r($orders); die;
        
        return view('admin-views.order.list', compact('orders', 'status', 'search', 'date', 'time'));
    }

    public function details($id)
    {
        $order = Order::with('details')->where(['id' => $id])->first();
        $cartId = $order['cart_id'];
        $cartData = DB::table('cart_final')->where(['id' => $cartId])->first();
        $deliveryOptions = DB::table('delivery_options')->get();
        $orderHistories = DB::table('order_histories')->where('order_id', $id)->get();
        $orderReview = DB::table('reviews')->where('order_id', $id)->first();
        
        $busineessData = BusinessSetting::get();
        $bisData = [];
        foreach($busineessData as $bData){
            $key = $bData['key'];
            $value = $bData['value'];
            $bisData[$key] = $value;
        }
        if (isset($order)) {
            if($order['invoice_no'] == NULL || $order['invoice_no'] == ""){
                $orderNo = $order['id'];
                $pdf = PDF::loadView('admin-views.order.partials._invoice', compact('order', 'cartData', 'deliveryOptions', 'bisData'));
                //return $pdf->download('order_'.$orderNo . '.pdf');
                $pdfName = 'order_'.$orderNo . '.pdf';
                $pdf->save($pdfName);
                $orderPdf = Helpers::upload('order/', 'pdf', $pdfName);
                Order::where(['id' => $id])->update([
                    'invoice_url' => $orderPdf
                ]);
                $order['invoice_url'] = $orderPdf;
                unlink($pdfName);
            }
            return view('admin-views.order.order-view', compact('order', 'deliveryOptions', 'cartData', 'orderHistories', 'orderReview'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }

    public function search(Request $request)
    {

        $key = explode(' ', $request['search']);
        $orders = Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('order_status', 'like', "%{$value}%")
                    ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->latest()->paginate(2);

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
        ]);
    }

    public function date_search(Request $request)
    {
        $dateData = ($request['dateData']);

        $orders = Order::where(['delivery_date' => $dateData])->latest()->paginate(10);
        // $timeSlots = $orders->pluck('time_slot_id')->unique()->toArray();
        // if ($timeSlots) {

        //     $timeSlots = TimeSlot::whereIn('id', $timeSlots)->get();
        // } else {
        //     $timeSlots = TimeSlot::orderBy('id')->get();

        // }
        // dd($orders);

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
            // 'timeSlot' => $timeSlots
        ]);

    }

    public function time_search(Request $request)
    {

        $orders = Order::where(['time_slot_id' => $request['timeData']])->where(['delivery_date' => $request['dateData']])->get();
        // dd($orders)->toArray();

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
        ]);

    }

    public function status(Request $request)
    {
        $order = Order::find($request->id);
        if ($request->order_status == 'out_for_delivery' && $order['delivery_man_id'] == null && $order['order_type'] != 'self_pickup') {
            Toastr::warning('Please assign delivery man first!');
            return back();
        }
        if ($request->order_status == 'returned' || $request->order_status == 'failed' || $request->order_status == 'canceled') {
            foreach ($order->details as $detail) {
                if ($detail['is_stock_decreased'] == 1) {
                    $product = Product::find($detail['product_id']);
                    $type = json_decode($detail['variation'])[0]->type;
                    $var_store = [];
                    foreach (json_decode($product['variations'], true) as $var) {
                        if ($type == $var['type']) {
                            $var['stock'] += $detail['quantity'];
                        }
                        array_push($var_store, $var);
                    }
                    Product::where(['id' => $product['id']])->update([
                        'variations' => json_encode($var_store),
                        'total_stock' => $product['total_stock'] + $detail['quantity'],
                    ]);
                    OrderDetail::where(['id' => $detail['id']])->update([
                        'is_stock_decreased' => 0,
                    ]);
                }
            }
        } else {
            foreach ($order->details as $detail) {
                if ($detail['is_stock_decreased'] == 0) {
                    $product = Product::find($detail['product_id']);

                    //check stock
                    foreach ($order->details as $c) {
                        $product = Product::find($c['product_id']);
                        $type = json_decode($c['variation'])[0]->type;
                        foreach (json_decode($product['variations'], true) as $var) {
                            if ($type == $var['type'] && $var['stock'] < $c['quantity']) {
                                Toastr::error('Stock is insufficient!');
                                return back();
                            }
                        }
                    }

                    $type = json_decode($detail['variation'])[0]->type;
                    $var_store = [];
                    foreach (json_decode($product['variations'], true) as $var) {
                        if ($type == $var['type']) {
                            $var['stock'] -= $detail['quantity'];
                        }
                        array_push($var_store, $var);
                    }
                    Product::where(['id' => $product['id']])->update([
                        'variations' => json_encode($var_store),
                        'total_stock' => $product['total_stock'] - $detail['quantity'],
                    ]);
                    OrderDetail::where(['id' => $detail['id']])->update([
                        'is_stock_decreased' => 1,
                    ]);
                }
            }
        }

        $order->order_status = $request->order_status;
        $order->save();

        $orderHistoryData = OrderHistory::create([
            'order_id' => $request->id,
            'user_id' => 1,
            'user_type' => 'admin',
            'status_captured' => $request->order_status,
            'status_reason' => ""
        ]);

        $fcm_token = $order->customer->cm_firebase_token;
        $value = Helpers::order_status_update_message($request->order_status);
        try {
            if ($value) {
                $data = [
                    'title' => 'Order',
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
            }
        } catch (\Exception $e) {
            Toastr::warning(\App\CentralLogics\translate('Push notification failed for Customer!'));
        }

        //delivery man notification
        if (($request->order_status == 'processing' || $request->order_status == 'out_for_delivery' || $request->order_status == 'delivered' || $request->order_status == 'delivery_boy_delivered' ) && $order->delivery_man != null) {
            $fcm_token = $order->delivery_man->fcm_token;
            //$value = \App\CentralLogics\translate('One of your order is in processing');
            $value = Helpers::order_status_update_message($request->order_status);
            try {
                if ($value) {
                    $data = [
                        'title' => 'Order',
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                    ];
                    Helpers::send_push_notif_to_device($fcm_token, $data);
                }
            } catch (\Exception $e) {
                Toastr::warning(\App\CentralLogics\translate('Push notification failed for DeliveryMan!'));
            }
        }

        Toastr::success('Order status updated!');
        return back();
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {


        if ($delivery_man_id == 0) {
            return response()->json([], 401);
        }

        $order = Order::find($order_id);

        if ($order->order_status == 'delivered' || $order->order_status == 'returned' || $order->order_status == 'failed' || $order->order_status == 'canceled') {
            return response()->json(['status' => false], 200);
        }

        $order->delivery_man_id = $delivery_man_id;
        $order->save();

        $fcm_token = $order->delivery_man->fcm_token;
        $value = Helpers::order_status_update_message('del_assign');
        try {
            if ($value) {
                $data = [
                    'title' => 'Order',
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
            }
        } catch (\Exception $e) {
            Toastr::warning(\App\CentralLogics\translate('Push notification failed for DeliveryMan!'));
        }

        //Toastr::success('Order deliveryman added!');
        return response()->json(['status' => true], 200);
    }

    public function payment_status(Request $request)
    {
        $order = Order::find($request->id);
        if ($request->payment_status == 'paid' && $order['transaction_reference'] == null && $order['payment_method'] != 'cash_on_delivery') {
            Toastr::warning('Add your payment reference code first!');
            return back();
        }
        $order->payment_status = $request->payment_status;
        $order->save();
        Toastr::success('Payment status updated!');
        return back();
    }

    public function update_shipping(Request $request, $id)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success('Payment status updated!');
        return back();
    }

    public function update_time_slot(Request $request)
    {
        if ($request->ajax()) {
            $order = Order::find($request->id);
            $order->time_slot_id = $request->timeSlot;
            $order->save();
            $data = $request->timeSlot;

            return response()->json($data);
        }
    }

    public function update_deliveryDate(Request $request)
    {
        if ($request->ajax()) {
            $order = Order::find($request->id);
            $order->delivery_Date = $request->deliveryDate;
            $order->save();
            $data = $request->deliveryDate;

            return response()->json($data);
        }
    }

    public function generate_invoice($id)
    {
        $order = Order::where('id', $id)->first();
        $cartId = $order['cart_id'];
        $cartData = DB::table('cart_final')->where(['id' => $cartId])->first();
        return view('admin-views.order.invoice', compact('order','cartData'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id])->update([
            'transaction_reference' => $request['transaction_reference'],
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
    }

    public function branch_filter($id)
    {
        session()->put('branch_filter', $id);
        return back();
    }
}
