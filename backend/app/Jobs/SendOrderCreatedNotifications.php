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

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->order->load('customer');
        

        if ($this->order->customer) {
            $this->order->customer->notify(new OrderCreatedNotification($this->order));
        }
        

        if ($this->createdByUserId) {
            $user = User::find($this->createdByUserId);
            
            // only notify if it's not the same person as the customer
            if ($user && (!$this->order->customer || $user->email !== $this->order->customer->email)) {
                $user->notify(new OrderCreatedNotification($this->order));
            }
        }
    }
}
