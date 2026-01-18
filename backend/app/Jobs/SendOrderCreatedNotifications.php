<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderCreatedNotifications implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public ?int $createdByUserId = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load customer relationship
        $this->order->load('customer');
        
        // Notify the customer
        if ($this->order->customer) {
            $this->order->customer->notify(new OrderCreatedNotification($this->order));
        }
        
        // Notify the user who created the order
        if ($this->createdByUserId) {
            $user = User::find($this->createdByUserId);
            if ($user) {
                $user->notify(new OrderCreatedNotification($this->order));
            }
        }
    }
}
