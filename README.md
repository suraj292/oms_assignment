# Order Management System - Phase 0 Setup

This repository contains a Laravel 11 REST API backend and a Vue 3 SPA frontend for an Order Management System.

## Project Structure

```
OMS Assignment/
├── backend/          # Laravel 11 API
└── frontend/         # Vue 3 SPA
```

## Backend (Laravel 11 API)

### Tech Stack
- Laravel 11
- Laravel Sanctum (Token-based authentication)
- MySQL database
- PHP 8.2+

### Folder Structure
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/          # API controllers
│   │   │       └── BaseApiController.php
│   │   └── Requests/         # Form request validation
│   └── Services/             # Business logic layer
├── routes/
│   └── api.php               # API routes
└── storage/
    └── app/public/           # Public file storage (linked)
```

### Setup Instructions

1. **Install Dependencies**
   ```bash
   cd backend
   composer install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   ```
   
   Update the following in `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=oms_db
   DB_USERNAME=root
   DB_PASSWORD=your_password
   
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:3000
   ```

3. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

4. **Create Database**
   ```bash
   mysql -u root -p
   CREATE DATABASE oms_db;
   exit;
   ```

5. **Run Migrations**
   ```bash
   php artisan migrate
   ```

6. **Start Development Server**
   ```bash
   php artisan serve
   ```
   
   API will be available at: `http://localhost:8000/api`

### API Endpoints

- `GET /api/health` - Health check (public)
- `GET /api/user` - Get authenticated user (protected)

### Authentication

Sanctum is configured for token-based authentication. Protected routes use the `auth:sanctum` middleware.

---

## Frontend (Vue 3 SPA)

### Tech Stack
- Vue 3 (Composition API)
- TypeScript
- Vue Router 4
- Pinia (State Management)
- Axios (HTTP Client)
- Vite (Build Tool)

### Folder Structure
```
frontend/
├── src/
│   ├── api/              # API client configuration
│   │   └── client.ts     # Axios instance with interceptors
│   ├── components/       # Reusable Vue components
│   ├── router/           # Vue Router configuration
│   │   └── index.ts
│   ├── stores/           # Pinia stores
│   │   └── auth.ts       # Authentication store
│   └── views/            # Page components
│       └── HomeView.vue
```

### Setup Instructions

1. **Install Dependencies**
   ```bash
   cd frontend
   npm install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   ```
   
   Update `.env` if needed:
   ```
   VITE_API_URL=http://localhost:8000/api
   ```

3. **Start Development Server**
   ```bash
   npm run dev
   ```
   
   App will be available at: `http://localhost:5173`

### Key Features

- **Axios Client**: Pre-configured with base URL and auth token interceptor
- **Auth Store**: Centralized authentication state with localStorage persistence
- **Router**: Ready for route-based navigation
- **Path Alias**: Use `@/` to import from `src/` directory

### API Client Usage

```typescript
import apiClient from '@/api/client'

// Example API call
const response = await apiClient.get('/endpoint')
```

The API client automatically:
- Attaches auth tokens to requests
- Handles 401 responses by logging out the user
- Uses the base URL from environment variables

---

## Development Workflow

1. **Backend First**: Start the Laravel API server
2. **Frontend Second**: Start the Vue dev server
3. **CORS**: Ensure Laravel CORS is configured for `localhost:5173`

## Git Workflow

### Initial Commit Messages (Example)

```bash
# Backend
git add backend/
git commit -m "chore: initialize Laravel 11 API with Sanctum auth"

# Frontend
git add frontend/
git commit -m "chore: bootstrap Vue 3 SPA with router and pinia"

# Documentation
git add README.md
git commit -m "docs: add project setup and structure documentation"
```

### What's Ignored

**Backend:**
- `.env` files
- `vendor/`
- `node_modules/`
- `storage/` (except `.gitkeep`)
- `public/storage`

**Frontend:**
- `.env` files
- `node_modules/`
- `dist/`
- Build artifacts

---

## Next Steps (Future Phases)

- Implement authentication endpoints (register, login, logout)
- Create order management models and migrations
- Build order CRUD API endpoints
- Develop frontend UI components
- Add form validation
- Implement file upload functionality

---

## Notes

- This is a **Phase 0** setup - no business logic or UI features yet
- Both applications are completely separate and can be deployed independently
- The structure is designed to scale without over-engineering
- Follow RESTful conventions for API design
- Use composition API for all Vue components
