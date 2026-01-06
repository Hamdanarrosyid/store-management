# Store Management API

## 1. ERD
Add your ERD diagram here.

## 2. API Documentation
- Base URL: `/api`
- Auth: JWT Bearer (`Authorization: Bearer <token>`)
- Success envelope (unless noted):
```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {}
}
```

### Auth
- **POST `/auth/login`**  
  Body:
  ```json
  {
    "email": "user@example.com",
    "password": "secret123"
  }
  ```
  Success 200:
  ```json
  {
    "success": true,
    "message": "OK",
    "data": {
      "access_token": "...",
      "token_type": "bearer",
      "expires_in": 3600
    }
  }
  ```

- **GET `/auth`** (me, bearer required)  
  Success 200:
  ```json
  {
    "success": true,
    "message": "OK",
    "data": {
      "id": 1,
      "name": "User",
      "email": "user@example.com",
      "is_active": true,
      "role": "ADMIN",
      "store": {"id": 3, "name": "Main Store", "level": "PUSAT"},
      "last_login_at": "2024-01-01T00:00:00Z"
    }
  }
  ```

- **POST `/auth/logout`** (bearer required)  
  Success 200:
  ```json
  {
    "message": "Successfully logged out"
  }
  ```

- **POST `/auth/auth/refresh`** (bearer required)  
  Success 200:
  ```json
  {
    "success": true,
    "message": "OK",
    "data": {
      "access_token": "...",
      "token_type": "bearer",
      "expires_in": 3600
    }
  }
  ```

- **POST `/auth/update-profile`** (bearer required)  
  Body (any combination):
  ```json
  {
    "name": "New Name",
    "email": "new@example.com",
    "password": "newpass",
    "password_confirmation": "newpass"
  }
  ```
  Success 200: same user payload as `/auth`.

### Super Admin (`/super-admin`, role SUPER_ADMIN, bearer required)
- **GET `/stores`**  
  Query (optional): `per_page`, `q`, `level`, `parent_store_id`  
  Success 200:
  ```json
  {
    "success": true,
    "message": "OK",
    "data": [],
    "meta": {
      "pagination": {}
    }
  }
  ```

- **POST `/stores`**  
  Body:
  ```json
  {
    "code": "STR-001",
    "name": "Central",
    "level": "PUSAT|CABANG|RETAIL",
    "parent_store_id": null,
    "address": "Jl. Example",
    "is_active": true
  }
  ```
  Success 201:
  ```json
  {
    "success": true,
    "message": "Store created",
    "data": {
      "id": 10,
      "code": "...",
      "name": "...",
      "level": "...",
      "parent_store_id": null,
      "address": "...",
      "is_active": true
    }
  }
  ```

- **GET `/stores/{id}`**  
  Success 200:
  ```json
  {
    "success": true,
    "message": "Store detail",
    "data": {}
  }
  ```

- **PUT/PATCH `/stores/{id}`**  
  Success 200:
  ```json
  {
    "success": true,
    "message": "Store updated",
    "data": {}
  }
  ```

- **DELETE `/stores/{id}`**  
  Success 204:
  ```json
  {
    "success": true,
    "message": "Store soft deleted",
    "data": null
  }
  ```

- **GET `/users`**  
  Query (optional): `per_page`, `q`, `role`, `store_id`  
  Success 200: pagination envelope with users.

- **POST `/users`**  
  Body:
  ```json
  {
    "name": "Admin",
    "email": "admin@example.com",
    "password": "secret123",
    "store_id": 3,
    "role": "ADMIN|CASHIER",
    "is_active": true
  }
  ```
  Success 201:
  ```json
  {
    "success": true,
    "message": "User created",
    "data": {
      "id": 20,
      "name": "...",
      "email": "...",
      "is_active": true,
      "role": {"id": 2, "name": "ADMIN"},
      "store": {"id": 3, "name": "Central", "level": "PUSAT"}
    }
  }
  ```

- **GET `/users/{id}`**  
- **PUT/PATCH `/users/{id}`**  
- **DELETE `/users/{id}`**  
  Success 200/204 envelopes match other resources.

### Admin (`/admin`, role ADMIN, bearer required, `store.scope` enforced)
- **GET `/cashiers`**  
  Pagination envelope, only cashiers in scoped store.

- **POST `/cashiers`**  
  Body:
  ```json
  {
    "name": "Cashier A",
    "email": "cashier@example.com",
    "password": "secret123",
    "is_active": true
  }
  ```
  Success 201:
  ```json
  {
    "success": true,
    "message": "User created",
    "data": {
      "id": 30,
      "name": "...",
      "email": "...",
      "is_active": true,
      "role": {"name": "CASHIER"},
      "store": {"id": 1, "name": "...", "level": "..."}
    }
  }
  ```

- **GET `/cashiers/{id}`**, **PUT/PATCH `/cashiers/{id}`**, **DELETE `/cashiers/{id}`**

- **GET `/products`**  
  Query (optional): `per_page`, `q`, `is_active`  
  Success 200: pagination envelope.

- **POST `/products`**  
  Body:
  ```json
  {
    "sku": "SKU-001",
    "name": "Product",
    "description": "Optional",
    "price": 10000,
    "is_active": true
  }
  ```
  Success 201:
  ```json
  {
    "success": true,
    "message": "Product created",
    "data": {
      "id": 40,
      "store_id": 1,
      "sku": "...",
      "name": "...",
      "description": "...",
      "price": 10000,
      "is_active": true
    }
  }
  ```

- **GET `/products/{id}`**, **PUT/PATCH `/products/{id}`**, **DELETE `/products/{id}`**

- **GET `/sales`**  
  Query (optional): `per_page`, `q` (invoice), `status`, `from`, `to`  
  Success 200: pagination envelope with `cashier` relation.

- **GET `/sales/{id}`**  
  Success 200:
  ```json
  {
    "success": true,
    "message": "Sale detail",
    "data": {
      "id": 1,
      "invoice_no": "...",
      "cashier": {"id": 1, "name": "...", "email": "..."},
      "items": [],
      "subtotal": 0,
      "total": 0
    }
  }
  ```

### Cashier (`/cashier`, role CASHIER or ADMIN, bearer required, `store.scope` enforced)
- **GET `/products`**  
- **GET `/products/{id}`**  
  Success: pagination/detail envelopes identical to admin products.

- **POST `/sales`**  
  Query (super admin only): `store_id=<int>`  
  Body:  
  ```json
  {
    "items": [
      {"product_id": 1, "quantity": 2},
      {"product_id": 5, "quantity": 1}
    ],
    "payment_method": "CASH",
    "paid_amount": 150000,
    "discount": 5000,
    "tax": 1000
  }
  ```  
  Success 201:  
  ```json
  {
    "success": true,
    "message": "Sale created",
    "data": {
      "id": 50,
      "store_id": <scoped_or_query>,
      "invoice_no": "INV-<...>",
      "status": "PAID",
      "subtotal": 0,
      "discount": 0,
      "tax": 0,
      "total": 0,
      "paid_amount": 0,
      "change_amount": 0,
      "payment_method": "CASH",
      "paid_at": "2024-01-01T00:00:00Z",
      "cashier": {"id": 1, "name": "Cashier", "email": "cashier@example.com"},
      "items": [
        {"id": 1, "sale_id": 50, "product_id": 1, "product_name_snapshot": "Product", "unit_price": 10000, "quantity": 2, "line_total": 20000}
      ]
    }
  }
  ```

- **GET `/sales`**  
  Query (optional): `per_page`, `q`, `status`, `from`, `to`  
  Success 200: pagination envelope.

- **GET `/sales/{id}`**  
  Success 200: sale detail envelope.

### Public
- **GET `/`**  
  Success 200:
  ```json
  {
    "message": "Welcome to the API"
  }
  ```
