# Kasir POS - Mobile (Flutter)

Aplikasi kasir mobile untuk POS. Terhubung ke API backend Laravel.

## Setup

1. Pastikan Flutter terpasang: `flutter doctor`
2. Install dependencies: `flutter pub get`
3. **Sesuaikan URL API** di `lib/config/api_config.dart`:
   - Android Emulator: `http://10.0.2.2:80/pos/public/api`
   - iOS Simulator: `http://localhost/pos/public/api`
   - Physical device: `http://<IP_PC_ANDA>:80/pos/public/api`

## Menjalankan

```bash
flutter run
```

## Fitur

- **Login** - Autentikasi kasir (email + password)
- **Dashboard** - Ringkasan: pesanan menunggu, transaksi hari ini, penjualan
- **Transaksi Baru** - Walk-in / Takeaway, pilih produk, keranjang, checkout
- **Pesanan Menunggu** - Daftar pesanan dari QR (dine-in) yang belum dibayar, proses pembayaran

## Struktur

```
lib/
├── config/       # Konfigurasi API
├── models/       # Model data (User, Product, Order, dll)
├── providers/    # State management (AuthProvider)
├── screens/      # Layar aplikasi
├── services/     # API & storage
└── main.dart
```
