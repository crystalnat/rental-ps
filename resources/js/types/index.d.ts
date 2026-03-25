export interface AuthUser {
    id: number
    name: string
    email: string
    role: 'owner' | 'admin' | 'cashier' | 'staff'
    avatar: string | null
    brand: { id: number; name: string; slug: string; logo: string | null } | null
    store: { id: number; name: string; slug: string } | null
}

export interface PageProps {
    auth: { user: AuthUser | null }
    stores?: { id: number; name: string; slug: string }[]
    flash: {
        success?: string
        error?: string
        warning?: string
    }
    [key: string]: unknown
}

export interface PaginatedData<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    links: { url: string | null; label: string; active: boolean }[]
}

export interface Brand {
    id: number
    name: string
    slug: string
    logo: string | null
    phone: string | null
    email: string | null
    timezone: string
    currency: string
    tax_rate: number
    is_active: boolean
}

export interface Store {
    id: number
    brand_id: number
    name: string
    slug: string
    city: string | null
    province: string | null
    phone: string | null
    is_active: boolean
}

export interface Category {
    id: number
    brand_id: number
    name: string
    slug: string
    icon: string | null
    color: string | null
    sort_order: number
    is_active: boolean
}

export interface Product {
    id: number
    brand_id: number
    category_id: number | null
    category?: Category
    name: string
    slug: string
    sku: string | null
    image: string | null
    unit: string
    is_available: boolean
    is_active: boolean
    current_price?: PriceLog
}

export interface PriceLog {
    id: number
    product_id: number
    store_id: number | null
    buy_price: number
    sell_price: number
    started_at: string
    ended_at: string | null
}

export interface User {
    id: number
    brand_id: number | null
    store_id: number | null
    name: string
    email: string
    phone: string | null
    avatar: string | null
    role: 'owner' | 'admin' | 'cashier' | 'staff'
    is_active: boolean
    store?: Store
}

export interface Order {
    id: number
    store_id: number
    order_code: string
    type: 'dine_in' | 'takeaway' | 'walk_in'
    status: 'pending' | 'confirmed' | 'processing' | 'ready' | 'done' | 'cancelled'
    final_amount: number
    payment_method: 'cash' | 'qris' | 'bank_transfer' | 'other' | null
    payment_status: 'unpaid' | 'paid' | 'refunded'
    created_at: string
    customer?: { name: string; email: string }
    table?: { name: string }
    items?: OrderItem[]
}

export interface OrderItem {
    id: number
    product_name: string
    quantity: number
    unit_price: number
    subtotal: number
    notes: string | null
}
