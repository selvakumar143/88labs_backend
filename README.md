<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


https://sel:_NsLeKRt84PkAPSmJ1w1QJAw9ykQs0P2p9gxr@github.com/selvakumar143/88labs_backend.git

## Create User API

### Endpoint

- `POST /api/admin/users`

### Authentication

- Required: `Bearer` token from an authenticated `admin` user (Sanctum token).
- Headers:
  - `Authorization: Bearer <admin_token>`
  - `Accept: application/json`
  - `Content-Type: application/json`

### Request Body

```json
{
  "role": "admin",
  "user": {
    "name": "Jane Admin",
    "email": "jane.admin@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
  }
}
```

### Field Rules

- `role`: required, must be one of `admin`, `customer`
- `user`: required object
- `user.name`: required, string, max 255
- `user.email`: required, valid email, max 255, unique in `users` table
- `user.password`: required, min 6
- `user.password_confirmation`: required, must match `user.password`

### Success Response (201)

```json
{
  "status": "success",
  "message": "User created successfully",
  "data": {
    "user": {
      "id": 10,
      "name": "Jane Admin",
      "email": "jane.admin@example.com",
      "email_verified_at": "2026-02-21T00:00:00.000000Z",
      "created_at": "2026-02-21T00:00:00.000000Z",
      "updated_at": "2026-02-21T00:00:00.000000Z"
    },
    "role": [
      "admin"
    ]
  }
}
```

### Validation Error Response (422)

```json
{
  "message": "The role field must be one of admin, customer.",
  "errors": {
    "role": [
      "The selected role is invalid."
    ]
  }
}
```

### Unauthorized/Forbidden

- `401 Unauthorized`: token missing or invalid
- `403 Forbidden`: authenticated user is not `admin`

### cURL Example

```bash
curl --request POST 'http://localhost:8000/api/admin/users' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "role":"customer",
    "user":{
      "name":"John Customer",
      "email":"john.customer@example.com",
      "password":"secret123",
      "password_confirmation":"secret123"
    }
  }'
```

## Admin Clients API

All endpoints below require an admin bearer token:

- `Authorization: Bearer <admin_token>`
- `Accept: application/json`
- `Content-Type: application/json`

Note: `id` is now auto-incremented by DB. You do not send `id` in create payload.

### 1) Create Client

`POST /api/admin/clients`

```bash
curl --request POST 'http://localhost:8000/api/admin/clients' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "clientCode":"CL-1001",
    "clientName":"Acme Fashion LLC",
    "email":"acme.client@example.com",
    "password":"Client@123",
    "country":"United Arab Emirates",
    "phone":"+971501112233",
    "clientType":"Agency",
    "niche":"Fashion",
    "marketCountry":"UAE",
    "settlementMode":"Bank Transfer",
    "statementCycle":"Monthly",
    "settlementCurrency":"AED",
    "cooperationStart":"2026-02-21",
    "serviceFeePercent":"12.50",
    "serviceFeeEffectiveTime":"2026-02-21 10:00:00"
  }'
```

### 2) List Clients

`GET /api/admin/clients`

Optional query params:
- `search`: filter by `clientName` (partial match)
- `status`: `all` | `active` | `inactive` (also supports `enabled`/`disabled`)
- `per_page`: items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/admin/clients' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

```bash
curl --request GET 'http://localhost:8000/api/admin/clients?search=Acme&status=active&per_page=20' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 3) Get Single Client

`GET /api/admin/clients/1`

```bash
curl --request GET 'http://localhost:8000/api/admin/clients/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 4) Update Client

`PUT /api/admin/clients/1`

```bash
curl --request PUT 'http://localhost:8000/api/admin/clients/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "clientCode":"CL-1001",
    "clientName":"Acme Fashion Group",
    "email":"acme.client@example.com",
    "country":"United Arab Emirates",
    "phone":"+971509998877",
    "clientType":"Agency",
    "niche":"Fashion & Beauty",
    "marketCountry":"UAE",
    "settlementMode":"Bank Transfer",
    "statementCycle":"Monthly",
    "settlementCurrency":"AED",
    "cooperationStart":"2026-02-21",
    "serviceFeePercent":"10.00",
    "serviceFeeEffectiveTime":"2026-03-01 00:00:00"
  }'
```

### 5) Delete Client

`DELETE /api/admin/clients/1`

```bash
curl --request DELETE 'http://localhost:8000/api/admin/clients/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

## Ad Account Request APIs

### 1) Client: Create Ad Account Request

`POST /api/ad-account-request`

```bash
curl --request POST 'http://localhost:8000/api/ad-account-request' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "business_name":"Acme Fashion Group",
    "platform":"Facebook",
    "timezone":"Asia/Dubai",
    "country":"United Arab Emirates",
    "currency":"AED",
    "business_manager_id":"BM-99887766",
    "website_url":"https://acmefashion.example.com",
    "account_type":"Business",
    "personal_profile":"https://facebook.com/john.customer",
    "number_of_accounts":2,
    "notes":"Need ad accounts for fashion and beauty campaigns."
  }'
```

### 2) Client: List Own Ad Account Requests

`GET /api/client/ad-account-requests`

Backward-compatible endpoint (same response):
- `GET /api/my-ad-account-requests`

Optional query params:
- `status` = `pending|approved|rejected|all`
- `search` = request id / business name / platform / business manager id / website url
- `per_page` = items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/client/ad-account-requests' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json'
```

Filter by status + search + pagination:

```bash
curl --request GET 'http://localhost:8000/api/client/ad-account-requests?status=pending&search=Acme&per_page=20' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json'
```

### 3) Admin: List All Client Ad Account Requests

`GET /api/admin/ad-account-requests`

Optional query params:
- `status` (`pending|approved|rejected|all`)
- `client_id` (specific client user id, or `all`)
- `search` (request id or client name)
- `per_page` (items per page, default `10`)

```bash
curl --request GET 'http://localhost:8000/api/admin/ad-account-requests?status=pending&client_id=5&search=REQ-&per_page=20' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 4) Admin: Update Ad Account Request Status

`PUT /api/admin/ad-account-requests/1`

Allowed values:
- `approved`
- `rejected`

Approve:

```bash
curl --request PUT 'http://localhost:8000/api/admin/ad-account-requests/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "status":"approved"
  }'
```

Reject:

```bash
curl --request PUT 'http://localhost:8000/api/admin/ad-account-requests/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "status":"rejected"
  }'
```

## Wallet Topup APIs

Note: `total_amount` in responses is derived from transaction `amount`.
Note: Wallet balance endpoints (`/api/client/wallet-summary` and `/api/client/dashboard/wallet`) now return net balance per currency:
- `approved wallet topups` minus `approved ad top requests` for the same client and currency.

### 1) Client: Create Wallet Topup Request

`POST /api/wallet-topup`

```bash
curl --request POST 'http://localhost:8000/api/wallet-topup' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "amount":"250.75",
    "transaction_hash":"0x9f2b6c6f9ab114df71c2036c1c42f58dd2a8f8ac9717002fb6cb9cd4f31a7e90"
  }'
```

### 2) Client: List Own Wallet Topup Requests

`GET /api/my-wallet-topups`

Optional query params:
- `status` = `pending|approved|rejected|all`
- `per_page` = items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/my-wallet-topups' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json'
```

Filter by my status:

```bash
curl --request GET 'http://localhost:8000/api/my-wallet-topups?status=pending&per_page=20' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json'
```

### 3) Admin: List All Wallet Topup Requests

`GET /api/admin/wallet-topups`

Optional query params:
- `status` = `pending|approved|rejected|all`
- `client_id` = specific client user id (or `all`)
- `search` = request id / transaction hash / amount / client name / client email
- `per_page` = items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/admin/wallet-topups' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

Filter by status and specific client:

```bash
curl --request GET 'http://localhost:8000/api/admin/wallet-topups?status=pending&client_id=5&search=TOP-&per_page=20' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

All clients (explicit):

```bash
curl --request GET 'http://localhost:8000/api/admin/wallet-topups?status=all&client_id=all' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 4) Admin: Update Wallet Topup Status

`PUT /api/admin/wallet-topups/1`

Allowed values:
- `approved`
- `rejected`

Approve:

```bash
curl --request PUT 'http://localhost:8000/api/admin/wallet-topups/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "status":"approved"
  }'
```

Reject:

```bash
curl --request PUT 'http://localhost:8000/api/admin/wallet-topups/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "status":"rejected"
  }'
```

## Top Request APIs

### 1) Client: Create Top Request

`POST /api/top-requests`

Required fields:
- `ad_account_request_id` (must belong to logged-in customer)
- `amount` (numeric, min `0.01`)
- `currency` (string, max `10`)

`status` is auto-set to `pending`.

```bash
curl --request POST 'http://localhost:8000/api/top-requests' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "ad_account_request_id": 1,
    "amount":"500.00",
    "currency":"USD"
  }'
```

### 2) Client: List Own Top Requests

`GET /api/my-top-requests`

Optional query params:
- `status` = `pending|approved|all`
- `ad_account_request_id` = specific ad account request id (or `all`)
- `per_page` = items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/my-top-requests' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json'
```

Filter by status:

```bash
curl --request GET 'http://localhost:8000/api/my-top-requests?status=pending&ad_account_request_id=1&per_page=20' \
  --header 'Authorization: Bearer <customer_token>' \
  --header 'Accept: application/json'
```

### 3) Admin: List All Top Requests

`GET /api/admin/top-requests`

Optional query params:
- `status` = `pending|approved|all`
- `client_id` = specific client user id (or `all`)
- `ad_account_request_id` = specific ad account request id (or `all`)
- `search` = amount / currency / client name / client email / ad account request id / business name / platform
- `per_page` = items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/admin/top-requests?status=pending&client_id=5&ad_account_request_id=1&search=USD&per_page=20' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 4) Admin: Update Top Request Status

`PUT /api/admin/top-requests/1`

Allowed values:
- `pending`
- `approved`

```bash
curl --request PUT 'http://localhost:8000/api/admin/top-requests/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "status":"approved"
  }'
```

### 5) Admin: Delete Top Request

`DELETE /api/admin/top-requests/1`

```bash
curl --request DELETE 'http://localhost:8000/api/admin/top-requests/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

## Business Manager API

All endpoints below require an admin bearer token:

- `Authorization: Bearer <admin_token>`
- `Accept: application/json`
- `Content-Type: application/json`

### 1) Create Business Manager

`POST /api/admin/business-managers`

Required fields:
- `name` (string, max `255`)
- `mail` (valid email, max `255`, unique)
- `contact` (string, max `50`)

Optional fields:
- `status` (`active|inactive`, default: `active`)

```bash
curl --request POST 'http://localhost:8000/api/admin/business-managers' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name":"Acme BM",
    "mail":"bm@acme.com",
    "contact":"+1-555-1234",
    "status":"active"
  }'
```

### 2) List Business Managers

`GET /api/admin/business-managers`

Optional query params:
- `search` = name / mail / contact (partial match)
- `status` = `active|inactive|all`
- `per_page` = items per page (default `10`)

```bash
curl --request GET 'http://localhost:8000/api/admin/business-managers' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

Filter example:

```bash
curl --request GET 'http://localhost:8000/api/admin/business-managers?search=acme&status=active&per_page=20' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 3) Get Single Business Manager

`GET /api/admin/business-managers/1`

```bash
curl --request GET 'http://localhost:8000/api/admin/business-managers/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### 4) Update Business Manager

`PUT /api/admin/business-managers/1`

Updatable fields:
- `name` (string, max `255`)
- `mail` (valid email, max `255`, unique except current row)
- `contact` (string, max `50`)
- `status` (`active|inactive`)

```bash
curl --request PUT 'http://localhost:8000/api/admin/business-managers/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "name":"Acme BM Updated",
    "mail":"bm.updated@acme.com",
    "contact":"+1-555-9999",
    "status":"inactive"
  }'
```

### 5) Delete Business Manager

`DELETE /api/admin/business-managers/1`

```bash
curl --request DELETE 'http://localhost:8000/api/admin/business-managers/1' \
  --header 'Authorization: Bearer <admin_token>' \
  --header 'Accept: application/json'
```

### Common Errors

- `401 Unauthorized` (token missing/invalid)
- `403 Forbidden` (authenticated user is not admin)
- `404 Not Found` (resource id does not exist)
- `422 Unprocessable Entity` (validation errors)

## Run Laravel In Background (Keep Running After Terminal Close)

Start server in background and save PID:

```bash
cd /var/www/88laps
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/artisan-serve.log 2>&1 < /dev/null &
echo $! > storage/artisan-serve.pid
```

Check running process:

```bash
cat storage/artisan-serve.pid
ps -fp "$(cat storage/artisan-serve.pid)"
```

View live logs:

```bash
tail -f storage/logs/artisan-serve.log
```

Stop server:

```bash
kill "$(cat storage/artisan-serve.pid)"
rm -f storage/artisan-serve.pid
```

Restart server:

```bash
kill "$(cat storage/artisan-serve.pid)" 2>/dev/null || true
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/artisan-serve.log 2>&1 < /dev/null &
echo $! > storage/artisan-serve.pid
```
