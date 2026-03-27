# Dokumentasi Fitur POS App (HPP & Diskon)

Berikut adalah ringkasan teknis mengenai fitur Harga Pokok Penjualan (HPP), Diskon Dinamis (Global/Transaksi), dan Diskon Statis (Otomatis/Produk) yang telah diimplementasikan ke dalam sistem.

---

## 1. Harga Pokok Penjualan (HPP)

**Tujuan:**
Menghitung dan memvisualisasikan total modal dari barang-barang yang berhasil terjual pada suatu periode tertentu. Data digunakan untuk indikator kerugian/keuntungan di modul Laporan (Reports).

**Implementasi:**
- **Perhitungan (Backend):** Kalkulasi ini diambil dari relasi tabel `order_items`, mengingat HPP harus berdasarkan modal historis pada masa pesanan tersebut dibuat. `Admin\ReportController.php` memiliki method `hppForStores` yang melakukan query penjumlahan (SUM) dari operasi `buy_price * quantity`.
- **API Payload:** Nilai HPP digabungkan langsung ke dalam object payload tipe `overall` maupun array `per_store`.
- **Antarmuka (Frontend - UI):** Pada file `resources/js/pages/Admin/Reports/Index.vue`, *Typescript interface* diubah agar menerima key `hpp: number`. Indikator ini divisualisasikan dalam bentuk `StatCard` pada deret paling atas pendamping indikator Pendapatan Penjualan, dengan pelebaran ruang dari `grid-cols-4` menjadi `grid-cols-5`. HPP individual juga ditambahkan saat admin membuka rincian toko (expanded mode).

---

## 2. Diskon Dinamis (Transaksi / Cashier Input)

**Tujuan:**
Memungkinkan staf kasir untuk memberikan modifikasi diskon manual pada total tagihan akhir dengan basis angka/nominal tunai sebelum pelanggan melakukan instrumen pembayaran.

**Implementasi:**
- **Database Mapping:** Skema database pada awalnya sudah memiliki kolom statis `discount_amount` pada tabel `orders`, sehingga dapat diintegrasikan sepenuhnya hanya lewat pembaruan logika transaksi controller.
- **Modifikasi UI (Frontend):** Pada file `resources/js/pages/Admin/Cashier/Index.vue` (antarmuka Kasir), fitur ini dipasangkan sebagai input form `discountAmount` (rupiah). UI diubah sedemikian rupa agar mendemonstrasikan penghitungan `subtotal - discountAmount = finalAmount` secara langsung (real-time). 
- **Validasi Kalkulasi (Backend):** Method validasi pembuatan pesanan di dalam `Admin\CashierController.php` maupun `Api\CashierController.php` akan memotong besaran subtotal dengan `discount_amount` yang dikirim dari payload. 

---

## 3. Diskon Statis (Otomatis per Produk)

**Tujuan:**
Mempercepat siklus pelayanan kasir di mana promo tertentu (mis: Jumat Berkah - Diskon Kopi 10%) berjalan secara mutlak pada katalog. Kasir tak perlu mengingat diskon setiap produk karena prosesnya kalkulatif tersentral.

**Implementasi:**
- **Data Persistence:** Kolom baru `discount_percent` (decimal `5,2`) ditambahkan ke tabel `products` melalui *database migration*. 
- **Model Attribute:** `discount_percent` ditambahkan ke parameter *Fillable* array berserta casting tipe `float` melalui `app/Models/Product.php`.
- **Manajemen Produk (CRUD):**
  - **Form Tambah/Edit:** Kolom input form persentase dimasukkan ke antarmuka `Admin/Products/Form.vue` persis di sebelah form harga.
  - **Katalog Manajemen:** Modifikasi tampilan `Admin/Products/Index.vue` untuk memutar logika harga asli menjadi gaya teks coret (strikethrough) yang mendampingi sebuah label Badge margin persentase warna merah `-10%`, berikut harga baru setelah dipotong.
- **Alur Etalase Kasir:** Data yang disupply controller untuk fungsi `getProductsForStore` kini melampirkan key *discount_percent*. File POS Dashboard `Admin/Cashier/Index.vue` secara transparan mengeksekusi kalkulasi keranjang setiap detiknya menyesuaikan pergerakan kuantitas untuk produk yang tersuntik persentase diskon.
- **Keamanan Pengecekan:** Sama seperti logika *Diskon Dinamis*, saat permintaan checkout di kirim ke route API/Admin, controller mendistorsi harga log aslinya (`PriceLog`) secara *force* jika produk yang direferensikan dalam keranjang memiliki kolom `discount_percent` mayor dari 0, lalu mengeja nilai diskon per masing-masing log tabel `order_items` sebelum dijumlah global di tabel `orders`. Konvensi ini memastikan rekapitulasi data selalu solid (Single Source of Truth).


---

## 4. Urutan Kalkulasi (Presedensi Diskon) & Pembulatan

Agar tidak terjadi anomali perhitungan data atau selisih harga (karena diskon berbasis persentase seringkali menghasilkan angka desimal yang tidak lazim dalam nominal Rupiah penuh), sistem mengikuti urutan pengolahan data kalkulasi sebagai berikut:

**1. Operasi Diskon Statis (Per-Item):**
Nilai potongan yang dikalkulasi `(Harga Normal × (Diskon Persen / 100))` langsung diproses dengan pembulatan matematis:
- Backend mengeksekusi PHP konvensional `round()` di parameter `Admin\CashierController` & `Api\CashierController`.
- Frontend mengeksekusi Javascript konvensional `Math.round()` di parameter array rendering `Cashier/Index.vue`.
  
**2. Penghitungan Subtotal Baris:**
Subtotal per produk di keranjang merupakan hasil perkalian *(Harga Normal - Nominal Potongan Bulat) x Kuantitas*.

**3. Pemotongan Diskon Dinamis (Transaksi Global):**
Diskon tambahan atau manual yang di-_input_ langsung dari form POS akan dipotong dari Akumulasi Seluruh Subtotal Baris sebelum pada akhirnya diotorisasi sebagai Tagihan Akhir *(Final Amount)*.

Dengan presedensi diskon item dieksekusi terlebih dahulu baru diikuti diskon total, pembukuan kas tidak akan pernah mengalami pecahan angka (misal angka 0.5 di nominal Rupiah) dan mempermudah pembukuan akutansi di sisi Reports nantinya.
