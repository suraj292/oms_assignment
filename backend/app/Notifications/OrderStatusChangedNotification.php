<?php

namespace App\Notifications;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Order $order;
    protected OrderStatus $oldStatus;
    protected OrderStatus $newStatus;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, OrderStatus $oldStatus, OrderStatus $newStatus)
    {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Status Updated - ' . $this->order->order_number)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your order status has been updated.')
            ->line('Order Number: ' . $this->order->order_number)
            ->line('Previous Status: ' . $this->oldStatus->label())
            ->line('New Status: ' . $this->newStatus->label())
            ->action('View Order', url('/orders/' . $this->order->id))
            ->line('Thank you for your patience!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
            'message' => "Order {$this->order->order_number} status changed from {$this->oldStatus->label()} to {$this->newStatus->label()}.",
        ];
    }
}
