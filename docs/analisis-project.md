# Analisis Project rental-ps

Tanggal analisis: 2026-09-26. Hasil review read-only, belum ada file yang diubah.

## 1. Ringkasan Project

Sistem manajemen rental PlayStation multi-brand / multi-store ("Spot Duren Tiga", slug `spot-duren-tiga`). Fitur: POS/kasir, timer sewa unit, kontrol smart plug Tuya, F&B, inventori, purchase order, pengeluaran, laporan, dan landing page publik. UI berbahasa Indonesia.

### Tech stack
- Backend: PHP ^8.3, Laravel ^13, Inertia-Laravel ^2, Sanctum, Ziggy. SQLite (dev), MySQL (prod). GD untuk kompres gambar.
- Frontend: Vue 3.5, TypeScript, Vite 7, Tailwind CSS 4, radix-vue (gaya shadcn), lucide-vue-next, Chart.js, vee-validate + zod, VueUse.
- Dev: `composer dev` (serve, queue, pail, vite).

### Role (`users.role`, dicek `EnsureAuthenticated`)
| Role | Akses |
|---|---|
| owner | Semua, plus `settings/reset-trial` |
| admin | Semua modul admin kecuali reset-trial |
| cashier | Dashboard (tanpa chart), POS, shift, order, refund, cashflow, expense, kontrol Tuya |
| staff | Dashboard |

### Route publik
- `/` landing page (`LandingPageController@home`)
- `/order/{storeSlug}/{qrCode}` dan `POST /order`: pesan via QR meja tanpa login
- `/login`, `/logout`
- `/p/{brandSlug}`: landing CMS per brand (404 bila belum published)

### Model
- Tenant/orang: Brand, Store, User, Customer
- Katalog: Category, Product, ProductModifierGroup/Option, PriceLog, Promo, PaymentMethod
- Penjualan: Order, OrderItem, OrderItemModifier, OrderFeedback, Refund, RefundItem, CashierShift
- Lantai/sewa: Floor, DiningTable, FloorElement, TuyaCommand
- Stok: StoreInventory, StockIn, StockMutation, Supplier, PurchaseOrder, PurchaseOrderItem
- Lainnya: DailyExpense, LandingSetting, LandingSection

## 2. Perubahan Lokal (uncommitted) dan Efeknya

| File | Perubahan | Efek / Risiko |
|---|---|---|
| `routes/web.php` | `/` dulu redirect ke dashboard, sekarang render landing (`home`) | Semua pengunjung dan user login yang buka `/` melihat landing. Bookmark/test yang mengandalkan redirect akan rusak |
| `Auth/LoginController.php` | Logout redirect ke `route('home')` bukan `/login` | Setelah logout masuk landing; jalan balik ke login hanya lewat tombol "Login Staf". Route admin tetap redirect ke `/login` |
| `LandingPageController.php` | Tambah `home()` dan `buildWhatsappUrl()` | Brand dari `LANDING_BRAND` atau brand aktif pertama. Tanpa brand aktif: nama fallback `app.name`, tombol booking jadi anchor `#unit`. Slug salah/nonaktif fallback diam-diam |
| `config/app.php` | Tambah `landing_brand` (env `LANDING_BRAND`) | Bila `config:cache` aktif, perlu `config:clear` |
| `resources/views/app.blade.php` | Preconnect + Google Fonts (Inter, Space Grotesk) di semua halaman | Request eksternal tiap halaman termasuk admin; fallback font sistem bila offline |
| `resources/css/app.css` | Variabel `--font-display` | Hanya heading landing, admin tidak terpengaruh |
| `resources/js/types/index.d.ts` | Tambah tipe `OrbPosition`, `ScrollSectionAction`, `ScrollSection` | Aditif, aman |
| `pages/Landing/Home.vue` (baru) | 4 section (hero, unit, fasilitas, lokasi) + ScrollOrb | Harga, jam buka, teks "Duren Tiga" hardcoded (bukan dari DB). Wallpaper hotlink Unsplash (risiko down/lisensi) |
| `components/ui/scroll-orb/` (baru) | `ScrollOrb.vue` (915 baris) + `index.ts` | Kontroler PS 3D CSS/SVG mengikuti scroll. Berat di mobile. Listener `matchMedia` belum dipastikan dilepas saat unmount |
| `dokumentasi.md` | +~320 baris dokumentasi landing | Ada karakter rusak (mojibake) dan peringatan CRLF ke LF |
| `.claude/settings.local.json` | Izin tool tambahan | Config lokal, sebaiknya tidak di-commit |

Catatan: build frontend dan test belum dijalankan, kompilasi belum terverifikasi.

## 3. Ubah Password Owner

**Tidak ada.** Owner tidak bisa mengubah password sendiri, dan tidak ada fitur ubah password mandiri untuk role manapun. Tidak ada halaman profil; auth custom (bukan Fortify/Breeze/Jetstream).

- Satu-satunya tempat set password: `Admin/UserController.php` (`store`, `update`) lewat `pages/Admin/Users/Form.vue`.
- `UserController::authorizeUser()` (sekitar baris 293-300) menolak edit user ber-role owner dengan 403 "Tidak dapat mengubah akun owner."; `index()` menyembunyikan owner dari daftar.
- Owner bisa mengubah password admin, cashier, staff. Admin hanya cashier dan staff.
- Password owner saat ini hanya bisa diubah lewat DB atau tinker.
- `routes/api.php` hanya punya login, logout, `/auth/me`.

Rekomendasi: tambah halaman profil (`/admin/profile`) dengan form password lama + baru untuk semua user login.

## 4. File Tidak Berguna

Hasil scan read-only. Belum ada yang dihapus. Semua controller dan model terpakai.

### Confidence tinggi (aman dihapus)
- `resources/js/pages/Admin/Reports/SelectStore.vue`: tidak ada render/import (orphan)
- `resources/js/components/ui/separator/`: tidak ada pemakai
- `resources/js/app.js`, `resources/js/bootstrap.js`: starter Laravel; vite pakai `app.ts`
- `resources/views/welcome.blade.php`: view default, tanpa route
- `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`: stub default (hapus bila tidak dijalankan)
- `scripts/__pycache__/adb_bridge.cpython-312.pyc`: cache Python, ter-track git; hapus + tambah `__pycache__` ke `.gitignore`
- `storage/logs/laravel.log`: log (sudah di-gitignore)
- `snapshot.json` (root): dump scan Tuya, duplikat `scripts/snapshot.json`, tidak dibaca kode
- `tmp/` (colorize_categories, debug_cache, debug_categories, seed_products): skrip debug/seed sekali pakai; cek `git status` dulu

### Confidence sedang (cek dulu)
- `scripts/snapshot.json`, `scripts/tuya-raw.json`: dump, bisa digenerate ulang (`scan_devices.py`). `devices.json` dan `config.json` kemungkinan config live, simpan
- `scripts/samsung_pair.py`, `vidaa_pair.py`, `setup.py`: skrip pairing sekali pakai
- `rule.md`, `update.md` (root): catatan dev, tak direferensikan kode
- `docs/API_KASIR_PLAN.md`, `docs/ACCESS_AUDIT.md`: kemungkinan usang (project pindah dari POS ke rental PS)
- `mobile/` (Flutter): tidak direferensikan Laravel/Vue; hapus hanya bila klien mobile ditinggalkan

### Jangan dihapus
`dokumentasi.md`, `deploy.sh`, `database/database.sqlite`, `.env`, `Landing/Show.vue` (masih dirender, cek apakah route `/p/{slug}` masih dipakai), `ScrollOrb`, `MoneyInput`.
