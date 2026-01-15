<?php

namespace App\Enums;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case DISPATCHED = 'dispatched';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get allowed next statuses from current status
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::DRAFT => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::DISPATCHED, self::CANCELLED],
            self::DISPATCHED => [self::DELIVERED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to new status is allowed
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    /**
     * Check if status is final (no more transitions allowed)
     */
    public function isFinal(): bool
    {
        return $this === self::DELIVERED || $this === self::CANCELLED;
    }

    /**
     * Check if order can be edited in this status
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::DISPATCHED => 'Dispatched',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get all statuses as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
