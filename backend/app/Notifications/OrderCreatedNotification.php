<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Created - ' . $this->order->order_number)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your order has been created successfully.')
            ->line('Order Number: ' . $this->order->order_number)
            ->line('Total: $' . $this->order->total)
            ->line('Status: ' . $this->order->status->label())
            ->action('View Order', url('/orders/' . $this->order->id))
            ->line('Thank you for your order!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_created',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total' => $this->order->total,
            'message' => "Order {$this->order->order_number} has been created successfully.",
        ];
    }
}
