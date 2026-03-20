# Admin API Documentation

This document covers admin-facing endpoints from `routes/api.php`.

## Base URL

- Local: `http://localhost:8000/api`

## Authentication

- Auth type: Laravel Sanctum bearer token
- Header: `Authorization: Bearer <admin_token>`
- Guard/role middleware for protected admin routes:
  - `auth:sanctum`
  - `role:admin|Admin,sanctum`

## Public Authentication Endpoint

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/admin/login` | Admin login |

## Protected Admin Endpoints

### Common Endpoint (Available after login)

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/forex-rates` | Get latest forex rates |

### User Creation

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/admin/users` | Create a new user |

### Clients

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/clients` | List clients |
| GET | `/admin/clients/{client}` | Get single client |
| POST | `/admin/clients` | Create client |
| PUT | `/admin/clients/{client}` | Update client |
| DELETE | `/admin/clients/{client}` | Delete client |

### Ad Account Requests

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/ad-account-requests` | List ad account requests |
| PUT | `/admin/ad-account-requests/{id}` | Update request status |

Ad account request fields:
- Records now include `req_name`, `type`, `api`, and `master_id`
- `type` is `master` or `child`
- Child rows store the parent row id in `master_id`
- Admin updates may include `req_name` and `api` (`enable|disable`)

### Wallet Topups

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/wallet-topups` | List wallet topup requests |
| PUT | `/admin/wallet-topups/{id}` | Update topup status |

### Top Requests

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/top-requests` | List top requests |
| PUT | `/admin/top-requests/{id}` | Update top request |
| DELETE | `/admin/top-requests/{id}` | Delete top request |

### Account Management

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/account-management` | List account-management records |
| POST | `/admin/account-management` | Create account-management record |

### User Management

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/users` | List users |
| GET | `/users/{id}` | Get user details |
| PUT | `/users/{id}` | Update user |
| DELETE | `/users/{id}` | Delete user |

### Dashboard

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin` | Admin dashboard summary |

### Notifications

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/notifications/unread-count` | Unread notification count |
| GET | `/admin/notifications/unread` | Unread notifications |
| GET | `/admin/notifications/all` | All notifications |
| PUT | `/admin/notifications/{id}/read` | Mark one notification as read |
| PUT | `/admin/notifications/read-all` | Mark all notifications as read |

### Export

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/admin/transactions/export` | Export transactions listing (wallet topup, account topup, exchange) as CSV or Excel (`format=csv|excel`) |
| GET | `/admin/export-topup` | Legacy alias for `/admin/transactions/export` (CSV/Excel) |

## Logout

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/admin/logout` | Logout admin |
