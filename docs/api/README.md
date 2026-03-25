# API Kasir - Dokumentasi Lengkap

API untuk aplikasi kasir (mobile/desktop terpisah). Menggunakan Laravel Sanctum untuk autentikasi token.

**Base URL:** `{APP_URL}/api`  
**Auth:** Header `Authorization: Bearer {token}`

---

## Daftar Isi

1. [Autentikasi](#1-autentikasi)
2. [Konteks Toko](#2-konteks-toko)
3. [Produk & Kategori](#3-produk--kategori)
4. [Meja](#4-meja)
5. [Metode Pembayaran](#5-metode-pembayaran)
6. [Transaksi Kasir (Buat Order)](#6-transaksi-kasir-buat-order)
7. [Pesanan dari Meja (QR)](#7-pesanan-dari-meja-qr)
8. [Denah Meja](#8-denah-meja)
9. [Ringkasan](#9-ringkasan)

---

## 1. Autentikasi

### POST /api/auth/login

Login kasir. Mengembalikan token untuk request selanjutnya.

**Request:**
```json
{
  "email": "kasir@example.com",
  "password": "password"
}
```

**Response 200:**
```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Kasir Satu",
    "email": "kasir@example.com",
    "phone": null,
    "role": "cashier",
    "brand_id": 1,
    "store_id": 1,
    "brand": { "id": 1, "name": "Brand Saya" },
    "store": { "id": 1, "name": "Toko Utama", "slug": "toko-utama" }
  }
}
```

**Error 422:** Email/password salah, akun nonaktif, atau role tidak diizinkan.

---

### POST /api/auth/logout

Logout (revoke token saat ini).

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "message": "Berhasil logout."
}
```

---

### GET /api/auth/me

Data user saat ini.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "user": {
    "id": 1,
    "name": "Kasir Satu",
    "email": "kasir@example.com",
    "phone": null,
    "role": "cashier",
    "brand_id": 1,
    "store_id": 1,
    "brand": { "id": 1, "name": "Brand Saya" },
    "store": { "id": 1, "name": "Toko Utama", "slug": "toko-utama" }
  }
}
```

---

## 2. Konteks Toko

### GET /api/cashier/store

Toko aktif kasir (berdasarkan `store_id` dari user).

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "store": {
    "id": 1,
    "name": "Toko Utama",
    "slug": "toko-utama",
    "address": "Jl. Contoh No. 1",
    "city": "Jakarta",
    "phone": "08123456789",
    "open_time": "08:00",
    "close_time": "22:00",
    "is_active": true
  }
}
```

---

### GET /api/cashier/stores

Daftar toko (untuk owner/admin ganti toko).

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "stores": [
    {
      "id": 1,
      "name": "Toko Utama",
      "slug": "toko-utama",
      "is_active": true
    }
  ]
}
```

---

## 3. Produk & Kategori

### GET /api/cashier/products

Daftar produk toko (harga, stok, kategori).

**Headers:** `Authorization: Bearer {token}`

**Query (opsional):**
| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| store_id | int | dari user | Filter produk per toko |

**Response 200:**
```json
{
  "products": [
    {
      "id": 1,
      "name": "Kopi Susu",
      "category_id": 1,
      "category_name": "Minuman",
      "category_color": "#3B82F6",
      "unit": "pcs",
      "track_stock": true,
      "current_stock": 50,
      "sell_price": 25000,
      "image_url": "https://..."
    }
  ]
}
```

---

### GET /api/cashier/categories

Daftar kategori.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Minuman",
      "slug": "minuman",
      "icon": null,
      "color": "#3B82F6",
      "sort_order": 0
    }
  ]
}
```

---

## 4. Meja

### GET /api/cashier/tables

Daftar meja toko (untuk dine-in).

**Headers:** `Authorization: Bearer {token}`

**Query (opsional):**
| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| store_id | int | dari user | Filter meja per toko |

**Response 200:**
```json
{
  "tables": [
    {
      "id": 1,
      "name": "Meja 1",
      "floor_id": 1,
      "capacity": 4,
      "status": "available",
      "is_active": true
    }
  ]
}
```

---

## 5. Metode Pembayaran

### GET /api/cashier/payment-methods

Daftar metode pembayaran toko.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "payment_methods": [
    {
      "id": 1,
      "name": "Tunai",
      "code": "cash",
      "requires_cash_input": true,
      "qrcode_image": null
    },
    {
      "id": 2,
      "name": "QRIS",
      "code": "qris",
      "requires_cash_input": false,
      "qrcode_image": "payment/qris.png"
    }
  ]
}
```

---

## 6. Transaksi Kasir (Buat Order)

### POST /api/cashier/orders

Buat order dari kasir (walk-in, dine-in, takeaway). Order langsung dianggap lunas (paid).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "store_id": 1,
  "type": "walk_in",
  "table_id": null,
  "customer_name": "Budi",
  "customer_phone": "08123456789",
  "customer_email": "budi@example.com",
  "items": [
    { "product_id": 1, "quantity": 2, "notes": "Kurang es" },
    { "product_id": 2, "quantity": 1 }
  ],
  "notes": "Pesanan bawa pulang",
  "payment_method": "cash",
  "cash_received": 75000
}
```

| Field | Tipe | Wajib | Deskripsi |
|-------|------|-------|-----------|
| store_id | int | Tidak | Default dari user. Untuk ganti toko. |
| type | string | Ya | `walk_in`, `dine_in`, atau `takeaway` |
| table_id | int | Ya jika dine_in | ID meja (wajib untuk dine_in) |
| customer_name | string | Tidak | Nama pelanggan |
| customer_phone | string | Tidak | No. telepon |
| customer_email | string | Tidak | Email pelanggan |
| items | array | Ya | Minimal 1 item |
| items[].product_id | int | Ya | ID produk |
| items[].quantity | number | Ya | Jumlah (min 0.001) |
| items[].notes | string | Tidak | Catatan per item |
| notes | string | Tidak | Catatan order |
| payment_method | string | Ya | Kode dari `/api/cashier/payment-methods` (cash, qris, dll) |
| cash_received | number | Ya jika tunai | Uang tunai diterima (harus ≥ total) |

**Response 201:**
```json
{
  "message": "Pesanan berhasil dibuat.",
  "order": {
    "id": 1,
    "order_code": "ORD-20260318-0001",
    "type": "walk_in",
    "status": "done",
    "subtotal": 50000,
    "discount_amount": 0,
    "tax_amount": 0,
    "final_amount": 50000,
    "payment_method": "cash",
    "payment_status": "paid",
    "cash_received": 75000,
    "change_amount": 25000,
    "paid_at": "2026-03-18T10:30:00.000000Z",
    "table": null,
    "items": [
      {
        "product_id": 1,
        "product_name": "Kopi Susu",
        "quantity": 2,
        "unit_price": 25000,
        "subtotal": 50000,
        "notes": "Kurang es"
      }
    ]
  }
}
```

**Error 422:** Validasi gagal, stok tidak cukup, meja tidak ditemukan, dll.

---

## 7. Pesanan dari Meja (QR)

### GET /api/cashier/pending-orders

Daftar pesanan menunggu pembayaran (dari QR/dine-in, belum dibayar).

**Headers:** `Authorization: Bearer {token}`

**Query (opsional):**
| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| store_id | int | dari user | Filter per toko |

**Response 200:**
```json
{
  "orders": [
    {
      "id": 1,
      "order_code": "ORD-20260318-0001",
      "type": "dine_in",
      "status": "pending",
      "created_at": "2026-03-18T10:00:00.000000Z",
      "subtotal": 50000,
      "final_amount": 50000,
      "payment_status": "unpaid",
      "table": { "id": 1, "name": "Meja 1" },
      "customer": { "id": 1, "name": "Budi", "email": "budi@example.com", "phone": "08123456789" },
      "items": [...]
    }
  ]
}
```

---

### GET /api/cashier/orders/{id}

Detail order.

**Headers:** `Authorization: Bearer {token}`

**Response 200:**
```json
{
  "order": {
    "id": 1,
    "order_code": "ORD-20260318-0001",
    "type": "dine_in",
    "status": "pending",
    "created_at": "2026-03-18T10:00:00.000000Z",
    "subtotal": 50000,
    "final_amount": 50000,
    "payment_status": "unpaid",
    "table": { "id": 1, "name": "Meja 1" },
    "customer": { "id": 1, "name": "Budi", "email": "budi@example.com", "phone": "08123456789" },
    "items": [
      {
        "product_id": 1,
        "product_name": "Kopi Susu",
        "quantity": 2,
        "unit_price": 25000,
        "subtotal": 50000,
        "notes": null
      }
    ]
  }
}
```

**Error 404:** Pesanan tidak ditemukan.

---

### POST /api/cashier/orders/{id}/pay

Terima pembayaran pesanan dari meja.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "payment_method": "cash",
  "cash_received": 75000
}
```

| Field | Tipe | Wajib | Deskripsi |
|-------|------|-------|-----------|
| payment_method | string | Ya | Kode dari payment methods (cash, qris, dll) |
| cash_received | number | Ya jika tunai | Uang tunai diterima (harus ≥ total) |

**Response 200:**
```json
{
  "message": "Pembayaran berhasil diterima.",
  "order": {
    "id": 1,
    "order_code": "ORD-20260318-0001",
    "payment_status": "paid",
    "cash_received": 75000,
    "change_amount": 25000,
    "paid_at": "2026-03-18T10:30:00.000000Z",
    ...
  }
}
```

**Error 422:** Pesanan sudah dibayar, dibatalkan, atau uang tunai kurang.

---

## 8. Denah Meja

### GET /api/cashier/floor-plan

Denah meja + pesanan aktif per meja (untuk tampilan layout kasir).

**Headers:** `Authorization: Bearer {token}`

**Query (opsional):**
| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| store_id | int | dari user | Filter per toko |

**Response 200:**
```json
{
  "floors": [
    {
      "id": 1,
      "name": "Lantai 1",
      "width_meters": 10,
      "length_meters": 15,
      "sort_order": 0,
      "tables": [
        {
          "id": 1,
          "name": "Meja 1",
          "capacity": 4,
          "status": "occupied",
          "x_meters": 2,
          "y_meters": 3,
          "width_meters": 1.2,
          "length_meters": 0.8,
          "rotation_deg": 0,
          "shape": "rectangle",
          "active_order": {
            "id": 1,
            "order_code": "ORD-20260318-0001",
            "status": "pending",
            "payment_status": "unpaid",
            "final_amount": 50000,
            "items_count": 2
          }
        }
      ],
      "elements": [
        {
          "id": 1,
          "type": "pillar",
          "name": "Tiang 1",
          "x_meters": 5,
          "y_meters": 5,
          "width_meters": 0.5,
          "length_meters": 0.5,
          "rotation_deg": 0
        }
      ]
    }
  ]
}
```

---

## 9. Ringkasan

### GET /api/cashier/summary

Ringkasan: jumlah pesanan menunggu, penjualan hari ini.

**Headers:** `Authorization: Bearer {token}`

**Query (opsional):**
| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| store_id | int | dari user | Filter per toko |

**Response 200:**
```json
{
  "pending_orders_count": 3,
  "sales_today": 1250000,
  "orders_today": 15
}
```

---

## Format Error

Semua error mengembalikan JSON:

```json
{
  "message": "Pesan error",
  "errors": {
    "field": ["Detail validasi"]
  }
}
```

**Status HTTP umum:**
- `401` – Unauthorized (token tidak valid/expired)
- `403` – Forbidden (role tidak diizinkan)
- `422` – Validation error
- `404` – Not found

---

## Role yang Diizinkan

- `owner`
- `admin`
- `cashier`

User dengan role lain akan ditolak saat login.
