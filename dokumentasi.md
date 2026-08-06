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

## 7. Penyelesaian Catatan Terbuka

Empat catatan yang tersisa dari pekerjaan sebelumnya, semuanya sudah dikerjakan.

### 7.1 Emoji pada teks UI

`Promos/Form.vue` ternyata sudah bersih. Pada `Products/Form.vue` ada enam emoji di
teks UI. Yang dekoratif dihapus, yang membawa makna diganti ikon lucide
(`Clock`, `AlertTriangle`, `Plus`). Sekalian dibersihkan separator komentar
box-drawing dan em dash, sehingga kedua berkas kini sepenuhnya ASCII.

### 7.2 Komponen dipakai tanpa diimpor

Seluruh 91 berkas `.vue` di `resources/js` disisir dengan membandingkan tag
PascalCase pada template terhadap daftar import. Tidak ada registrasi komponen
global di `app.ts`, jadi setiap komponen wajib diimpor per berkas.

| Berkas | Komponen hilang |
|---|---|
| `Cashier/Index.vue` | `CashierFloorPlan` |
| `components/NotificationDrawer.vue` | `Link` |

Ditemukan juga `components/FeedbackModal.vue` mengimpor enam komponen Dialog dari
`@/components/ui/button`. Path itu tidak mengekspor apa pun, jadi semuanya bernilai
`undefined`. Tidak meledak hanya karena template-nya memang tidak memakai tag Dialog.
Baris impor tersebut dihapus, bukan diperbaiki.

Import yang tidak terpakai juga dibersihkan: 27 nama di 15 berkas.

Daftar awal berisi sekitar 40 nama, tetapi 14 di antaranya ternyata dipakai dan tidak
jadi dihapus. Semuanya dirujuk lewat `:is` atau tabel pemetaan, sehingga tidak pernah
muncul sebagai tag di template: `Eye`, `EyeOff`, `ChevronUp`, dan `ChevronDown` pada
`Landing/Index.vue`; enam ikon elemen denah pada `FloorPlan/Edit.vue`; `Wifi` dan
`WifiOff` pada `RentalPanel.vue`; serta `Banknote` dan `Smartphone` pada
`Cashier/Index.vue` yang dikembalikan dari sebuah fungsi.

Cara memverifikasi, agar tidak ada import terpakai yang ikut terhapus:

1. Cari tiap nama di seluruh berkas, bukan hanya pada tag template.
2. Ulangi dengan pola yang juga menangkap bentuk kebab-case, karena Vue menerima
   `<arrow-left />` untuk import bernama `ArrowLeft`.
3. Pastikan hasil yang tersisa memang bukan komponen. Yang sering menipu:
   `<button>`, `<input>`, dan `<label>` adalah elemen HTML native, dan `search`
   huruf kecil biasanya nama ref atau prop, bukan ikon `Search`.
4. Cari ulang setelah penghapusan, pastikan nol kecocokan.

### 7.3 Halaman cetak

`Orders/Invoice.vue`, `Orders/Receipt.vue`, dan `FloorPlan/PrintQr.vue` ditinjau
dengan acuan `Reports/Print.vue`.

Masalah cetak yang nyata:

- **Struk terpotong.** `.receipt` memakai lebar `80mm` ditambah padding `5mm` kiri
  dan kanan tanpa `box-sizing: border-box`, total 90mm di atas kertas 80mm, sehingga
  sisi kanan hilang di printer thermal.
- **Gambar belum dimuat saat cetak.** Ketiga halaman memanggil `window.print()`
  seketika. Pola `waitForImages()` dari `Reports/Print.vue` diterapkan. Pada
  `PrintQr.vue` ini paling terasa karena QR diambil dari layanan eksternal.
- **QR buram.** Gambar diminta seukuran 96 piksel lalu dicetak sebesar itu juga.
  Permintaan dinaikkan ke 300 piksel, tata letak tidak berubah.
- **Warna hilang di hasil cetak.** Tidak ada `print-color-adjust`, sehingga status
  LUNAS, header tabel, dan garis potong kartu QR ikut hilang.
- **Halaman terpotong di tengah baris.** Ditambahkan `page-break-inside: avoid` pada
  baris tabel, item struk, dan kartu QR, serta `display: table-header-group` agar
  header tabel invoice ikut tercetak di tiap halaman.
- **Token tema pada halaman cetak.** `PrintQr.vue` memakai `bg-background` dan
  `text-muted-foreground`. Bila admin memakai mode gelap, halaman ikut tercetak gelap.
- **Font unduhan.** `Invoice.vue` menarik Inter dari Google Fonts. Sebuah dokumen
  cetak tidak boleh bergantung pada jaringan, jadi diganti sistem dua font milik
  `Reports/Print.vue`. Struk yang sebelumnya memakai Courier New juga ikut,
  dengan `tabular-nums` sebagai pengganti monospace agar angka tetap rata.

Bug lain: emoji pada tombol dan teks di ketiga halaman, `const props` yang tidak
pernah dipakai pada `Invoice.vue` dan `PrintQr.vue`, tombol tanpa `type="button"`
sehingga bersifat submit, serta `break-all` yang memotong nama meja di tengah kata.

### 7.4 Kompresi bukti di sisi server

`DailyExpenseController.php` mendapat method `storeReceipt()` yang dipakai bersama
oleh `store()` dan `update()`. Gambar dikecilkan ke sisi terpanjang 1600 piksel dan
disimpan sebagai JPEG kualitas 80. Latar putih diisikan lebih dulu supaya PNG atau
WebP transparan tidak berubah menjadi hitam saat dikonversi.

Kompresi di browser tetap dipertahankan sebagai penahan pertama agar tidak kena
batas `client_max_body_size` nginx. Yang di server menutup jalur lain: permintaan
yang tidak lewat form, atau JavaScript yang mati.

Dipakai GD yang sudah menyatu dengan PHP, bukan menambah dependency. Bila nanti
butuh koreksi orientasi EXIF atau watermark, barulah pindah ke Intervention Image.

Pastikan ekstensi GD aktif di server:

```bash
php -m | grep -i gd
```

Bila kosong, aktifkan `extension=gd` pada `php.ini` lalu muat ulang PHP-FPM.

---

## 8. Pemeriksaan Ulang, 6 Agustus 2026

Hasil menelusuri kembali kode yang diklaim pada bagian-bagian di atas.

### 8.1 `window` tidak dapat diakses dari template Vue

Lima tempat memanggil `window` langsung dari template, misalnya
`@click="() =&gt; { window.print() }"`. Template Vue hanya mengenali sekumpulan
global tertentu, dan `window` tidak termasuk. Ekspresinya dikompilasi menjadi
`_ctx.window` yang bernilai `undefined`, sehingga tombolnya melempar TypeError
saat diklik.

| Berkas | Kendali yang mati |
|---|---|
| `Orders/Invoice.vue` | tombol Kembali dan Cetak Ulang |
| `Orders/Receipt.vue` | tombol Kembali dan Cetak Ulang |
| `ManualInvoice/Index.vue` | `@blur` penutup dropdown pencarian item |

Semua dipindahkan ke fungsi di dalam `<script setup>`. Bug ini sudah ada sebelum
pekerjaan kemarin, kemungkinan lolos karena halaman cetak memanggil `window.print()`
sendiri lewat `onMounted`, jadi tombol cetak ulang jarang disentuh. Pemanggilan
`window` di dalam blok `<script>` tidak bermasalah dan tidak diubah.

### 8.2 Karakter non-ASCII yang terlewat

Bagian 7.1 hanya menyisir dua berkas form. Halaman cetak ternyata masih memakai
em dash, titik tengah, dan tanda kali. Pada struk thermal karakter seperti ini
berisiko salah cetak. Diganti ASCII di `Orders/Receipt.vue`, `Orders/Invoice.vue`,
dan `FloorPlan/PrintQr.vue`.

### 8.3 Perapihan filter di layar HP

- **Rentang tanggal.** Di layar sempit ikon kalender bawaan browser terpotong.
  Penyebabnya bukan input tanggalnya, melainkan pemisah "s/d" di tengah yang
  memakan lebar sekitar 32 piksel, sehingga sisa ruang tiap input turun di bawah
  lebar minimum isinya. Pemisah itu dihapus dan diganti label kecil "Dari" dan
  "Sampai" di atas masing-masing input. Keduanya tetap bersampingan bahkan di
  layar 320px, dan sekarang justru lebih jelas mana tanggal awal dan mana akhir.
  Tiap input juga diberi `aria-label`. Berlaku di `Orders/Index.vue`,
  `Sales/Index.vue`, `Cashflow/Index.vue`, dan `Reports/Index.vue`.
- **Teks dropdown tertutup panah.** Kelas `.filter-select` menggambar panah sebagai
  gambar latar di posisi `right 0.5rem`, tetapi paddingnya hanya `0.75rem` di kedua
  sisi. Akibatnya pilihan berteks panjang seperti "Semua Pembayaran" tercetak
  menembus panah. Ditambahkan `padding-right: 2rem` pada aturannya. Kelas ini
  disalin di 12 berkas, jadi perbaikannya diterapkan ke semuanya.
- **Ikon kalender ganda.** `ExpenseBook/Index.vue` menempelkan ikon `Calendar`
  sendiri di sebelah kiri input, padahal `type="date"` sudah membawa ikon bawaan
  di kanan. Ikon manual dihapus.
- **Tombol unduh Excel.** `Reports/Index.vue` memakai grid dua kolom, sedangkan
  jumlah tombol mengikuti jumlah segmen. Tiga tombol menyisakan satu yatim dan
  label panjang seperti "Makanan & Minuman" terpotong. Di HP kini satu kolom.

---

### 8.4 Catatan Terbuka Baru

Komponen `components/FilterSelect.vue` sudah ada, tetapi aturan CSS `.filter-select`
tetap disalin utuh di 11 halaman. Bagian 3 `rule.md` mewajibkan komponen yang dipakai
lebih dari satu tempat dipindah ke `components/shared/`. Belum dikerjakan karena
menyentuh 11 berkas sekaligus, sementara perbaikan panah tadi sudah cukup mengatasi
gejalanya.

---

## 9. Keputusan yang Diambil

- `FloorPlan/PrintQr.vue` dan `FloorPlan/Edit.vue` mengambil QR dari `api.qrserver.com`.
  Dibiarkan. Stiker yang sudah tercetak tidak terpengaruh karena yang tersimpan di
  dalam QR hanyalah URL meja, bukan gambar dari layanan tersebut. Ketergantungan
  internet hanya terjadi saat mencetak stiker baru, dan itu dilakukan dari dashboard
  yang memang sudah online. Bila suatu saat perlu dilepas, `qrcode` di sisi npm
  membuat QR langsung di browser tanpa perubahan backend.
- Berkas bukti lama tidak diproses ulang setelah kompresi server dipasang. Isi
  direktori bukti hanya satu berkas berukuran 299 KB, jadi tidak ada yang perlu
  dihemat.
