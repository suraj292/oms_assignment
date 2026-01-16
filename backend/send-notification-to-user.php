#!/usr/bin/env php
<?php

/**
 * Send Notification to Specific User
 * 
 * This script sends test notifications to a specific user by ID
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Enums\OrderStatus;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderStatusChangedNotification;

echo "=== Send Notification to Specific User ===\n\n";

// Get user ID from command line argument
$userId = $argv[1] ?? null;

if (!$userId) {
    echo "Usage: php send-notification-to-user.php <user_id>\n\n";
    echo "Available users:\n";
    $users = User::select('id', 'name', 'email')->get();
    foreach ($users as $user) {
        echo "  ID: {$user->id} - {$user->name} ({$user->email})\n";
    }
    exit(1);
}

// Get the user
$user = User::find($userId);

if (!$user) {
    echo "Error: User with ID {$userId} not found.\n";
    exit(1);
}

echo "Sending notifications to:\n";
echo "  ID: {$user->id}\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n\n";

// Check if user is a customer
$customer = Customer::where('email', $user->email)->first();

if (!$customer) {
    echo "Note: User is not a customer. Creating customer record...\n";
    $customer = Customer::create([
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '1234567890',
        'address' => 'Test Address',
    ]);
    echo "✓ Customer created with ID: {$customer->id}\n\n";
}

// Get a test product
$product = Product::first();

if (!$product) {
    echo "Error: No products found in database. Please create a product first.\n";
    exit(1);
}

// Create a test order
echo "Creating test order...\n";
DB::beginTransaction();

try {
    $order = Order::create([
        'customer_id' => $customer->id,
        'notes' => 'Test order for user: ' . $user->name,
        'status' => OrderStatus::DRAFT,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => 1,
    ]);

    $order->load(['customer', 'items']);
    
    echo "✓ Order created: {$order->order_number}\n";
    echo "  Total: \${$order->total}\n\n";

    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    echo "Error creating order: {$e->getMessage()}\n";
    exit(1);
}

// Send notification to the user
echo "Sending OrderCreatedNotification to user...\n";
try {
    $user->notify(new OrderCreatedNotification($order));
    echo "✓ Notification sent!\n";
    echo "  Channels: database, mail, broadcast\n\n";
} catch (Exception $e) {
    echo "Error sending notification: {$e->getMessage()}\n\n";
}

// Change order status
sleep(2);
echo "Changing order status to CONFIRMED...\n";
try {
    $oldStatus = $order->status;
    $newStatus = OrderStatus::CONFIRMED;
    
    if ($order->transitionTo($newStatus)) {
        echo "✓ Status changed: {$oldStatus->label()} → {$newStatus->label()}\n";
        
        // Send status change notification
        $user->notify(new OrderStatusChangedNotification($order, $oldStatus, $newStatus));
        echo "✓ OrderStatusChangedNotification sent!\n\n";
    }
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n\n";
}

// Check notifications
echo "Checking notifications for user...\n";
$notifications = $user->notifications()->latest()->take(5)->get();

echo "✓ Found {$notifications->count()} notification(s):\n";
foreach ($notifications as $notification) {
    $data = $notification->data;
    $readStatus = $notification->read_at ? '✓ Read' : '✗ Unread';
    echo "  [{$readStatus}] {$data['message']}\n";
}
echo "\n";

echo "=== Summary ===\n";
echo "✓ User: {$user->name} (ID: {$user->id})\n";
echo "✓ Order: {$order->order_number}\n";
echo "✓ Notifications sent: 2\n";
echo "✓ Unread count: {$user->unreadNotifications()->count()}\n\n";

echo "Now check the frontend notification bell!\n";
