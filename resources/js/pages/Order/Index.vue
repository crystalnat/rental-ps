<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatCurrency } from '@/lib/utils'
import {
    ShoppingCart, Plus, Minus, UtensilsCrossed, Coffee, CupSoda,
    Cookie, IceCream, Pizza, Salad, Sandwich, Wine, Search,
    Banknote, Smartphone,
} from 'lucide-vue-next'

const CATEGORY_ICONS: Record<string, typeof UtensilsCrossed> = {
    minuman: CupSoda,
    makanan: UtensilsCrossed,
    kopi: Coffee,
    coffee: Coffee,
    snack: Cookie,
    dessert: IceCream,
    es: IceCream,
    pizza: Pizza,
    salad: Salad,
    sandwich: Sandwich,
    roti: Sandwich,
    wine: Wine,
    jajanan: Cookie,
}

function getCategoryIcon(categoryName: string | null) {
    if (!categoryName) return UtensilsCrossed
    const lower = categoryName.toLowerCase()
    for (const [key, icon] of Object.entries(CATEGORY_ICONS)) {
        const term = key.replace('_', ' ')
        if (lower.includes(term) || lower.includes(key)) return icon
    }
    return UtensilsCrossed
}

interface Product {
    id: number
    name: string
    category_id: number | null
    category_name: string | null
    category_color: string | null
    unit: string
    track_stock: boolean
    current_stock: number
    sell_price: number
    image_url: string | null
}

interface Category {
    id: number
    name: string
    color: string | null
}

interface CartItem {
    product_id: number
    name: string
    unit: string
    sell_price: number
    quantity: number
    notes?: string
}

interface PlacedOrderItem {
    name: string
    quantity: number
    subtotal: number
}

interface PlacedOrder {
    id: number
    order_code: string
    status: string
    payment_status: string
    payment_method: string | null
    final_amount: number
    created_at: string
    items: PlacedOrderItem[]
}

const props = defineProps<{
    store: { id: number; name: string; slug: string }
    table: { id: number; name: string }
    products: Product[]
    categories: Category[]
    orders: PlacedOrder[]
    qris_payment?: { name: string; qrcode_image: string | null } | null
}>()

const ORDER_STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu diproses',
    processing: 'Sedang disiapkan',
    ready: 'Siap disajikan',
    done: 'Selesai',
}

function orderStatusLabel(status: string) {
    return ORDER_STATUS_LABELS[status] ?? status
}

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success as string | undefined)
const flashOrderPaymentMethod = computed(() => page.props.flash?.order_payment_method as string | undefined)
const flashOrderFinalAmount = computed(() => (page.props.flash?.order_final_amount as number) ?? 0)

const searchQuery = ref('')
const categoryFilter = ref<string>('all')
const cart = ref<CartItem[]>([])
const showCheckout = ref(false)
const checkoutForm = ref({
    payment_method: 'cash' as 'cash' | 'qris',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    notes: '',
})
const submitting = ref(false)

const filteredProducts = computed(() => {
    let list = props.products
    const q = searchQuery.value.toLowerCase().trim()
    if (q) {
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                (p.category_name?.toLowerCase().includes(q) ?? false),
        )
    }
    if (categoryFilter.value !== 'all') {
        const catId = Number(categoryFilter.value)
        list = list.filter((p) => p.category_id === catId)
    }
    return list
})

const subtotal = computed(() =>
    cart.value.reduce((sum, i) => sum + i.sell_price * i.quantity, 0),
)

function addToCart(product: Product, qty = 1) {
    const existing = cart.value.find((c) => c.product_id === product.id)
    const newQty = (existing?.quantity ?? 0) + qty
    if (product.track_stock && newQty > product.current_stock) return
    if (existing) {
        existing.quantity = newQty
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            unit: product.unit,
            sell_price: product.sell_price,
            quantity: qty,
        })
    }
}

function removeFromCart(productId: number, qty = 1) {
    const idx = cart.value.findIndex((c) => c.product_id === productId)
    if (idx < 0) return
    const item = cart.value[idx]
    item.quantity -= qty
    if (item.quantity <= 0) cart.value.splice(idx, 1)
}

function openCheckout() {
    if (cart.value.length === 0) return
    showCheckout.value = true
}

function submitOrder() {
    if (cart.value.length === 0) return
    const { customer_name, customer_email, customer_phone, notes } = checkoutForm.value
    if (!customer_name?.trim() || !customer_phone?.trim()) return

    submitting.value = true
    router.post('/order', {
        store_id: props.store.id,
        table_id: props.table.id,
        payment_method: checkoutForm.value.payment_method,
        customer_name: customer_name.trim(),
        customer_email: customer_email.trim(),
        customer_phone: customer_phone.trim(),
        notes: notes.trim() || null,
        items: cart.value.map((i) => ({
            product_id: i.product_id,
            quantity: i.quantity,
            notes: i.notes || null,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = []
            showCheckout.value = false
            checkoutForm.value = {
                payment_method: 'cash',
                customer_name: '',
                customer_email: '',
                customer_phone: '',
                notes: '',
            }
        },
        onError: (errors) => {
            alert(Object.values(errors).flat().join('\n'))
        },
        onFinish: () => {
            submitting.value = false
        },
    })
}
</script>

<template>
    <Head :title="`Order — ${store.name} Meja ${table.name}`" />
    <div class="min-h-screen bg-background pb-24">
        <!-- Header -->
        <header class="sticky top-0 z-10 border-b bg-card px-4 py-3">
            <div class="mx-auto max-w-2xl">
                <h1 class="text-lg font-semibold">{{ store.name }}</h1>
                <p class="text-sm text-muted-foreground">Meja {{ table.name }}</p>
            </div>
        </header>

        <!-- Success message -->
        <div
            v-if="flashSuccess"
            class="mx-4 mt-3 space-y-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            <p>{{ flashSuccess }}</p>
            <div
                v-if="flashOrderPaymentMethod === 'qris' && qris_payment?.qrcode_image"
                class="flex flex-col items-center gap-2 rounded-lg bg-white p-4 dark:bg-zinc-900"
            >
                <p class="font-medium">Scan QRIS untuk membayar</p>
                <img
                    :src="qris_payment.qrcode_image"
                    alt="QRIS"
                    class="h-48 w-48 rounded-lg border object-contain"
                />
                <p class="text-xs text-muted-foreground">{{ formatCurrency(flashOrderFinalAmount) }}</p>
            </div>
        </div>

        <!-- Pesanan yang sudah masuk (bertahan setelah refresh) -->
        <section v-if="orders.length > 0" class="mx-4 mt-3 space-y-2">
            <h2 class="text-sm font-semibold">Pesanan Anda</h2>
            <div
                v-for="ord in orders"
                :key="ord.id"
                class="rounded-lg border bg-card p-3 text-sm"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ ord.order_code }}</span>
                    <span class="text-xs text-muted-foreground">{{ ord.created_at }}</span>
                </div>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ orderStatusLabel(ord.status) }} &middot;
                    {{ ord.payment_status === 'paid' ? 'Lunas' : 'Belum dibayar' }}
                </p>
                <ul class="mt-2 space-y-0.5">
                    <li
                        v-for="(item, idx) in ord.items"
                        :key="idx"
                        class="flex justify-between gap-2 text-xs"
                    >
                        <span>{{ item.quantity }}x {{ item.name }}</span>
                        <span>{{ formatCurrency(item.subtotal) }}</span>
                    </li>
                </ul>
                <div class="mt-2 flex justify-between border-t pt-2 font-semibold">
                    <span>Total</span>
                    <span>{{ formatCurrency(ord.final_amount) }}</span>
                </div>
            </div>
        </section>

        <!-- Search & Category filter -->
        <div class="sticky top-14 z-10 space-y-2 border-b bg-background px-4 py-2">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Cari produk..."
                    class="h-10 pl-9"
                />
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                type="button"
                class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="categoryFilter === 'all' ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/80'"
                @click="categoryFilter = 'all'"
            >
                Semua
            </button>
            <button
                v-for="cat in categories"
                :key="cat.id"
                type="button"
                class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="categoryFilter === String(cat.id) ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/80'"
                @click="categoryFilter = String(cat.id)"
            >
                {{ cat.name }}
            </button>
            </div>
        </div>

        <!-- Products -->
        <main class="mx-auto max-w-2xl px-4 py-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="p in filteredProducts"
                    :key="p.id"
                    class="flex gap-3 rounded-xl border bg-card p-3"
                >
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-muted flex items-center justify-center">
                        <img
                            v-if="p.image_url"
                            :src="p.image_url"
                            :alt="p.name"
                            class="h-full w-full object-cover"
                        />
                        <component
                            v-else
                            :is="getCategoryIcon(p.category_name)"
                            class="h-10 w-10 text-muted-foreground"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ p.name }}</p>
                        <p class="text-sm font-semibold text-primary">{{ formatCurrency(p.sell_price) }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full border bg-background hover:bg-muted"
                                :disabled="(cart.find(c => c.product_id === p.id)?.quantity ?? 0) <= 0"
                                @click="removeFromCart(p.id, 1)"
                            >
                                <Minus class="h-4 w-4" />
                            </button>
                            <span class="min-w-[1.5rem] text-center text-sm font-medium">
                                {{ cart.find(c => c.product_id === p.id)?.quantity ?? 0 }}
                            </span>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full border bg-primary text-primary-foreground hover:bg-primary/90"
                                :disabled="p.track_stock && ((cart.find(c => c.product_id === p.id)?.quantity ?? 0) >= p.current_stock)"
                                @click="addToCart(p, 1)"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="filteredProducts.length === 0" class="py-12 text-center text-muted-foreground">
                <UtensilsCrossed class="mx-auto mb-2 h-12 w-12 opacity-50" />
                <p>Tidak ada produk tersedia.</p>
            </div>
        </main>

        <!-- Cart bar (fixed bottom) -->
        <div
            v-if="cart.length > 0"
            class="fixed bottom-0 left-0 right-0 z-20 border-t bg-card p-4"
        >
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">{{ cart.length }} item</p>
                    <p class="text-lg font-bold">{{ formatCurrency(subtotal) }}</p>
                </div>
                <Button @click="openCheckout" class="shrink-0">
                    <ShoppingCart class="h-4 w-4" />
                    Order Sekarang
                </Button>
            </div>
        </div>

        <!-- Checkout modal -->
        <Teleport to="body">
            <div
                v-if="showCheckout"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                @click.self="showCheckout = false"
            >
                <div
                    class="w-full max-w-md rounded-t-2xl bg-background p-6 pb-8 shadow-xl sm:rounded-2xl sm:pb-6"
                    @click.stop
                >
                    <h2 class="mb-4 text-lg font-semibold">Data Pemesan</h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Pilih metode pembayaran dan isi data untuk melanjutkan pesanan.
                    </p>
                    <form class="space-y-4" @submit.prevent="submitOrder">
                        <div>
                            <Label class="mb-2 block">Metode Pembayaran</Label>
                            <div class="flex gap-2">
                                <label
                                    class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border-2 p-3 transition-colors"
                                    :class="checkoutForm.payment_method === 'cash' ? 'border-primary bg-primary/5' : 'border-muted hover:border-muted-foreground/50'"
                                >
                                    <input v-model="checkoutForm.payment_method" type="radio" value="cash" class="sr-only" />
                                    <Banknote class="h-5 w-5" />
                                    <span class="text-sm font-medium">Tunai (Bayar di Kasir)</span>
                                </label>
                                <label
                                    class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border-2 p-3 transition-colors"
                                    :class="checkoutForm.payment_method === 'qris' ? 'border-primary bg-primary/5' : 'border-muted hover:border-muted-foreground/50'"
                                >
                                    <input v-model="checkoutForm.payment_method" type="radio" value="qris" class="sr-only" />
                                    <Smartphone class="h-5 w-5" />
                                    <span class="text-sm font-medium">QRIS</span>
                                </label>
                            </div>
                            <p
                                v-if="checkoutForm.payment_method === 'qris' && !qris_payment?.qrcode_image"
                                class="mt-2 text-xs text-amber-600 dark:text-amber-400"
                            >
                                QRIS dinamis akan ditampilkan setelah pesanan dibuat. (Fitur segera hadir)
                            </p>
                        </div>
                        <div>
                            <Label for="customer_name">Nama</Label>
                            <Input
                                id="customer_name"
                                v-model="checkoutForm.customer_name"
                                type="text"
                                placeholder="Nama lengkap"
                                class="mt-1.5"
                                required
                            />
                        </div>
                        <div>
                            <Label for="customer_email">Email <span class="text-muted-foreground">(opsional)</span></Label>
                            <Input
                                id="customer_email"
                                v-model="checkoutForm.customer_email"
                                type="email"
                                placeholder="email@contoh.com"
                                class="mt-1.5"
                            />
                        </div>
                        <div>
                            <Label for="customer_phone">No. Telepon</Label>
                            <Input
                                id="customer_phone"
                                v-model="checkoutForm.customer_phone"
                                type="tel"
                                inputmode="numeric"
                                placeholder="08xxxxxxxxxx"
                                class="mt-1.5"
                                required
                                @input="(e: Event) => { checkoutForm.customer_phone = (e.target as HTMLInputElement).value.replace(/\D/g, '') }"
                            />
                        </div>
                        <div>
                            <Label for="notes">Catatan (opsional)</Label>
                            <Input
                                id="notes"
                                v-model="checkoutForm.notes"
                                type="text"
                                placeholder="Pedes, kurang garam, dll."
                                class="mt-1.5"
                            />
                        </div>
                        <div class="flex gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="flex-1"
                                @click="showCheckout = false"
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                class="flex-1"
                                :disabled="submitting || !checkoutForm.customer_name?.trim() || !checkoutForm.customer_phone?.trim()"
                            >
                                {{ submitting ? 'Memproses...' : 'Kirim Pesanan' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
