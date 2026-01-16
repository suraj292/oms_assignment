#!/usr/bin/env php
<?php

/**
 * Notification System Test Script
 * 
 * This script tests the notification system by:
 * 1. Creating a test order
 * 2. Sending notifications to the customer
 * 3. Changing order status to trigger status change notification
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Enums\OrderStatus;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderStatusChangedNotification;

echo "=== Notification System Test ===\n\n";

// Step 1: Get or create a test customer
echo "Step 1: Getting test customer...\n";
$customer = Customer::first();

if (!$customer) {
    echo "Error: No customers found in database. Please create a customer first.\n";
    exit(1);
}

echo "✓ Using customer: {$customer->name} (ID: {$customer->id})\n";
echo "  Email: {$customer->email}\n\n";

// Step 2: Get a test product
echo "Step 2: Getting test product...\n";
$product = Product::first();

if (!$product) {
    echo "Error: No products found in database. Please create a product first.\n";
    exit(1);
}

echo "✓ Using product: {$product->name} (ID: {$product->id})\n";
echo "  Price: \${$product->price}\n\n";

// Step 3: Create a test order
echo "Step 3: Creating test order...\n";
DB::beginTransaction();

try {
    $order = Order::create([
        'customer_id' => $customer->id,
        'notes' => 'Test order for notification system verification',
        'status' => OrderStatus::DRAFT,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => 2,
    ]);

    $order->load(['customer', 'items']);
    
    echo "✓ Order created successfully!\n";
    echo "  Order Number: {$order->order_number}\n";
    echo "  Total: \${$order->total}\n";
    echo "  Status: {$order->status->label()}\n\n";

    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    echo "Error creating order: {$e->getMessage()}\n";
    exit(1);
}

// Step 4: Send OrderCreatedNotification
echo "Step 4: Sending OrderCreatedNotification...\n";
try {
    $customer->notify(new OrderCreatedNotification($order));
    echo "✓ OrderCreatedNotification sent!\n";
    echo "  Channels: database, mail, broadcast\n";
    echo "  Check your email: {$customer->email}\n\n";
} catch (Exception $e) {
    echo "Error sending notification: {$e->getMessage()}\n\n";
}

// Wait a moment
sleep(2);

// Step 5: Change order status to trigger status change notification
echo "Step 5: Changing order status to CONFIRMED...\n";
try {
    $oldStatus = $order->status;
    $newStatus = OrderStatus::CONFIRMED;
    
    if ($order->transitionTo($newStatus)) {
        echo "✓ Order status changed successfully!\n";
        echo "  Old Status: {$oldStatus->label()}\n";
        echo "  New Status: {$newStatus->label()}\n\n";
        
        // The notification should be sent automatically via the Order model observer
        echo "✓ OrderStatusChangedNotification should have been sent automatically!\n";
        echo "  (via Order model observer)\n\n";
    } else {
        echo "Error: Invalid status transition\n\n";
    }
} catch (Exception $e) {
    echo "Error changing status: {$e->getMessage()}\n\n";
}

// Step 6: Check notifications in database
echo "Step 6: Checking notifications in database...\n";
$notifications = $customer->notifications()->latest()->take(5)->get();

echo "✓ Found {$notifications->count()} notification(s) for customer:\n";
foreach ($notifications as $notification) {
    $data = $notification->data;
    $readStatus = $notification->read_at ? '✓ Read' : '✗ Unread';
    echo "  - [{$readStatus}] {$data['message']}\n";
    echo "    Type: {$data['type']}\n";
    echo "    Created: {$notification->created_at->diffForHumans()}\n";
}
echo "\n";

// Step 7: Summary
echo "=== Test Summary ===\n";
echo "✓ Test order created: {$order->order_number}\n";
echo "✓ Notifications sent to: {$customer->email}\n";
echo "✓ Total notifications: {$notifications->count()}\n";
echo "✓ Unread count: {$customer->unreadNotifications()->count()}\n\n";

echo "Next Steps:\n";
echo "1. Check your email inbox for notification emails\n";
echo "2. Open the frontend application and check the notification bell\n";
echo "3. Verify real-time notifications appear without refresh\n";
echo "4. Click on notifications to test navigation\n";
echo "5. Test 'Mark all as read' functionality\n\n";

echo "Frontend URL: http://localhost:5173\n";
echo "Order ID for testing: {$order->id}\n\n";
