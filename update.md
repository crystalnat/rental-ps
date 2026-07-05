# Update Log

---

## Catatan Penting: Konfigurasi Production Server

### Info Server
| Item | Value |
|------|-------|
| Domain | `http://spodurentiga.com` |
| App directory | `/var/www/spodurentiga/` |
| Web root | `/var/www/spodurentiga/public` |
| PHP version | PHP 8.3 (php8.3-fpm) |
| Web server | Nginx |
| OS user (web) | `www-data` |
| DB driver | SQLite |
| DB path | `/var/www/spodurentiga/database/database.sqlite` |
| Session driver | `database` |
| Queue driver | `database` |
| APP_DEBUG | `true` (production) |

### Hal Penting yang Perlu Diingat

**1. Database adalah SQLite, bukan MySQL**
- Semua migration yang pakai `ALTER TABLE` atau `MODIFY COLUMN` wajib punya blok `sqlite` tersendiri
- SQLite tidak support `ALTER COLUMN` — harus recreate tabel (CREATE new → INSERT → DROP → RENAME)
- SQLite tidak enforce ENUM — tipe TEXT, value apapun bisa masuk
- Saat bikin migration yang ubah skema, selalu tambahkan handling untuk ketiga driver: `mysql`, `pgsql`, `sqlite`

**2. Ada dua direktori di server — jangan salah masuk**
- `/var/www/html1/` → direktori lama/kosong, BUKAN app yang aktif
- `/var/www/spodurentiga/` → app yang aktif dan dipakai production
- Nginx config di `/etc/nginx/sites-enabled/spodurentiga.com` point ke `/var/www/spodurentiga/public`

**3. Error log production**
- Log ada di: `/var/www/spodurentiga/storage/logs/laravel.log`
- Ignition **tidak** terinstall (pakai `composer install --no-dev`), jadi browser hanya tampil halaman 500 generik
- Untuk debug, selalu cek log langsung: `tail -n 50 /var/www/spodurentiga/storage/logs/laravel.log`

**4. Git pull perlu safe.directory**
- Tiap `git pull` dari root di direktori ini perlu jalankan dulu:
  ```bash
  git config --global --add safe.directory /var/www/spodurentiga
  ```
- Setelah itu baru `git pull origin main`

**5. Permission database SQLite**
- File database harus owned by `www-data`:
  ```bash
  chown www-data:www-data /var/www/spodurentiga/database/database.sqlite
  chmod 664 /var/www/spodurentiga/database/database.sqlite
  ```
- Direktori `database/` juga harus writable oleh `www-data`

**6. Urutan deploy ke production**
```bash
git config --global --add safe.directory /var/www/spodurentiga
cd /var/www/spodurentiga
git pull origin main
php artisan migrate
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

## 2026-06-07

### Bug Fix: 500 Error saat Submit Pesanan dari QR Code

**Root Cause:**
Migration `2026_03_18_081638_add_brand_id_to_customers_table.php` tidak punya handling untuk SQLite ketika membuat kolom `customers.email` jadi nullable. Migration hanya handle MySQL dan PostgreSQL, sehingga di production SQLite kolom `email` tetap `NOT NULL`. Ketika customer submit pesanan tanpa isi email, Laravel insert `null` ke kolom tersebut dan SQLite lempar error:

```
SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: customers.email
```

**Fix:**
Buat migration baru: `database/migrations/2026_06_07_000002_fix_customers_email_nullable_sqlite.php`

Karena SQLite tidak support `ALTER COLUMN`, migration ini recreate tabel `customers` dengan cara:
1. `PRAGMA foreign_keys = OFF`
2. `CREATE TABLE customers_new` dengan kolom `email` nullable
3. `INSERT INTO customers_new SELECT ... FROM customers`
4. `DROP TABLE customers`
5. `ALTER TABLE customers_new RENAME TO customers`
6. Buat ulang index `UNIQUE (brand_id, email)`
7. `PRAGMA foreign_keys = ON`

**File yang dibuat:**
- `database/migrations/2026_06_07_000002_fix_customers_email_nullable_sqlite.php`

**Deploy ke production:**
```bash
git config --global --add safe.directory /var/www/spodurentiga
git pull origin main
php artisan migrate
```

**Verifikasi fix:**
```bash
php artisan tinker --execute="
\$cols = DB::select('PRAGMA table_info(customers)');
foreach(\$cols as \$c) {
    if(\$c->name === 'email') {
        echo 'email notnull=' . \$c->notnull . PHP_EOL;
    }
}
"
# Output yang benar: email notnull=0
```

---

### Migration Tambahan (sebelumnya di sesi ini)

**File:** `database/migrations/2026_06_07_000001_add_manual_type_to_orders.php`

Tambah value `'manual'` ke ENUM `orders.type` untuk MySQL (dibutuhkan oleh `ManualInvoiceController`). SQLite tidak perlu karena tipe disimpan sebagai TEXT.
