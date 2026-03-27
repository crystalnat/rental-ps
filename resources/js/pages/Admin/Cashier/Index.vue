<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatCurrency } from '@/lib/utils'
import CashierFloorPlan from '@/components/CashierFloorPlan.vue'
import {
    Search, Plus, Minus, Trash2, CreditCard, Banknote, Smartphone,
    ShoppingBag, Mic, MicOff, LayoutGrid, X,
} from 'lucide-vue-next'

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
    discount_percent: number
    image_url: string | null
}

interface Category {
    id: number
    name: string
    color: string | null
}

interface Table {
    id: number
    name: string
    floor: string | number | null
    status: string
}

interface Store {
    id: number
    name: string
    slug: string
}

interface PaymentMethodItem {
    id: number
    name: string
    code: string
    requires_cash_input: boolean
    qrcode_image: string | null
    account_name: string | null
    account_number: string | null
}

interface CartItem {
    product_id: number
    name: string
    unit: string
    sell_price: number
    discount_percent: number
    quantity: number
    notes?: string
}

const props = defineProps<{
    store: Store
    pending_orders_count?: number
    products: Product[]
    categories: Category[]
    tables: Table[]
    stores: Store[]
    payment_methods: PaymentMethodItem[]
    floor_plan?: Array<{
        id: number
        name: string
        width_meters: number
        length_meters: number
        tables: Array<{
            id: number
            name: string
            capacity: number
            x_meters: number
            y_meters: number
            width_meters: number
            length_meters: number
            rotation_deg: number
            shape: string
            active_orders: Array<{
                id: number
                order_code: string
                status: string
                final_amount: number
                items_count: number
                created_at: string
            }>
            has_orders: boolean
        }>
    }>
}>()

const searchQuery = ref('')
const categoryFilter = ref<string>('all')
const cart = ref<CartItem[]>([])
const orderType = ref<'dine_in' | 'takeaway' | 'walk_in'>('walk_in')
const selectedTableId = ref<number | null>(null)
const customerName = ref('')
const customerPhone = ref('')
const customerEmail = ref('')
const notes = ref('')
const discountAmount = ref<number | string>('')
const showPaymentDialog = ref(false)
const showFloorPlan = ref(false)
const showMobileCart = ref(false)
const paymentMethod = ref<string>('')
const cashReceived = ref('')
const processing = ref(false)
const isListening = ref(false)
const newOrderNotification = ref<{ order_code: string; table_name?: string; amount: number } | null>(null)
type PendingOrderItem = {
    id: number
    order_code: string
    table_name?: string
    customer_name?: string
    customer_email?: string
    customer_phone?: string
    notes?: string
    final_amount: number
    created_at: string
}
const pendingOrdersList = ref<PendingOrderItem[]>([])
const selectedPendingOrder = ref<PendingOrderItem | null>(null)
const payForm = ref({ payment_method: '', cash_received: '' })
const payProcessing = ref(false)
let pendingOrdersPollInterval: ReturnType<typeof setInterval> | null = null
const sttError = ref<string | null>(null)

type SpeechRecognitionCtor = new () => {
    start: () => void
    stop: () => void
    continuous: boolean
    interimResults: boolean
    lang: string
    onstart: (() => void) | null
    onend: (() => void) | null
    onerror: (() => void) | null
    onresult: ((e: { results: { 0?: { 0?: { transcript?: string } } } }) => void) | null
}
const SpeechRecognition = typeof window !== 'undefined'
    && ((window as unknown as { SpeechRecognition?: SpeechRecognitionCtor; webkitSpeechRecognition?: SpeechRecognitionCtor }).SpeechRecognition
        || (window as unknown as { webkitSpeechRecognition?: SpeechRecognitionCtor })['webkitSpeechRecognition'])
const sttSupported = !!SpeechRecognition

let recognition: InstanceType<NonNullable<typeof SpeechRecognition>> | null = null

const NUM_WORDS: Record<string, number> = {
    satu: 1, dua: 2, tiga: 3, empat: 4, lima: 5,
    enam: 6, tujuh: 7, delapan: 8, sembilan: 9, sepuluh: 10,
}
const FILLER_START = /^(terus|dan|sama|lagi|yang|tolong|mau|minta|saya|mohon|kasih|porsi|gelas|piring|mangkok)\s+/i
const FILLER_END = /\s+(terus|dan|sama|lagi|yang|tolong|mau|minta|porsi|gelas|piring|mangkok)$/i
const SPLIT_BY = /\s+(dan|sama|lagi|,)\s+/i

/** Antara qty (1–2 digit) dan nama item berikut — tanpa "dan". */
const BETWEEN_QTY_AND_NEXT = /(?<=\d)\s+(?=\p{L})/gu

/** Antara bilang angka (satu…sepuluh) dan nama item berikut. */
const BETWEEN_WORD_QTY_AND_NEXT = new RegExp(
    String.raw`(?<=\b(?:satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\b)\s+(?=\p{L})`,
    'giu',
)

function splitChainedDigitSegments(t: string): string[] {
    const segments: string[] = []
    let start = 0
    for (const m of t.matchAll(BETWEEN_QTY_AND_NEXT)) {
        const fullBefore = t.slice(0, m.index)
        const digitRun = fullBefore.match(/\d+$/)?.[0]
        if (digitRun && digitRun.length <= 2) {
            segments.push(t.slice(start, m.index).trim())
            start = m.index + m[0].length
        }
    }
    segments.push(t.slice(start).trim())
    return segments.filter(Boolean)
}

function splitChainedWordSegments(t: string): string[] {
    const segments: string[] = []
    let start = 0
    for (const m of t.matchAll(BETWEEN_WORD_QTY_AND_NEXT)) {
        segments.push(t.slice(start, m.index).trim())
        start = m.index + m[0].length
    }
    segments.push(t.slice(start).trim())
    return segments.filter(Boolean)
}

function expandChainedSegments(part: string): string[] {
    let segs = splitChainedDigitSegments(part)
    segs = segs.flatMap((s) => splitChainedWordSegments(s))
    return segs
}

function parseSpeechToItems(text: string): { productName: string; qty: number }[] {
    const parts = text.split(SPLIT_BY).filter((p) => p && !/^(dan|sama|lagi|,)$/i.test(p))
    const items: { productName: string; qty: number }[] = []

    for (let part of parts) {
        part = part.replace(FILLER_START, '').replace(FILLER_END, '').trim()
        if (!part) continue

        const subparts = expandChainedSegments(part)
        for (const raw of subparts) {
            const segment = raw.trim()
            if (!segment) continue

            const numEnd = segment.match(/\s+(\d+)\s*$/)
            const numStart = segment.match(/^(\d+)\s+(.+)$/)
            const wordNumStart = segment.match(/^(satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\s+(.+)$/i)
            let productName: string
            let qty = 1

            if (numEnd) {
                productName = segment.replace(/\s+\d+\s*$/, '').trim()
                qty = parseInt(numEnd[1], 10)
            } else if (numStart) {
                qty = parseInt(numStart[1], 10)
                productName = numStart[2].trim()
            } else if (wordNumStart) {
                qty = NUM_WORDS[wordNumStart[1].toLowerCase()] ?? 1
                productName = wordNumStart[2].trim()
            } else {
                productName = segment
            }

            if (productName && qty > 0) {
                items.push({ productName: productName.toLowerCase(), qty })
            }
        }
    }

    return items
}

function compactLower(s: string): string {
    return s.toLowerCase().replace(/\s+/g, '')
}

function findBestProductMatch(productName: string, products: Product[]): Product | null {
    const words = productName.split(/\s+/).filter((w) => w.length > 0)
    if (words.length === 0) return null

    let best: { product: Product; score: number } | null = null

    for (const p of products) {
        const name = p.name.toLowerCase()
        const cat = (p.category_name ?? '').toLowerCase()
        const searchText = `${name} ${cat}`
        const cq = compactLower(productName)
        const cn = compactLower(name)

        if (name.includes(productName)) {
            const score = 1000 + productName.length
            if (!best || score > best.score) best = { product: p, score }
        } else if (productName.includes(name)) {
            const score = 500 + name.length
            if (!best || score > best.score) best = { product: p, score }
        } else if (cq.length > 0 && cn.length > 0 && (cq === cn || cn.includes(cq) || cq.includes(cn))) {
            const score = 520 + cq.length
            if (!best || score > best.score) best = { product: p, score }
        } else {
            const matchCount = words.filter((w) => searchText.includes(w)).length
            if (matchCount === words.length) {
                const score = 100 + matchCount * 10 - name.length / 100
                if (!best || score > best.score) best = { product: p, score }
            } else if (matchCount > 0) {
                const score = matchCount - name.length / 1000
                if (!best || score > best.score) best = { product: p, score }
            }
        }
    }

    return best?.product ?? null
}

function processSpeechResult(transcript: string) {
    const items = parseSpeechToItems(transcript)
    const added: string[] = []
    const notFound: string[] = []

    for (const { productName, qty } of items) {
        const product = findBestProductMatch(productName, props.products)
        if (product) {
            addToCart(product, qty)
            added.push(`${product.name} × ${qty}`)
        } else {
            notFound.push(productName)
        }
    }

    if (added.length > 0) {
        sttError.value = null
    }
    if (notFound.length > 0) {
        sttError.value = `Tidak ditemukan: ${notFound.join(', ')}`
        setTimeout(() => { sttError.value = null }, 4000)
    }
}

function startListening() {
    if (!recognition) return
    recognition.start()
}

function stopListening() {
    recognition?.stop()
    recognition = null
    isListening.value = false
}

function toggleSpeechToText() {
    if (!sttSupported || !SpeechRecognition) {
        sttError.value = 'Speech-to-Text tidak didukung di browser ini. Gunakan Chrome.'
        return
    }
    if (isListening.value) {
        stopListening()
        return
    }
    sttError.value = null
    recognition = new SpeechRecognition()
    recognition.continuous = true
    recognition.interimResults = false
    recognition.lang = 'id-ID'
    recognition.onstart = () => { isListening.value = true }
    recognition.onend = () => {
        if (isListening.value && recognition) {
            startListening()
        } else {
            isListening.value = false
        }
    }
    recognition.onerror = (e: { error: string }) => {
        if (e.error === 'aborted') return
        if (isListening.value && recognition) {
            sttError.value = 'Gagal mendengarkan. Coba lagi.'
            setTimeout(() => { if (isListening.value) startListening() }, 500)
        }
    }
    recognition.onresult = (event: { resultIndex: number; results: { [i: number]: { isFinal?: boolean; [j: number]: { transcript?: string } } } }) => {
        // Hanya proses hasil BARU — continuous mode mengembalikan seluruh history,
        // jadi pakai resultIndex agar tidak memproses ulang ucapan sebelumnya
        const startIdx = event.resultIndex ?? 0
        for (let i = startIdx; i < event.results.length; i++) {
            const result = event.results[i]
            if (result?.isFinal) {
                const transcript = (result[0] as { transcript?: string })?.transcript?.trim()
                if (transcript) processSpeechResult(transcript)
            }
        }
    }
    startListening()
}

function playNewOrderSound() {
    try {
        const ctx = new AudioContext()
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.connect(gain)
        gain.connect(ctx.destination)
        osc.frequency.value = 800
        osc.type = 'sine'
        gain.gain.setValueAtTime(0.3, ctx.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3)
        osc.start(ctx.currentTime)
        osc.stop(ctx.currentTime + 0.3)
    } catch {
        // Audio not supported
    }
}

async function checkPendingOrders() {
    if (!props.store?.id) return
    try {
        const res = await fetch(`/admin/cashier/pending-orders?store=${props.store.id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        const data = await res.json()
        const count = data.count ?? 0
        const orders = data.orders ?? []
        const prevCount = Number(sessionStorage.getItem('cashier_pending_count') ?? props.pending_orders_count ?? 0)
        sessionStorage.setItem('cashier_pending_count', String(count))
        pendingOrdersList.value = orders
        if (count > prevCount && data.latest) {
            playNewOrderSound()
            newOrderNotification.value = {
                order_code: data.latest.order_code,
                table_name: data.latest.table_name,
                amount: data.latest.final_amount,
            }
            setTimeout(() => { newOrderNotification.value = null }, 3000)
        }
    } catch {
        // Ignore poll errors
    }
}

onMounted(() => {
    sessionStorage.setItem('cashier_pending_count', String(props.pending_orders_count ?? 0))
    void checkPendingOrders()
    pendingOrdersPollInterval = setInterval(checkPendingOrders, 15000)
})

onBeforeUnmount(() => {
    stopListening()
    if (pendingOrdersPollInterval) clearInterval(pendingOrdersPollInterval)
})

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
    cart.value.reduce((sum, i) => {
        const discount = i.discount_percent > 0 ? Math.round(i.sell_price * (i.discount_percent / 100)) : 0
        return sum + (i.sell_price - discount) * i.quantity
    }, 0),
)

const finalAmount = computed(() => {
    return Math.max(0, subtotal.value - (Number(discountAmount.value) || 0))
})

const canCheckout = computed(() => cart.value.length > 0)

function addToCart(product: Product, qty = 1) {
    if (product.track_stock && product.current_stock < qty) return
    const existing = cart.value.find((c) => c.product_id === product.id)
    if (existing) {
        const newQty = existing.quantity + qty
        if (product.track_stock && product.current_stock < newQty) return
        existing.quantity = newQty
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            unit: product.unit,
            sell_price: product.sell_price,
            discount_percent: product.discount_percent,
            quantity: qty,
        })
    }
}

function updateQty(item: CartItem, delta: number) {
    const product = props.products.find((p) => p.id === item.product_id)
    const newQty = Math.max(0, item.quantity + delta)
    if (product?.track_stock && product.current_stock < newQty) return
    item.quantity = newQty
    if (item.quantity <= 0) {
        cart.value = cart.value.filter((c) => c.product_id !== item.product_id)
    }
}

function removeFromCart(item: CartItem) {
    cart.value = cart.value.filter((c) => c.product_id !== item.product_id)
}

const currentPaymentMethod = computed(() =>
    props.payment_methods.find((pm) => pm.code === paymentMethod.value),
)
const requiresCashInput = computed(() => currentPaymentMethod.value?.requires_cash_input ?? false)

function openPaymentDialog() {
    if (!canCheckout.value) return
    if (!paymentMethod.value && props.payment_methods.length > 0) {
        paymentMethod.value = props.payment_methods[0].code
    }
    cashReceived.value = String(finalAmount.value)
    showPaymentDialog.value = true
}

function submitOrder() {
    if (!canCheckout.value) return
    const payload = {
        type: orderType.value,
        table_id: orderType.value === 'dine_in' ? selectedTableId.value : null,
        customer_name: orderType.value === 'dine_in' ? customerName.value.trim() || undefined : undefined,
        customer_phone: orderType.value === 'dine_in' ? customerPhone.value.trim() || undefined : undefined,
        customer_email: orderType.value === 'dine_in' ? customerEmail.value.trim() || undefined : undefined,
        items: cart.value.map((i) => ({
            product_id: i.product_id,
            quantity: i.quantity,
            notes: i.notes,
        })),
        notes: notes.value,
        payment_method: paymentMethod.value,
        cash_received: requiresCashInput.value ? Number(cashReceived.value) || 0 : 0,
        discount_amount: Number(discountAmount.value) || 0,
    }
    router.post(route('admin.cashier.store'), { ...payload, store: props.store.id }, {
        preserveScroll: true,
        onStart: () => { processing.value = true },
        onFinish: () => { processing.value = false },
        onSuccess: () => {
            showPaymentDialog.value = false
            showMobileCart.value = false
            cart.value = []
            notes.value = ''
            discountAmount.value = ''
            selectedTableId.value = null
            customerName.value = ''
            customerPhone.value = ''
            customerEmail.value = ''
        },
    })
}

function changeStore(storeId: number) {
    router.get(route('admin.cashier.index'), { store: storeId })
}

const orderTypeLabel = {
    dine_in: 'Makan di Tempat',
    takeaway: 'Bungkus',
    walk_in: 'Walk-in',
}

function openPayModal(order: PendingOrderItem) {
    selectedPendingOrder.value = order
    payForm.value = {
        payment_method: props.payment_methods[0]?.code ?? 'cash',
        cash_received: String(order.final_amount),
    }
}

function closePayModal() {
    selectedPendingOrder.value = null
}

function getRequiresCashInput() {
    const pm = props.payment_methods.find((p) => p.code === payForm.value.payment_method)
    return pm?.requires_cash_input ?? payForm.value.payment_method === 'cash'
}

function submitPayOrder() {
    const order = selectedPendingOrder.value
    if (!order) return
    payProcessing.value = true
    router.post(route('admin.orders.pay', order.id), {
        payment_method: payForm.value.payment_method,
        cash_received: getRequiresCashInput() ? payForm.value.cash_received : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closePayModal()
            void checkPendingOrders()
        },
        onError: (errors) => {
            alert(Object.values(errors).flat().join('\n'))
        },
        onFinish: () => { payProcessing.value = false },
    })
}

function getPaymentIcon(code: string) {
    if (code === 'cash') return Banknote
    if (code === 'qris') return Smartphone
    return CreditCard
}

const showQrOrAccount = computed(() => {
    const pm = currentPaymentMethod.value
    if (!pm) return null
    if (pm.qrcode_image) return { type: 'qr' as const, image: pm.qrcode_image }
    if (pm.account_name || pm.account_number) {
        return {
            type: 'account' as const,
            name: pm.account_name ?? '',
            number: pm.account_number ?? '',
            code: pm.code,
        }
    }
    return null
})
</script>

<template>
    <AdminLayout :title="`Kasir - ${store.name}`" full-width>
        <!-- Pesanan baru masuk - brief toast + sound -->
        <Teleport to="body">
            <Transition name="slide-down">
                <div
                    v-if="newOrderNotification"
                    class="fixed top-4 left-1/2 z-[100] -translate-x-1/2 rounded-lg border border-green-500 bg-green-100 px-4 py-2 text-sm font-medium text-green-800 dark:bg-green-900 dark:text-green-100"
                >
                    Pesanan baru: {{ newOrderNotification.order_code }} · Meja {{ newOrderNotification.table_name ?? '—' }}
                </div>
            </Transition>
        </Teleport>

        <div class="flex flex-col lg:flex-row h-[calc(100vh-7rem)] gap-4 pb-[72px] lg:pb-0 lg:overflow-hidden relative">
            <!-- Product Grid -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card h-full lg:h-auto">
                <div class="flex flex-col md:flex-row md:items-center gap-2 border-b p-3">
                    <!-- Search & Mic -->
                    <div class="flex flex-1 items-center gap-2 min-w-0">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Cari produk..."
                                class="pl-9 w-full"
                            />
                        </div>
                        <Button
                            v-if="orderType === 'walk_in'"
                            :variant="isListening ? 'default' : 'outline'"
                            size="icon"
                            class="shrink-0"
                            :title="sttSupported ? 'Ucapkan nama produk (Speech-to-Text)' : 'Speech-to-Text tidak didukung'"
                            :disabled="!sttSupported"
                            @click="toggleSpeechToText"
                        >
                            <Mic v-if="!isListening" class="h-4 w-4" />
                            <MicOff v-else class="h-4 w-4 animate-pulse" />
                        </Button>
                    </div>
                    
                    <p v-if="sttError" class="text-xs text-destructive">{{ sttError }}</p>
                    
                    <!-- Filters & Layout (Horizontal Scroll on Mobile) -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                        <select
                            v-model="categoryFilter"
                            class="filter-select flex shrink-0 h-9 rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                        >
                            <option value="all">Semua Kategori</option>
                            <option
                                v-for="c in categories"
                                :key="c.id"
                                :value="String(c.id)"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                        <select
                            v-if="stores.length > 1"
                            :value="store.id"
                            class="filter-select flex shrink-0 h-9 rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                            @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option
                                v-for="s in stores"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0 h-9"
                            title="Lihat denah meja & pesanan"
                            @click="showFloorPlan = true"
                        >
                            <LayoutGrid class="mr-2 h-4 w-4" />
                            Denah Meja
                        </Button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-3">
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7">
                        <button
                            v-for="p in filteredProducts"
                            :key="p.id"
                            type="button"
                            class="group relative flex flex-col items-stretch rounded-xl border bg-background p-2 md:p-3 text-left transition-all hover:bg-accent hover:border-primary/30"
                            :disabled="p.track_stock && p.current_stock <= 0"
                            @click="addToCart(p)"
                        >
                            <div
                                v-if="p.category_color"
                                class="absolute top-0 left-0 h-1 w-full rounded-t-xl"
                                :style="{ backgroundColor: p.category_color }"
                            />
                            <div
                                class="mb-2 aspect-square w-full overflow-hidden rounded-lg bg-muted"
                            >
                                <img
                                    v-if="p.image_url"
                                    :src="p.image_url"
                                    :alt="p.name"
                                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-2xl font-bold text-muted-foreground/40"
                                >
                                    {{ p.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-xs md:text-sm font-semibold leading-tight mb-1">{{ p.name }}</p>
                                <p class="truncate text-[10px] md:text-xs text-muted-foreground whitespace-nowrap">
                                    <span v-if="p.discount_percent > 0" class="line-through text-muted-foreground/50 mr-1">{{ formatCurrency(p.sell_price) }}</span>
                                    <span v-if="p.discount_percent > 0" class="font-bold text-destructive">{{ formatCurrency(p.sell_price - Math.round(p.sell_price * (p.discount_percent / 100))) }}</span>
                                    <span v-else>{{ formatCurrency(p.sell_price) }}</span>
                                    <span v-if="p.discount_percent > 0" class="ml-1 rounded bg-destructive/10 px-1 py-0.5 text-[8px] font-bold text-destructive overflow-hidden">-{{ p.discount_percent }}%</span>
                                </p>
                                <p v-if="p.track_stock" class="truncate text-[9px] md:text-[10px] text-muted-foreground/80 mt-0.5">
                                    Stok: {{ p.current_stock }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dark Overlay for Mobile Cart -->
            <div
                v-if="showMobileCart"
                class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                @click="showMobileCart = false"
            />

            <!-- Right: Pesanan Menunggu + Cart -->
            <div
                :class="[
                    'fixed inset-x-0 bottom-0 z-50 flex h-[85vh] flex-col gap-3 rounded-t-xl bg-background p-4 shadow-2xl transition-transform lg:static lg:z-auto lg:h-auto lg:w-[380px] lg:shrink-0 lg:translate-y-0 lg:rounded-none lg:bg-transparent lg:p-0 lg:shadow-none min-h-0',
                    showMobileCart ? 'translate-y-0' : 'translate-y-full'
                ]"
            >
                <div class="mb-2 flex items-center justify-between lg:hidden">
                    <h2 class="text-lg font-semibold">Keranjang</h2>
                    <Button variant="ghost" size="icon" @click="showMobileCart = false">
                        <X class="h-5 w-5" />
                    </Button>
                </div>
                <!-- Pesanan Menunggu - tinggi tetap, scroll dalam, keranjang dapat sisa ruang -->
                <Card v-if="pendingOrdersList.length > 0" class="shrink-0 max-h-[140px] min-h-0 flex flex-col overflow-hidden">
                    <CardHeader class="shrink-0 px-4 py-2">
                        <CardTitle class="flex items-center justify-between text-sm">
                            <span>Pesanan Menunggu</span>
                            <Badge variant="destructive">{{ pendingOrdersList.length }}</Badge>
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Klik untuk terima pembayaran
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="min-h-0 flex-1 overflow-y-auto p-4 pt-0">
                        <div class="space-y-2">
                            <button
                                v-for="o in pendingOrdersList"
                                :key="o.id"
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border bg-muted/50 px-3 py-2 text-left transition-colors hover:bg-muted"
                                @click="openPayModal(o)"
                            >
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-medium truncate">{{ o.order_code }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Meja {{ o.table_name ?? '—' }} · {{ o.created_at }}
                                    </p>
                                </div>
                                <span class="ml-2 shrink-0 font-semibold text-primary">{{ formatCurrency(o.final_amount) }}</span>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="flex flex-1 min-h-0 flex-col overflow-hidden">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-base">Keranjang</CardTitle>
                        <Badge variant="secondary">{{ cart.length }} item</Badge>
                    </CardHeader>
                    <CardContent class="flex min-h-0 flex-1 flex-col overflow-hidden p-0">
                        <div class="flex gap-2 border-b px-4 pb-3">
                            <select
                                v-model="orderType"
                                class="filter-select flex h-9 flex-1 rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                            >
                                <option value="walk_in">
                                    {{ orderTypeLabel.walk_in }}
                                </option>
                                <option value="dine_in">
                                    {{ orderTypeLabel.dine_in }}
                                </option>
                                <option value="takeaway">
                                    {{ orderTypeLabel.takeaway }}
                                </option>
                            </select>
                            <select
                                v-if="orderType === 'dine_in'"
                                v-model="selectedTableId"
                                class="filter-select flex h-9 min-w-[100px] rounded-md border border-input bg-transparent pl-3 pr-9 py-1 text-sm text-foreground"
                            >
                                <option :value="null">Pilih Meja</option>
                                <option
                                    v-for="t in tables"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="orderType === 'dine_in'" class="space-y-2 border-b px-4 pb-3">
                            <p class="text-xs font-medium text-muted-foreground">Data Pelanggan (opsional — isi HP atau email untuk menyimpan)</p>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <Input
                                    v-model="customerName"
                                    placeholder="Nama"
                                    class="h-9 text-sm"
                                />
                                <Input
                                    v-model="customerPhone"
                                    placeholder="No. HP"
                                    type="tel"
                                    inputmode="numeric"
                                    class="h-9 text-sm"
                                    @input="(e: Event) => { customerPhone = (e.target as HTMLInputElement).value.replace(/\D/g, '') }"
                                />
                                <Input
                                    v-model="customerEmail"
                                    placeholder="Email"
                                    type="email"
                                    class="h-9 text-sm"
                                />
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4">
                            <div
                                v-if="cart.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground"
                            >
                                <ShoppingBag class="mb-2 h-12 w-12 opacity-50" />
                                <p class="text-sm">Keranjang kosong</p>
                                <p class="text-xs">Klik produk untuk menambah</p>
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="item in cart"
                                    :key="item.product_id"
                                    class="flex items-center gap-2 rounded-lg border p-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            <span v-if="item.discount_percent > 0" class="line-through text-muted-foreground/50 mr-1">{{ formatCurrency(item.sell_price) }}</span>
                                            <span v-if="item.discount_percent > 0" class="font-bold text-destructive">{{ formatCurrency(item.sell_price - Math.round(item.sell_price * (item.discount_percent / 100))) }}</span>
                                            <span v-else>{{ formatCurrency(item.sell_price) }}</span>
                                            × {{ item.quantity }} {{ item.unit }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            class="h-7 w-7"
                                            @click="updateQty(item, -1)"
                                        >
                                            <Minus class="h-3 w-3" />
                                        </Button>
                                        <span class="min-w-[2rem] text-center text-sm">{{ item.quantity }}</span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            class="h-7 w-7"
                                            @click="updateQty(item, 1)"
                                        >
                                            <Plus class="h-3 w-3" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-7 w-7 text-destructive"
                                            @click="removeFromCart(item)"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t p-4">
                            <div class="mb-2 flex justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
                            </div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-muted-foreground text-xs">Akan dipotong dari subtotal sebelum pajak/biaya</span>
                            </div>
                            <div class="mb-2 flex items-center justify-between text-sm font-medium">
                                <span>Diskon Tambahan (Rp)</span>
                                <Input
                                    v-model="discountAmount"
                                    type="number"
                                    class="h-8 w-28 text-right text-sm px-2 font-black tabular-nums border-orange-200 focus-visible:ring-orange-500"
                                    placeholder="0"
                                    min="0"
                                />
                            </div>
                            <div class="mb-3 flex justify-between font-bold text-lg mt-3 pt-3 border-t">
                                <span>Total Tagihan</span>
                                <span class="text-primary tabular-nums">{{ formatCurrency(finalAmount) }}</span>
                            </div>
                            <textarea
                                v-model="notes"
                                placeholder="Catatan pesanan..."
                                rows="2"
                                class="mb-3 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <Button
                                class="w-full"
                                size="lg"
                                :disabled="!canCheckout"
                                @click="openPaymentDialog"
                            >
                                Bayar {{ formatCurrency(finalAmount) }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Mobile Footer -->
        <div class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between border-t bg-background p-3 shadow-[0_-4px_10px_-2px_rgba(0,0,0,0.1)] lg:hidden">
            <div class="flex flex-col">
                <span class="text-xs font-medium text-muted-foreground">{{ cart.length }} Item</span>
                <span class="text-base font-bold text-primary">{{ formatCurrency(finalAmount) }}</span>
            </div>
            <Button size="lg" class="shadow-sm" @click="showMobileCart = true">
                <ShoppingBag class="mr-2 h-4 w-4" /> Keranjang
            </Button>
        </div>

        <!-- Payment Dialog -->
        <Dialog v-model:open="showPaymentDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Pembayaran</DialogTitle>
                    <DialogDescription>
                        Pilih metode pembayaran dan masukkan jumlah uang tunai jika perlu.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <p class="mb-2 text-sm font-medium">Metode Pembayaran</p>
                        <p v-if="payment_methods.length === 0" class="text-sm text-muted-foreground">
                            Belum ada metode pembayaran. Atur di <a href="/admin/settings" class="text-primary underline">Pengaturan</a>.
                        </p>
                        <div v-else class="grid grid-cols-2 gap-2">
                            <button
                                v-for="pm in payment_methods"
                                :key="pm.code"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border p-3 text-left transition-colors"
                                :class="paymentMethod === pm.code ? 'border-primary bg-primary/5' : 'border-input'"
                                @click="paymentMethod = pm.code"
                            >
                                <component :is="getPaymentIcon(pm.code)" class="h-4 w-4" />
                                {{ pm.name }}
                            </button>
                        </div>
                    </div>
                    <div v-if="showQrOrAccount?.type === 'qr'" class="flex justify-center rounded-lg border bg-muted/30 p-4">
                        <img
                            :src="showQrOrAccount.image"
                            alt="QR Code"
                            class="max-h-48 w-auto"
                        />
                    </div>
                    <div v-else-if="showQrOrAccount?.type === 'account'" class="rounded-lg border bg-muted/30 p-4">
                        <p class="text-sm font-medium">
                            {{ showQrOrAccount.code === 'bank_transfer' ? 'Transfer Bank' : 'E-Wallet' }}
                        </p>
                        <div class="mt-3 space-y-2">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    {{ showQrOrAccount.code === 'bank_transfer' ? 'Nomor Rekening' : 'Nomor HP / ID' }}
                                </p>
                                <p class="font-mono text-lg font-semibold">{{ showQrOrAccount.number || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    {{ showQrOrAccount.code === 'bank_transfer' ? 'Nama Rekening' : 'Nama (a.n.)' }}
                                </p>
                                <p class="font-medium">{{ showQrOrAccount.name || '-' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="requiresCashInput">
                        <p class="mb-2 text-sm font-medium">Uang Diterima</p>
                        <Input
                            v-model="cashReceived"
                            type="number"
                            placeholder="0"
                            min="0"
                            step="0.01"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Kembalian: {{ formatCurrency(Math.max(0, (Number(cashReceived) || 0) - finalAmount)) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-muted p-3">
                        <p class="text-sm text-muted-foreground">Total Dibayar</p>
                        <p class="text-2xl font-black text-primary">{{ formatCurrency(finalAmount) }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showPaymentDialog = false">
                        Batal
                    </Button>
                    <Button
                        :disabled="processing || payment_methods.length === 0 || (requiresCashInput && (Number(cashReceived) || 0) < finalAmount)"
                        @click="submitOrder"
                    >
                        {{ processing ? 'Memproses...' : 'Selesaikan Pembayaran' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Terima Pembayaran (pesanan dari QR) -->
        <Dialog :open="!!selectedPendingOrder" @update:open="(v) => { if (!v) closePayModal() }">
            <DialogContent class="max-w-sm" v-if="selectedPendingOrder">
                <DialogHeader>
                    <DialogTitle>Terima Pembayaran</DialogTitle>
                    <DialogDescription>
                        {{ selectedPendingOrder.order_code }} · Meja {{ selectedPendingOrder.table_name ?? '—' }} · {{ formatCurrency(selectedPendingOrder.final_amount) }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2 rounded-lg border bg-muted/50 p-3 text-sm">
                    <p v-if="selectedPendingOrder.customer_name">
                        <span class="font-medium text-muted-foreground">Atas nama:</span> {{ selectedPendingOrder.customer_name }}
                    </p>
                    <p v-if="selectedPendingOrder.customer_email">
                        <span class="font-medium text-muted-foreground">Email:</span> {{ selectedPendingOrder.customer_email }}
                    </p>
                    <p v-if="selectedPendingOrder.customer_phone">
                        <span class="font-medium text-muted-foreground">No. Telp:</span> {{ selectedPendingOrder.customer_phone }}
                    </p>
                    <p v-if="selectedPendingOrder.notes">
                        <span class="font-medium text-muted-foreground">Catatan:</span> {{ selectedPendingOrder.notes }}
                    </p>
                    <p v-if="!selectedPendingOrder.customer_name && !selectedPendingOrder.customer_email && !selectedPendingOrder.customer_phone && !selectedPendingOrder.notes" class="text-muted-foreground italic">
                        Tidak ada data pelanggan/catatan
                    </p>
                </div>
                <form class="space-y-4" @submit.prevent="submitPayOrder">
                    <div>
                        <Label>Metode Pembayaran</Label>
                        <select
                            v-model="payForm.payment_method"
                            class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option
                                v-for="pm in payment_methods"
                                :key="pm.id"
                                :value="pm.code"
                            >
                                {{ pm.name }}
                            </option>
                        </select>
                    </div>
                    <div v-if="getRequiresCashInput()">
                        <Label for="pay_cash_received">Uang Diterima</Label>
                        <Input
                            id="pay_cash_received"
                            v-model="payForm.cash_received"
                            type="number"
                            min="0"
                            step="100"
                            class="mt-1.5"
                            :placeholder="formatCurrency(selectedPendingOrder.final_amount)"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Total: {{ formatCurrency(selectedPendingOrder.final_amount) }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closePayModal">
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            :disabled="payProcessing || (getRequiresCashInput() && (Number(payForm.cash_received) || 0) < selectedPendingOrder.final_amount)"
                        >
                            {{ payProcessing ? 'Memproses...' : 'Konfirmasi Pembayaran' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Floor Plan Modal -->
        <CashierFloorPlan
            :open="showFloorPlan"
            :store="store"
            :floor-plan="floor_plan ?? []"
            @update:open="showFloorPlan = $event"
        />
    </AdminLayout>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translate(-50%, -20px);
}

.filter-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.25rem 1.25rem;
}

:global(.theme-dark) .filter-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}
</style>
