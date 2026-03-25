# Rencana API Kasir

Dokumen ini merangkum endpoint API yang akan dibangun untuk aplikasi kasir (mobile/desktop terpisah).

---

## 1. Autentikasi

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/auth/login` | Login kasir (email + password) → token |
| POST | `/api/auth/logout` | Logout (revoke token) |
| GET | `/api/auth/me` | Data user saat ini |

**Response login:** `{ token, user: { id, name, email, role, store_id, brand_id } }`

---

## 2. Konteks Toko

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/store` | Toko aktif kasir (store_id dari user) |
| GET | `/api/cashier/stores` | Daftar toko (untuk owner/admin ganti toko) |

---

## 3. Produk & Kategori

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/products` | Daftar produk toko (harga, stok, kategori) |
| GET | `/api/cashier/categories` | Daftar kategori |

**Query products:** `?store_id=1` (opsional, default dari user)

---

## 4. Meja

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/tables` | Daftar meja toko (untuk dine-in) |

---

## 5. Metode Pembayaran

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/payment-methods` | Daftar metode pembayaran toko |

---

## 6. Transaksi Kasir (Buat Order)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/cashier/orders` | Buat order dari kasir (walk-in, dine-in, takeaway) |

**Body:** `{ type, table_id?, customer_name?, customer_phone?, customer_email?, items: [{product_id, quantity, notes?}], notes?, payment_method, cash_received? }`

---

## 7. Pesanan dari Meja (QR)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/pending-orders` | Daftar pesanan menunggu (dari QR, belum dibayar) |
| GET | `/api/cashier/orders/{id}` | Detail order |
| POST | `/api/cashier/orders/{id}/pay` | Terima pembayaran pesanan dari meja |

**Body pay:** `{ payment_method, cash_received? }`

---

## 8. Denah Meja (Opsional)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/floor-plan` | Denah meja + pesanan aktif per meja |

---

## 9. Ringkasan (Opsional)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/cashier/summary` | Ringkasan: pending count, penjualan hari ini |

---

## Teknologi

- **Auth:** Laravel Sanctum (token API)
- **Prefix:** `/api/cashier` atau `/api/v1/cashier`
- **Middleware:** `auth:sanctum` + role cashier/owner/admin

---

## Urutan Implementasi

1. **Setup** – Install Sanctum, konfigurasi
2. **Auth** – Login, logout, me
3. **Store & Products** – Konteks toko, produk, kategori
4. **Tables & Payment Methods**
5. **Create Order** – POST orders (transaksi kasir)
6. **Pending Orders** – GET, Pay
7. **Floor Plan** (jika perlu)
8. **Summary** (jika perlu)

---

## Catatan

- Semua endpoint memerlukan header: `Authorization: Bearer {token}`
- Response format: JSON
- Error: `{ message, errors? }` dengan status HTTP sesuai
