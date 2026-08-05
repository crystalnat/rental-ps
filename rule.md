# Development Rules — Travel System

Perjanjian kerja antara developer dan AI assistant dalam membangun project ini.

---

## 1. Clean Code

- Nama variabel, fungsi, dan komponen harus deskriptif dan jelas, tanpa singkatan ambigu.
- Satu fungsi hanya melakukan satu hal (single responsibility).
- Tidak ada dead code, console.log sisa debug, atau kode yang di-comment tanpa alasan.
- Hindari magic number — gunakan konstanta bernama.

## 2. Arsitektur Rapi

- Setiap fitur mengikuti struktur yang sudah ada: `modules/[nama]/[nama].routes.ts + controller.ts + service.ts`.
- Frontend: logika API di layer `api/`, business logic di dalam page atau custom hook, bukan langsung di komponen UI.
- Tidak ada logika bisnis di dalam komponen presentasi.
- Tipe harus selalu didefinisikan di `types/index.ts`, tidak inline di komponen.
- Database query hanya boleh ada di `*.service.ts`, tidak di controller atau routes.

## 3. Scalable Build

- Komponen yang dipakai lebih dari satu tempat wajib dipindah ke `components/shared/`.
- Tidak ada hardcode URL, konfigurasi, atau credential di dalam kode — semua lewat environment variable.
- Backend response selalu menggunakan format standar: `{ success, message, data }`.
- Setiap endpoint baru wajib dilindungi `authMiddleware` jika bersifat admin.
- Prisma schema adalah sumber kebenaran tunggal — frontend types harus sinkron dengan schema.

## 4. Responsive di Semua Device

- Setiap halaman wajib tampil rapi di semua ukuran layar, dari HP kecil (lebar 320px) sampai desktop lebar.
- User mobile hanya boleh scroll atas-bawah. Tidak boleh ada scroll horizontal di level halaman.
- Data table lebar tidak boleh diselesaikan dengan `overflow-x-auto`. Di bawah breakpoint `md`, tabel diganti daftar kartu vertikal (`<div class="md:hidden">` berisi kartu, `<div class="hidden md:block">` berisi tabel), memakai loop data yang sama tanpa menduplikasi logic.
- Jika kolom tabel disembunyikan per breakpoint, header dan sel body harus disembunyikan bersamaan. Kolom yang disembunyikan datanya diselipkan sebagai baris kecil di kolom pertama agar informasi tidak hilang.
- Chart wajib `responsive: true` dan `maintainAspectRatio: false` dengan tinggi bertingkat. Di layar sempit, legenda dipindah ke bawah, ukuran font sumbu diperkecil, jumlah label dibatasi, dan nominal disingkat.
- Dialog memakai `w-[95vw]` dengan `max-w-*` supaya tidak menempel ke tepi layar sempit. Grup tombol menumpuk vertikal di HP.
- Tinggi viewport memakai `dvh`, bukan `vh`, karena bilah alamat browser mobile mengubah tinggi area terlihat.
- Elemen interaktif tidak boleh bergantung pada hover saja, karena perangkat sentuh tidak punya hover.
- Teks panjang diberi `min-w-0` dengan `break-words` atau `truncate`; nominal dan angka memakai `tabular-nums` dan `whitespace-nowrap`.

## 5. Tanpa Emoji

- Tidak ada emoji di dalam kode, komentar, template pesan bot, log, atau response API.
- Teks UI boleh ekspresif, tapi tetap profesional dan tanpa karakter non-ASCII dekoratif.

## 6. Komentar Efisien

- Komentar hanya ditulis jika kode tidak bisa menjelaskan dirinya sendiri.
- Gunakan komentar untuk menjelaskan MENGAPA, bukan APA yang dilakukan kode.
- Section divider (`// ===`) boleh dipakai secukupnya untuk memisahkan blok logis yang panjang.
- Tidak ada komentar TODO yang dibiarkan tanpa tindak lanjut.

## 7. Testing oleh Developer

- AI tidak perlu menjalankan testing, build, dev server, atau TypeScript compilation check.
- Semua pengujian dan validasi dilakukan secara manual oleh developer.
- AI cukup fokus pada penulisan kode yang benar dan sesuai aturan di atas.

## 8. Tanpa Akses CMD

- AI tidak boleh menjalankan command apapun di terminal (npm install, mkdir, copy, dll).
- AI cukup memberikan instruksi yang jelas agar developer menjalankannya sendiri.
- Jika ada command yang perlu dijalankan, tulis dalam format code block yang siap di-copy-paste.

## 9. Hemat Token

- AI harus seefisien mungkin dalam penggunaan token.
- Hindari membaca ulang file yang sudah pernah dibaca kecuali benar-benar diperlukan.
- Jawaban harus ringkas dan langsung ke inti, tanpa penjelasan berlebihan.
- Jangan mengulangi informasi yang sudah disampaikan sebelumnya.

---

*Aturan ini berlaku untuk seluruh kode yang ditulis dalam project ini, baik oleh developer maupun AI.*
