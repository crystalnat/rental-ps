<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { ArrowLeft, Printer } from 'lucide-vue-next'

interface Table {
    id: number
    name: string
    qr_url: string
}

defineProps<{
    store: { id: number; name: string; slug: string }
    floor: { id: number; name: string }
    tables: Table[]
}>()

function qrImageUrl(url: string, size = 120) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(url)}`
}

// QR diambil dari layanan eksternal, cetak harus menunggu semua gambar selesai
// dimuat kalau tidak sebagian kotak QR keluar kosong di kertas
function waitForImages(): Promise<unknown> {
    const images = Array.from(document.querySelectorAll('img'))
    return Promise.all(images.map(img => img.complete
        ? Promise.resolve()
        : new Promise(resolve => {
            img.addEventListener('load', resolve, { once: true })
            img.addEventListener('error', resolve, { once: true })
        })))
}

function doPrint() {
    waitForImages().then(() => window.print())
}
</script>

<template>
    <div class="min-h-screen bg-background print:min-h-0 print:bg-white">
        <!-- Header: tampil di layar, sembunyi saat print -->
        <header class="print:hidden border-b bg-card px-4 py-3">
            <div class="mx-auto flex max-w-5xl items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="`/admin/stores/${store.id}/floor-plan/${floor.id}`">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                            Kembali
                        </Button>
                    </Link>
                    <span class="text-sm text-muted-foreground">
                        {{ store.name }} - {{ floor.name }} - {{ tables.length }} meja
                    </span>
                </div>
                <Button @click="doPrint">
                    <Printer class="h-4 w-4" />
                    Cetak QR
                </Button>
            </div>
        </header>

        <!-- Area cetak -->
        <main class="qr-print mx-auto max-w-5xl px-4 py-6 print:py-0 print:px-0">
            <!-- Judul cetak -->
            <div class="qr-doc-head mb-6 text-center print:mb-4">
                <h1 class="qr-title text-xl font-semibold print:text-lg">
                    QR Order - {{ store.name }}
                </h1>
                <p class="qr-sub mt-1 text-sm text-muted-foreground">
                    {{ floor.name }} - {{ tables.length }} meja
                </p>
                <p class="qr-sub mt-0.5 text-xs text-muted-foreground">
                    Scan untuk order di meja. Cetak, potong, tempel di masing-masing meja.
                </p>
            </div>

            <!-- Grid QR: 4 kolom untuk cetak A4, cocok untuk ditempel di meja -->
            <div
                v-if="tables.length > 0"
                class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 print:grid-cols-4 print:gap-4"
            >
                <div
                    v-for="t in tables"
                    :key="t.id"
                    class="qr-card flex flex-col items-center rounded-xl border-2 border-border bg-white p-4 print:rounded-lg print:p-4 print:break-inside-avoid"
                >
                    <div class="mb-2 flex h-28 w-28 shrink-0 items-center justify-center rounded-lg border bg-white p-2 print:h-24 print:w-24">
                        <img
                            :src="qrImageUrl(t.qr_url, 300)"
                            :alt="`QR ${t.name}`"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <p class="qr-table-name text-center text-base">
                        Meja {{ t.name }}
                    </p>
                    <p class="qr-sub mt-0.5 text-center text-[10px] text-muted-foreground break-words max-w-full print:text-[9px]">
                        Scan untuk order
                    </p>
                </div>
            </div>
            <div
                v-else
                class="rounded-xl border-2 border-dashed py-12 text-center text-muted-foreground"
            >
                <p class="font-medium">Belum ada meja di lantai ini.</p>
                <p class="mt-1 text-sm">Tambah meja di editor denah terlebih dahulu.</p>
            </div>
        </main>
    </div>
</template>

<style>
/* Font sistem saja, halaman cetak tidak boleh bergantung pada unduhan font.
   Serif untuk judul, sans untuk isi. */
.qr-print {
    --font-heading: 'Georgia', 'Cambria', 'Times New Roman', serif;
    --font-body: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
    font-variant-numeric: tabular-nums;
}

.qr-title {
    font-family: var(--font-heading);
    font-weight: 700;
    letter-spacing: 0;
}

.qr-table-name {
    font-family: var(--font-body);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.qr-sub { font-family: var(--font-body); }

@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    html, body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Halaman cetak selalu terang, jangan ikut tema gelap aplikasi */
    .qr-print { max-width: none !important; color: #111827 !important; }
    .qr-print .qr-sub { color: #4b5563 !important; }
    .qr-card {
        background: #ffffff !important;
        border-color: #9ca3af !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    /* Judul dokumen diulang tidak mungkin tanpa tabel, minimal jangan terpotong */
    .qr-doc-head { break-inside: avoid; page-break-inside: avoid; }

    /* Kotak QR dan garis potong wajib ikut tercetak */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>