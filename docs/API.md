# AttendX API Reference

AttendX exposes a JWT-authenticated REST API for mobile apps and third-party integrations, alongside the session-authenticated web UI.

Base URL: `https://your-domain/api`

## Authentication

### Login

```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@attendx.com",
  "password": "Admin@1234"
}
```

**Response 200:**
```json
{
  "success": true,
  "access_token": "eyJ0eXAiOiJKV1Qi...",
  "refresh_token": "a1b2c3d4...",
  "expires_in": 86400,
  "user": { "id": 1, "name": "System Administrator", "email": "admin@attendx.com", "role": "Super Admin" }
}
```

Include the access token on all subsequent requests:
```
Authorization: Bearer <access_token>
```

### Refresh Token

```
POST /api/auth/refresh
Content-Type: application/json

{ "refresh_token": "a1b2c3d4..." }
```

Returns a new `access_token` / `refresh_token` pair. Refresh tokens are valid for 7 days (`JWT_REFRESH_TTL` in `.env`) and are revoked on use (rotation).

### Logout

```
POST /api/auth/logout
Authorization: Bearer <access_token>
```

Revokes the associated refresh token.

## Mobile Endpoints

These endpoints back the mobile app experience and use the same JWT bearer auth.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/mobile/profile` | Current user + linked employee profile |
| GET | `/mobile/attendance` | Own attendance history (paginated) |
| GET | `/mobile/attendance/today` | Today's punch status |
| POST | `/mobile/leave/apply` | Submit a leave application |
| GET | `/mobile/leave` | Own leave applications |
| GET | `/mobile/notifications` | Unread count + recent notifications |
| POST | `/mobile/push-token` | Register FCM device token for push notifications |
| GET | `/mobile/payslip/{id}` | View own payslip detail |

## Core Resource Endpoints

All resource endpoints below require `Authorization: Bearer <token>` and respect the same role/permission rules as the web UI (`Auth::can()`).

### Employees

```
GET    /api/employees                List (filters: status, department_id, search)
GET    /api/employees/{id}           Detail
POST   /api/employees                Create
PUT    /api/employees/{id}           Update
```

### Attendance

```
GET    /api/attendance               List (filters: date_from, date_to, employee_id, department_id, status)
GET    /api/attendance/{employee_id} Employee attendance summary + records
POST   /api/attendance/manual        Insert a manual punch (employee_id, punch_time, punch_type)
```

### Leave

```
GET    /api/leave                    List applications
POST   /api/leave                    Apply for leave
POST   /api/leave/{id}/approve       Approve (requires leave.approve permission)
POST   /api/leave/{id}/reject        Reject (requires leave.approve permission, remarks required)
```

### Devices

```
GET    /api/devices                  List all registered devices + status
POST   /api/devices/{id}/sync        Queue a manual SDK sync
```

### Payroll

```
GET    /api/payroll/periods          List payroll periods
GET    /api/payroll/slips/{id}       Payslip detail
```

## Device Webhook (ADMS)

Granding F09/ID devices push attendance data directly to the **ADMS listener service** (Python, not the PHP API) on the configured ADMS port (default `8080`/`8081`). This is a separate protocol (tab-delimited text over HTTP POST), not part of this JSON REST API. See `python/adms_listener.py` for the implementation.

## Error Format

All errors follow a consistent shape:

```json
{
  "error": "Human-readable message",
  "code": 422
}
```

| HTTP Code | Meaning |
|---|---|
| 401 | Missing/invalid/expired access token |
| 403 | Authenticated but lacking required permission |
| 404 | Resource not found |
| 419 | CSRF token mismatch (web/session routes only) |
| 422 | Validation failure |
| 429 | Rate limited |
| 500 | Server error |

## Rate Limiting

Login attempts are rate-limited per email address: 5 failed attempts trigger a lockout (default 30 minutes), configurable via **System → Settings → Security**.

## Pagination

List endpoints accept `page` and return:

```json
{
  "data": [ ... ],
  "total": 142,
  "page": 1,
  "per_page": 20
}
```

## CSV Export

Report endpoints under `/reports/*/export` (web/session-authenticated, not JWT) stream `text/csv` directly rather than returning JSON — these are designed for browser-triggered downloads, not programmatic API consumption.
