# API Endpoint Documentation

Dokumen ini merangkum endpoint yang saat ini terdaftar di aplikasi Laravel pada prefix `/api`.

## Base URL

Gunakan base URL sesuai environment Anda, misalnya:

```text
http://localhost/api
```

## Header Umum

### 1. Public endpoint

Untuk endpoint public seperti health check, cukup gunakan:

```http
Content-Type: application/json
Accept: application/json
```

### 2. Endpoint auth

Semua endpoint di grup `/auth/*` wajib membawa header aplikasi berikut:

```http
Content-Type: application/json
Accept: application/json
X-API-Key: your_api_key
X-App-License: your_app_license
```

Untuk endpoint auth yang butuh login (`/auth/me`, `/auth/logout`, `/auth/sessions`), tambahkan juga:

```http
Authorization: Bearer your_access_token
```

### 3. Endpoint protected non-auth

Untuk endpoint `/apps`, `/logs`, `/menus`, `/permissions`, dan `/roles`, gunakan:

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer your_access_token
```

## Ringkasan Endpoint

| Method | Endpoint                         | Auth                                        | Keterangan                                                |
| ------ | -------------------------------- | ------------------------------------------- | --------------------------------------------------------- |
| GET    | `/health`                        | Public                                      | Cek status service                                        |
| POST   | `/auth/register`                 | X-API-Key + X-App-License                   | Registrasi user baru                                      |
| POST   | `/auth/login`                    | X-API-Key + X-App-License                   | Login user                                                |
| POST   | `/auth/refresh`                  | X-API-Key + X-App-License                   | Refresh access token                                      |
| GET    | `/auth/me`                       | App headers + Bearer token                  | Ambil profil user login                                   |
| POST   | `/auth/logout`                   | App headers + Bearer token                  | Logout session aktif                                      |
| GET    | `/auth/sessions`                 | App headers + Bearer token                  | Daftar session aktif user                                 |
| GET    | `/apps`                          | Bearer token                                | Daftar aplikasi                                           |
| GET    | `/apps/{id}`                     | Bearer token                                | Detail aplikasi                                           |
| GET    | `/logs/auth`                     | Bearer token (admin/superadmin)             | List activity log modul auth dengan filter dan pagination |
| GET    | `/logs/auth/summary`             | Bearer token (admin/superadmin)             | Ringkasan statistik activity log auth                     |
| POST   | `/menus`                         | Bearer token                                | Buat menu baru                                            |
| GET    | `/menus`                         | Bearer token                                | Daftar menu aktif                                         |
| PUT    | `/menus/{menu}`                  | Bearer token                                | Update menu                                               |
| DELETE | `/menus/{menu}`                  | Bearer token                                | Hapus menu                                                |
| GET    | `/permissions`                   | Bearer token + permission:view_permission   | List permission                                           |
| GET    | `/permissions/all`               | Bearer token                                | Semua permission tanpa pagination                         |
| GET    | `/permissions/groups`            | Bearer token + permission:view_permission   | Daftar group permission                                   |
| GET    | `/permissions/grouped`           | Bearer token + permission:view_permission   | Permission per group                                      |
| GET    | `/permissions/summary`           | Bearer token                                | Ringkasan permission                                      |
| POST   | `/permissions`                   | Bearer token + permission:create_permission | Tambah permission                                         |
| POST   | `/permissions/bulk-delete`       | Bearer token + permission:delete_permission | Hapus banyak permission                                   |
| POST   | `/permissions/assign-to-role`    | Bearer token                                | Assign permission ke role                                 |
| GET    | `/permissions/role/{roleId}`     | Bearer token                                | Permission per role                                       |
| GET    | `/permissions/{id}`              | Bearer token + permission:view_permission   | Detail permission                                         |
| PUT    | `/permissions/{id}`              | Bearer token + permission:edit_permission   | Update permission                                         |
| DELETE | `/permissions/{id}`              | Bearer token + permission:delete_permission | Hapus permission                                          |
| GET    | `/roles`                         | Bearer token                                | List role                                                 |
| GET    | `/roles/all`                     | Bearer token                                | Semua role tanpa pagination                               |
| GET    | `/roles/summary`                 | Bearer token                                | Ringkasan role                                            |
| GET    | `/roles/user/{userId}`           | Bearer token                                | Role milik user tertentu                                  |
| POST   | `/roles`                         | Bearer token                                | Tambah role                                               |
| GET    | `/roles/{id}`                    | Bearer token                                | Detail role                                               |
| PUT    | `/roles/{id}`                    | Bearer token                                | Update role                                               |
| DELETE | `/roles/{id}`                    | Bearer token                                | Hapus role                                                |
| POST   | `/roles/bulk-delete`             | Bearer token                                | Hapus banyak role                                         |
| POST   | `/roles/{id}/assign-permissions` | Bearer token                                | Attach, detach, atau sync permission ke role              |
| GET    | `/roles/{id}/permissions`        | Bearer token                                | Lihat permission pada role                                |

---

## 1. Health Check

### GET `/health`

Response sukses:

```json
{
    "status": "ok",
    "timestamp": "2026-05-22T10:00:00.000000Z",
    "service": "Auth API"
}
```

---

## 2. Authentication

### POST `/auth/register`

Registrasi user baru dan langsung mendapatkan token.

Request body:

```json
{
    "username": "denis",
    "email": "denis@example.com",
    "full_name": "Denis Pratama",
    "nik": "3276xxxx",
    "phone": "08123456789",
    "password": "Password123",
    "password_confirmation": "Password123",
    "device_name": "Postman",
    "app_id": 1
}
```

Field validasi:

| Field         | Wajib | Tipe    | Catatan                                                               |
| ------------- | ----- | ------- | --------------------------------------------------------------------- |
| `username`    | Ya    | string  | Maksimal 100 karakter, unik                                           |
| `email`       | Ya    | string  | Format email, unik                                                    |
| `full_name`   | Ya    | string  | Maksimal 150 karakter                                                 |
| `nik`         | Tidak | string  | Maksimal 50 karakter, unik                                            |
| `phone`       | Tidak | string  | Maksimal 30 karakter                                                  |
| `password`    | Ya    | string  | Minimal 8 karakter, huruf besar, huruf kecil, angka, wajib konfirmasi |
| `device_name` | Tidak | string  | Maksimal 100 karakter                                                 |
| `app_id`      | Tidak | integer | Bisa juga diisi dari header `X-App-Id`                                |

Response sukses `201 Created`:

```json
{
    "message": "User registered successfully.",
    "user": {},
    "apps": [],
    "active_app": {},
    "app_info": {
        "code": "APP001",
        "name": "My Application"
    },
    "token_type": "Bearer",
    "access_token": "jwt_token",
    "expires_in": 900,
    "refresh_token": "refresh_token",
    "refresh_token_expires_at": "2026-06-21T10:00:00Z",
    "session_id": "uuid"
}
```

### POST `/auth/login`

Login menggunakan `email` atau `username` pada field `login`.

Request body:

```json
{
    "login": "denis@example.com",
    "password": "Password123",
    "device_name": "Postman",
    "app_id": 1
}
```

Field validasi:

| Field         | Wajib | Tipe    | Catatan                          |
| ------------- | ----- | ------- | -------------------------------- |
| `login`       | Ya    | string  | Bisa email atau username         |
| `password`    | Ya    | string  | Password akun                    |
| `device_name` | Tidak | string  | Maksimal 100 karakter            |
| `app_id`      | Tidak | integer | Bisa juga dari header `X-App-Id` |

Response sukses `200 OK`:

```json
{
    "message": "Login successful.",
    "user": {},
    "token_type": "Bearer",
    "access_token": "jwt_token",
    "expires_in": 900,
    "refresh_token": "refresh_token",
    "refresh_token_expires_at": "2026-06-21T10:00:00Z",
    "session_id": "uuid"
}
```

Kemungkinan error penting:

| HTTP Code | Kondisi                                               |
| --------- | ----------------------------------------------------- |
| `400`     | Header `X-API-Key` atau `X-App-License` tidak dikirim |
| `401`     | Credential aplikasi tidak valid / tidak aktif         |
| `403`     | User tidak punya akses ke aplikasi tersebut           |
| `422`     | Login/password salah                                  |

### POST `/auth/refresh`

Request body:

```json
{
    "refresh_token": "your_refresh_token",
    "device_name": "Postman"
}
```

Field validasi:

| Field           | Wajib | Tipe   | Catatan               |
| --------------- | ----- | ------ | --------------------- |
| `refresh_token` | Ya    | string | Refresh token aktif   |
| `device_name`   | Tidak | string | Maksimal 100 karakter |

Response sukses `200 OK`:

```json
{
    "message": "Token refreshed successfully.",
    "token_type": "Bearer",
    "access_token": "new_jwt_token",
    "expires_in": 900,
    "refresh_token": "new_refresh_token",
    "refresh_token_expires_at": "2026-06-21T10:00:00Z",
    "session_id": "uuid"
}
```

### GET `/auth/me`

Mengembalikan data user login, daftar aplikasi yang dimiliki, dan JWT claims.

Response sukses:

```json
{
    "user": {},
    "apps": [],
    "claims": {
        "sub": 1,
        "app_id": 1,
        "role": "viewer",
        "sid": "uuid",
        "type": "access"
    }
}
```

### POST `/auth/logout`

Mencabut session aktif berdasarkan `sid` pada access token.

Response sukses:

```json
{
    "message": "Logout successful."
}
```

### GET `/auth/sessions`

Mengambil session refresh token aktif milik user pada aplikasi aktif.

Response sukses:

```json
{
    "data": [
        {
            "session_id": "uuid",
            "device_name": "Postman",
            "ip_address": "127.0.0.1",
            "user_agent": "PostmanRuntime",
            "last_used_at": null,
            "expires_at": "2026-06-21T10:00:00Z",
            "created_at": "2026-05-22T10:00:00Z"
        }
    ]
}
```

---

## 3. Apps

### GET `/apps`

Daftar aplikasi dengan pagination dan pencarian.

Query params:

| Param      | Wajib | Tipe    | Default | Keterangan                                     |
| ---------- | ----- | ------- | ------- | ---------------------------------------------- |
| `per_page` | Tidak | integer | `10`    | Jumlah data per halaman                        |
| `search`   | Tidak | string  | `""`    | Cari berdasarkan kode, nama, URL, atau koneksi |

Response sukses:

```json
{
    "success": true,
    "data": [],
    "pagination": {
        "currentPage": 1,
        "lastPage": 1,
        "total": 1,
        "perPage": 10
    }
}
```

### GET `/apps/{id}`

Detail aplikasi berdasarkan ID.

Response sukses:

```json
{
    "success": true,
    "data": {}
}
```

Response error penting:

| HTTP Code | Kondisi             |
| --------- | ------------------- |
| `404`     | App tidak ditemukan |

---

## 4. Logs

### GET `/logs/auth`

Mengambil daftar activity log untuk modul `auth`. Endpoint ini hanya bisa diakses role `admin` atau `superadmin`.

Query params:

| Param        | Wajib | Tipe    | Default      | Keterangan                                                           |
| ------------ | ----- | ------- | ------------ | -------------------------------------------------------------------- |
| `search`     | Tidak | string  | -            | Cari berdasarkan action, module, ip, username, email, atau full name |
| `user_id`    | Tidak | integer | -            | Filter berdasarkan user                                              |
| `action`     | Tidak | string  | -            | Filter action spesifik, mis. `auth.login`                            |
| `ip_address` | Tidak | string  | -            | Filter IP address                                                    |
| `date_from`  | Tidak | date    | -            | Ambil log mulai tanggal tertentu                                     |
| `date_to`    | Tidak | date    | -            | Ambil log sampai tanggal tertentu                                    |
| `sort_by`    | Tidak | string  | `created_at` | `created_at`, `action`, `module`, `ip_address`                       |
| `sort_order` | Tidak | string  | `desc`       | `asc` atau `desc`                                                    |
| `per_page`   | Tidak | integer | `15`         | Maksimal 100 data per halaman                                        |

Response sukses:

```json
{
    "success": true,
    "message": "Activity logs retrieved successfully",
    "data": [
        {
            "id": 12,
            "action": "auth.login",
            "module": "auth",
            "ip_address": "127.0.0.1",
            "created_at": "2026-05-22T09:15:00.000000Z",
            "updated_at": "2026-05-22T09:15:00.000000Z",
            "payload": {
                "app_id": 1,
                "client_ip": "127.0.0.1",
                "client_ips": ["127.0.0.1"],
                "user_agent": "PostmanRuntime/7.45.0",
                "request_host": "localhost"
            },
            "user": {
                "id": 3,
                "username": "denis",
                "email": "denis@example.com",
                "full_name": "Denis Pratama"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    },
    "filters": {
        "search": null,
        "user_id": null,
        "action": null,
        "ip_address": null,
        "date_from": null,
        "date_to": null
    }
}
```

### GET `/logs/auth/summary`

Mengambil ringkasan statistik untuk activity log modul `auth`. Endpoint ini hanya bisa diakses role `admin` atau `superadmin`.

Response sukses:

```json
{
    "success": true,
    "message": "Activity log summary retrieved successfully",
    "data": {
        "total_logs": 120,
        "today_logs": 8,
        "last_7_days_logs": 42,
        "unique_users": 6,
        "unique_ip_addresses": 4,
        "actions": [
            {
                "action": "auth.login",
                "total": 60
            },
            {
                "action": "auth.register",
                "total": 10
            }
        ],
        "daily_activity": [
            {
                "activity_date": "2026-05-16",
                "total": 3
            },
            {
                "activity_date": "2026-05-22",
                "total": 8
            }
        ],
        "top_users": [
            {
                "user_id": 3,
                "username": "denis",
                "email": "denis@example.com",
                "full_name": "Denis Pratama",
                "total": 25
            }
        ],
        "latest_log": {
            "id": 12,
            "action": "auth.login",
            "module": "auth",
            "ip_address": "127.0.0.1",
            "created_at": "2026-05-22T09:15:00.000000Z",
            "username": "denis",
            "full_name": "Denis Pratama"
        }
    }
}
```

Response error penting:

| HTTP Code | Kondisi                              |
| --------- | ------------------------------------ |
| `401`     | Token tidak valid atau expired       |
| `403`     | Role bukan `admin` atau `superadmin` |

---

## 5. Menus

### POST `/menus`

Membuat menu baru.

Request body:

```json
{
    "parent_id": null,
    "type": "menu",
    "name": "Dashboard",
    "icon": "home",
    "path": "/dashboard",
    "badge_key": null,
    "step": 1,
    "sort_order": 1,
    "is_active": true
}
```

Field validasi:

| Field        | Wajib | Tipe    | Catatan                   |
| ------------ | ----- | ------- | ------------------------- |
| `parent_id`  | Tidak | integer | Harus ada di tabel `menu` |
| `type`       | Ya    | string  | Maksimal 50 karakter      |
| `name`       | Ya    | string  | Maksimal 150 karakter     |
| `icon`       | Tidak | string  | Maksimal 100 karakter     |
| `path`       | Tidak | string  | Maksimal 255 karakter     |
| `badge_key`  | Tidak | string  | Maksimal 100 karakter     |
| `step`       | Tidak | integer | Urutan step               |
| `sort_order` | Tidak | integer | Urutan menu               |
| `is_active`  | Tidak | boolean | Status menu               |

Response sukses `201 Created`:

```json
{
    "success": true,
    "message": "Menu created successfully",
    "data": {}
}
```

Response error penting:

| HTTP Code | Kondisi                                     |
| --------- | ------------------------------------------- |
| `401`     | Token tidak valid / expired                 |
| `404`     | Parent menu tidak ditemukan                 |
| `409`     | Nama menu sudah ada pada aplikasi yang sama |

### GET `/menus`

Mengambil semua root menu aktif beserta children.

Response sukses:

```json
{
    "success": true,
    "data": []
}
```

### PUT `/menus/{menu}`

Update data menu. Validasi body sama seperti create.

Catatan penting:

Endpoint ini mengecek role user harus `admin` atau `superadmin`.

### DELETE `/menus/{menu}`

Hapus menu jika tidak memiliki child.

Catatan penting:

| HTTP Code | Kondisi                   |
| --------- | ------------------------- |
| `400`     | Menu masih memiliki child |
| `401`     | Tidak punya akses         |
| `404`     | Menu tidak ditemukan      |

---

## 6. Permissions

Catatan otorisasi dari controller:

| Endpoint                                                                                           | Permission          |
| -------------------------------------------------------------------------------------------------- | ------------------- |
| `GET /permissions`, `GET /permissions/{id}`, `GET /permissions/groups`, `GET /permissions/grouped` | `view_permission`   |
| `POST /permissions`                                                                                | `create_permission` |
| `PUT /permissions/{id}`                                                                            | `edit_permission`   |
| `DELETE /permissions/{id}`, `POST /permissions/bulk-delete`                                        | `delete_permission` |

### GET `/permissions`

Query params:

| Param        | Wajib | Tipe    | Default | Keterangan                  |
| ------------ | ----- | ------- | ------- | --------------------------- |
| `group`      | Tidak | string  | -       | Filter group                |
| `search`     | Tidak | string  | -       | Cari nama atau display name |
| `sort_by`    | Tidak | string  | `name`  | Field sorting               |
| `sort_order` | Tidak | string  | `asc`   | `asc` atau `desc`           |
| `per_page`   | Tidak | integer | `15`    | Pagination                  |

Response sukses:

```json
{
    "success": true,
    "message": "Permissions retrieved successfully",
    "data": {}
}
```

### GET `/permissions/all`

Mengambil semua permission tanpa pagination.

Query params:

| Param   | Wajib | Tipe   | Keterangan   |
| ------- | ----- | ------ | ------------ |
| `group` | Tidak | string | Filter group |

### GET `/permissions/groups`

Mengambil daftar group unik permission.

### GET `/permissions/grouped`

Mengambil permission yang sudah dikelompokkan per group.

### GET `/permissions/summary`

Mengambil statistik permission seperti total permission, total group, permission per group, dan permission yang paling banyak dipakai.

### POST `/permissions`

Request body:

```json
{
    "name": "create_user",
    "display_name": "Create User",
    "group": "user"
}
```

Field validasi:

| Field          | Wajib | Tipe   | Catatan                               |
| -------------- | ----- | ------ | ------------------------------------- |
| `name`         | Ya    | string | Unik, huruf kecil dan underscore saja |
| `display_name` | Ya    | string | Maksimal 150 karakter                 |
| `group`        | Tidak | string | Maksimal 100 karakter                 |

### POST `/permissions/bulk-delete`

Request body:

```json
{
    "ids": [1, 2, 3]
}
```

### POST `/permissions/assign-to-role`

Request body:

```json
{
    "role_id": 1,
    "permission_ids": [1, 2, 3]
}
```

Field validasi:

| Field              | Wajib | Tipe    | Catatan                          |
| ------------------ | ----- | ------- | -------------------------------- |
| `role_id`          | Ya    | integer | Harus ada di tabel `role`        |
| `permission_ids`   | Ya    | array   | Minimal 1 item                   |
| `permission_ids.*` | Ya    | integer | Harus ada di tabel `permissions` |

### GET `/permissions/role/{roleId}`

Mengambil daftar permission milik role tertentu.

### GET `/permissions/{id}`

Mengambil detail satu permission beserta role yang terkait.

### PUT `/permissions/{id}`

Body sama seperti create:

```json
{
    "name": "edit_user",
    "display_name": "Edit User",
    "group": "user"
}
```

### DELETE `/permissions/{id}`

Menghapus satu permission jika belum dipakai role mana pun.

Response error penting:

| HTTP Code | Kondisi                                |
| --------- | -------------------------------------- |
| `400`     | Permission masih dipakai role          |
| `403`     | Tidak punya permission yang dibutuhkan |
| `422`     | Validasi gagal                         |

---

## 7. Roles

### GET `/roles`

Query params:

| Param             | Wajib | Tipe    | Default | Keterangan                        |
| ----------------- | ----- | ------- | ------- | --------------------------------- |
| `search`          | Tidak | string  | -       | Cari role                         |
| `has_permissions` | Tidak | boolean | -       | Filter role yang punya permission |
| `sort_by`         | Tidak | string  | `name`  | Field sorting                     |
| `sort_order`      | Tidak | string  | `asc`   | `asc` atau `desc`                 |
| `per_page`        | Tidak | integer | `15`    | Pagination                        |

Response sukses:

```json
{
    "success": true,
    "message": "Roles retrieved successfully",
    "data": [],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

### GET `/roles/all`

Query params:

| Param              | Wajib | Tipe    | Keterangan             |
| ------------------ | ----- | ------- | ---------------------- |
| `with_permissions` | Tidak | boolean | Muat relasi permission |

### GET `/roles/summary`

Mengambil statistik role, termasuk top role dan permission yang paling sering dipakai.

### GET `/roles/user/{userId}`

Mengambil role milik user tertentu.

### POST `/roles`

Request body:

```json
{
    "name": "editor",
    "display_name": "Editor",
    "description": "Role editor",
    "permission_ids": [1, 2, 3]
}
```

Field validasi:

| Field              | Wajib | Tipe    | Catatan                               |
| ------------------ | ----- | ------- | ------------------------------------- |
| `name`             | Ya    | string  | Unik, huruf kecil dan underscore saja |
| `display_name`     | Ya    | string  | Maksimal 150 karakter                 |
| `description`      | Tidak | string  | Deskripsi role                        |
| `permission_ids`   | Tidak | array   | Daftar permission                     |
| `permission_ids.*` | Tidak | integer | Harus ada di tabel `permissions`      |

### GET `/roles/{id}`

Mengambil detail satu role beserta users dan permissions.

### PUT `/roles/{id}`

Body sama seperti create.

Catatan penting:

Role sistem `super_admin` dan `admin` tidak boleh diubah namanya.

### DELETE `/roles/{id}`

Menghapus role jika bukan role sistem dan belum dipakai user.

Response error penting:

| HTTP Code | Kondisi                         |
| --------- | ------------------------------- |
| `400`     | Role masih dipakai user         |
| `403`     | Role sistem tidak boleh dihapus |
| `404`     | Role tidak ditemukan            |

### POST `/roles/bulk-delete`

Request body:

```json
{
    "ids": [1, 2, 3]
}
```

### POST `/roles/{id}/assign-permissions`

Assign permission ke role menggunakan mode `sync`, `attach`, atau `detach`.

Request body:

```json
{
    "permission_ids": [1, 2, 3],
    "sync_type": "sync"
}
```

Field validasi:

| Field              | Wajib | Tipe    | Catatan                          |
| ------------------ | ----- | ------- | -------------------------------- |
| `permission_ids`   | Ya    | array   | Minimal 1 item                   |
| `permission_ids.*` | Ya    | integer | Harus ada di tabel `permissions` |
| `sync_type`        | Tidak | string  | `sync`, `attach`, atau `detach`  |

### GET `/roles/{id}/permissions`

Mengambil permission dari role tertentu, termasuk hasil grouping per `group`.

---

## Contoh Curl

### Login

```bash
curl --request POST 'http://localhost/api/auth/login' \
	--header 'Accept: application/json' \
	--header 'Content-Type: application/json' \
	--header 'X-API-Key: your_api_key' \
	--header 'X-App-License: your_app_license' \
	--data-raw '{
		"login": "denis@example.com",
		"password": "Password123",
		"device_name": "Postman"
	}'
```

### Ambil profil user login

```bash
curl --request GET 'http://localhost/api/auth/me' \
	--header 'Accept: application/json' \
	--header 'X-API-Key: your_api_key' \
	--header 'X-App-License: your_app_license' \
	--header 'Authorization: Bearer your_access_token'
```

### Buat role baru

```bash
curl --request POST 'http://localhost/api/roles' \
	--header 'Accept: application/json' \
	--header 'Content-Type: application/json' \
	--header 'Authorization: Bearer your_access_token' \
	--data-raw '{
		"name": "editor",
		"display_name": "Editor",
		"description": "Role editor",
		"permission_ids": [1, 2]
	}'
```
