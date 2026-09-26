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

---
---

# Dokumentasi Perubahan â€” Landing Page

Catatan pekerjaan yang dilakukan pada tanggal 8 sampai 9 Agustus 2026.

---

## 1. Latar Belakang

Sebelumnya `/` hanya mengalihkan ke `/admin/dashboard`, yang berarti pengunjung
yang belum masuk selalu mendarat di halaman login. Berkas
`resources/views/welcome.blade.php` (225 baris, bawaan Laravel) tidak dipakai
route mana pun.

Sudah ada CMS landing per merek (`/p/{brandSlug}`, `LandingPageController@show`,
`Landing/Show.vue`, tabel `landing_settings` dan `landing_sections`), tetapi tidak
ada seeder untuk kedua tabel itu. Akibatnya `/p/spot-duren-tiga` selalu menjawab
404 karena `isPublished()` tidak pernah terpenuhi. Yang dibangun di sesi ini adalah
landing statis terpisah, bukan menyentuh CMS tersebut.

---

## 2. Route dan Alur Masuk

**`routes/web.php`**

- `/` sekarang ke `LandingPageController@home`, dinamai `home`, diletakkan bersama
  route publik lain di bagian atas berkas.
- Komentar lama yang menyebut "jatuh ke login jika landing belum dipublish" dibuang
  karena sudah tidak menggambarkan perilaku sebenarnya.

**`app/Http/Controllers/Auth/LoginController.php`**

`destroy()` sebelumnya mengalihkan ke `/login`, sehingga setelah keluar pengguna
terlempar kembali ke formulir masuk. Sekarang ke `route('home')`.

`EnsureAuthenticated` sengaja tidak diubah â€” halaman admin memang harus meminta
login.

Alur yang berlaku sekarang:

| Aksi | Tujuan |
|---|---|
| Buka `/` | Landing, tanpa autentikasi |
| Keluar (logout) | Landing |
| Buka `/admin/*` tanpa sesi | `/login` |
| Masuk berhasil | `/admin/dashboard` |

---

## 3. Controller

**`LandingPageController@home`**

Mengambil merek dari `config('app.landing_brand')`, dan bila kosong memakai merek
aktif pertama berdasarkan id. Merender `Landing/Home` dengan `brand`, `whatsappUrl`,
dan `loginUrl`. Semua akses properti memakai tanda tanya panah supaya aman ketika
belum ada merek aktif.

**`buildWhatsappUrl()`**

Menormalkan nomor telepon menjadi format yang diterima `wa.me`: karakter non-digit
dibuang, awalan `0` diganti `62`, lalu ditempeli pesan awal yang sudah terisi.
Mengembalikan `null` bila nomor kosong, dan tombol pada landing otomatis berubah
menjadi tautan ke bagian daftar unit.

**`config/app.php`**

Kunci baru `landing_brand` dari environment `LANDING_BRAND`. Kosong berarti memakai
merek aktif pertama.

```
LANDING_BRAND=spot-duren-tiga
```

```
php artisan config:clear
```

---

## 4. Komponen ScrollOrb

Titik awalnya adalah komponen React shadcn `landing-page.tsx` beserta dependensi
`globe.tsx`. Proyek ini memakai Vue 3 dan Inertia, jadi berkas tersebut tidak bisa
dipakai langsung dan seluruhnya ditulis ulang sebagai Vue SFC.

Berkas: `resources/js/components/ui/scroll-orb/ScrollOrb.vue` dan `index.ts`,
mengikuti pola folder yang sudah dipakai `ui/badge`.

### 4.1 Perbedaan dari Versi Asal

| Versi React | Yang dipakai di sini | Alasan |
|---|---|---|
| React hooks | `script setup` dan `computed` | Proyek ini Vue |
| Globe bumi dengan gambar dari `r2.dev` | Stik PS berupa SVG sebaris | Bumi tidak berkaitan dengan rental PS, dan gambar eksternal menambah titik gagal |
| `min-h-screen`, satuan `vh` | `min-h-dvh`, satuan `dvh` | Bagian 4 `rule.md` |
| `onClick: console.log` | Tautan `a href` ke WhatsApp dan jangkar | Landing memerlukan tautan sungguhan |
| `lerp`, `parsePercent`, `navLabelTimeoutRef`, `NodeJS.Timeout` | dibuang | `lerp` tidak pernah dipanggil di berkas asalnya |
| Label navigasi lewat `animate-fadeOut` | Label section aktif saja, `lg:` ke atas | Keyframe `fadeOut` tidak pernah terpakai, dan tidak bergantung hover |
| `opacity-100` yang ditulis tetap | IntersectionObserver | Di versi asal kelas itu statis, jadi tidak pernah menganimasikan apa pun |

### 4.2 Pelacakan Scroll

`measure()` menghitung `scrollProgress` untuk bilah kemajuan, lalu menentukan
`sectionOffset` sebagai posisi pecahan antar section. Nilai 1.4 berarti 40 persen
perjalanan dari section kedua ke ketiga. Satuannya adalah jarak antar titik tengah
section.

Semula posisi stik langsung mengunci ke section aktif lalu diperhalus dengan
transisi CSS 1400ms. Hasilnya justru selalu tertinggal dari scroll. Sekarang
transisi CSS dibuang dan posisi diinterpolasi per frame dengan `lerp`, ditambah
`easeInOut` di kedua ujung agar tidak berhenti mendadak saat scroll cepat.

Pendengar scroll memakai opsi passive dan dibatasi `requestAnimationFrame`.
Semuanya dilepas pada `onBeforeUnmount`.

### 4.3 Reveal Konten

IntersectionObserver dengan threshold 0.2. Section yang sudah tampil langsung
di-unobserve sehingga tidak dianimasikan ulang saat scroll balik.

### 4.4 Tipe

Ditambahkan ke `resources/js/types/index.d.ts` sesuai bagian 2 `rule.md`, bukan
inline di komponen: `OrbPosition`, `ScrollSectionAction`, `ScrollSection`.

---

## 5. Stik PS 3D

Bentuk dasarnya diambil dari `ps-controller-svgrepo-com.svg` milik pengguna.
Empat path siluet (badan atas, dua pegangan, penghubung bawah) disimpan sebagai
konstanta `PAD_SILHOUETTE`.

### 5.1 Cara Kesan 3D Dibentuk

Tanpa three.js dan tanpa dependensi baru:

- **Ekstrusi.** Siluet yang sama dirender 22 kali, masing-masing digeser
  `translateZ` sebesar 2.2px ke belakang dengan `brightness` menurun. Total
  ketebalan 48px.
- **Taper.** `PAD_EXTRUDE_TAPER` mengecilkan tiap lapisan 0.42 persen. Tanpa ini
  semua lapisan identik dan bentuknya terbaca sebagai balok lurus, bukan benda
  bervolume.
- **Perspektif.** `perspective: 1400px` pada `.pad-stage` dan `transform-style:
  preserve-3d` pada elemen di dalamnya.
- **Parallax kontrol.** Kontrol yang menonjol diangkat ke `translateZ(10px)`
  sehingga bergeser relatif terhadap bodi ketika diputar. Ini isyarat kedalaman
  yang paling menentukan, karena mata menilai volume dari pergeseran relatif antar
  bagian, bukan dari gradien.

### 5.2 Pemisahan Lapisan Berdasarkan Bentuk Fisik

Bagian yang cekung atau rata dengan bodi tetap berada di bidang bodi, agar
bayangannya tidak melayang: sumur analog, rim, bayangan kontak, touchpad, lubang
speaker, lubang mic, port USB-C, dan kilau plastik. Kilau khususnya harus tetap di
bidang bodi supaya tepinya tidak lepas dari siluet saat diputar.

Yang menonjol berada di lapisan terangkat: tutup analog beserta knurling dan
cekungan jempol, D-pad, tutup tombol beserta simbol, tombol Create dan Options,
tombol PS, dan LED indikator pemain.

### 5.3 Rotasi Mengikuti Scroll

Semula yaw dihitung akumulatif, yaitu minus 22 ditambah `sectionOffset` kali 40.
Di section terakhir nilainya mencapai 98 derajat sehingga stik terlihat hampir dari
sisi tipisnya â€” tepat pada bagian penutup yang seharusnya paling kuat.

Sekarang rotasi dipetakan ke seluruh panjang halaman:

```
progress = sectionOffset / (jumlah section - 1)
yaw      = PAD_YAW_START * (1 - progress) + sin(progress * PI) * PAD_YAW_PEAK
pitch    = PAD_PITCH_START menuju PAD_PITCH_END
roll     = sin(progress * PI) * -6
```

Mulai miring 18 derajat, memutar paling jauh sekitar 35 derajat di tengah, lalu
kembali menghadap kamera di section terakhir. Stik mendarat frontal dan besar tepat
ketika tombol pemesanan muncul.

### 5.4 Detail SVG

Analog terdiri dari enam lapis: bayangan kontak, sumur `pad-well` yang gelap di
tengah dan terang di tepi sehingga terasa cekung, rim, tutup bergradien, knurling,
cekungan jempol, dan titik spekular. Tekstur knurling memakai `stroke-dasharray`
2 dan 4.2 pada satu lingkaran, bukan puluhan node path.

Setiap tombol terdiri dari tiga lapis: sumur gelap radius 19.4, tutup bergradien
radius 18.54, dan gloss elips di kiri atas. Ditambah satu `pad-contact` radial di
bawah gugusan tombol dan D-pad, supaya tidak terlihat seperti tempelan datar.

Lightbar memakai `feGaussianBlur` dengan `stdDeviation` 7 pada salinan stroke yang
lebih tebal di belakang stroke tajamnya. Animasi denyut dipasang di grup pembungkus
agar halo dan stroke berdenyut bersama.

Detail lain: touchpad kaca dengan gradien, sheen diagonal, dan garis belah tengah;
grille speaker sepuluh lubang; dua lubang mic; port USB-C di sisi atas; garis
sambungan bodi dengan pegangan; dan highlight di bibir atas bodi.

Warna simbol dipertahankan seperti aslinya (hijau, merah muda, biru) sebagai
satu-satunya aksen non-merah, karena justru itu yang membuatnya langsung dikenali.

---

## 6. Latar Bercahaya

Latar tetap gelap. Stik memiliki lightbar menyala, glow, dan highlight spekular
yang hanya bekerja di atas dasar gelap; latar terang akan mematikan semuanya
sekaligus merusak `.theme-dark` yang sudah menjadi identitas panel admin.

Yang diperbaiki adalah kesan ratanya, memakai empat lapis:

1. Base gradien radial dari atas, biru gelap menuju hampir hitam.
2. Dua sumber cahaya bergerak berlawanan arah mengikuti scroll: merah
   `--color-primary` dan biru PlayStation. Keduanya bersilangan di tengah halaman
   sehingga setiap section memiliki suasana warna berbeda tanpa mengganti tema.
   Biru dipilih sebagai counter-light karena membuat merahnya terasa lebih panas
   dibanding bila berdiri sendiri.
3. Grid 68px dengan opacity 0.045, di-mask agar memudar di tepi. Tanpa grid, glow
   hanya terlihat seperti noda tanpa skala ruang.
4. Vignette menggelapkan tepi sehingga perhatian tertarik ke tengah.

Glow digeser dengan `translate3d` pada dua div, bukan dengan mengubah posisi
gradien. Mengubah posisi gradien akan memaksa browser melukis ulang gradien
seukuran viewport penuh setiap frame, dan itu berat di perangkat mobile.

---

## 7. Tipografi

Ditemukan masalah lama: `--font-sans: 'Inter'` sudah tertulis di blok `@theme` pada
`resources/css/app.css`, tetapi Inter tidak pernah dimuat di mana pun. Seluruh
aplikasi sejak awal jatuh ke font sistem.

- `resources/views/app.blade.php` memuat Inter dan Space Grotesk dengan
  `preconnect` dan `display=swap`.
- `resources/css/app.css` menambahkan `--font-display` berisi Space Grotesk.
  Badan teks tetap Inter agar angka dan tabel pada panel admin tidak berubah bentuk.

---

## 8. Tata Letak

- Lebar teks dibatasi `max-w-prose`. Sebelumnya `max-w-5xl`, yang membuat satu baris
  bisa mencapai sekitar 120 karakter.
- Judul memakai `text-balance`, deskripsi memakai `text-pretty`.
- Posisi stik diatur per section: teks rata kiri mendapat stik di kanan, teks rata
  tengah mendapat stik di belakang teks.
- Scrim gradien menjaga kontras teks saat stik lewat di belakangnya.
- Di layar kecil stik diredam menjadi opacity 70 persen dan skala 0.42 agar teks
  menang.
- Nominal memakai `tabular-nums` sesuai bagian 4 `rule.md`.
- Navigasi titik di kanan tidak bergantung hover, dan label hanya tampil untuk
  section aktif.

---

## 9. Halaman Landing

`resources/js/pages/Landing/Home.vue` berisi empat section: hero, unit dan harga,
fasilitas, serta lokasi. Aksi utama mengarah ke WhatsApp dengan pesan yang sudah
terisi, dan aksi kedua ke jangkar daftar unit atau ke halaman masuk staf.

---

## 10. Yang Sengaja Tidak Dikerjakan

- **Formulir pemesanan di landing.** Memakai WhatsApp lebih dahulu. Formulir berarti
  perlu validasi slot, penanganan bentrok jadwal, dan notifikasi. Ditambahkan bila
  volume pesanan sudah ramai.
- **Galeri foto dan testimoni.** Belum ada asetnya.
- **three.js dengan model GLB.** Batas dari cara sekarang: sisi belakang stik tidak
  bisa dilihat dan tidak ada pantulan lingkungan. Tersedia slot bernama `orb` bila
  suatu saat ingin diganti WebGL tanpa menyentuh logika scroll.
- **Bayangan jatuh di lantai dan pantulan lingkungan.** Memerlukan bidang lantai
  terpisah.
- **Partikel bergerak dan noise film.** Grid sudah memberi tekstur; partikel mudah
  membuat tampilan ramai.
- **Animasi berbasis `animation-timeline`.** Dukungan browser belum merata.

---

## 11. Keputusan yang Diambil

- Landing ini statis di dalam kode, terpisah dari CMS `/admin/landing` yang isinya
  berasal dari basis data. Keduanya dibiarkan hidup berdampingan agar CMS yang sudah
  ada tidak rusak. Bila nanti landing ini perlu bisa disunting dari panel,
  `Home.vue` disambungkan ke `landing_sections`.
- Tulisan SONY pada berkas SVG asal (empat path berwarna abu-abu) dibuang. Itu merek
  dagang dan tidak diperlukan pada landing rental.
- Properti `filter` tidak boleh dipasang pada elemen `preserve-3d` karena browser
  akan meratakan konteks 3D-nya dan ekstrusi menjadi gepeng. Drop-shadow dipasang
  pada SVG terdalam, dan sudah ditinggalkan komentar di tempatnya agar tidak
  dipindah kembali.
- `welcome.blade.php` dibiarkan. Berkas itu memang tidak dipakai route mana pun,
  tetapi menghapusnya di luar lingkup pekerjaan ini.
- Kolom boolean `is_published` pada `landing_settings` dan `is_visible` pada
  `landing_sections` sudah tidak dibaca sama sekali; controller hanya memakai
  bitmask. Belum dibereskan.

---

## 12. Catatan Terbuka

- **Harga dan jam operasional pada `Home.vue` masih angka karangan** (Rp 10.000 per
  jam, PS5 VIP Rp 20.000, PS4 Rp 7.000, paket nobar Rp 90.000 per 6 jam, buka 10.00
  sampai 24.00, fasilitas parkir dan kantin). Semuanya harus diganti dengan data
  sebenarnya sebelum landing dipakai pengunjung.
- Jumlah node path untuk sisi ekstrusi ada 88. Bila terasa berat di perangkat lama,
  turunkan `PAD_EXTRUDE_LAYERS` ke 14 dan naikkan `PAD_EXTRUDE_STEP` ke 3.4;
  ketebalan tetap 48px dengan sisi yang sedikit lebih kasar.
- Dua kolom boolean mati pada tabel landing, seperti dicatat di bagian 11.
- Foto ruangan asli akan lebih menaikkan daya tarik dibanding penyesuaian gradien
  lanjutan, khususnya sebagai latar section fasilitas dan lokasi.

