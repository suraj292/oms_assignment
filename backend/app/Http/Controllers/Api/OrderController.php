<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseApiController
{
    public function index(Request $request)
    {
        $ordersQuery = Order::with(['customer', 'items']);

        if ($request->has('search')) {
            $ordersQuery->search($request->search);
        }

        if ($request->has('status')) {
            $ordersQuery->byStatus($request->status);
        }

        if ($request->has('customer_id')) {
            $ordersQuery->where('customer_id', $request->customer_id);
        }

        $paginatedOrders = $ordersQuery->latest()->paginate($request->get('per_page', 15));

        return OrderResource::collection($paginatedOrders);
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            DB::beginTransaction();

            $newOrder = Order::create([
                'customer_id' => $request->customer_id,
                'notes' => $request->notes,
                'status' => OrderStatus::DRAFT,
            ]);

            // create order items from the products
            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                
                // we store product name and price at order time so we have a snapshot
                // even if the product changes later
                OrderItem::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $itemData['quantity'],
                ]);
            }

            $newOrder->load(['customer', 'items']);

            DB::commit();

            return $this->successResponse(
                new OrderResource($newOrder),
                'Order created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items', 'documents']);
        return $this->successResponse(new OrderResource($order));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        try {
            DB::beginTransaction();

            $order->update([
                'customer_id' => $request->customer_id ?? $order->customer_id,
                'notes' => $request->notes ?? $order->notes,
            ]);

            // if items are being updated, we just delete all old ones and recreate them
            // simpler than trying to diff and update individual items
            if ($request->has('items')) {
                $order->items()->delete();

                foreach ($request->items as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);
                    
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'price' => $product->price,
                        'quantity' => $itemData['quantity'],
                    ]);
                }
            }

            $order->load(['customer', 'items']);

            DB::commit();

            return $this->successResponse(
                new OrderResource($order),
                'Order updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update order: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $newStatus = OrderStatus::from($request->status);

        // the Order model handles validation and notifications
        if ($order->transitionTo($newStatus)) {
            $order->load(['customer', 'items']);
            
            return $this->successResponse(
                new OrderResource($order),
                "Order status updated to {$newStatus->label()}"
            );
        }

        return $this->errorResponse('Invalid status transition', 422);
    }

    public function destroy(Order $order)
    {
        // we only let people delete draft orders, otherwise it messes up reporting
        if (!$order->isEditable()) {
            return $this->errorResponse('Only draft orders can be deleted', 403);
        }

        $order->delete();

        return $this->successResponse(null, 'Order deleted successfully');
    }
}
