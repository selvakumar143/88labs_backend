# Client API Documentation

This document covers customer/client-facing endpoints from `routes/api.php`.

## Base URL

- Local: `http://localhost:8000/api`

## Authentication

- Auth type: Laravel Sanctum bearer token
- Header: `Authorization: Bearer <customer_token>`
- Guard/role middleware for protected client routes:
  - `auth:sanctum`
  - `role:customer|Customer,sanctum`

## Public Authentication Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/customer/register` | Register a customer account |
| POST | `/customer/login` | Customer login |

## Protected Client Endpoints

### Common Endpoint (Available after login)

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/forex-rates` | Get latest forex rates |

### Ad Account Requests

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/ad-account-request` | Create ad account request |
| GET | `/client/ad-account-requests` | List ad account requests |
| GET | `/my-ad-account-requests` | List current customer requests |

### Wallet

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/wallet-topup` | Submit wallet topup request |
| GET | `/my-wallet-topups` | List current customer topups |
| GET | `/client/wallet-summary` | Wallet summary |

### Top Requests

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/top-requests` | Create top request |
| GET | `/my-top-requests` | List current customer top requests |

### Transactions

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/client/transactions` | List wallet topup, account topup, and exchange transactions |
| GET | `/client/transactions/export` | Export transactions listing as CSV or Excel (`format=csv|excel`) |

### Dashboard

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/client/dashboard` | Dashboard overview |
| GET | `/client/dashboard/wallet` | Dashboard wallet details |
| GET | `/client/dashboard/active-accounts-total` | Active ad account count |

### Notifications

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/client/notifications/unread-count` | Unread notification count |
| GET | `/client/notifications/unread` | Unread notifications |
| GET | `/client/notifications/all` | All notifications |
| PUT | `/client/notifications/{id}/read` | Mark one notification as read |
| PUT | `/client/notifications/read-all` | Mark all notifications as read |

### Services

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/services/get` | Fetch services |
| POST | `/services/update` | Update service |

## Logout

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/customer/logout` | Logout customer |
