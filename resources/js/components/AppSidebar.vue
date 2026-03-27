<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    LayoutDashboard, Store, Package, Tags, Users, Receipt,
    Settings, LogOut, ChevronDown, ShoppingCart,
    CreditCard, BarChart3, Wallet, UserCircle, LayoutGrid, BookOpen, Clock, RotateCcw,
    Truck,
} from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import type { PageProps } from '@/types'

const page = usePage<PageProps>()
const user = computed(() => page.props.auth.user)
const stores = computed(() => (page.props.stores ?? []) as { id: number; name: string; slug: string }[])
const currentPath = computed(() => page.url)
const sidebarNavRef = ref<HTMLElement | null>(null)
const expandedProduk = ref(true)
const expandedDenahMeja = ref(true)
const expandedGroups = ref<Record<string, boolean>>({
    transaksi: true,
    produk: true,
    keuangan: true,
    manajemen: true,
})

interface NavItem {
    label: string
    href: string
    icon: unknown
    roles: string[]
    children?: { label: string; href: string }[]
}

interface NavGroup {
    key: string
    label: string
    items: NavItem[]
}

const navigationGroups = computed((): NavGroup[] => {
    const produkItem: NavItem =
        user.value?.role === 'owner' && stores.value.length > 0
            ? {
                label: 'Produk',
                href: `/admin/stores/${stores.value[0].id}/products`,
                icon: Package,
                roles: ['owner'],
                children: stores.value.map((s) => ({
                    label: s.name,
                    href: `/admin/stores/${s.id}/products`,
                })),
            }
            : {
                label: 'Produk',
                href: '/admin/products',
                icon: Package,
                roles: ['owner', 'admin'],
            }

    const denahMejaItem: NavItem =
        user.value?.role === 'owner' && stores.value.length > 0
            ? stores.value.length > 1
                ? {
                    label: 'Denah Meja',
                    href: `/admin/stores/${stores.value[0].id}/floor-plan`,
                    icon: LayoutGrid,
                    roles: ['owner'],
                    children: stores.value.map((s) => ({
                        label: s.name,
                        href: `/admin/stores/${s.id}/floor-plan`,
                    })),
                }
                : {
                    label: 'Denah Meja',
                    href: `/admin/stores/${stores.value[0].id}/floor-plan`,
                    icon: LayoutGrid,
                    roles: ['owner'],
                }
            : {
                label: 'Denah Meja',
                href: '/admin/stores',
                icon: LayoutGrid,
                roles: ['owner'],
            }

    return [
        {
            key: 'utama',
            label: 'Utama',
            items: [
                {
                    label: 'Dashboard',
                    href: '/admin/dashboard',
                    icon: LayoutDashboard,
                    roles: ['owner', 'admin', 'cashier', 'staff'],
                },
            ],
        },
        {
            key: 'transaksi',
            label: 'Transaksi',
            items: [
                { label: 'Kasir', href: '/admin/cashier', icon: ShoppingCart, roles: ['owner', 'admin', 'cashier'] },
                { label: 'Pesanan', href: '/admin/orders', icon: Receipt, roles: ['owner', 'admin', 'cashier'] },
                { label: 'Riwayat Penjualan', href: '/admin/sales', icon: BarChart3, roles: ['owner', 'admin', 'cashier'] },
                { label: 'Shift Kasir', href: '/admin/shifts', icon: Clock, roles: ['owner', 'admin', 'cashier'] },
                { label: 'Refund', href: '/admin/refunds', icon: RotateCcw, roles: ['owner', 'admin', 'cashier'] },
            ],
        },
        {
            key: 'produk',
            label: 'Produk & Inventori',
            items: [
                produkItem,
                { label: 'Kategori', href: '/admin/categories', icon: Tags, roles: ['owner', 'admin'] },
                { label: 'Inventaris', href: '/admin/inventory', icon: Store, roles: ['owner', 'admin'] },
                { label: 'Purchase Order', href: '/admin/purchase-orders', icon: Receipt, roles: ['owner', 'admin'] },
            ],
        },
        {
            key: 'keuangan',
            label: 'Keuangan',
            items: [
                { label: 'Cashflow', href: '/admin/cashflow', icon: Wallet, roles: ['owner', 'admin', 'cashier'] },
                { label: 'Pembukuan Harian', href: '/admin/expense-book', icon: BookOpen, roles: ['owner', 'admin', 'cashier'] },
                { label: 'Laporan', href: '/admin/reports', icon: BarChart3, roles: ['owner', 'admin'] },
            ],
        },
        {
            key: 'manajemen',
            label: 'Manajemen',
            items: [
                { label: 'Pelanggan', href: '/admin/customers', icon: UserCircle, roles: ['owner', 'admin'] },
                { label: 'Supplier', href: '/admin/suppliers', icon: Truck, roles: ['owner', 'admin'] },
                { label: 'Promo & Voucher', href: '/admin/promos', icon: Tags, roles: ['owner', 'admin'] },
                { label: 'Karyawan', href: '/admin/users', icon: Users, roles: ['owner', 'admin'] },
                { label: 'Cabang', href: '/admin/stores', icon: Store, roles: ['owner'] },
                denahMejaItem,
            ],
        },
        {
            key: 'sistem',
            label: 'Sistem',
            items: [
                { label: 'Pengaturan', href: '/admin/settings', icon: Settings, roles: ['owner', 'admin'] },
            ],
        },
    ]
})

const visibleGroups = computed(() =>
    navigationGroups.value.map((group) => ({
        ...group,
        items: group.items.filter((item) =>
            item.roles.includes(user.value?.role ?? ''),
        ),
    })).filter((group) => group.items.length > 0),
)

function isActive(href: string) {
    return currentPath.value.startsWith(href)
}

function toggleGroup(key: string) {
    expandedGroups.value[key] = !expandedGroups.value[key]
}

function scrollActiveNavIntoView() {
    nextTick(() => {
        const nav = sidebarNavRef.value
        if (!nav) return
        const active = nav.querySelector('[data-sidebar-active="true"]')
        active?.scrollIntoView({ block: 'nearest', behavior: 'instant' })
    })
}

watch(
    currentPath,
    (path) => {
        for (const s of stores.value) {
            if (path.startsWith(`/admin/stores/${s.id}/products`)) {
                expandedProduk.value = true
                break
            }
        }
        for (const s of stores.value) {
            if (path.startsWith(`/admin/stores/${s.id}/floor-plan`)) {
                expandedDenahMeja.value = true
                break
            }
        }
        for (const g of visibleGroups.value) {
            for (const item of g.items) {
                const childHit = item.children?.some((c) => isActive(c.href))
                const selfHit = !item.children?.length && isActive(item.href)
                if (childHit || selfHit) {
                    expandedGroups.value[g.key] = true
                    break
                }
            }
        }
        scrollActiveNavIntoView()
    },
    { immediate: true },
)

onMounted(() => {
    scrollActiveNavIntoView()
})
</script>

<template>
    <aside class="flex h-screen w-64 flex-col bg-sidebar text-sidebar-foreground">
        <!-- Brand Logo -->
        <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <CreditCard class="h-4 w-4" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ user?.brand?.name ?? 'POS System' }}</p>
                <p class="truncate text-xs text-sidebar-muted-foreground">{{ user?.store?.name ?? 'All Stores' }}</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav ref="sidebarNavRef" class="flex-1 overflow-y-auto px-3 py-4 scrollbar-hide">
            <div class="space-y-4">
                <div v-for="group in visibleGroups" :key="group.key" class="space-y-0.5">
                    <button
                        v-if="group.items.length > 1"
                        type="button"
                        class="flex w-full items-center gap-2 px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-sidebar-muted-foreground hover:text-sidebar-foreground"
                        @click="toggleGroup(group.key)"
                    >
                        {{ group.label }}
                        <ChevronDown
                            class="ml-auto h-3.5 w-3.5 transition-transform"
                            :class="{ 'rotate-180': expandedGroups[group.key] !== false }"
                        />
                    </button>
                    <p
                        v-else
                        class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-sidebar-muted-foreground"
                    >
                        {{ group.label }}
                    </p>
                    <ul v-show="group.items.length <= 1 || expandedGroups[group.key] !== false" class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.href">
                            <template v-if="item.children?.length">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors text-sidebar-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground"
                                    @click="item.label === 'Produk' ? (expandedProduk = !expandedProduk) : (expandedDenahMeja = !expandedDenahMeja)"
                                >
                                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                    {{ item.label }}
                                    <ChevronDown
                                        class="ml-auto h-4 w-4 shrink-0 transition-transform"
                                        :class="{ 'rotate-180': item.label === 'Produk' ? expandedProduk : expandedDenahMeja }"
                                    />
                                </button>
                                <ul v-show="(item.label === 'Produk' ? expandedProduk : expandedDenahMeja)" class="mt-0.5 space-y-0.5 pl-7">
                                    <li v-for="child in item.children" :key="child.href">
                                        <Link
                                            :href="child.href"
                                            :data-sidebar-active="isActive(child.href) ? true : undefined"
                                            :class="cn(
                                                'block rounded-lg px-2 py-1.5 text-sm transition-colors',
                                                isActive(child.href)
                                                    ? 'bg-sidebar-accent text-sidebar-foreground font-medium'
                                                    : 'text-sidebar-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground',
                                            )"
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </li>
                                </ul>
                            </template>
                            <Link
                                v-else
                                :href="item.href"
                                :data-sidebar-active="isActive(item.href) ? true : undefined"
                                :class="cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    isActive(item.href)
                                        ? 'bg-sidebar-accent text-sidebar-foreground'
                                        : 'text-sidebar-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-foreground',
                                )"
                            >
                                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- User Footer -->
        <div class="border-t border-sidebar-border p-3">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sidebar-accent text-xs font-bold uppercase">
                    {{ user?.name?.slice(0, 2) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ user?.name }}</p>
                    <p class="truncate text-xs capitalize text-sidebar-muted-foreground">{{ user?.role }}</p>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="rounded-md p-1 text-sidebar-muted-foreground hover:text-sidebar-foreground"
                >
                    <LogOut class="h-4 w-4" />
                </Link>
            </div>
        </div>
    </aside>
</template>
