<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Order;
use App\Models\OrderCancelReason;
use App\Models\Store;
use App\Models\Coupon;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\OrderDetail;
use App\Scopes\StoreScope;
use App\Exports\OrderExport;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\CouponLogic;
use App\CentralLogics\ProductLogic;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderPayment;
use App\Traits\PlaceNewOrder;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;


class OrderController extends Controller
{
    use PlaceNewOrder;
    public function list($status)
    {
        $key = explode(' ', request()?->search);
        Order::where(['checked' => 0])->where('store_id',Helpers::get_store_id())->update(['checked' => 1]);

        $orders = Order::with(['customer'])
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'confirmed', function($query){
            return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
        })
        ->when($status == 'pending', function($query){
            if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
            {
                return $query->where('order_status','pending');
            }
            else
            {
                return $query->where('order_status','pending')->where('order_type', 'take_away');
            }
        })
        ->when($status == 'cooking', function($query){
            return $query->where('order_status','processing');
        })
        ->when($status == 'item_on_the_way', function($query){
            return $query->where('order_status','picked_up');
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'ready_for_delivery', function($query){
            return $query->where('order_status','handover');
        })
        ->when($status == 'refund_requested', function($query){
            return $query->RefundRequest();
        })
        ->when($status == 'refunded', function($query){
            return $query->Refunded();
        })
        ->when($status == 'scheduled', function($query){
            return $query->Scheduled()->where(function($q){
                if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
                {
                    $q->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded']);
                }
                else
                {
                    $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                        $query->where('order_status','pending')->where('order_type', 'take_away');
                    });
                }

            });
        })
        ->when($status == 'all', function($query){
            return $query->where(function($query){
                $query->whereNotIn('order_status',(config('order_confirmation_model') == 'store'|| Helpers::get_store_data()->sub_self_delivery)?['failed','canceled', 'refund_requested', 'refunded']:[ 'accepted' ,'pending','failed','canceled', 'refund_requested', 'refunded'])
                ->orWhere(function($query){
                    return $query->where('order_status','pending')->where('order_type', 'take_away');
                });
            });
        })
        ->when(in_array($status, ['pending','confirmed']), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
        })
        ->StoreOrder()->NotDigitalOrder()
        ->where('store_id',\App\CentralLogics\Helpers::get_store_id())
        ->orderBy('schedule_at', 'desc')
        ->paginate(config('default_pagination'));
        $status = $status;
        return view('vendor-views.order.list', compact('orders', 'status'));
    }


    public function export_orders($file_type, $status, $type, Request $request)
    {
        $key = explode(' ', request()?->search);
        Order::where(['checked' => 0])->where('store_id',Helpers::get_store_id())->update(['checked' => 1]);
        $orders = Order::with(['customer'])
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'confirmed', function($query){
            return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
        })
        ->when($status == 'pending', function($query){
            if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
            {
                return $query->where('order_status','pending');
            }
            else
            {
                return $query->where('order_status','pending')->where('order_type', 'take_away');
            }
        })
        ->when($status == 'cooking', function($query){
            return $query->where('order_status','processing');
        })
        ->when($status == 'item_on_the_way', function($query){
            return $query->where('order_status','picked_up');
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'ready_for_delivery', function($query){
            return $query->where('order_status','handover');
        })
        ->when($status == 'refund_requested', function($query){
            return $query->RefundRequest();
        })
        ->when($status == 'refunded', function($query){
            return $query->Refunded();
        })
        ->when($status == 'scheduled', function($query){
            return $query->Scheduled()->where(function($q){
                if(config('order_confirmation_model') == 'store' || Helpers::get_store_data()->sub_self_delivery)
                {
                    $q->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded']);
                }
                else
                {
                    $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                        $query->where('order_status','pending')->where('order_type', 'take_away');
                    });
                }

            });
        })
        ->when($status == 'all', function($query){
            return $query->where(function($query){
                $query->whereNotIn('order_status',(config('order_confirmation_model') == 'store'|| Helpers::get_store_data()->sub_self_delivery)?['failed','canceled', 'refund_requested', 'refunded']:['pending','failed','canceled', 'refund_requested', 'refunded'])
                ->orWhere(function($query){
                    return $query->where('order_status','pending')->where('order_type', 'take_away');
                });
            });
        })
        ->when(in_array($status, ['pending','confirmed']), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
        })
        ->StoreOrder()->NotDigitalOrder()
        ->where('store_id',\App\CentralLogics\Helpers::get_store_id())
        ->orderBy('schedule_at', 'desc')
        ->get();

        $data = [
            'orders'=>$orders,
            'type'=>$type,
            'status'=>$status,
            'order_status'=>isset($request->orderStatus)?implode(', ', $request->orderStatus):null,
            'search'=>$request->search??null,
            'from'=>$request->from_date??null,
            'to'=>$request->to_date??null,
            'zones'=>isset($request->zone)?Helpers::get_zones_name($request->zone):null,
            'stores'=>isset($request->vendor)?Helpers::get_stores_name(Helpers::get_store_id()):null,
        ];

    if ($file_type == 'excel') {
        return Excel::download(new OrderExport($data), 'Orders.xlsx');
    } else if ($file_type == 'csv') {
        return Excel::download(new OrderExport($data), 'Orders.csv');
    }

    }



    public function details(Request $request,$id)
    {
        $order = Order::with(['details','offline_payments','customer'=>function($query){
            return $query->withCount('orders');
        },'delivery_man'=>function($query){
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id, 'store_id' => Helpers::get_store_id()])->first();
        if (isset($order)) {
            $reasons=OrderCancelReason::where('status', 1)->where('user_type' ,'store' )->get();
            $editing = false;
            if ($request->session()->has('order_cart')) {
                $cart = session()->get('order_cart');
                if (count($cart) > 0 && $cart[0]->order_id == $order->id) {
                    $editing = true;
                } else {
                    session()->forget('order_cart');
                }
            }
            $category = $request->query('category_id', 0);
            $categories = \App\Models\Category::active()->get();
            $keyword = $request->query('keyword', false);
            $key = explode(' ', $keyword);
            $products = Item::where('store_id', Helpers::get_store_id())
                ->when($category, function ($query) use ($category) {
                    $query->whereHas('category', function ($q) use ($category) {
                        return $q->whereId($category)->orWhere('parent_id', $category);
                    });
                })
                ->when($keyword, function ($query) use ($key) {
                    return $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->active()->paginate(10);
            return view('vendor-views.order.order-view', compact('order' ,'reasons', 'editing', 'categories', 'products', 'category', 'keyword'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }

    /**
     * Ports the admin panel's order-cart-editing engine (edit/update/
     * checkValidity/add_to_cart/remove_from_cart/quick_view/
     * quick_view_cart_item) to the vendor panel, store-scoped throughout.
     * The routes for all of these already existed - they pointed at
     * methods that were never written. makeEditOrderDetails() and
     * setOrderEditCalculatedTax() come from the PlaceNewOrder trait this
     * controller already uses, same as the admin controller.
     */
    public function edit(Request $request, $id)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id, 'store_id' => Helpers::get_store_id()])->first();

        if (!$order) {
            Toastr::info('No more orders!');
            return back();
        }

        if ($request->cancle) {
            if ($request->session()->has(['order_cart'])) {
                session()->forget(['order_cart']);
            }
            return back();
        }
        $cart = collect([]);
        foreach ($order->details as $details) {
            unset($details['item_details']);
            $details['status'] = true;
            $cart->push($details);
        }
        if ($cart->isEmpty()) {
            Toastr::error(translate('messages.cart_is_empty'));
            return back();
        }

        if ($request->session()->has('order_cart')) {
            session()->forget('order_cart');
        } else {
            $request->session()->put('order_cart', $cart);
            $this->setOrderEditCalculatedTax(store: $order->store, order_id: $order->id);
        }
        return back();
    }

    public function update(Request $request, $id)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'customer' => function ($query) {
            return $query->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where(['id' => $id, 'store_id' => Helpers::get_store_id()])->first();

        if (!$order) {
            Toastr::info('No more orders!');
            return back();
        }

        if (!$request->session()->has('order_cart')) {
            Toastr::error(translate('messages.order_data_not_found'));
            return back();
        }
        $cart = $request->session()->get('order_cart', collect([]));
        $store = $order->store;
        $validity = $this->checkValidity($cart, $store, $request);
        if ($validity['status_code'] != 200) {
            Toastr::error($validity['message']);
            return back();
        }
        $coupon = null;
        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;
        $order_details_ids = [];
        if ($order->coupon_code) {
            $coupon = Coupon::where(['code' => $order->coupon_code])->first();
        }

        foreach ($cart as $c) {
            try {
                if ($c['status'] == true) {
                    unset($c['status']);
                    $product = $c['item_campaign_id'] != null ? ItemCampaign::find($c['item_campaign_id']) : Item::find($c['item_id']);
                    if ($product) {
                        $price = $c['price'];
                        $product = Helpers::product_data_formatting($product);
                        $c->item_details = json_encode($product);
                        $c->updated_at = now();
                        if (isset($c->id)) {
                            OrderDetail::where('id', $c->id)->update([
                                'item_id' => $c->item_id,
                                'item_campaign_id' => $c->item_campaign_id,
                                'item_details' => $c->item_details,
                                'quantity' => $c->quantity,
                                'price' => $c->price,
                                'tax_amount' => $c->tax_amount,
                                'discount_on_item' => $c->discount_on_item * $c->quantity,
                                'discount_on_product_by' => $request->session()->has('discount_on_product_by_session') ? $request->session()->get('discount_on_product_by_session') : $c?->discount_on_product_by,
                                'discount_type' => $c->discount_type,
                                'variant' => $c->variant,
                                'variation' => $c->variation,
                                'add_ons' => $c->add_ons,
                                'total_add_on_price' => $c->total_add_on_price,
                                'updated_at' => $c->updated_at
                            ]);
                        } else {
                            $c->save();
                        }
                        $order_details_ids[] = $c->id;
                        $total_addon_price += $c['total_add_on_price'];
                        $product_price += $price * $c['quantity'];
                        $store_discount_amount += $c['discount_on_item'] * $c['quantity'];
                    } else {
                        Toastr::error(translate('messages.item_not_found'));
                        return back();
                    }
                } else {
                    $c->delete();
                }
            } catch (\Throwable $th) {
                info($th->getMessage());
            }
        }

        $store_discount = Helpers::get_store_discount($store);
        if (isset($store_discount)) {
            if ($product_price + $total_addon_price < $store_discount['min_purchase']) {
                $store_discount_amount = 0;
            }
            if ($store_discount_amount > $store_discount['max_discount'] && $store_discount_amount > $store_discount['max_discount']) {
                $store_discount_amount = $store_discount['max_discount'];
            }
        }
        $order->delivery_charge = $order->original_delivery_charge;
        if ($coupon) {
            if ($coupon->coupon_type == 'free_delivery') {
                $order->delivery_charge = 0;
                $coupon = null;
            }
        }
        if ($order->store->free_delivery || $order->order_type == 'take_away') {
            $order->delivery_charge = 0;
        }

        $additionalCharges = [];
        $settings = BusinessSetting::whereIn('key', [
            'additional_charge_status',
            'additional_charge',
            'extra_packaging_data',
        ])->pluck('value', 'key');
        $additional_charge_status = $settings['additional_charge_status'] ?? null;
        $additional_charge = $settings['additional_charge'] ?? null;
        $order->additional_charge = 0;
        if ($additional_charge_status == 1) {
            $order->additional_charge = $additional_charge ?? 0;
        }

        $order_details = $this->makeEditOrderDetails($cart, null, $store);

        foreach ($order_details['order_details'] as $key => $order_de) {
            $order->details()->where('id', $order_de['cart_id'])->update([
                'discount_on_item' => $order_de['discount_on_item'],
                'discount_on_product_by' => $order_de['discount_on_product_by'],
                'discount_type' => $order_de['discount_type'],
            ]);
        }

        if (data_get($order_details, 'status_code') === 403) {
            DB::rollBack();
            return response()->json([
                'errors' => [
                    ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                ]
            ], data_get($order_details, 'status_code'));
        }
        $total_addon_price = $order_details['total_addon_price'];
        $product_price = $order_details['product_price'];
        $store_discount_amount = $order_details['store_discount_amount'];
        $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
        $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];
        $product_data = $order_details['product_data'];
        $order_details = $order_details['order_details'];
        $coupon_discount_amount = $coupon ? CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
        $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount - $coupon_discount_amount;
        $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $coupon_discount_amount + $order->ref_bonus_amount;

        $finalCalculatedTax = Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $store->id);
        $tax_amount = $finalCalculatedTax['tax_amount'];
        $tax_included = $finalCalculatedTax['tax_included'];
        $tax_status = $finalCalculatedTax['tax_status'];
        $taxMap = $finalCalculatedTax['taxMap'];
        $orderTaxIds = data_get($finalCalculatedTax, 'taxData.orderTaxIds', []);
        $taxType = data_get($finalCalculatedTax, 'taxType');
        $order->tax_type = $taxType;
        $order->tax_status = $tax_status;
        $total_tax_amount = $tax_amount;
        $total_tax_amount = $order->tax_status == 'included' ? 0 : $total_tax_amount;

        if ($store->minimum_order > $product_price + $total_addon_price) {
            Toastr::error(translate('messages.you_need_to_order_at_least', ['amount' => $store->minimum_order . ' ' . Helpers::currency_code()]));
            return back();
        }

        $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
        if (isset($free_delivery_over)) {
            if ($free_delivery_over <= $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount) {
                $order->delivery_charge = 0;
            }
        }

        $total_order_ammount = $total_price + $total_tax_amount + $order->delivery_charge + $order->additional_charge;
        $adjustment = $order->order_amount - $total_order_ammount;

        $order->coupon_discount_amount = $coupon_discount_amount;
        $order->store_discount_amount = $store_discount_amount;
        $order->total_tax_amount = $total_tax_amount;
        $order->order_amount = $total_order_ammount;
        $order->adjusment = $adjustment;
        $order->edited = true;
        $order->save();

        if ($order->order_type !== 'parcel') {
            $taxMapCollection = collect($taxMap);
            foreach ($order_details as $key => $item) {
                $order_details[$key]['order_id'] = $order->id;
                $item_id = $item['item_id'] ? $item['item_id'] : $item['item_campaign_id'];
                $index = $taxMapCollection->search(function ($tax) use ($item_id) {
                    return $tax['product_id'] == $item_id;
                });
                if ($index !== false) {
                    $matchedTax = $taxMapCollection->pull($index);
                    $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                    $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                }
            }

            $order?->orderTaxes()?->delete();
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $order->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
            if (count($product_data) > 0) {
                foreach ($product_data as $item) {
                    ProductLogic::update_stock($item['item'], $item['quantity'], $item['variant'])->save();
                    ProductLogic::update_flash_stock($item['item'], $item['quantity'])?->save();
                }
            }
        }

        session()->forget('order_cart');
        session()->forget('edit_tax_amount');
        session()->forget('edit_tax_included');
        Toastr::success(translate('messages.order_updated_successfully'));
        return back();
    }

    public function checkValidity($carts, $store, $request)
    {
        if ($carts->isEmpty()) {
            return [
                'status_code' => 403,
                'code' => 'not_found',
                'message' => translate('Cart is empty'),
            ];
        }
        foreach ($carts as $c) {
            if (!isset($c['status']) || $c['status'] !== false) {
                if (isset($c['item_type']) && ($c['item_type'] === 'App\Models\ItemCampaign' || $c['item_type'] === 'AppModelsItemCampaign')) {
                    $product = ItemCampaign::with('module')->active()->find($c['item_id']);
                } else {
                    $product = Item::with('module')->active()->find($c['item_id'] ?? $c['id']);
                }

                if ($product) {
                    if ($product->store_id != $store->id) {
                        return [
                            'status_code' => 403,
                            'code' => 'different_stores',
                            'message' => translate('messages.Please_select_items_from_the_same_store'),
                        ];
                    }
                    if ($product?->maximum_cart_quantity && $c['quantity'] > $product?->maximum_cart_quantity) {
                        return [
                            'status_code' => 403,
                            'code' => 'quantity',
                            'message' => translate('messages.maximum_cart_quantity_limit_over'),
                        ];
                    }
                    if ($product?->module?->module_type != 'food') {
                        if (
                            is_array(json_decode($product['variations'], true)) && count(json_decode($product['variations'], true)) > 0 &&
                            is_array($c['variation']) && count($c['variation']) > 0
                        ) {
                            $variant_data = Helpers::variation_price($product, json_encode($c['variation']));
                            $stock = $variant_data['stock'];
                        } else {
                            $stock = $product?->stock;
                        }
                        if (config('module.' . $product->module->module_type)['stock']) {
                            if ($c['quantity'] > $stock) {
                                return [
                                    'status_code' => 403,
                                    'code' => 'stock',
                                    'message' => $product?->name . ' ' . translate('messages.is_out_of_stock')
                                ];
                            }
                        }
                    }
                } else {
                    return [
                        'status_code' => 403,
                        'code' => 'not_found',
                        'message' => translate('messages.product_not_found'),
                    ];
                }
            }
        }
        return [
            'status_code' => 200,
            'code' => 'success',
            'message' => translate('messages.order_updated_successfully'),
        ];
    }

    /**
     * Security boundary admin doesn't need: a vendor can only add items
     * from their own store into the order-edit cart.
     */
    public function add_to_cart(Request $request)
    {
        $product = $request->item_type == 'item' ? Item::find($request->id) : ItemCampaign::find($request->id);

        if (!$product || $product->store_id != Helpers::get_store_id()) {
            return response()->json([
                'errors' => [['code' => 'store_id', 'message' => translate('messages.Please_select_items_from_the_same_store')]]
            ], 403);
        }

        if (isset($product->module_id) && $product->module->module_type == 'food' && $product->food_variations) {
            $data = new OrderDetail();
            if ($request->order_details_id) {
                $data['id'] = $request->order_details_id;
            }
            $data['item_id'] = $request->item_type == 'item' ? $product->id : null;
            $data['item_campaign_id'] = $request->item_type == 'campaign' ? $product->id : null;
            $data['item'] = $request->item_type == 'item' ? $product : null;
            $data['item_campaign'] = $request->item_type == 'campaign' ? $product : null;
            $data['order_id'] = $request->order_id;
            $variations = [];
            $price = 0;
            $variation_price = 0;

            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && $product_variations && count($product_variations)) {
                foreach ($request->variations as $key => $value) {
                    if ($value['required'] == 'on' && isset($value['values']) == false) {
                        return response()->json(['data' => 'variation_error', 'message' => translate('Please select items from') . ' ' . $value['name']]);
                    }
                    if (isset($value['values']) && $value['min'] != 0 && $value['min'] > count($value['values']['label'])) {
                        return response()->json(['data' => 'variation_error', 'message' => translate('Please select minimum ') . $value['min'] . translate('For') . $value['name'] . '.']);
                    }
                    if (isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])) {
                        return response()->json(['data' => 'variation_error', 'message' => translate('Please select maximum ') . $value['max'] . translate('For') . $value['name'] . '.']);
                    }
                }
                $variation_data = Helpers::get_varient($product_variations, $request->variations);
                $variation_price = $variation_data['price'];
                $variations = $variation_data['variations'];
            }
            $price = $product->price + $variation_price;
            $data['variation'] = json_encode($variations);
            $data['variant'] = '';
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['status'] = true;
            $data['discount_on_item'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data["discount_type"] = "discount_on_product";
            $data["tax_amount"] = Helpers::tax_calculate($product, $price);
            $add_ons = [];
            $add_on_qtys = [];
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $add_on_qtys[] = $request['addon-quantity' . $id];
                }
                $add_ons = $request['addon_id'];
            }
            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withOutGlobalScope(StoreScope::class)->whereIn('id', $add_ons)->get(), $add_on_qtys);
            $data['add_ons'] = json_encode($addon_data['addons']);
            $data['total_add_on_price'] = $addon_data['total_add_on_price'];
            $cart = $request->session()->get('order_cart', collect([]));

            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                return response()->json(['data' => 2]);
            } else {
                $cart->push($data);
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
            }
        } else {
            $data = new OrderDetail();
            if ($request->order_details_id) {
                $data['id'] = $request->order_details_id;
            }
            $data['item_id'] = $request->item_type == 'item' ? $product->id : null;
            $data['item_campaign_id'] = $request->item_type == 'campaign' ? $product->id : null;
            $data['order_id'] = $request->order_id;
            $str = '';
            $price = 0;

            foreach (json_decode($product->choice_options) as $key => $choice) {
                $str .= $str != null ? '-' . str_replace(' ', '', $request[$choice->name]) : str_replace(' ', '', $request[$choice->name]);
            }
            $data['variant'] = json_encode([]);
            $data['variation'] = json_encode([]);
            if ($request->session()->has('order_cart') && !isset($request->cart_item_key)) {
                if (count($request->session()->get('order_cart')) > 0) {
                    foreach ($request->session()->get('order_cart') as $key => $cartItem) {
                        if ($cartItem && $cartItem['item_id'] == $request['id'] && $cartItem['status'] == true) {
                            if (count(json_decode($cartItem['variation'], true)) > 0) {
                                if (json_decode($cartItem['variation'], true)[0]['type'] == $str) {
                                    return response()->json(['data' => 1]);
                                }
                            } else {
                                return response()->json(['data' => 1]);
                            }
                        }
                    }
                }
            }
            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $vr = json_decode($product->variations);
                        $price = $vr[$i]->price;
                        $stock = isset($vr[$i]->stock) ? $vr[$i]->stock : 0;
                    }
                }
                $data['variation'] = json_encode([["type" => $str, "price" => $price, "stock" => $stock]]);
            } else {
                $price = $product->price;
            }

            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['status'] = true;
            $data['discount_on_item'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data["discount_type"] = "discount_on_product";
            $data["tax_amount"] = Helpers::tax_calculate($product, $price);
            $add_ons = [];
            $add_on_qtys = [];
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $add_on_qtys[] = $request['addon-quantity' . $id];
                }
                $add_ons = $request['addon_id'];
            }
            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withoutGlobalScope(StoreScope::class)->whereIn('id', $add_ons)->get(), $add_on_qtys);
            $data['add_ons'] = json_encode($addon_data['addons']);
            $data['total_add_on_price'] = $addon_data['total_add_on_price'];

            $cart = $request->session()->get('order_cart', collect([]));
            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                return response()->json(['data' => 2]);
            } else {
                $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
                $cart->push($data);
            }
        }

        $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
        return response()->json(['data' => 0]);
    }

    public function remove_from_cart(Request $request)
    {
        $cart = $request->session()->get('order_cart', collect([]));
        $item_id = $cart[$request->key]['item_id'];
        $cart[$request->key]->status = false;
        $request->session()->put('order_cart', $cart);

        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($item_id);
        if ($product && $product->store) {
            $this->setOrderEditCalculatedTax(store: $product->store, order_id: $request->order_id);
        }
        return response()->json([], 200);
    }

    public function quick_view(Request $request)
    {
        $product = Item::where('store_id', Helpers::get_store_id())->findOrFail($request->product_id);
        $item_type = 'item';
        $order_id = $request->order_id;

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.order.partials._quick-view', compact('product', 'order_id', 'item_type'))->render(),
        ]);
    }

    public function quick_view_cart_item(Request $request)
    {
        $cart_item = session('order_cart')[$request->key];
        $order_id = $request->order_id;
        $item_key = $request->key;
        $product = $cart_item->item ? $cart_item->item : $cart_item->campaign;
        $item_type = $cart_item->item ? 'item' : 'campaign';

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.order.partials._quick-view-cart-item', compact('order_id', 'product', 'cart_item', 'item_key', 'item_type'))->render(),
        ]);
    }

    public function status(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'order_status' => 'required|in:confirmed,processing,handover,delivered,canceled',
            'reason' =>'required_if:order_status,canceled',
        ],[
            'id.required' => 'Order id is required!'
        ]);

        $order = Order::where(['id' => $request->id, 'store_id' => Helpers::get_store_id()])->first();

        if($order->delivered != null)
        {
            Toastr::warning(translate('messages.cannot_change_status_after_delivered'));
            return back();
        }

        if($request['order_status']=='canceled' && !config('canceled_by_store'))
        {
            Toastr::warning(translate('messages.you_can_not_cancel_a_order'));
            return back();
        }

        if($request['order_status']=='canceled' && $order->confirmed)
        {
            Toastr::warning(translate('messages.you_can_not_cancel_after_confirm'));
            return back();
        }



        if($request['order_status']=='delivered' && $order->order_type != 'take_away' && !Helpers::get_store_data()->sub_self_delivery)
        {
            Toastr::warning(translate('messages.you_can_not_delivered_delivery_order'));
            return back();
        }

        if($request['order_status'] =="confirmed")
        {
            if(!Helpers::get_store_data()->sub_self_delivery && config('order_confirmation_model') == 'deliveryman' && $order->order_type != 'take_away')
            {
                Toastr::warning(translate('messages.order_confirmation_warning'));
                return back();
            }
        }

        if ($request->order_status == 'delivered') {
            $order_delivery_verification = (boolean)\App\Models\BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value;
            if($order_delivery_verification)
            {
                if($request->otp)
                {
                    if($request->otp != $order->otp)
                    {
                        Toastr::warning(translate('messages.order_varification_code_not_matched'));
                        return back();
                    }
                }
                else
                {
                    Toastr::warning(translate('messages.order_varification_code_is_required'));
                    return back();
                }
            }

            if($order->transaction  == null)
            {
                $unpaid_payment = OrderPayment::where('payment_status','unpaid')->where('order_id',$order->id)->first()?->payment_method;
                $unpaid_pay_method = 'digital_payment';
                if($unpaid_payment){
                    $unpaid_pay_method = $unpaid_payment;
                }
                if($order->payment_method == 'cash_on_delivery' || $unpaid_pay_method == 'cash_on_delivery')
                {
                    $ol = OrderLogic::create_transaction($order,'store', null);
                }
                else{
                    $ol = OrderLogic::create_transaction($order,'admin', null);
                }
                if(!$ol)
                {
                    Toastr::warning(translate('messages.faield_to_create_order_transaction'));
                    return back();
                }
                if($order->delivery_man_id){
                    Helpers::deliverymanLoyaltyPointHistory(deliveryManId:$order->delivery_man_id, amount: $order->order_amount, transactionType:'earn_on_order_completion' ,pointConversionType :'credit', reference: $order->id);
                }

            }

            $order->payment_status = 'paid';

            OrderLogic::update_unpaid_order_payment(order_id:$order->id, payment_method:$order->payment_method);

            $order->details->each(function($item, $key){
                if($item->item)
                {
                    $item->item->increment('order_count');
                }
            });
            if($order->is_guest == 0) {
            $order?->customer?->increment('order_count');
            }
        }
        if($request->order_status == 'canceled' || $request->order_status == 'delivered')
        {
            if($order->delivery_man)
            {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders>1?$dm->current_orders-1:0;
                $dm->save();
            }
            if($request->order_status == 'canceled'){

                $order->cancellation_reason = $request->reason;
                $order->canceled_by = 'store';

                $order?->store ?   Helpers::increment_order_count($order?->store) : '';

                if($order->is_guest == 0){

                    OrderLogic::refund_before_delivered($order);
                }

            $hasStock = config('module.' . $order->module->module_type)['stock'];
            $hasFlashDiscount = $order->flash_admin_discount_amount > 0 && $order->flash_store_discount_amount > 0;

            if ($hasStock || $hasFlashDiscount) {
                foreach ($order->details as $detail) {

                    $item = $detail->campaign ?? $detail->item;

                    if ($hasStock) {
                        $variant = json_decode($detail->variation, true);
                        $variantType = !empty($variant) ? $variant[0]['type'] : null;
                        ProductLogic::update_stock($item, -$detail->quantity, $variantType)?->save();
                    }

                    if ($hasFlashDiscount) {
                        ProductLogic::update_flash_stock($detail->item, $detail->quantity, true)?->save();
                    }
                }
            }

            }


        }

        if($request->order_status == 'delivered')
        {
            $order->store->increment('order_count');
            if($order->delivery_man)
            {
                $order->delivery_man->increment('order_count');
            }

        }

        $order->order_status = $request->order_status;
        if($request->order_status == 'processing') {
            $order->processing_time = ($request?->processing_time) ? $request->processing_time : explode('-', $order['store']['delivery_time'])[0];
        }
        else if ($order->order_type != 'parcel' && in_array($request->order_status, ['picked_up']) ) {
            Helpers::sendOrderDeliveryVerificationOtp($order);
        }

        $order[$request['order_status']] = now();
        $order->save();
        if(!Helpers::send_order_notification($order))
        {
            Toastr::warning(translate('messages.push_notification_faild'));
        }

        Toastr::success(translate('messages.order_status_updated'));
        return back();
    }

    public function update_shipping(Request $request, $id)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required'
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success('Delivery address updated!');
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::where(['id' => $id, 'store_id' => Helpers::get_store_id()])->first();
        return view('vendor-views.order.invoice', compact('order'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id, 'store_id' => Helpers::get_store_id()])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
    }

    public function edit_order_amount(Request $request)
    {

        $request->validate([
            'order_amount' => 'required',

        ]);

        $order = Order::find($request->order_id);
        if(!$order){
            Toastr::error(translate('messages.Order_not_found'));
            return back();
        }
        if(!in_array($order->order_status, ['pending','confirmed','processing','picked_up','handover','accepted']) ){
            Toastr::error(translate('messages.Order_can_not_edit_a_completed_order'));
            return back();
        }
        $store = Store::find($order->store_id);
        $coupon = null;
        $free_delivery_by = null;
        if ($order->coupon_code) {
            $coupon = Coupon::active()->where(['code' => $order->coupon_code])->first();
            if (isset($coupon)) {
                $staus = CouponLogic::is_valide($coupon, $order->user_id, $order->store_id);
                if ($staus == 407) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_expire')]
                        ]
                    ], 407);
                } else if ($staus == 406) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_usage_limit_over')]
                        ]
                    ], 406);
                } else if ($staus == 404) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.not_found')]
                        ]
                    ], 404);
                }
            } else {
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.not_found')]
                    ]
                ], 404);
            }
        }

        $product_price = $request->order_amount;
        $total_addon_price = 0;
        $store_discount_amount = $order->store_discount_amount;

        // $discount=$order->store_discount_amount;
        $discount_on_product_by = $order->discount_on_product_by ?? 'vendor' ;

        $store_discount = Helpers::get_store_discount($store);
        $store_discount =  $store_discount ? $store_discount : ['discount' => 0, 'max_discount' => 0, 'min_purchase' => 0];
        $admin_discount = Helpers::checkAdminDiscount(price: $product_price + $total_addon_price, discount: $store_discount['discount'], max_discount: $store_discount['max_discount'], min_purchase: $store_discount['min_purchase']);

        $discount = $admin_discount;

        if($admin_discount > 0 && $discount == $admin_discount ){
                $discount_on_product_by =  'admin' ;
            }


        $order->discount_on_product_by= $discount_on_product_by;
        $store_discount_amount=$discount;
        $additionalCharges=[];


        $coupon_discount_amount = $coupon ? CouponLogic::get_discount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
        $total_price = $product_price + $total_addon_price - $store_discount_amount - $coupon_discount_amount;
        $total_price = max($total_price, 0);
        //Added service charge


   $settings = BusinessSetting::whereIn('key', [
                'dm_tips_status',
                'additional_charge_status',
                'additional_charge',
                'extra_packaging_data',
            ])->pluck('value', 'key');

            $dm_tips_manage_status     = $settings['dm_tips_status'] ?? null;
            $additional_charge_status  = $settings['additional_charge_status'] ?? null;
            $additional_charge         = $settings['additional_charge'] ?? null;

            $extra_packaging_data_raw  = $settings['extra_packaging_data'] ?? '';
            $extra_packaging_data      = json_decode($extra_packaging_data_raw, true) ?? [];


            //Added DM TIPS
            if ($dm_tips_manage_status == 1) {
                $order->dm_tips =$order->dm_tips ?? $request->dm_tips ?? 0;
            } else{
                $order->dm_tips = 0;
            }

            //Added service charge
            $order->additional_charge =$order->additional_charge;

            if ($additional_charge_status == 1) {
                $order->additional_charge = $additional_charge ?? 0;
                // $additionalCharges['tax_on_additional_charge'] = $order->additional_charge;
            }

            // extra packaging charge

            // $order->extra_packaging_amount =  (!empty($extra_packaging_data) && $request?->extra_packaging_amount > 0 && $store && ($extra_packaging_data[$store->module->module_type] == '1') && ($store?->storeConfig?->extra_packaging_status == '1')) ? $store?->storeConfig?->extra_packaging_amount : 0;

            // if ($order->extra_packaging_amount > 0) {
            //     $additionalCharges['tax_on_packaging_charge'] =  $order->extra_packaging_amount;
            // }

            $taxData =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                    amount: $total_price,
                    productIds: [],
                    taxPayer: 'prescription',
                    storeData: true,
                    additionalCharges: $additionalCharges,
                    addonIds: [],
                    orderId: null,
                    storeId:  $store->id
                );

                $tax_amount = $taxData['totalTaxamount'];
                $tax_included = $taxData['include'];
                $orderTaxIds = $taxData['orderTaxIds'] ?? [];
                $tax_status = $tax_included ?  'included' : 'excluded';

                $order->total_tax_amount = round($tax_amount, config('round_up_to_digit'));
                $order->tax_status = $tax_status;

        $free_delivery_over = BusinessSetting::where('key', 'free_delivery_over')->first()->value;
        if (isset($free_delivery_over)) {
            if ($free_delivery_over <= $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount) {
                $order->delivery_charge = 0;
                $free_delivery_by = 'admin';
            }
        }

        if ($store->free_delivery) {
            $order->delivery_charge = 0;
            $free_delivery_by = 'vendor';
        }

        if ($coupon) {
            if ($coupon->coupon_type == 'free_delivery') {
                if ($coupon->min_purchase <= $product_price + $total_addon_price - $store_discount_amount) {
                    $order->delivery_charge = 0;
                    $free_delivery_by = 'admin';
                }
            }
            // $coupon->increment('total_uses');
        }

        $order->coupon_discount_amount = round($coupon_discount_amount, config('round_up_to_digit'));
        $order->coupon_discount_title = $coupon ? $coupon->title : '';

        $order->store_discount_amount = round($store_discount_amount, config('round_up_to_digit'));
        $order->order_amount = round($total_price + $order->total_tax_amount + $order->additional_charge + $order->delivery_charge, config('round_up_to_digit'));
        $order->free_delivery_by = $free_delivery_by;
        $order->order_amount = $order->order_amount + $order->dm_tips;
        $order->save();
            $order?->orderTaxes()?->delete();
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $order->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
        Toastr::success(translate('messages.order_amount_updated'));
        return back();
    }
    public function edit_discount_amount(Request $request)
    {
        $request->validate([
            'discount_amount' => 'required',

        ]);

        $order = Order::find($request->order_id);
        if(!$order){
            Toastr::error(translate('messages.Order_not_found'));
            return back();
        }

        if(!in_array($order->order_status, ['pending','confirmed','processing','picked_up','handover','accepted']) ){
            Toastr::error(translate('messages.Order_can_not_edit_a_completed_order'));
            return back();
        }
        $product_price = $order['order_amount']-$order['delivery_charge']-$order['total_tax_amount']-$order['dm_tips'] - $order->additional_charge  +$order->store_discount_amount;


        if($request->discount_amount > $product_price)
        {
            Toastr::error(translate('messages.discount_amount_is_greater_then_product_amount'));
            return back();
        }
        $order->store_discount_amount = round($request->discount_amount, config('round_up_to_digit'));

        $order->discount_on_product_by= 'vendor';

        $settings = BusinessSetting::whereIn('key', [
                'dm_tips_status',
                'additional_charge_status',
                'additional_charge',
                'extra_packaging_data',
            ])->pluck('value', 'key');

            $dm_tips_manage_status     = $settings['dm_tips_status'] ?? null;
            $additional_charge_status  = $settings['additional_charge_status'] ?? null;
            $additional_charge         = $settings['additional_charge'] ?? null;

            $extra_packaging_data_raw  = $settings['extra_packaging_data'] ?? '';
            $extra_packaging_data      = json_decode($extra_packaging_data_raw, true) ?? [];


            //Added DM TIPS
            if ($dm_tips_manage_status == 1) {
                $order->dm_tips =$order->dm_tips ?? $request->dm_tips ?? 0;
            } else{
                $order->dm_tips = 0;
            }

            //Added service charge
            $order->additional_charge =$order->additional_charge;

            if ($additional_charge_status == 1) {
                $order->additional_charge = $additional_charge ?? 0;
                // $additionalCharges['tax_on_additional_charge'] = $order->additional_charge;
            }

            // // extra packaging charge

            // $order->extra_packaging_amount =  (!empty($extra_packaging_data) && $request?->extra_packaging_amount > 0 && $store && ($extra_packaging_data[$store->module->module_type] == '1') && ($store?->storeConfig?->extra_packaging_status == '1')) ? $store?->storeConfig?->extra_packaging_amount : 0;

            // if ($order->extra_packaging_amount > 0) {
            //     $additionalCharges['tax_on_packaging_charge'] =  $order->extra_packaging_amount;
            // }



         $taxData =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                    amount: $product_price-$request->discount_amount,
                    productIds: [],
                    taxPayer: 'prescription',
                    storeData: true,
                    additionalCharges: [],
                    addonIds: [],
                    orderId: null,
                    storeId:  $order->store_id
                );

                $tax_amount = $taxData['totalTaxamount'];
                $tax_included = $taxData['include'];
                $orderTaxIds = $taxData['orderTaxIds'] ?? [];
                $tax_status = $tax_included ?  'included' : 'excluded';

                $order->total_tax_amount = round($tax_amount, config('round_up_to_digit'));
                $order->tax_status = $tax_status;



        $order->order_amount = $product_price+$order['delivery_charge']+ $order->total_tax_amount +$order['dm_tips'] + $order->additional_charge  -$order->store_discount_amount;
        $order->save();
        $order?->orderTaxes()?->delete();
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $order->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
        Toastr::success(translate('messages.discount_amount_updated'));
        return back();
    }

    public function add_order_proof(Request $request, $id)
    {
        $order = Order::find($id);
        $img_names = $order->order_proof?json_decode($order->order_proof):[];
        $images = [];
        $total_file = count($request->order_proof) + count($img_names);
        if(!$img_names){
            $request->validate([
                'order_proof' => 'required|array|max:5',
            ]);
        }

        if ($total_file>5) {
            Toastr::error(translate('messages.order_proof_must_not_have_more_than_5_item'));
            return back();
        }

        if (!empty($request->file('order_proof'))) {
            foreach ($request->order_proof as $img) {
                $image_name = Helpers::upload('order/', 'png', $img);
                array_push($img_names, ['img'=>$image_name, 'storage'=> Helpers::getDisk()]);
            }
            $images = $img_names;
        }

        if(count($images)>0){
            $order->order_proof = json_encode($images);
        }
        $order->save();

        Toastr::success(translate('messages.order_proof_added'));
        return back();
    }


    public function remove_proof_image(Request $request)
    {
        $order = Order::find($request['id']);
        $array = [];
        $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : [];
        if (count($proof) < 2) {
            Toastr::warning(translate('all_image_delete_warning'));
            return back();
        }

        Helpers::check_and_delete('order/' , $request['name']);

        foreach ($proof as $image) {
            if ($image != $request['name']) {
                array_push($array, $image);
            }
        }
        Order::where('id', $request['id'])->update([
            'order_proof' => json_encode($array),
        ]);
        Toastr::success(translate('order_proof_image_removed_successfully'));
        return back();
    }
}
