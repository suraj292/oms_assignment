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
    /**
     * Display a listing of orders
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'items']);

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * Store a newly created order
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            DB::beginTransaction();

            $order = Order::create([
                'customer_id' => $request->customer_id,
                'notes' => $request->notes,
                'status' => OrderStatus::DRAFT,
            ]);

            // Add items
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

            $order->load(['customer', 'items']);

            DB::commit();

            return $this->successResponse(
                new OrderResource($order),
                'Order created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Order $order)
    {
        $order->load(['customer', 'items.product']);
        return $this->successResponse(new OrderResource($order));
    }

    /**
     * Update the specified order
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        try {
            DB::beginTransaction();

            $order->update([
                'customer_id' => $request->customer_id ?? $order->customer_id,
                'notes' => $request->notes ?? $order->notes,
            ]);

            // Update items if provided
            if ($request->has('items')) {
                // Delete existing items
                $order->items()->delete();

                // Add new items
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

    /**
     * Update order status
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $newStatus = OrderStatus::from($request->status);

        if ($order->transitionTo($newStatus)) {
            $order->load(['customer', 'items']);
            
            return $this->successResponse(
                new OrderResource($order),
                "Order status updated to {$newStatus->label()}"
            );
        }

        return $this->errorResponse('Invalid status transition', 422);
    }

    /**
     * Remove the specified order
     */
    public function destroy(Order $order)
    {
        // Only allow deleting draft orders
        if (!$order->isEditable()) {
            return $this->errorResponse('Only draft orders can be deleted', 403);
        }

        $order->delete();

        return $this->successResponse(null, 'Order deleted successfully');
    }
}
