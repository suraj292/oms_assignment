export interface Notification {
    id: string
    type: string
    data: NotificationData
    read_at: string | null
    created_at: string
}

export interface NotificationData {
    type: 'order_created' | 'order_status_changed'
    order_id: number
    order_number: string
    message: string
    total?: string
    old_status?: string
    new_status?: string
}
