# Order Management System

A complete Order Management System with Laravel 11 backend API and Vue 3 frontend SPA.

## Features

- **Authentication & Authorization** - Role-based access (Admin, Staff)
- **Product Management** - CRUD operations with search and image upload
- **Customer Management** - Full customer database with search
- **Order Management** - Multi-item orders with auto-calculated totals
- **Order Status Flow** - Draft → Confirmed → Processing → Dispatched → Delivered/Cancelled
- **Notifications** - In-app, Email, and Real-time (Pusher)
- **File Uploads** - Product images and order documents
- **Large File Upload** - Resumable chunked uploads (up to 5GB)
- **Multi-Word Search** - Intelligent search across all resources

---

## Tech Stack

### Backend
- Laravel 11
- PHP 8.2+
- MySQL
- Laravel Sanctum (Authentication)
- Pusher (Real-time notifications)

### Frontend
- Vue 3
- TypeScript
- Vite
- Pinia (State management)
- Axios (HTTP client)
- Pusher JS (Real-time)

---

## Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- MySQL 8.0+

### Backend Setup

1. **Navigate to backend directory**
```bash
cd backend
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure environment**
```bash
cp .env.example .env
```

Edit `.env` and set:
```env
APP_NAME="Order Management System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oms_db
DB_USERNAME=root
DB_PASSWORD=your_password

# Pusher for real-time notifications
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster

# Mail (Mailtrap for development)
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS="noreply@oms.com"

# Queue (use 'sync' for development, 'database' for production)
QUEUE_CONNECTION=sync
```

4. **Generate application key**
```bash
php artisan key:generate
```

5. **Run migrations and seeders**
```bash
php artisan migrate --seed
```

This creates:
- Admin user: `admin@example.com` / `password`
- Staff user: `staff@example.com` / `password`
- Sample products, customers, and orders

6. **Create storage link**
```bash
php artisan storage:link
```

7. **Start the server**
```bash
php artisan serve
```

Backend runs at: `http://localhost:8000`

### Frontend Setup

1. **Navigate to frontend directory**
```bash
cd frontend
```

2. **Install dependencies**
```bash
npm install
```

3. **Configure environment**
```bash
cp .env.example .env
```

Edit `.env`:
```env
VITE_API_URL=http://localhost:8000/api
```

4. **Start development server**
```bash
npm run dev
```

Frontend runs at: `http://localhost:5173`

5. **Build for production**
```bash
npm run build
```

---

## Default Credentials

After running migrations with seed:

**Admin Account:**
- Email: `admin@example.com`
- Password: `password`
- Can: Full access to all features

**Staff Account:**
- Email: `staff@example.com`
- Password: `password`
- Can: Create/update products, customers, orders (cannot delete)

---

## System Flow

### 1. Authentication
```
User → Login → Sanctum Token → Stored in Pinia → Sent with all API requests
```

### 2. Product Management
```
Admin/Staff → Create Product → Upload Image → Save to Database
Search → Multi-word search (all words must match)
```

### 3. Customer Management
```
Admin/Staff → Create Customer → Save contact info
Search → Name, Email, or Phone
```

### 4. Order Creation
```
Select Customer → Add Products → Set Quantities → Auto-calculate Total → Create Order
```

### 5. Order Status Lifecycle

```
Draft
  ↓ (can edit)
Confirmed
  ↓ (cannot edit)
Processing
  ↓
Dispatched
  ↓
Delivered

(Can cancel from any status except Delivered)
```

**Status Rules:**
- **Draft**: Editable, can be deleted
- **Confirmed**: No longer editable
- **Processing/Dispatched**: In fulfillment
- **Delivered**: Final state
- **Cancelled**: Terminal state

### 6. Notifications

**Triggers:**
- Order created
- Order status changed

**Channels:**
1. **Database** - Stored in `notifications` table
2. **Email** - Sent via configured mail driver
3. **Real-time** - Pusher broadcast (instant delivery)

**Frontend:**
- Notification bell with unread count
- Real-time updates (no polling)
- Click to mark as read

---

## Large File Upload

### How It Works

**Problem:** Uploading large files (>100MB) in a single request is unreliable.

**Solution:** Chunked upload with resume capability.

### Process

1. **File Chunking**
   - Frontend splits file into 1MB chunks
   - Each chunk uploaded independently

2. **Upload Session**
   ```
   Initialize → Get Upload ID → Upload Chunks → Complete → Merge
   ```

3. **Resume Capability**
   - If upload fails, check status
   - Skip already uploaded chunks
   - Continue from last successful chunk

4. **Backend Processing**
   - Store chunks in `storage/app/chunks/{upload_id}/`
   - Track metadata (received chunks, file size)
   - Merge all chunks when complete
   - Verify file integrity
   - Cleanup temporary files

### Features
- **Max file size**: 5GB
- **Chunk size**: 1MB
- **Retry logic**: 3 attempts per chunk with exponential backoff
- **Pause/Resume**: Full control over upload
- **Progress tracking**: Real-time progress bar
- **Network resilience**: Handles interruptions gracefully

---

## Multi-Word Search

### Behavior

**Input:** `"dell laptop"`

**Logic:**
- Split into words: `['dell', 'laptop']`
- Each word must match at least one field
- ALL words must have matches (AND logic)
- Word order doesn't matter

**Example:**
- ✅ Matches: "Dell Laptop 15 inch"
- ✅ Matches: "Laptop by Dell"
- ❌ Doesn't match: "Dell Monitor" (missing "laptop")

### Searchable Fields
- **Products**: name, description
- **Customers**: name, email, phone
- **Orders**: order_number

---

## API Structure

### Endpoints

**Authentication:**
- `POST /api/register` - Register new user
- `POST /api/login` - Login
- `POST /api/logout` - Logout

**Products:**
- `GET /api/products` - List (with search, pagination)
- `POST /api/products` - Create
- `GET /api/products/{id}` - Get one
- `PUT /api/products/{id}` - Update
- `DELETE /api/products/{id}` - Delete

**Customers:**
- `GET /api/customers` - List (with search, pagination)
- `POST /api/customers` - Create
- `GET /api/customers/{id}` - Get one
- `PUT /api/customers/{id}` - Update
- `DELETE /api/customers/{id}` - Delete

**Orders:**
- `GET /api/orders` - List (with search, filters, pagination)
- `POST /api/orders` - Create
- `GET /api/orders/{id}` - Get one
- `PUT /api/orders/{id}` - Update
- `PATCH /api/orders/{id}/status` - Update status
- `DELETE /api/orders/{id}` - Delete (draft only)

**Notifications:**
- `GET /api/notifications` - List
- `GET /api/notifications/unread-count` - Count
- `PATCH /api/notifications/{id}/read` - Mark as read
- `POST /api/notifications/mark-all-read` - Mark all

**Chunked Uploads:**
- `POST /api/uploads/init` - Initialize
- `POST /api/uploads/{id}/chunk` - Upload chunk
- `GET /api/uploads/{id}/status` - Check status
- `POST /api/uploads/{id}/complete` - Finalize
- `DELETE /api/uploads/{id}` - Cancel

### Response Format

**Success:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

---

## Development

### Backend Commands

```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear cache
php artisan cache:clear
php artisan config:clear

# Run tests
php artisan test

# Queue worker (if using database queue)
php artisan queue:work
```

### Frontend Commands

```bash
# Development
npm run dev

# Build
npm run build

# Preview production build
npm run preview

# Type check
npm run type-check
```

---

## Project Structure

### Backend
```
backend/
├── app/
│   ├── Enums/          # OrderStatus enum
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/    # API controllers
│   │   ├── Middleware/ # Auth, Role middleware
│   │   ├── Requests/   # Form validation
│   │   └── Resources/  # API resources
│   ├── Models/         # Eloquent models
│   ├── Notifications/  # Email/broadcast notifications
│   ├── Services/       # Business logic (ChunkedUploadService)
│   └── Traits/         # Searchable trait
├── database/
│   ├── migrations/     # Database schema
│   └── seeders/        # Sample data
└── routes/
    └── api.php         # API routes
```

### Frontend
```
frontend/
├── src/
│   ├── api/            # API client modules
│   ├── components/
│   │   ├── common/     # Reusable components
│   │   └── layout/     # Layout components
│   ├── router/         # Vue Router
│   ├── services/       # ChunkedUploadManager
│   ├── stores/         # Pinia stores
│   ├── types/          # TypeScript types
│   ├── utils/          # Utilities
│   └── views/          # Page components
└── public/             # Static assets
```

---

## Troubleshooting

### Backend Issues

**Database connection error:**
- Check `.env` database credentials
- Ensure MySQL is running
- Verify database exists

**Storage permission error:**
```bash
chmod -R 775 storage bootstrap/cache
```

**Pusher not working:**
- Verify Pusher credentials in `.env`
- Check `BROADCAST_CONNECTION=pusher`

### Frontend Issues

**API connection error:**
- Verify `VITE_API_URL` in `.env`
- Ensure backend is running
- Check CORS configuration

**Build errors:**
```bash
rm -rf node_modules package-lock.json
npm install
```

---

## Production Deployment

### Backend

1. Set environment to production
```env
APP_ENV=production
APP_DEBUG=false
```

2. Optimize
```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. Set up queue worker
```bash
php artisan queue:work --daemon
```

4. Configure web server (Nginx/Apache)

### Frontend

1. Build
```bash
npm run build
```

2. Deploy `dist/` folder to web server

3. Configure web server to serve SPA
```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

---

## License

This project is for educational purposes.

---

## Support

For issues or questions, please refer to the code documentation or contact the development team.
