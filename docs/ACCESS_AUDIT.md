# Audit Akses Menu POS

## Perbandingan Sidebar vs Route Middleware

| Menu | Path | Sidebar Roles | Route Middleware | Status |
|------|------|---------------|------------------|--------|
| Dashboard | /admin/dashboard | owner, admin, cashier, staff | owner, admin, cashier, staff | ✓ |
| Kasir | /admin/cashier | owner, admin, cashier | owner, admin, cashier | ✓ |
| Riwayat Penjualan | /admin/orders | owner, admin, cashier | owner, admin, cashier | ✓ |
| Produk | /admin/products, /admin/stores/{id}/products | owner, admin | owner, admin / owner | ✓ |
| Kategori | /admin/categories | owner, admin | owner, admin | ✓ |
| Inventaris | /admin/inventory | owner, admin | owner, admin | ✓ |
| Cashflow | /admin/cashflow | owner, admin, cashier | owner, admin, cashier | ✓ |
| Pembukuan Harian | /admin/expense-book | owner, admin, cashier | owner, admin, cashier | ✓ |
| Laporan | /admin/reports | owner, admin | owner, admin | ✓ |
| Pelanggan | /admin/customers | owner, admin | owner, admin | ✓ |
| Karyawan | /admin/users | owner, admin | owner, admin | ✓ |
| Cabang | /admin/stores | owner | owner | ✓ |
| Denah Meja | /admin/stores/{id}/floor-plan | owner | owner | ✓ |
| Pengaturan | /admin/settings | owner, admin | owner, admin | ✓ |

## Peran (Roles)

- **owner**: Full access, termasuk Cabang & Denah Meja
- **admin**: Semua kecuali Cabang & Denah Meja
- **cashier**: Dashboard, Kasir, Riwayat Penjualan, Cashflow, Pembukuan Harian
- **staff**: Hanya Dashboard

## Perbaikan yang Telah Diterapkan

1. **Dashboard**: Middleware eksplisit `owner,admin,cashier,staff` telah ditambahkan
