<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { router, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
    DialogDescription, DialogFooter,
} from '@/components/ui/dialog'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { formatCurrency } from '@/lib/utils'
import RentalPanel from '@/components/RentalPanel.vue'
import {
    Search, Plus, Minus, Trash2, CreditCard, Banknote, Smartphone,
    ShoppingBag, Mic, MicOff, X, Clock, Loader2, RotateCcw, ShoppingCart,
    TicketPercent, Receipt, Gamepad2, Package, ChevronLeft, ChevronRight,
} from 'lucide-vue-next'
import axios from 'axios'
import FeedbackModal from '@/components/FeedbackModal.vue'

interface Product {
    id: number
    name: string
    sku: string | null
    category_id: number | null
    category_name: string | null
    category_color: string | null
    unit: string
    track_stock: boolean
    current_stock: number
    sell_price: number
    discount_percent: number
    image_url: string | null
    is_rental_package: boolean
    rental_duration_minutes: number | null
    included_items_json: any[]
    modifier_groups?: Array<{
        id: number
        name: string
        is_required: boolean
        min_select: number
        max_select: number
        options: Array<{
            id: number
            name: string
            price_extra: number
            is_active?: boolean
            is_available?: boolean
        }>
    }>
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
    id: string // internal unique id
    product_id: number
    name: string
    unit: string
    sell_price: number
    discount_percent: number
    quantity: number
    notes?: string
    modifiers: Array<{
        option_id: number
        name: string
        price_extra: number
    }>
}

const props = defineProps<{
    store: Store;
    pending_orders_count?: number;
    products: Product[];
    categories: Category[];
    tables: Table[];
    stores: Store[];
    payment_methods: PaymentMethodItem[];
    last_order_id?: number | null;
    floor_plan?: Array<{
        id: number;
        name: string;
        width_meters: number;
        length_meters: number;
        tables: Array<{
            id: number;
            name: string;
            capacity: number;
            x_meters: number;
            y_meters: number;
            width_meters: number;
            length_meters: number;
            rotation_deg: number;
            shape: string;
            tuya_device_id: string | null;
            tuya_status: boolean;
            rental_price_per_hour: number;
            active_orders: Array<{
                id: number;
                order_code: string;
                status: string;
                final_amount: number;
                items_count: number;
                created_at: string;
                is_rental: boolean;
                rental_started_at: string | null;
                rental_end_at: string | null;
                rental_duration_minutes: number | null;
            }>;
            has_orders: boolean;
        }>;
    }>;
    flash: {
        success?: string;
        error?: string;
        last_order_id?: number | null;
        last_stopped_order_id?: number | null;
        last_stopped_order_time?: number | null;
    }
}>()

const showSuccessDialog = ref(false)
const lastCreatedOrderId = ref<number | null>(null)

watch(() => props.flash.last_order_id, (newVal) => {
    if (newVal) {
        lastCreatedOrderId.value = newVal
        currentFeedbackOrderId.value = newVal
        showSuccessDialog.value = true
    }
}, { immediate: true })

watch(() => props.flash.last_stopped_order_time, (newVal) => {
    const orderId = props.flash.last_stopped_order_id
    if (newVal && newVal !== lastProcessedOrderTime.value && orderId) {
        lastProcessedOrderTime.value = newVal
        void openPayModalForOrder(orderId)
    }
}, { immediate: true })

async function openPayModalForOrder(orderId: number) {
    try {
        const res = await axios.get(`/admin/orders/${orderId}/detail`)
        const order = res.data
        const pendingOrder: PendingOrderItem = {
            id: order.id,
            order_code: order.order_code,
            table_name: order.table_name,
            customer_name: order.customer_name,
            customer_email: order.customer_email,
            customer_phone: order.customer_phone,
            final_amount: Number(order.final_amount),
            created_at: order.created_at,
            items: order.items,
            notes: order.notes,
            is_rental: order.is_rental,
            rental_started_at: order.rental_started_at,
            rental_end_at: order.rental_end_at,
            rental_duration_minutes: order.rental_duration_minutes,
        }
        openPayModal(pendingOrder)
    } catch (e) {
        console.error('Failed to open receipt', e)
    }
}

function onRentalExtended(orderId: number) {
    lastCreatedOrderId.value = orderId
    currentFeedbackOrderId.value = orderId
    showSuccessDialog.value = true
}

function printLastOrder() {
    if (lastCreatedOrderId.value) {
        showSuccessDialog.value = false
        const id = lastCreatedOrderId.value
        setTimeout(() => {
            window.open(`/admin/orders/${id}/receipt`, '_blank')
            // Force-clean any leftover Radix UI body locks
            setTimeout(() => {
                document.body.style.pointerEvents = ''
                document.body.style.overflow = ''
                document.body.removeAttribute('data-scroll-locked')
            }, 300)
        }, 50)
    }
}

const searchQuery = ref('')
const categoryFilter = ref<string>('all')
const cart = ref<CartItem[]>([])
const orderType = ref<'dine_in' | 'takeaway' | 'walk_in'>('walk_in')
const selectedTableId = ref<number | null>(null)
const customerName = ref('')
const customerPhone = ref('')
const customerEmail = ref('')
const notes = ref('')

// Promo state
const promoCodeInput = ref('')
const activePromo = ref<{ code: string; type: string; value: number } | null>(null)
const promoError = ref('')
const promoLoading = ref(false)

const discountAmount = ref<number | string>('')
const showPaymentDialog = ref(false)
const showFloorPlan = ref(false)
const showFeedbackModal = ref(false)
const currentFeedbackOrderId = ref<number | null>(null)

const showMobileCart = ref(false)
const isCartCollapsed = ref(false)
const activeTab = ref<'rental' | 'produk'>('rental')
const paymentMethod = ref<string>('')
const cashReceived = ref('')
const processing = ref(false)
const isListening = ref(false)
const newOrderNotification = ref<{ order_code: string; table_name?: string; amount: number } | null>(null)

// Multi-cart state management
const cartStates = ref<Record<string, any>>({})
// Keranjang cuma di memori → hilang tiap denah kirim router.post (remount). Simpan ke sessionStorage.
const cartStorageKey = `cashier_cart_${props.store?.id ?? 'x'}`
let restoring = false
const currentCartKey = computed(() => {
    if (orderType.value === 'dine_in' && selectedTableId.value) {
        return `table-${selectedTableId.value}`
    }
    return orderType.value
})

watch(currentCartKey, (newKey, oldKey) => {
    // Save previous state
    if (oldKey && !restoring) {
        cartStates.value[oldKey] = {
            cart: [...cart.value],
            notes: notes.value,
            discountAmount: discountAmount.value,
            activePromo: activePromo.value,
            promoCodeInput: promoCodeInput.value,
            customerName: customerName.value,
            customerPhone: customerPhone.value,
            customerEmail: customerEmail.value,
        }
    }
    
    // Load or initialize new state
    const state = cartStates.value[newKey] || {
        cart: [],
        notes: '',
        discountAmount: '',
        activePromo: null,
        promoCodeInput: '',
        customerName: '',
        customerPhone: '',
        customerEmail: '',
    }
    
    cart.value = [...state.cart]
    notes.value = state.notes
    discountAmount.value = state.discountAmount
    activePromo.value = state.activePromo
    promoCodeInput.value = state.promoCodeInput
    customerName.value = state.customerName
    customerPhone.value = state.customerPhone
    customerEmail.value = state.customerEmail
}, { immediate: true })

function persistCart() {
    if (restoring) return
    const states = { ...cartStates.value }
    states[currentCartKey.value] = {
        cart: cart.value,
        notes: notes.value,
        discountAmount: discountAmount.value,
        activePromo: activePromo.value,
        promoCodeInput: promoCodeInput.value,
        customerName: customerName.value,
        customerPhone: customerPhone.value,
        customerEmail: customerEmail.value,
    }
    try {
        sessionStorage.setItem(cartStorageKey, JSON.stringify({
            states,
            orderType: orderType.value,
            selectedTableId: selectedTableId.value,
        }))
    } catch { /* storage penuh/diblokir → abaikan */ }
}

function restoreCart() {
    let raw: string | null = null
    try { raw = sessionStorage.getItem(cartStorageKey) } catch { return }
    if (!raw) return
    restoring = true
    try {
        const s = JSON.parse(raw)
        cartStates.value = s.states ?? {}
        orderType.value = s.orderType ?? 'walk_in'
        selectedTableId.value = s.selectedTableId ?? null
        const active = cartStates.value[currentCartKey.value]
        if (active) {
            cart.value = [...(active.cart ?? [])]
            notes.value = active.notes ?? ''
            discountAmount.value = active.discountAmount ?? ''
            activePromo.value = active.activePromo ?? null
            promoCodeInput.value = active.promoCodeInput ?? ''
            customerName.value = active.customerName ?? ''
            customerPhone.value = active.customerPhone ?? ''
            customerEmail.value = active.customerEmail ?? ''
        }
    } catch { /* data rusak → abaikan */ } finally {
        restoring = false
    }
}

watch(
    [cart, cartStates, orderType, selectedTableId, notes, discountAmount, activePromo, promoCodeInput, customerName, customerPhone, customerEmail],
    persistCart,
    { deep: true },
)

watch(selectedTableId, (newVal) => {
    if (newVal) {
        orderType.value = 'dine_in'
    }
})

watch(orderType, (newVal) => {
    if (newVal !== 'dine_in') {
        selectedTableId.value = null
    }
})

// Modifier Modal State
const showModifierDialog = ref(false)
const selectedProductForModifiers = ref<Product | null>(null)
const modifierSelections = ref<Record<number, number[]>>({}) // group_id -> option_ids[]

const canConfirmModifiers = computed(() => {
    if (!selectedProductForModifiers.value) return false
    for (const group of selectedProductForModifiers.value.modifier_groups || []) {
        const selectedIds = modifierSelections.value[group.id] || []
        const min = group.is_required ? (group.min_select || 1) : 0
        if (selectedIds.length < min) return false
    }
    return true
})
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
    is_rental?: boolean
    rental_started_at?: string | null
    rental_end_at?: string | null
    rental_duration_minutes?: number | null
    items?: Array<{ product_name: string; quantity: number; unit: string; subtotal: number; modifiers?: Array<{ name: string; price_extra: number }> }>
}
const pendingOrdersList = ref<PendingOrderItem[]>([])
const selectedPendingOrder = ref<PendingOrderItem | null>(null)
const lastProcessedOrderTime = ref<number | null>(null)
const payForm = ref({ payment_method: '', cash_received: '' })
const payProcessing = ref(false)
let pendingOrdersPollInterval: ReturnType<typeof setInterval> | null = null
const sttError = ref<string | null>(null)

// Shift state
const activeShift = ref<any>(null)
const showOpenShiftDialog = ref(false)

const openShiftForm = useForm({
    store_id: props.store.id,
    opening_cash: '0',
    scheduled_start: '',
    scheduled_end: '',
})

async function fetchActiveShift() {
    try {
        const response = await fetch(`/admin/shifts/active?store=${props.store.id}`)
        const data = await response.json()
        activeShift.value = data.shift
        if (!activeShift.value) {
            showOpenShiftDialog.value = true
        }
    } catch (e) {
        console.error('Failed to fetch active shift', e)
    }
}

function openShift() {
    openShiftForm.post('/admin/shifts/open', {
        onBefore: () => {
            showOpenShiftDialog.value = false
        },
        onSuccess: () => {
            fetchActiveShift()
        },
        onError: (errors) => {
            // Re-open on error
            showOpenShiftDialog.value = true
            alert(Object.values(errors).flat().join('\n'))
        }
    })
}

// Barcode scanner state
let barcodeBuffer = ''
let lastBarcodeKeyTime = 0
const BARCODE_TIMEOUT_MS = 50 // USB scanners type faster than 50ms between keys
const barcodeActive = ref(false)

function handleBarcodeKeydown(e: KeyboardEvent) {
    // Ignore if focused on an input/textarea
    const target = e.target as HTMLElement
    if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') {
        return
    }

    const now = Date.now()
    const timeDiff = now - lastBarcodeKeyTime

    if (e.key === 'Enter' && barcodeBuffer.length >= 3) {
        e.preventDefault()
        const scannedCode = barcodeBuffer.trim()
        barcodeBuffer = ''
        barcodeActive.value = false
        handleBarcodeScan(scannedCode)
        return
    }

    // If too much time passed, reset buffer
    if (timeDiff > BARCODE_TIMEOUT_MS && barcodeBuffer.length > 0) {
        barcodeBuffer = ''
    }

    // Only accumulate printable single characters
    if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
        barcodeBuffer += e.key
        lastBarcodeKeyTime = now
        if (barcodeBuffer.length >= 3) {
            barcodeActive.value = true
        }
    }
}

function handleBarcodeScan(code: string) {
    // Match against SKU first, then name
    const product = props.products.find(
        p => p.sku && p.sku.toLowerCase() === code.toLowerCase()
    ) || props.products.find(
        p => p.name.toLowerCase() === code.toLowerCase()
    )

    if (product) {
        addToCart(product, 1)
        // Visual feedback
        barcodeScanResult.value = { success: true, name: product.name }
    } else {
        barcodeScanResult.value = { success: false, name: code }
    }

    // Clear feedback after 2s
    setTimeout(() => { barcodeScanResult.value = null }, 2000)
}

const barcodeScanResult = ref<{ success: boolean; name: string } | null>(null)

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

/** Suffixes to strip from end of words (Indonesian focus) */
const SUFFIX_REGEX = /(nya|kan|i|an)$/i
const DELETE_KEYWORDS = /^(hapus|kurang|buang|delete|remove|cancel)\s+/i

/** Simple Levenshtein distance for fuzzy matching */
function getLevenshteinDistance(a: string, b: string): number {
    const tmp = []
    for (let i = 0; i <= a.length; i++) tmp[i] = [i]
    for (let j = 0; j <= b.length; j++) tmp[0][j] = j
    for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) {
            tmp[i][j] = Math.min(
                tmp[i - 1][j] + 1,
                tmp[i][j - 1] + 1,
                tmp[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1)
            )
        }
    }
    return tmp[a.length][b.length]
}

function getSimilarity(s1: string, s2: string): number {
    const longer = s1.length > s2.length ? s1 : s2
    const shorter = s1.length > s2.length ? s2 : s1
    if (longer.length === 0) return 1.0
    return (longer.length - getLevenshteinDistance(longer, shorter)) / longer.length
}

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

function parseSpeechToItems(text: string): { productName: string; qty: number; isDelete: boolean }[] {
    const parts = text.split(SPLIT_BY).filter((p) => p && !/^(dan|sama|lagi|,)$/i.test(p))
    const items: { productName: string; qty: number; isDelete: boolean }[] = []

    for (let part of parts) {
        part = part.replace(FILLER_START, '').replace(FILLER_END, '').trim()
        if (!part) continue

        const subparts = expandChainedSegments(part)
        for (const raw of subparts) {
            let segment = raw.trim()
            if (!segment) continue

            const isDelete = DELETE_KEYWORDS.test(segment)
            if (isDelete) {
                segment = segment.replace(DELETE_KEYWORDS, '').trim()
            }

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
                items.push({ productName: productName.toLowerCase(), qty, isDelete })
            }
        }
    }

    return items
}

function compactLower(s: string): string {
    return s.toLowerCase().replace(/\s+/g, '')
}

function findBestProductMatch(productName: string, products: Product[]): Product | null {
    // Strip suffixes like "-nya", "-kan" from each word in input
    const words = productName.split(/\s+/)
        .filter((w) => w.length > 0)
        .map(w => w.replace(SUFFIX_REGEX, ''))

    if (words.length === 0) return null
    const cleanPN = words.join(' ')
    const cq = compactLower(cleanPN)

    let best: { product: Product; score: number } | null = null

    for (const p of products) {
        const name = p.name.toLowerCase()
        const cn = compactLower(name)
        const cat = (p.category_name ?? '').toLowerCase()
        const fullName = `${name} ${cat}`
        
        let score = 0

        // 1. Exact or include matches (High weight)
        if (cn === cq) score = 5000
        else if (cn.includes(cq)) score = 2000 + cq.length
        else if (cq.includes(cn)) score = 1500 + cn.length
        
        // 2. Word overlap
        const nameWords = name.split(/\s+/).map(w => w.replace(SUFFIX_REGEX, ''))
        const overlapCount = words.filter(w => nameWords.some(nw => nw.includes(w) || w.includes(nw))).length
        if (overlapCount === words.length) score += 1000
        else if (overlapCount > 0) score += (overlapCount * 200)

        // 3. Fuzzy similarity (Levenshtein based)
        const similarity = getSimilarity(cn, cq)
        if (similarity > 0.8) score += (similarity * 800)
        
        // Bonus for SKU match if input looks like a code
        if (p.sku && p.sku.toLowerCase() === cleanPN) score += 6000

        if (score > 0) {
            if (!best || score > best.score) {
                best = { product: p, score }
            }
        }
    }

    // Threshold check
    return (best && best.score > 300) ? best.product : null
}

function processSpeechResult(transcript: string) {
    const items = parseSpeechToItems(transcript)
    const activeActions: string[] = []
    const notFound: string[] = []

    for (const { productName, qty, isDelete } of items) {
        const product = findBestProductMatch(productName, props.products)
        if (product) {
            if (isDelete) {
                removeFromCartCompletely(product, qty)
                activeActions.push(`Hapus: ${product.name} × ${qty}`)
            } else {
                addToCart(product, qty)
                activeActions.push(`Tambah: ${product.name} × ${qty}`)
            }
        } else {
            notFound.push(productName)
        }
    }

    if (activeActions.length > 0) {
        sttError.value = null
    }
    if (notFound.length > 0) {
        sttError.value = `Tidak ditemukan: ${notFound.join(', ')}`
        setTimeout(() => { sttError.value = null }, 4000)
    }
}

function removeFromCartCompletely(product: Product, qty: number) {
    // Cari item terakhir dengan product_id yang sama
    for (let i = 0; i < qty; i++) {
        const idx = [...cart.value].reverse().findIndex(item => item.product_id === product.id)
        if (idx !== -1) {
            const actualIdx = cart.value.length - 1 - idx
            const item = cart.value[actualIdx]
            if (item.quantity > 1) {
                item.quantity -= 1
            } else {
                cart.value.splice(actualIdx, 1)
            }
        }
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
    restoreCart()
    sessionStorage.setItem('cashier_pending_count', String(props.pending_orders_count ?? 0))
    void checkPendingOrders()
    pendingOrdersPollInterval = setInterval(checkPendingOrders, 15000)
    document.addEventListener('keydown', handleBarcodeKeydown)
    fetchActiveShift()
})

onBeforeUnmount(() => {
    stopListening()
    if (pendingOrdersPollInterval) clearInterval(pendingOrdersPollInterval)
    document.removeEventListener('keydown', handleBarcodeKeydown)
})

const filteredProducts = computed(() => {
    let list = props.products.filter(p => !p.is_rental_package)
    const q = searchQuery.value.toLowerCase().trim()
    if (q) {
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                (p.sku?.toLowerCase().includes(q) ?? false) ||
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
        const modifierTotal = i.modifiers.reduce((mSum, m) => mSum + m.price_extra, 0)
        const itemUnitPrice = i.sell_price + modifierTotal
        const discount = i.discount_percent > 0 ? Math.round(i.sell_price * (i.discount_percent / 100)) : 0
        return sum + (itemUnitPrice - discount) * i.quantity
    }, 0),
)

// Auto-recalculate promo if subtotal changes
watch(subtotal, (newSubtotal) => {
    if (activePromo.value && newSubtotal > 0) {
        applyPromoCode()
    } else if (newSubtotal === 0) {
        removePromo()
    }
})

async function applyPromoCode() {
    if (!promoCodeInput.value) return
    promoError.value = ''
    promoLoading.value = true
    try {
        const res = await axios.post('/admin/cashier/check-promo', {
            code: promoCodeInput.value,
            subtotal: subtotal.value,
            store: props.store.id
        })
        activePromo.value = res.data.promo
        discountAmount.value = res.data.discount_amount
        promoCodeInput.value = res.data.promo.code
    } catch (e: any) {
        activePromo.value = null
        discountAmount.value = ''
        promoError.value = e.response?.data?.error || 'Gagal menggunakan promo'
    } finally {
        promoLoading.value = false
    }
}

function addProductFromRental({ product, tableId }: { product: any; tableId: number | null }) {
    if (tableId) {
        orderType.value = 'dine_in'
        selectedTableId.value = tableId
    }
    // Timeout added to ensure the watcher has finished swapping the cart state before adding item
    setTimeout(() => {
        addToCart(product as Product)
    }, 0)
}

function removePromo() {
    activePromo.value = null
    promoCodeInput.value = ''
    discountAmount.value = ''
    promoError.value = ''
}

const finalAmount = computed(() => {
    return Math.max(0, subtotal.value - (Number(discountAmount.value) || 0))
})

const canCheckout = computed(() => cart.value.length > 0)

function addToCart(product: Product, qty = 1) {
    if (product.track_stock && product.current_stock < qty) return

    // Special handling for PS Rental Packages
    if (product.is_rental_package) {
        if (!selectedTableId.value) {
            showFloorPlan.value = true
            alert(`Pilih Unit PS terlebih dahulu untuk memulai paket "${product.name}".`)
            return
        }
    }

    // If product has modifiers, show dialog instead of direct add
    if (product.modifier_groups && product.modifier_groups.length > 0) {
        selectedProductForModifiers.value = product
        modifierSelections.value = {}
        // Pre-fill required groups with first option if max_select is 1
        product.modifier_groups.forEach(g => {
            if (g.is_required && g.max_select === 1 && g.options.length > 0) {
                modifierSelections.value[g.id] = [g.options[0].id]
            } else {
                modifierSelections.value[g.id] = []
            }
        })
        showModifierDialog.value = true
        return
    }

    addFinalToCart(product, qty, [])
}

function addFinalToCart(product: Product, qty: number, selectedModifiers: Array<{ option_id: number; name: string; price_extra: number }>) {
    // Generate a unique ID for this cart entry based on product + modifiers
    const modifierKey = selectedModifiers.map(m => m.option_id).sort().join('-')
    const cartId = `${product.id}-${modifierKey}`

    const existing = cart.value.find((c) => c.id === cartId)
    if (existing) {
        const newQty = existing.quantity + qty
        if (product.track_stock && product.current_stock < newQty) return
        existing.quantity = newQty
    } else {
        cart.value.push({
            id: cartId,
            product_id: product.id,
            name: product.name,
            unit: product.unit,
            sell_price: product.sell_price,
            discount_percent: product.discount_percent,
            quantity: qty,
            modifiers: selectedModifiers,
        })
    }
}

function confirmModifiers() {
    if (!selectedProductForModifiers.value) return

    const product = selectedProductForModifiers.value
    const finalModifiers: Array<{ option_id: number; name: string; price_extra: number }> = []

    // Validate requirements
    for (const group of product.modifier_groups || []) {
        const selectedIds = modifierSelections.value[group.id] || []
        if (group.is_required && selectedIds.length < (group.min_select || 1)) {
            alert(`Silakan pilih "${group.name}" terlebih dahulu.`)
            return
        }
        
        selectedIds.forEach(optId => {
            const opt = group.options.find(o => o.id === optId)
            if (opt) {
                finalModifiers.push({
                    option_id: opt.id,
                    name: opt.name,
                    price_extra: opt.price_extra
                })
            }
        })
    }

    addFinalToCart(product, 1, finalModifiers)
    showModifierDialog.value = false
    selectedProductForModifiers.value = null
}

function toggleModifierOption(groupId: number, optionId: number, maxSelect: number) {
    const current = modifierSelections.value[groupId] || []
    if (current.includes(optionId)) {
        modifierSelections.value[groupId] = current.filter(id => id !== optionId)
    } else {
        if (maxSelect === 1) {
            modifierSelections.value[groupId] = [optionId]
        } else if (current.length < maxSelect) {
            modifierSelections.value[groupId] = [...current, optionId]
        }
    }
}

function updateQty(item: CartItem, delta: number) {
    const product = props.products.find((p) => p.id === item.product_id)
    const newQty = Math.max(0, item.quantity + delta)
    if (product?.track_stock && product.current_stock < newQty) return
    item.quantity = newQty
    if (item.quantity <= 0) {
        cart.value = cart.value.filter((c) => c.id !== item.id)
    }
}

function removeFromCart(item: CartItem) {
    cart.value = cart.value.filter((c) => c.id !== item.id)
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
            unit_price: i.sell_price,
            notes: i.notes,
            modifiers: i.modifiers.map(m => ({ option_id: m.option_id })),
        })),
        notes: notes.value,
        payment_method: paymentMethod.value,
        cash_received: requiresCashInput.value ? Number(cashReceived.value) || 0 : 0,
        discount_amount: Number(discountAmount.value) || 0,
        promo_code: activePromo.value ? activePromo.value.code : undefined,
    }
    processing.value = true
    showPaymentDialog.value = false // Close immediately
    showMobileCart.value = false

    // Hapus dulu supaya keranjang yang sudah terjual tidak muncul lagi saat halaman remount.
    try { sessionStorage.removeItem(cartStorageKey) } catch { /* abaikan */ }
    router.post('/admin/cashier', { ...payload, store: props.store.id }, {
        preserveScroll: true,
        onError: () => {
             showPaymentDialog.value = true // Re-open on error
             persistCart() // gagal bayar → simpan lagi keranjangnya
        },
        onSuccess: () => {
            cart.value = []
            notes.value = ''
            discountAmount.value = ''
            selectedTableId.value = null
            customerName.value = ''
            customerPhone.value = ''
            customerEmail.value = ''
            removePromo()
        },
        onFinish: () => { processing.value = false },
    })
}

function changeStore(storeId: number) {
    router.get('/admin/cashier', { store: storeId })
}

const orderTypeLabel = {
    dine_in: 'Makan di Tempat',
    takeaway: 'Bungkus',
    walk_in: 'Pesan Langsung',
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
    
    // Close immediately
    const prevOrder = selectedPendingOrder.value
    selectedPendingOrder.value = null

    // Clean formatting for backend
    const cleanCash = String(payForm.value.cash_received).replace(/\D/g, '')

    router.post(`/admin/orders/${order.id}/pay`, {
        payment_method: payForm.value.payment_method,
        cash_received: getRequiresCashInput() ? cleanCash : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            void checkPendingOrders()
        },
        onError: (errors) => {
            selectedPendingOrder.value = prevOrder
            alert(Object.values(errors).flat().join('\n'))
        },
        onFinish: () => { 
            payProcessing.value = false 
        },
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
            <!-- Barcode scan result toast -->
            <Transition name="slide-down">
                <div
                    v-if="barcodeScanResult"
                    class="fixed top-4 left-1/2 z-[100] -translate-x-1/2 rounded-lg border px-4 py-2 text-sm font-medium shadow-lg"
                    :class="barcodeScanResult.success
                        ? 'border-blue-500 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100'
                        : 'border-red-500 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100'"
                >
                    <template v-if="barcodeScanResult.success">
                        ✅ Barcode: <strong>{{ barcodeScanResult.name }}</strong> ditambahkan ke keranjang
                    </template>
                    <template v-else>
                        ❌ Barcode <strong>{{ barcodeScanResult.name }}</strong> tidak ditemukan
                    </template>
                </div>
            </Transition>
        </Teleport>

        <!-- 2-Tab Layout -->
        <!-- dvh dipakai supaya tinggi ikut menyusut saat bilah alamat browser mobile muncul/hilang -->
        <div class="relative flex h-[calc(100dvh-7rem)] flex-col gap-3 pb-[72px] md:flex-row md:gap-4 md:overflow-hidden md:pb-0">
            <!-- Tab switcher + main content area -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card h-full">
                <!-- Tab buttons -->
                <div class="flex shrink-0 items-center gap-1 border-b bg-muted/30 px-2 py-2 sm:px-3">
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold transition-all sm:gap-2 sm:px-4 sm:text-sm"
                        :class="activeTab === 'rental' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                        @click="activeTab = 'rental'"
                    >
                        <Gamepad2 class="h-4 w-4 shrink-0" />
                        Rental PS
                    </button>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold transition-all sm:gap-2 sm:px-4 sm:text-sm"
                        :class="activeTab === 'produk' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                        @click="activeTab = 'produk'"
                    >
                        <Package class="h-4 w-4 shrink-0" />
                        Produk
                    </button>

                    <!-- Shift & store info in tab bar -->
                    <div class="ml-auto flex min-w-0 items-center gap-1.5 sm:gap-2">
                        <div v-if="activeShift" class="hidden md:flex items-center gap-1.5 rounded-full bg-muted/50 border px-2.5 py-1 text-[11px] font-medium text-muted-foreground">
                            <Clock class="h-3 w-3 text-primary" />
                            <span>Shift #{{ activeShift.id }}</span>
                        </div>
                        <select
                            v-if="stores.length > 1"
                            :value="store.id"
                            class="filter-select flex h-8 min-w-0 max-w-[110px] truncate rounded-md border border-input bg-background py-1 pl-2 pr-7 text-xs text-foreground sm:max-w-none sm:pl-3 sm:pr-8"
                            @change="changeStore(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <Button
                            v-if="activeShift"
                            variant="outline"
                            size="sm"
                            class="h-8 text-destructive border-destructive/20 hover:bg-destructive/10"
                            @click="router.visit(`/admin/shifts/${activeShift.id}`)"
                        >
                            <Clock class="mr-1.5 h-3.5 w-3.5" />
                            <span class="text-xs hidden sm:inline">Tutup Shift</span>
                        </Button>
                    </div>
                </div>

                <!-- TAB: RENTAL -->
                <div v-show="activeTab === 'rental'" class="flex-1 min-h-0 overflow-hidden p-3">
                    <RentalPanel
                        :store="store"
                        :floor-plan="floor_plan ?? []"
                        :products="products"
                        :payment-methods="payment_methods"
                        :cart="cart"
                        v-model:selected-table-id="selectedTableId"
                        @add-to-cart="addProductFromRental"
                        @checkout="openPaymentDialog"
                        @pay-order="openPayModalForOrder"
                        @extended="onRentalExtended"
                    />
                </div>

                <!-- TAB: PRODUK -->
                <div v-show="activeTab === 'produk'" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <!-- Search & Mic -->
                    <div class="flex items-center gap-2 border-b px-3 py-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Cari produk..." class="pl-9 w-full" />
                        </div>
                        <Button
                            :variant="isListening ? 'default' : 'outline'"
                            size="icon"
                            class="shrink-0"
                            :disabled="!sttSupported"
                            @click="toggleSpeechToText"
                        >
                            <Mic v-if="!isListening" class="h-4 w-4" />
                            <MicOff v-else class="h-4 w-4 animate-pulse" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0 hidden md:flex h-9"
                            @click="router.visit('/admin/refunds')"
                        >
                            <RotateCcw class="h-4 w-4 mr-1.5" />
                            <span class="text-xs">Refund</span>
                        </Button>
                        <p v-if="sttError" class="text-xs text-destructive">{{ sttError }}</p>
                    </div>
                    <!-- Category Pills -->
                    <div class="border-b bg-muted/40 px-3 py-2.5">
                        <div class="flex items-center gap-2 overflow-x-auto pb-2 flex-nowrap scrollbar-thin">
                            <button
                                type="button"
                                class="shrink-0 px-4 py-2 rounded-full text-xs font-bold transition-all border shadow-sm uppercase tracking-wide"
                                :class="categoryFilter === 'all' ? 'bg-primary text-primary-foreground border-primary' : 'bg-white text-muted-foreground border-input hover:border-primary/50'"
                                @click="categoryFilter = 'all'"
                            >Semua</button>
                            <button
                                v-for="c in props.categories"
                                :key="c.id"
                                type="button"
                                class="shrink-0 px-4 py-2 rounded-full text-xs font-bold transition-all border shadow-sm whitespace-nowrap uppercase tracking-wide"
                                :class="categoryFilter === String(c.id) ? 'text-white border-transparent' : 'bg-white text-muted-foreground border-input hover:border-primary/50'"
                                :style="categoryFilter === String(c.id) ? { backgroundColor: c.color || '#3b82f6' } : {}"
                                @click="categoryFilter = String(c.id)"
                            >{{ c.name }}</button>
                        </div>
                    </div>
                    <!-- Products grid -->
                    <div class="flex-1 overflow-y-auto p-2 sm:p-3">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                        <button
                            v-for="p in filteredProducts"
                            :key="p.id"
                            type="button"
                            class="group relative flex flex-col items-stretch rounded-xl border p-2 md:p-3 text-left transition-all hover:bg-accent hover:border-primary/30"
                            :style="{ backgroundColor: p.category_color ? p.category_color + '40' : undefined }"
                            :disabled="p.track_stock && p.current_stock <= 0"
                            @click="addToCart(p)"
                        >
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
                                <div v-if="p.category_name" class="mb-1">
                                    <span 
                                        class="inline-block rounded-sm px-1 py-0.5 text-[8px] font-bold uppercase tracking-wider"
                                        :style="{ backgroundColor: (p.category_color || '#e2e8f0') + '20', color: p.category_color || 'currentColor' }"
                                    >
                                        {{ p.category_name }}
                                    </span>
                                </div>
                                <p class="truncate text-xs md:text-sm font-semibold leading-tight mb-1">{{ p.name }}</p>
                                <p class="text-[10px] leading-tight text-muted-foreground md:text-xs">
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
                <!-- end TAB: PRODUK -->
                </div>

            <div
                v-if="showMobileCart"
                class="fixed inset-0 z-40 bg-black/50 md:hidden"
                @click="showMobileCart = false"
            />

            <div
                :class="[
                    // 'relative' tidak boleh ikut di sini: utility itu menang atas 'fixed' di
                    // urutan CSS Tailwind, sehingga panel keranjang batal jadi bottom sheet di mobile
                    'fixed inset-x-0 bottom-0 z-50 flex h-[85dvh] min-h-0 flex-col gap-3 rounded-t-xl bg-background p-4 shadow-2xl transition-transform duration-300 md:relative md:z-auto md:h-full md:shrink-0 md:translate-y-0 md:bg-transparent md:p-0 md:shadow-none',
                    showMobileCart ? 'translate-y-0' : 'translate-y-full',
                    isCartCollapsed ? 'md:w-[60px] md:overflow-visible' : 'md:w-[400px] lg:w-[460px]'
                ]"
            >
                <!-- Toggle Button (Desktop Only) -->
                <button
                    type="button"
                    class="absolute -left-3 top-12 z-[60] hidden h-6 w-6 items-center justify-center rounded-full border bg-background shadow-sm hover:bg-accent md:flex"
                    @click="isCartCollapsed = !isCartCollapsed"
                >
                    <ChevronRight v-if="isCartCollapsed" class="h-4 w-4" />
                    <ChevronLeft v-else class="h-4 w-4" />
                </button>

                <div class="mb-2 flex items-center justify-between md:hidden">
                    <h2 class="text-lg font-semibold">Keranjang</h2>
                    <Button variant="ghost" size="icon" @click="showMobileCart = false">
                        <X class="h-5 w-5" />
                    </Button>
                </div>

                <!-- Collapsed View Content -->
                <div v-if="isCartCollapsed" class="hidden md:flex flex-col items-center gap-6 py-4">
                    <div class="relative cursor-pointer" @click="isCartCollapsed = false">
                        <ShoppingBag class="h-6 w-6 text-primary" />
                        <Badge v-if="cart.length > 0" class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center p-0 text-[10px]">{{ cart.length }}</Badge>
                    </div>
                </div>

                <template v-else>
                    <!-- Pesanan Menunggu -->
                    <Card v-if="pendingOrdersList.length > 0" class="shrink-0 max-h-[140px] min-h-0 flex flex-col overflow-hidden">
                    <CardHeader class="shrink-0 px-4 py-2">
                        <CardTitle class="flex items-center justify-between text-sm">
                            <span>Pesanan Menunggu</span>
                            <Badge variant="destructive">{{ pendingOrdersList.length }}</Badge>
                        </CardTitle>
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
                                    <p class="text-xs text-muted-foreground">Meja {{ o.table_name ?? '—' }}</p>
                                </div>
                                <span class="ml-2 shrink-0 font-semibold text-primary text-xs">{{ formatCurrency(o.final_amount) }}</span>
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <Card class="flex flex-1 min-h-0 flex-col overflow-hidden">
                    <CardHeader class="shrink-0 flex flex-row items-center justify-between space-y-0 pb-2 px-4">
                        <CardTitle class="text-sm font-bold uppercase tracking-wider">Keranjang</CardTitle>
                        <Badge variant="secondary" class="text-[10px]">{{ cart.length }} Item</Badge>
                    </CardHeader>
                    <CardContent class="flex min-h-0 flex-1 flex-col overflow-hidden p-0">
                        <!-- Tipe Order & Meja -->
                        <div class="shrink-0 flex gap-2 border-b px-4 pb-3">
                            <select
                                v-model="orderType"
                                class="filter-select flex h-8 flex-1 rounded-md border border-input bg-transparent px-2 py-1 text-xs text-foreground"
                            >
                                <option value="walk_in">{{ orderTypeLabel.walk_in }}</option>
                                <option value="dine_in">{{ orderTypeLabel.dine_in }}</option>
                                <option value="takeaway">{{ orderTypeLabel.takeaway }}</option>
                            </select>
                            <select
                                v-if="orderType === 'dine_in'"
                                v-model="selectedTableId"
                                class="filter-select flex h-8 min-w-[100px] rounded-md border border-input bg-transparent px-2 py-1 text-xs text-foreground"
                            >
                                <option :value="null">Meja</option>
                                <option v-for="t in tables" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>



                        <!-- Daftar Item (Scrollable) -->
                        <div class="flex-1 overflow-y-auto p-3">
                            <div v-if="cart.length === 0" class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground opacity-50">
                                <ShoppingBag class="mb-2 h-8 w-8" />
                                <p class="text-[11px]">Keranjang kosong</p>
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="item in cart" :key="item.id" class="flex flex-col gap-2 rounded-lg border bg-muted/5 p-3 transition-all hover:border-primary/30">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold leading-tight">{{ item.name }}</p>
                                            <div v-if="item.modifiers.length > 0" class="flex flex-wrap gap-1 mt-1">
                                                <span v-for="m in item.modifiers" :key="m.option_id" class="text-[10px] bg-primary/10 px-1 rounded text-primary font-bold uppercase">{{ m.name }}</span>
                                            </div>
                                        </div>
                                        <p class="shrink-0 text-sm font-black text-primary">
                                            {{ formatCurrency((item.sell_price - Math.round(item.sell_price * (item.discount_percent / 100)) + item.modifiers.reduce((s, m) => s + m.price_extra, 0)) * item.quantity) }}
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-dashed pt-2">
                                        <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                            <span>Rp</span>
                                            <input
                                                type="number"
                                                min="0"
                                                v-model.number="item.sell_price"
                                                class="w-20 rounded border border-input bg-transparent px-1.5 py-0.5 text-xs font-bold text-foreground tabular-nums focus:border-primary focus:outline-none"
                                            />
                                            <span class="mx-1">×</span>
                                            <span class="font-bold text-foreground">{{ item.quantity }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <Button variant="outline" size="icon" class="h-8 w-8 rounded" @click="updateQty(item, -1)"><Minus class="h-3 w-3" /></Button>
                                            <span class="min-w-[1.5rem] text-center text-sm font-bold">{{ item.quantity }}</span>
                                            <Button variant="outline" size="icon" class="h-8 w-8 rounded" @click="updateQty(item, 1)"><Plus class="h-3 w-3" /></Button>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive/60 hover:text-destructive ml-1" @click="removeFromCart(item)"><Trash2 class="h-3 w-3" /></Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan & Checkout -->
                        <div v-if="cart.length > 0" class="shrink-0 border-t bg-muted/10 p-3 space-y-3">
                            <div class="space-y-1.5">
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-muted-foreground">Subtotal</span>
                                    <span class="font-medium tabular-nums">{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="relative flex-1">
                                        <TicketPercent class="absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground/60" />
                                        <Input v-model="promoCodeInput" placeholder="PROMO" class="h-7 pl-6 text-[10px] uppercase focus-visible:ring-primary/20" :disabled="activePromo !== null || promoLoading" @keyup.enter="applyPromoCode" />
                                    </div>
                                    <Button v-if="!activePromo" size="sm" variant="secondary" class="h-7 px-2 text-[10px]" :disabled="!promoCodeInput || promoLoading" @click="applyPromoCode">Pakai</Button>
                                    <Button v-else size="sm" variant="ghost" class="h-7 px-2 text-[10px] text-destructive" @click="removePromo">Batal</Button>
                                    <Input v-model="discountAmount" type="number" class="h-7 w-16 text-right text-[10px] px-1 font-bold" placeholder="Disc" min="0" :disabled="activePromo !== null" />
                                </div>
                                <p v-if="activePromo" class="text-[9px] text-emerald-600 font-bold bg-emerald-500/5 px-1.5 py-0.5 rounded">Promo: {{ activePromo.type === 'percentage' ? activePromo.value + '%' : 'Rp' + activePromo.value }}</p>
                                <p v-if="promoError" class="text-[9px] text-destructive px-1">{{ promoError }}</p>
                            </div>

                            <div class="flex gap-2">
                                <textarea v-model="notes" placeholder="Catatan..." rows="1" class="flex-1 rounded border border-input bg-background px-2 py-1 text-[10px] placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-primary/20 resize-none min-h-[30px]" />
                                <div class="shrink-0 text-right">
                                    <p class="text-[8px] font-bold uppercase text-muted-foreground leading-none">Total</p>
                                    <p class="text-base font-black text-primary leading-tight">{{ formatCurrency(finalAmount) }}</p>
                                </div>
                            </div>

                            <Button class="w-full h-10 text-sm font-bold shadow-md shadow-primary/10" :disabled="!canCheckout" @click="openPaymentDialog">
                                <CreditCard class="mr-2 h-4 w-4" /> Bayar
                            </Button>
                        </div>
                    </CardContent>
                </Card>
                </template>
            </div>
        </div>

        <!-- Mobile Footer -->
        <div class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between border-t bg-background p-3 shadow-[0_-4px_10px_-2px_rgba(0,0,0,0.1)] md:hidden">
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
            <DialogContent class="w-[95vw] max-w-md rounded-xl">
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
                        <Label for="checkout_cash_received">Uang Diterima</Label>
                        <div class="relative mt-1.5">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">Rp</span>
                            <Input
                                id="checkout_cash_received"
                                v-model="cashReceived"
                                type="number"
                                min="0"
                                step="any"
                                class="pl-9 font-mono font-bold text-primary"
                                :placeholder="String(finalAmount)"
                                autofocus
                            />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="amt in [finalAmount, 10000, 20000, 50000, 100000].filter((v, i, a) => a.indexOf(v) === i).sort((a,b) => a-b)"
                                :key="amt"
                                type="button"
                                class="rounded-md border px-2 py-1 text-[11px] font-bold transition-colors hover:border-primary hover:bg-primary/5"
                                :class="Number(cashReceived) === amt ? 'border-primary bg-primary/10 text-primary' : 'border-input text-muted-foreground'"
                                @click="cashReceived = String(amt)"
                            >
                                {{ formatCurrency(amt) }}
                            </button>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-xs text-muted-foreground">Total: {{ formatCurrency(finalAmount) }}</p>
                            <p v-if="Number(cashReceived) > finalAmount" class="text-xs font-bold text-emerald-600">
                                Kembalian: {{ formatCurrency(Number(cashReceived) - finalAmount) }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
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
                    <!-- Rental Info -->
                    <div v-if="selectedPendingOrder.is_rental" class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                        <Clock class="h-4 w-4 shrink-0" />
                        <div class="flex flex-wrap gap-x-4 gap-y-0.5">
                            <span v-if="selectedPendingOrder.rental_duration_minutes">
                                <span class="font-medium">Durasi:</span>
                                {{ Math.floor(selectedPendingOrder.rental_duration_minutes / 60) > 0 ? Math.floor(selectedPendingOrder.rental_duration_minutes / 60) + 'j ' : '' }}{{ selectedPendingOrder.rental_duration_minutes % 60 }}m
                            </span>
                            <span v-if="selectedPendingOrder.rental_started_at">
                                <span class="font-medium">Mulai:</span> {{ selectedPendingOrder.rental_started_at }}
                            </span>
                            <span v-if="selectedPendingOrder.rental_end_at">
                                <span class="font-medium">Selesai:</span> {{ selectedPendingOrder.rental_end_at }}
                            </span>
                        </div>
                    </div>
                    <!-- Receipt Detail in Payment Modal -->
                    <div v-if="selectedPendingOrder.items && selectedPendingOrder.items.length > 0" class="rounded-xl border bg-muted/30 p-4">
                        <p class="mb-3 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Rincian Pesanan</p>
                        <div class="space-y-2 max-h-[200px] overflow-y-auto pr-1 scrollbar-thin">
                            <div v-for="(item, idx) in selectedPendingOrder.items" :key="idx" class="flex justify-between border-b border-dashed border-muted-foreground/20 pb-2 last:border-0 last:pb-0">
                                <div class="min-w-0 pr-2">
                                    <p class="text-xs font-bold leading-tight">{{ item.product_name }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ item.quantity }} {{ item.unit }}</p>
                                    <!-- Modifiers -->
                                    <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 flex flex-wrap gap-1">
                                        <span v-for="(mod, midx) in item.modifiers" :key="midx" class="text-[9px] text-muted-foreground/80 italic">
                                            + {{ mod.name }}{{ midx < item.modifiers.length - 1 ? ',' : '' }}
                                        </span>
                                    </div>
                                </div>
                                <span class="shrink-0 font-mono text-xs font-bold">{{ formatCurrency(item.subtotal) }}</span>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-between border-t pt-3 font-black text-primary">
                            <span class="text-xs uppercase tracking-wider">Total Tagihan</span>
                            <span class="text-base font-mono">{{ formatCurrency(selectedPendingOrder.final_amount) }}</span>
                        </div>
                    </div>

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
                        <div class="relative mt-1.5">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">Rp</span>
                            <Input
                                id="pay_cash_received"
                                v-model="payForm.cash_received"
                                type="number"
                                min="0"
                                step="any"
                                class="pl-9 font-mono font-bold text-primary"
                                :placeholder="String(selectedPendingOrder.final_amount)"
                                autofocus
                            />
                        </div>
                        <!-- Quick amount buttons -->
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="amt in [selectedPendingOrder.final_amount, 10000, 20000, 50000, 100000].filter((v, i, a) => a.indexOf(v) === i).sort((a,b) => a-b)"
                                :key="amt"
                                type="button"
                                class="rounded-md border px-2 py-1 text-[11px] font-bold transition-colors hover:border-primary hover:bg-primary/5"
                                :class="Number(payForm.cash_received) === amt ? 'border-primary bg-primary/10 text-primary' : 'border-input text-muted-foreground'"
                                @click="payForm.cash_received = String(amt)"
                            >
                                {{ formatCurrency(amt) }}
                            </button>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-xs text-muted-foreground">
                                Total: {{ formatCurrency(selectedPendingOrder.final_amount) }}
                            </p>
                            <p v-if="Number(payForm.cash_received) > selectedPendingOrder.final_amount" class="text-xs font-bold text-emerald-600">
                                Kembalian: {{ formatCurrency(Number(payForm.cash_received) - selectedPendingOrder.final_amount) }}
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closePayModal">
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            :disabled="payProcessing || (getRequiresCashInput() && (Number(String(payForm.cash_received).replace(/\D/g, '')) || 0) < selectedPendingOrder.final_amount)"
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

        <!-- Mandatory Open Shift Dialog -->
        <Dialog :open="showOpenShiftDialog" @update:open="activeShift ? (showOpenShiftDialog = $event) : null">
            <DialogContent class="w-[95vw] max-w-md rounded-xl" :hide-close="!activeShift">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5 text-primary" />
                        Buka Shift Kasir
                    </DialogTitle>
                    <DialogDescription>
                        Anda harus membuka shift sebelum dapat melayani pesanan di {{ store.name }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="opening_cash">Modal Awal (Kas di Laci)</Label>
                        <Input
                            id="opening_cash"
                            v-model="openShiftForm.opening_cash"
                            type="number"
                            placeholder="0"
                            :disabled="openShiftForm.processing"
                        />
                        <p class="text-xs text-muted-foreground">
                            Masukkan jumlah uang tunai yang ada di laci kasir saat ini.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="sch_start">Jadwal Mulai</Label>
                            <Input id="sch_start" v-model="openShiftForm.scheduled_start" type="time" :disabled="openShiftForm.processing" />
                        </div>
                        <div class="space-y-2">
                            <Label for="sch_end">Jadwal Selesai</Label>
                            <Input id="sch_end" v-model="openShiftForm.scheduled_end" type="time" :disabled="openShiftForm.processing" />
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-col">
                    <Button
                        class="w-full"
                        size="lg"
                        :disabled="!openShiftForm.opening_cash || Number(openShiftForm.opening_cash) < 0 || openShiftForm.processing"
                        @click="openShift"
                    >
                        <Loader2 v-if="openShiftForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ openShiftForm.processing ? 'Memproses...' : 'Buka Shift Sekarang' }}
                    </Button>
                    <Button
                        v-if="!activeShift"
                        class="w-full"
                        variant="outline"
                        :disabled="openShiftForm.processing"
                        @click="router.visit('/admin/dashboard')"
                    >
                        Kembali
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modifier Selection Dialog -->
        <Dialog v-model:open="showModifierDialog">
            <DialogContent class="w-[95vw] max-w-md rounded-xl" v-if="selectedProductForModifiers">
                <DialogHeader>
                    <DialogTitle>Pilih Varian & Topping</DialogTitle>
                    <DialogDescription>
                        {{ selectedProductForModifiers.name }} · {{ formatCurrency(selectedProductForModifiers.sell_price) }}
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-6 py-4 max-h-[60vh] overflow-y-auto pr-2 px-1">
                    <div v-for="group in selectedProductForModifiers.modifier_groups" :key="group.id" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label class="text-sm font-bold flex items-center gap-2">
                                {{ group.name }}
                                <Badge v-if="group.is_required" variant="destructive" class="text-[10px] px-1 py-0 h-4">Wajib</Badge>
                            </Label>
                            <span class="text-[10px] text-muted-foreground">Max {{ group.max_select }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="opt in group.options"
                                :key="opt.id"
                                type="button"
                                class="flex flex-col items-start rounded-lg border p-3 text-left transition-all"
                                :class="[
                                    modifierSelections[group.id]?.includes(opt.id) ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-input',
                                    !opt.is_available ? 'opacity-40 grayscale cursor-not-allowed bg-muted' : 'hover:bg-accent'
                                ]"
                                :disabled="!opt.is_available"
                                @click="toggleModifierOption(group.id, opt.id, group.max_select)"
                            >
                                <div class="flex items-center justify-between w-full">
                                    <span class="text-sm font-medium leading-none">{{ opt.name }}</span>
                                    <Badge v-if="!opt.is_available" variant="outline" class="text-[8px] h-3 px-1">Habis</Badge>
                                </div>
                                <span v-if="opt.price_extra > 0" class="mt-1 text-xs text-primary font-bold">+{{ formatCurrency(opt.price_extra) }}</span>
                                <span v-else class="mt-1 text-xs text-muted-foreground italic">Tanpa Tambahan</span>
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showModifierDialog = false">Batal</Button>
                    <Button :disabled="!canConfirmModifiers" @click="confirmModifiers">Tambah ke Keranjang</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Order Success Dialog -->
        <Dialog :open="showSuccessDialog" @update:open="showSuccessDialog = $event">
            <DialogContent class="max-w-xs sm:max-w-md text-center">
                <DialogHeader>
                    <div class="mx-auto my-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                        <ShoppingCart class="h-8 w-8 text-green-600" />
                    </div>
                    <DialogTitle class="text-2xl font-bold">Pesanan Berhasil!</DialogTitle>
                    <DialogDescription>
                        Transaksi telah berhasil diproses dan stok telah diperbarui.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-3 py-4">
                    <Button size="lg" class="w-full bg-green-600 hover:bg-green-700" @click="printLastOrder">
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Cetak Struk (Receipt)
                    </Button>
                    <Button variant="outline" size="lg" class="w-full" @click="showSuccessDialog = false">
                        Tutup & Pesanan Baru
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Barcode Scan Feedback Toast -->
        <Transition name="slide-down">
            <div 
                v-if="barcodeScanResult" 
                class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] px-4 py-2 rounded-full shadow-lg flex items-center gap-2 border"
                :class="barcodeScanResult.success ? 'bg-primary text-primary-foreground border-primary shadow-primary/20' : 'bg-destructive text-destructive-foreground border-destructive shadow-destructive/20'"
            >
                <div v-if="barcodeScanResult.success" class="flex items-center gap-2">
                    <ShoppingCart class="h-4 w-4" />
                    <span class="text-sm font-medium">Berhasil tambah: {{ barcodeScanResult.name }}</span>
                </div>
                <div v-else class="flex items-center gap-2">
                    <X class="h-4 w-4" />
                    <span class="text-sm font-medium">Produk tidak ditemukan: {{ barcodeScanResult.name }}</span>
                </div>
            </div>
        </Transition>
        <FeedbackModal 
            :order-id="showFeedbackModal ? currentFeedbackOrderId : null" 
            @close="showFeedbackModal = false" 
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

.scrollbar-thin::-webkit-scrollbar {
    height: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

:global(.theme-dark) .scrollbar-thin::-webkit-scrollbar-thumb {
    background: #334155;
}
</style>
