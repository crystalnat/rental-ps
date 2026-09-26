# Prompt Video Marketing SPOT Duren Tiga (Remotion, HDR, 30 detik)

Salin prompt di bawah ke Claude Code / AI coding agent yang membuat project Remotion.

---

## PROMPT

Buatkan project Remotion (TypeScript, Remotion v4 terbaru) untuk video marketing 30 detik bagi "SPOT", rental PS5 di Duren Tiga, Jakarta Selatan. Target audiens: dua kelompok sekaligus. (1) Gamer muda yang mencari tempat main. (2) Pemilik rental PS lain yang tertarik dengan sistem operasional SPOT. Bahasa video: Indonesia, gaya santai, singkat, tanpa kata klise.

### Spesifikasi teknis
- Komposisi 1920x1080, 30 fps, total 900 frame (30 detik). Sediakan juga komposisi vertikal 1080x1920 untuk Reels/TikTok dengan konten sama (layout menyesuaikan, bukan sekadar crop).
- Output HDR: render dengan `colorSpace: 'bt2020-ncl'` (setara `--color-space=bt2020-ncl`), codec `h265`, 10-bit bila didukung. Verifikasi dukungan HDR di versi Remotion yang terpasang dan tuliskan perintah render final di README. Gunakan warna dan gradasi yang memanfaatkan rentang dinamis lebih lebar (highlight terang untuk neon/layar TV, hitam pekat untuk latar) dan sediakan fallback SDR (`h264`, `bt709`) karena tidak semua platform menampilkan HDR.
- Semua animasi dengan `useCurrentFrame`, `interpolate`, `spring`, dan `<Sequence>`. Tanpa CSS transition atau animation. Pisahkan tiap adegan jadi komponen sendiri, dan simpan teks dan angka di satu file data (`content.ts`) supaya mudah diedit.
- Sinkronkan potongan adegan dengan beat BGM (lihat bagian audio). Tulis konstanta BPM dan frame per beat di satu tempat.
- Font: system font atau font lokal yang dimuat lewat `@remotion/fonts` / `staticFile`. Tanpa Google Fonts remote.
- Tidak ada em dash di teks video.

### Identitas visual (samakan dengan landing SPOT)
- Palet: krem hangat `#f3eee8`, hitam tinta `#16130e`, satu aksen oranye `#ff5a1f`. Untuk HDR, oranye aksen dan elemen layar dibuat lebih terang dari putih SDR (highlight).
- Tipografi tebal, besar, rapat, dengan kotak highlight oranye di kata kunci (contoh: "Main PS5 mulai [Rp10 ribu] per jam.").
- Objek utama: kontroler PS bergaya 3D (boleh pakai `@remotion/three` dengan model prosedural, atau ilustrasi vektor bergerak). Kontroler berpindah antar adegan seperti di landing page.
- Logo teks "SPOT" dan tagline "Rental PS5 Duren Tiga".

### Struktur adegan (storyboard, 30 detik)

| Waktu | Adegan | Isi layar | Teks (Indonesia) |
|---|---|---|---|
| 0-3 dtk | Hook | Kontroler 3D berputar masuk ke tengah, harga muncul dengan efek pukulan | "Main PS5 mulai Rp10 ribu per jam." |
| 3-8 dtk | Pengalaman pemain | Cuplikan ilustrasi ruangan: TV besar, AC, stik, chip game (EA FC, GTA V, Tekken 8, God of War) muncul bergantian sesuai beat | "AC dingin. TV besar. Stik terawat. Ratusan jam main." |
| 8-11 dtk | Harga | Empat kartu: PS5 Reguler Rp10.000/jam, PS5 VIP Rp20.000/jam, PS4 Rp7.000/jam, Paket Nobar Rp90.000/6 jam | "Pilih unit, bayar per jam." |
| 11-13 dtk | Transisi ke sisi bisnis | Layar terbelah: sisi kiri pemain, sisi kanan dashboard admin. Musik naik satu tingkat | "Di balik layar, semuanya otomatis." |
| 13-26 dtk | Keunggulan sistem (5 adegan, tiap ~2,6 dtk) | Mockup UI dashboard SPOT (gambar ulang sederhana, bukan screenshot asli data) dengan callout | lihat daftar di bawah |
| 26-30 dtk | CTA | Kontroler di tengah, logo SPOT, alamat, jam buka (Senin-Kamis 11.00-23.00, Jumat-Minggu 11.00-02.00). Fitur booking WhatsApp sedang dinonaktifkan, jangan tampilkan tombol atau ajakan booking | "Datang langsung ke SPOT Duren Tiga. Senin-Kamis 11.00-23.00, Jumat-Minggu 11.00-02.00." |

### Keunggulan sistem (lima adegan bisnis, urut)
1. **Timer sewa otomatis + kontrol colokan pintar (Tuya).** Waktu mulai dan berhenti tercatat, colokan unit menyala dan mati sendiri, tidak ada lagi lupa mematikan konsol. Teks: "Timer jalan sendiri. Colokan mati sendiri."
2. **Kasir cepat dengan shift dan refund.** Kasir buka dan tutup shift, terima pembayaran, perpanjang sewa, dan proses refund dengan jejak yang jelas. Teks: "Kasir, shift, dan refund. Semua tercatat."
3. **Pesan lewat QR di meja.** Pelanggan scan QR di unit, pesan makanan dan minuman tanpa login, pesanan masuk ke kasir. Teks: "Scan QR. Pesan snack tanpa panggil kasir."
4. **Multi-cabang dan stok.** Satu sistem untuk banyak brand dan toko, inventori per toko, purchase order ke supplier, catatan pengeluaran harian dengan foto nota. Teks: "Banyak cabang. Satu dashboard. Stok terpantau."
5. **Laporan dan hak akses.** Dashboard analitik, laporan yang bisa diekspor ke Excel dan PDF, peran owner, admin, kasir, dan staf dengan akses berbeda. Teks: "Laporan siap ekspor. Akses sesuai peran."

Setiap adegan bisnis: satu kalimat judul besar, satu ikon atau mockup bergerak, dan satu angka atau kata kunci yang disorot dengan kotak oranye. Jangan menampilkan data pelanggan atau angka penjualan asli.

### Audio (BGM)
- Remotion tidak menghasilkan musik. Gunakan `<Audio src={staticFile('bgm.mp3')} />` dan siapkan file BGM berlisensi aman untuk komersial (mis. Pixabay Music, YouTube Audio Library, atau hasil generator seperti Suno/ElevenLabs Music dengan lisensi komersial). Catat sumber dan lisensinya di README.
- Karakter musik: elektronik energik bergaya synthwave/future bass ringan, terasa arcade modern, 120 BPM, kunci mayor, berdurasi 30 detik atau lebih dan bisa di-loop. Struktur: intro 0-3 dtk dengan hit impact, groove ringan 3-11 dtk, build-up 11-13 dtk, drop penuh 13-26 dtk dengan pergantian adegan tepat di beat (120 BPM berarti satu beat sama dengan 15 frame di 30 fps), outro dengan riser lalu satu hit akhir di 27 dtk yang diikuti ruang untuk CTA.
- Sound effect kecil (whoosh saat transisi, klik saat kartu muncul, "ding" saat harga muncul) boleh ditambah sebagai `<Audio>` terpisah dengan volume rendah.
- Mixing: BGM volume 0.6, fade in 10 frame dan fade out 30 frame di akhir, gunakan `interpolate` pada `volume`.

### Hasil yang diharapkan
- Folder `remotion/` (atau project terpisah) berisi `src/Root.tsx`, komposisi `SpotPromo` (16:9) dan `SpotPromoVertical` (9:16), komponen tiap adegan, `content.ts`, dan `README.md`.
- Skrip di `package.json`: `preview` (Remotion Studio), `render:hdr`, `render:sdr`, `render:vertical`.
- README menjelaskan cara mengganti teks, harga, nomor WhatsApp, dan file BGM, serta cara mengecek bahwa file hasil benar-benar HDR (mis. `ffprobe` menampilkan `bt2020` dan `smpte2084` atau `arib-std-b67`).
- Jalankan `npx remotion render` untuk satu frame uji dan satu render penuh, lalu laporkan hasil, durasi, ukuran file, dan masalah yang tersisa secara jujur. Jangan mengklaim HDR berfungsi sebelum diverifikasi.

---

## Catatan untukmu

- **HDR:** dukungan HDR Remotion bergantung versi dan codec, dan banyak platform (Instagram, TikTok) menurunkannya ke SDR. Karena itu prompt meminta versi SDR juga.
- **Musik:** aku tidak bisa membuat file musiknya. Pakai musik berlisensi bebas atau generator musik, lalu taruh sebagai `public/bgm.mp3` di project Remotion.
- **Klaim fitur:** semua keunggulan di atas berasal dari fitur yang ada di kode SPOT. Kalau ada yang belum dipakai di tempatmu (misalnya kontrol Tuya belum terpasang), hapus adegannya supaya iklan tidak menjanjikan hal yang belum ada.
- **Gambar game:** cover game tidak dipakai di video, cukup nama game sebagai teks supaya tidak ada masalah hak cipta.
