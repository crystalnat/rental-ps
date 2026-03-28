# 📘 Dokumentasi Pengembangan Fitur POS App (Sesi Maret 2026)

Dokumentasi ini mencatat pembaruan sistem POS yang dilakukan pada sesi pengembangan terbaru, meliputi implementasi **Landing Page CMS** dan **Optimasi Laporan**.

---

## 1. Optimasi Export Laporan
Sesuai dengan standarisasi laporan profesional, sistem kini berfokus pada format dokumen yang memiliki struktur kaya data (Rich Data).

### Perubahan Utama:
- **Penghapusan Format CSV**: Fitur export ke CSV telah dihapus dari halaman Laporan Analitas untuk menyeragamkan output data.
- **Fokus XLSX & PDF**: Sistem kini hanya menyediakan format **XLSX** (untuk pengolahan data spreadsheet lanjut) dan **PDF** (untuk dokumen siap cetak/audit).
- **Keamanan Data**: Proses export tetap dilakukan secara *client-side* untuk menjaga performa server tetap stabil saat menangani data transaksi besar.

---

## 2. Optimasi Data: Bit Packing
Sistem CMS kini mengadopsi teknik **Bit Packing** untuk menyimpan berbagai pengaturan (flags/toggles) dalam satu kolom integer tunggal.

### Mengapa Bit Packing?
Teknik ini mengompresi beberapa data Boolean atau Enum (yang biasanya memakan 1 byte/lebih di memori DB) menjadi hanya **1-bit** per data. Dengan menggabungkan hingga 32 flag dalam satu kolom `unsigned integer`, kita menghemat penggunaan storage dan memori server hingga **75-90%** untuk kolom pengaturan.

### Implementasi pada CMS:
- **`landing_settings`**: Menyimpan status `Published`, `Sticky Header`, `Show Social Header`, dll dalam bitmask 32-bit.
- **`landing_sections`**: Menyimpan status `Visible`, `Full Width`, `Dark Background`, dan `Reverse Grid` dalam bitmask 32-bit.

### Contoh Teknis:
Jika kita memiliki 4 pengaturan aktif: `Visible (1)`, `Full Width (2)`, `Dark BG (4)`, `Reverse (8)`.
- **Dulu**: Menggunakan 4 kolom `boolean` (4 Byte).
- **Sekarang**: Disimpan dalam satu nilai integer `15` (`00001111` bin) -> Hanya **1 Byte** untuk menyimpan 4 data sekaligus.
- **Operasi Bitwise**: Pencarian dan filter dilakukan langsung di level database menggunakan `whereRaw` dengan operator bitwise `&`, sehingga sangat cepat dan efisien.### Kamus Bit (Bitmask Reference)
Untuk memudahkan pengembang lain dalam memodifikasi atau menambah fitur, berikut adalah referensi bitmask yang digunakan:

#### 1. `landing_settings` (settings_bitmask)
| Bit | Value | Variable | Deskripsi |
|---|---|---|---|
| 0 | 1 | `FLAG_PUBLISHED` | Status tayang landing page secara publik. |
| 1 | 2 | `FLAG_SHOW_HEADER_LOGO` | Menampilkan/sembunyikan logo di navigasi atas. |
| 2 | 4 | `FLAG_SHOW_FOOTER_SOCIAL` | Menampilkan/sembunyikan ikon sosial di footer. |
| 3 | 8 | `FLAG_STICKY_HEADER` | Mengaktifkan posisi header tetap saat scroll. |

#### 2. `landing_sections` (section_bitmask)
| Bit | Value | Variable | Deskripsi |
|---|---|---|---|
| 0 | 1 | `FLAG_VISIBLE` | Menyembunyikan atau menampilkan section ini. |
| 1 | 2 | `FLAG_FULL_WIDTH` | Mengabaikan container max-width (layout full). |
| 2 | 4 | `FLAG_DARK_BG` | Mengaktifkan warna background gelap untuk section. |
| 3 | 8 | `FLAG_REVERSE_GRID` | Menukar posisi gambar dan teks (kiri vs kanan). |

---

## 8. Landing Page CMS (Company Profile)
Fitur ini memungkinkan setiap brand memiliki halaman Company Profile publik yang dapat dikustomisasi sepenuhnya dari Admin Panel tanpa menyentuh kode.

### A. Arsitektur Data
Sistem menggunakan dua tabel utama:
- **`landing_settings`**: Menyimpan konfigurasi global per Brand (nama situs, tagline, warna tema, font, link sosial media, SEO meta, dan status publish).
- **`landing_sections`**: Menyimpan konten per section (hero, about, services, gallery, testimonials, contact) dengan kolom `items` (JSON) untuk data dinamis dan `config` (JSON) untuk pengaturan layout.

### B. Section yang Tersedia
| Section | Konten yang Bisa Diedit |
|---|---|
| **Hero Banner** | Judul, subjudul, deskripsi, gambar background, layout (tengah/kiri), overlay opacity |
| **Tentang Kami** | Judul, deskripsi, gambar, layout gambar (kiri/kanan/full) |
| **Layanan** | Judul, daftar kartu layanan (judul, deskripsi, ikon) -- bisa ditambah/hapus |
| **Galeri** | Upload gambar dengan caption -- bisa ditambah/hapus |
| **Testimoni** | Daftar ulasan (nama, jabatan, isi, rating bintang) -- bisa ditambah/hapus |
| **Kontak** | Alamat, telepon, email, WhatsApp, Google Maps embed |

### C. Theming & Branding
Admin dapat mengatur:
- **5 Warna Tema**: Primary, Secondary, Accent, Background, Text -- semua dengan color picker.
- **2 Font**: Heading dan Body -- dipilih dari Google Fonts (Inter, Poppins, Outfit, dll).
- **Logo & Favicon**: Upload langsung dari CMS.
- **Social Links**: Instagram, Facebook, WhatsApp, TikTok.
- **SEO**: Meta Title dan Meta Description untuk optimasi mesin pencari.

### D. Alur Kerja CMS
1.  **Admin Panel**: Akses via menu **Manajemen > Landing Page** (`/admin/landing`).
2.  **Edit Section**: Klik section yang ingin diedit, ubah konten, lalu klik **Simpan Section**.
3.  **Atur Tema**: Pindah ke tab **Pengaturan & Tema** untuk mengubah warna, font, dan branding.
4.  **Publish/Unpublish**: Toggle status publish. Halaman publik hanya bisa diakses jika status **Published**.
5.  **URL Publik**: Halaman dapat diakses di `/p/{brand-slug}` (contoh: `/p/kopi-nusantara`).

### E. Keunggulan untuk Pemasaran
- Setiap klien (Brand) mendapat landing page sendiri yang 100% berbeda konten dan tampilannya.
- Tidak perlu developer untuk mengubah konten -- semua bisa dilakukan dari Admin Panel.
- SEO-ready dengan meta title, meta description, dan semantic HTML.

---

## 9. Persiapan Deployment (Checklist)

*   **Database**: Jalankan `php artisan migrate` untuk mengaktifkan tabel feedback, notifikasi, landing page, dan kolom target penjualan.
*   **Optimization**: Jalankan `php artisan config:cache` dan `php artisan route:cache` di production.
*   **Storage Link (PENTING)**: Jalankan `php artisan storage:link`. Tanpa perintah ini, gambar Hero Banner, Gallery, dan Logo yang di-upload via CMS akan menghasilkan error 404 karena file fisik tidak terhubung secara publik.
*   **Backup**: Sangat disarankan menginstal package `spatie/laravel-backup` dan menjadwalkan backup database harian (00:00) ke cloud storage (Google Drive/S3).
*   **Environment**: Pastikan `CACHE_DRIVER` dikonfigurasi (default: `file`, disarankan: `redis` jika tersedia di server Hostinger) untuk kestabilan polling notifikasi.
