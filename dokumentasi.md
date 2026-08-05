# Dokumentasi Perubahan

Catatan pekerjaan yang dilakukan pada tanggal 5 Agustus 2026.

---

## 1. Laporan Pengeluaran di Excel

Sebelumnya pengeluaran hanya muncul sebagai total per kategori. Sekarang setiap
catatan pengeluaran bisa ditelusuri satu per satu, sejajar dengan daftar transaksi.

**Backend** — `app/Http/Controllers/Admin/ReportController.php`

- Method baru `buildExpenseList()` mengembalikan daftar pengeluaran per baris:
  tanggal, waktu input, toko, kategori, keterangan, pencatat, nominal, dan URL bukti.
- Data ini hanya dimuat saat export diminta (mengikuti pola `has_detail` yang sudah ada),
  supaya payload halaman tetap ringan.

**Frontend** — `resources/js/Pages/Admin/Reports/Index.vue`

Dua sheet baru pada export Excel lengkap:

| Sheet | Isi |
|---|---|
| Rekap Pengeluaran | Total dan jumlah entri per kategori, porsi dalam persen, baris total |
| Daftar Pengeluaran | Satu baris per catatan, lengkap dengan keterangan dan tautan bukti |

Keduanya memakai header beku, autofilter, format rupiah, dan baris total.

---

## 2. Bukti Pengeluaran Berupa Foto

Kolom `receipt_image` sudah ada di tabel `daily_expenses` sejak awal, tetapi tidak
pernah diisi karena tidak ada mekanisme upload. Sekarang kolom itu berfungsi.

**Upload** — `app/Http/Controllers/Admin/DailyExpenseController.php`

- Field `receipt` (gambar, maksimal 4 MB) pada create dan update.
- File disimpan ke `storage/app/public/expenses/receipts`.
- Mengganti foto akan menghapus file lama.
- File bukti tidak ikut terhapus saat pengeluaran dihapus, karena record memakai
  soft delete dan bukti masih dibutuhkan untuk audit.

**Komponen upload** — `resources/js/components/shared/ReceiptUpload.vue` (baru)

- Area unggah bergaris putus-putus, mendukung klik maupun seret-lepas.
- Setelah file dipilih berubah jadi kartu pratinjau: thumbnail, nama file, ukuran,
  serta aksi Ganti, Lihat, dan Hapus.
- Validasi tipe dan ukuran dilakukan di sisi klien.
- Gambar dikecilkan otomatis di browser sebelum dikirim (sisi terpanjang 1600px,
  JPEG kualitas 0.8). Ini mengatasi error `413 Request Entity Too Large` dari nginx
  yang batas bawaannya 1 MB, tanpa perlu menunggu perubahan konfigurasi server.

**Tampilan bukti**

- Buku Pengeluaran: thumbnail di kolom tersendiri, bisa diklik untuk melihat penuh.
- Laporan PDF: foto bukti tercetak langsung sebagai thumbnail dalam tabel.
  Proses cetak ditunda sampai semua gambar selesai dimuat agar tidak keluar kotak kosong.
- Laporan Excel: kolom Bukti berisi tautan yang bisa diklik. SheetJS tidak dapat
  menyisipkan gambar ke dalam file xlsx, jadi tautan adalah batas yang bisa dicapai.

**Yang perlu dijalankan di server**

```bash
php artisan storage:link
sudo chown -R www-data:www-data storage/app/public
sudo find storage/app/public -type d -exec chmod 755 {} \;
sudo find storage/app/public -type f -exec chmod 644 {} \;
```

Pastikan juga `APP_URL` pada `.env` produksi sudah benar, dan naikkan
`client_max_body_size` pada nginx menjadi `10M` sebagai pengaman.

---

## 3. Dashboard Analitik

**Backend** — `app/Http/Controllers/Admin/DashboardController.php`

Empat sumber data baru:

- Distribusi omzet per jam selama 7 hari terakhir, untuk melihat jam sibuk.
- Tren enam bulan terakhir: pemasukan, pengeluaran, dan laba bersih.
- Komposisi pengeluaran per kategori pada bulan berjalan.
- Komposisi metode pembayaran selama 7 hari terakhir.

Helper `sqlHour()` dan `sqlYearMonth()` memilih ekspresi tanggal sesuai driver
koneksi, karena SQLite yang dipakai saat pengembangan tidak mengenal `HOUR()` dan
`DATE_FORMAT()` milik MySQL.

**Frontend** — `resources/js/Pages/Admin/Dashboard.vue`

Bagian "Analitik" berisi empat grafik baru, disembunyikan untuk role kasir
mengikuti pola bagian grafik yang sudah ada.

---

## 4. Tipografi Laporan Cetak

`resources/js/Pages/Admin/Reports/Print.vue` disesuaikan agar layak dibawa ke
tingkat komisaris.

- Sistem dua font: serif untuk judul, sans-serif untuk isi dan angka. Keduanya font
  sistem, bukan unduhan, supaya hasil cetak tidak berubah bentuk bila font gagal dimuat.
- `tabular-nums` dipaksa di seluruh dokumen agar kolom nominal rata lurus.
- Bobot huruf diturunkan dari 900 ke 700 dan teks tabel ke 500, sehingga hierarki
  kembali terbaca. Jarak huruf negatif pada judul besar dihapus.
- Warna teks abu yang terlalu muda dinaikkan kontrasnya karena nyaris hilang di kertas.
- Halaman baru "Daftar Pengeluaran Detail" dengan keterangan dan foto bukti.

---

## 5. Responsivitas Menyeluruh

Aturan baru ditambahkan sebagai Bagian 4 pada `rule.md`. Intinya: setiap halaman
harus rapi dari layar 320px sampai desktop, dan pengguna mobile hanya boleh
menggulir atas-bawah.

**Pendekatan yang dipakai**

- Tabel lebar tidak diselesaikan dengan `overflow-x-auto`. Di bawah breakpoint `md`
  tabel diganti daftar kartu vertikal, memakai loop data yang sama tanpa duplikasi logic.
- Bila kolom disembunyikan per breakpoint, header dan sel body disembunyikan
  bersamaan. Data dari kolom yang disembunyikan diselipkan sebagai baris kecil di
  kolom pertama agar informasi tidak hilang.
- Grafik memakai tinggi bertingkat. Di layar sempit legenda pindah ke bawah, ukuran
  font sumbu diperkecil, jumlah label dibatasi, dan nominal disingkat menjadi
  bentuk seperti "1.2 jt" atau "450 rb".
- Tinggi viewport memakai `dvh`, bukan `vh`, karena bilah alamat browser mobile
  mengubah tinggi area yang terlihat.
- Dialog memakai `w-[95vw]` dengan `max-w-*` supaya tidak menempel ke tepi layar sempit.
- Tidak ada aksi yang hanya muncul saat hover, karena perangkat sentuh tidak punya hover.

**Cakupan: 45 file**

| Kelompok | Jumlah | Keterangan |
|---|---|---|
| Halaman daftar utama | 22 | Dashboard, Kasir, Pesanan, Penjualan, Laporan, dan lainnya |
| Halaman detail | 5 | Orders, Customers, PurchaseOrders, Shifts, Users |
| Form, create, dan edit | 8 | Termasuk tabel item dinamis pada PO dan Refund |
| Editor halaman depan | 1 | Landing |
| Pemilih toko | 8 | Hanya perbaikan `dvh` dan pemotongan nama toko |
| Komponen bersama | 2 | AppHeader dan AdminLayout |

**Perbaikan yang menonjol**

- Topbar: judul panjang mendorong ikon notifikasi dan tema keluar layar. Diperbaiki
  dengan `min-w-0` dan `truncate`, tinggi dan padding ikut mengecil di HP.
- Panel keranjang kasir: daftar class-nya diakhiri `relative` padahal diawali `fixed`.
  Dalam urutan CSS Tailwind, `.relative` menang atas `.fixed`, sehingga panel batal
  menjadi bottom sheet dan muncul melayang di tengah halaman.
- Sidebar tertimpa panel keranjang karena urutan z-index. Sidebar dinaikkan ke `z-[60]`
  dan lapisan gelapnya ke `z-[55]`.
- Editor denah: kanvas memakai `100vh` sehingga terpotong di HP, dan tombol simpan
  dengan `ml-auto` terlempar sendirian saat toolbar membungkus.
- Struk manual: form dipaku selebar 420px tanpa breakpoint sama sekali. Sekarang
  menumpuk vertikal sampai `lg`. Lebar struk memakai `min(80mm, 100%)`.

---

## 6. Bug yang Ditemukan dan Diperbaiki

Sembilan masalah nyata yang ditemukan sambil merapikan tampilan.

| Berkas | Masalah |
|---|---|
| `Inventory/Index.vue` | Ikon `X` dipakai tanpa diimpor |
| `Customers/Index.vue` | `Link` dipakai tanpa diimpor |
| `Cashflow/Index.vue` | `ChevronLeft` dipakai tanpa diimpor |
| `ExpenseBook/Index.vue` | `Badge` dipakai tanpa diimpor |
| `PurchaseOrders/Show.vue` | Atribut ditulis `v-v-if`, tidak dikenali Vue, sehingga ikon centang selalu tampil bersama spinner |
| `Promos/Form.vue` | `submit()` menyusun objek `payload` yang tidak pernah dipakai, sehingga konversi nilai kosong menjadi `null` tidak pernah terkirim |
| `Landing/Index.vue` | Tombol hapus gambar galeri hanya muncul saat hover, sehingga gambar tidak dapat dihapus dari perangkat sentuh |
| `FloorPlan/Index.vue` | Tombol cetak QR dan hapus lantai hanya muncul saat hover |
| `FloorPlan/Edit.vue`, `StoreProducts/Index.vue` | Tiga titik kontrol lain yang juga hanya muncul saat hover |

Selain itu dibersihkan: import yang tidak terpakai (`Badge`, `Percent`, `Banknote`,
`Link`, `DollarSign`), deklarasi `const props` yang tidak dipakai, dan emoji pada
badge status `Shifts/Show.vue`.

Beberapa tabel juga sebelumnya tidak memiliki tampilan kosong sama sekali, yaitu
tabel item pada `Orders/Show.vue` dan `PurchaseOrders/Show.vue`.

---

## 7. Catatan Terbuka

- Emoji masih tersisa pada teks UI di `Products/Form.vue` dan `Promos/Form.vue`,
  bertentangan dengan Bagian 5 `rule.md`. Belum dibersihkan.
- Halaman cetak `Orders/Invoice.vue`, `Orders/Receipt.vue`, dan `FloorPlan/PrintQr.vue`
  belum ditinjau, karena keluarannya kertas sehingga responsivitas layar tidak relevan.
- Penyisiran menyeluruh `resources/js` untuk kasus komponen dipakai tanpa diimpor
  belum dijalankan. Mengingat empat kasus sudah terbukti ada, ini layak dilakukan.
- Kompresi gambar bukti hanya dilakukan di sisi klien. Bila ukuran storage menjadi
  masalah, perlu ditambahkan pemrosesan di sisi server.
