# Folder Structure Guide

## Backend (Laravel 11)

### Recommended Structure for Scaling

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BaseApiController.php      # Base controller with response helpers
│   │       ├── Auth/
│   │       │   ├── LoginController.php
│   │       │   └── RegisterController.php
│   │       └── Orders/
│   │           └── OrderController.php
│   │
│   ├── Requests/                          # Form Request Validation
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterRequest.php
│   │   └── Orders/
│   │       ├── StoreOrderRequest.php
│   │       └── UpdateOrderRequest.php
│   │
│   └── Resources/                         # API Resources (Transformers)
│       ├── UserResource.php
│       └── OrderResource.php
│
├── Models/
│   ├── User.php
│   └── Order.php
│
└── Services/                              # Business Logic
    ├── AuthService.php
    └── OrderService.php
```

### When to Use Each Layer

**Controllers** (`app/Http/Controllers/Api/`)
- Handle HTTP requests and responses
- Validate input (via Form Requests)
- Call services for business logic
- Return JSON responses
- Keep thin - delegate to services

**Form Requests** (`app/Http/Requests/`)
- Validation rules
- Authorization logic
- Custom error messages
- Input sanitization

**Services** (`app/Services/`)
- Business logic
- Database transactions
- Complex operations
- Reusable functionality
- Independent of HTTP layer

**Resources** (`app/Http/Resources/`)
- Transform models to JSON
- Control API response structure
- Hide sensitive data
- Format dates, relationships

### Example Controller Pattern

```php
namespace App\Http\Controllers\Api\Orders;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Services\OrderService;

class OrderController extends BaseApiController
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function store(StoreOrderRequest $request)
    {
        $order = $this->orderService->createOrder($request->validated());
        
        return $this->successResponse($order, 'Order created successfully', 201);
    }
}
```

---

## Frontend (Vue 3)

### Recommended Structure for Scaling

```
src/
├── api/
│   ├── client.ts                          # Axios instance
│   ├── auth.ts                            # Auth API calls
│   └── orders.ts                          # Order API calls
│
├── components/
│   ├── common/                            # Reusable components
│   │   ├── AppButton.vue
│   │   ├── AppInput.vue
│   │   └── AppModal.vue
│   │
│   └── orders/                            # Feature-specific components
│       ├── OrderList.vue
│       ├── OrderCard.vue
│       └── OrderForm.vue
│
├── composables/                           # Composition API utilities
│   ├── useAuth.ts
│   └── useOrders.ts
│
├── router/
│   └── index.ts                           # Route definitions
│
├── stores/
│   ├── auth.ts                            # Auth state
│   └── orders.ts                          # Orders state
│
├── types/                                 # TypeScript types
│   ├── auth.ts
│   └── order.ts
│
├── utils/                                 # Helper functions
│   ├── validators.ts
│   └── formatters.ts
│
└── views/                                 # Page components
    ├── HomeView.vue
    ├── LoginView.vue
    └── orders/
        ├── OrdersView.vue
        └── OrderDetailView.vue
```

### When to Use Each Layer

**API Modules** (`src/api/`)
- Organize API calls by feature
- Use the configured axios client
- Return typed responses
- Handle API-specific logic

**Components** (`src/components/`)
- `common/`: Shared across features
- `[feature]/`: Feature-specific components
- Keep focused and reusable
- Props for input, events for output

**Composables** (`src/composables/`)
- Reusable composition logic
- Combine stores, API calls, and reactive state
- Business logic for components
- Follow `use*` naming convention

**Stores** (`src/stores/`)
- Global state management
- One store per feature/domain
- Use composition API style
- Keep actions simple

**Types** (`src/types/`)
- TypeScript interfaces and types
- API response types
- Component prop types
- Shared type definitions

**Views** (`src/views/`)
- Route-level components
- Compose smaller components
- Handle page-level logic
- One view per route

### Example API Module Pattern

```typescript
// src/api/orders.ts
import apiClient from './client'
import type { Order, CreateOrderDto } from '@/types/order'

export const ordersAPI = {
  getAll: () => apiClient.get<Order[]>('/orders'),
  
  getById: (id: number) => apiClient.get<Order>(`/orders/${id}`),
  
  create: (data: CreateOrderDto) => apiClient.post<Order>('/orders', data),
  
  update: (id: number, data: Partial<CreateOrderDto>) => 
    apiClient.put<Order>(`/orders/${id}`, data),
  
  delete: (id: number) => apiClient.delete(`/orders/${id}`),
}
```

### Example Composable Pattern

```typescript
// src/composables/useOrders.ts
import { ref } from 'vue'
import { ordersAPI } from '@/api/orders'
import type { Order } from '@/types/order'

export function useOrders() {
  const orders = ref<Order[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchOrders() {
    loading.value = true
    error.value = null
    try {
      const response = await ordersAPI.getAll()
      orders.value = response.data
    } catch (e: any) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  return {
    orders,
    loading,
    error,
    fetchOrders,
  }
}
```

---

## General Principles

1. **Separation of Concerns**: Each layer has a specific responsibility
2. **Don't Repeat Yourself**: Extract common logic into services/composables
3. **Keep It Simple**: Don't over-abstract until you need to
4. **Type Safety**: Use TypeScript types and Laravel type hints
5. **Consistent Naming**: Follow framework conventions
6. **Test-Friendly**: Structure makes unit testing easier

---

## Scaling Considerations

- Start simple, refactor when patterns emerge
- Group by feature, not by type (when it makes sense)
- Use dependency injection (Laravel) and composition (Vue)
- Keep files small and focused
- Document complex logic
- Use consistent error handling patterns
