import { ref, computed, type Ref } from 'vue'

export type SortDirection = 'asc' | 'desc'

export interface UseTableFeaturesOptions<T> {
    /** Field keys to search in (supports nested: 'category.name') */
    searchFields?: (keyof T | string)[]
    /** Custom function to get searchable text from item */
    getSearchText?: (item: T) => string
    /** Fields that can be sorted (key or getter path) */
    sortableFields?: (keyof T | string)[]
    /** Filter config: { filterKey: { value: label }[] } */
    filters?: Record<string, { value: string; label: string }[]>
    /** Get filter value from item for a given filter key */
    getFilterValue?: (item: T, filterKey: string) => string | number | boolean | null
}

function getNestedValue(obj: unknown, path: string): unknown {
    return path.split('.').reduce((o: unknown, k) => (o as Record<string, unknown>)?.[k], obj)
}

// ponytail: `object` not `Record<string, unknown>` — interfaces lack an index signature and won't satisfy the latter
export function useTableFeatures<T extends object>(
    items: Ref<T[]>,
    options: UseTableFeaturesOptions<T> = {},
) {
    const { searchFields = [], getSearchText, sortableFields = [], filters = {}, getFilterValue } = options

    const searchQuery = ref('')
    const sortKey = ref<string | null>(null)
    const sortDir = ref<SortDirection>('asc')
    const filterValues = ref<Record<string, string>>(
        Object.fromEntries(Object.keys(filters).map((k) => [k, 'all'])),
    )

    const filteredAndSortedData = computed(() => {
        let result = [...items.value]

        // Search
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim()
            result = result.filter((item) => {
                if (getSearchText) {
                    return getSearchText(item).toLowerCase().includes(q)
                }
                const text = searchFields
                    .map((f) => {
                        const v = getNestedValue(item, String(f))
                        return v != null ? String(v) : ''
                    })
                    .join(' ')
                return text.toLowerCase().includes(q)
            })
        }

        // Filters
        for (const [filterKey, selectedValue] of Object.entries(filterValues.value)) {
            if (!selectedValue || selectedValue === 'all') continue
            const opts = filters[filterKey]
            if (!opts?.length) continue
            result = result.filter((item) => {
                const val = getFilterValue
                    ? getFilterValue(item, filterKey)
                    : getNestedValue(item, filterKey)
                return String(val) === selectedValue
            })
        }

        // Sort
        if (sortKey.value) {
            const key = sortKey.value
            const dir = sortDir.value
            result = [...result].sort((a, b) => {
                const va = getNestedValue(a, key)
                const vb = getNestedValue(b, key)
                const aNum = typeof va === 'number'
                const bNum = typeof vb === 'number'
                let cmp = 0
                if (aNum && bNum) {
                    cmp = (va as number) - (vb as number)
                } else {
                    const sa = va != null ? String(va) : ''
                    const sb = vb != null ? String(vb) : ''
                    cmp = sa.localeCompare(sb, 'id')
                }
                return dir === 'asc' ? cmp : -cmp
            })
        }

        return result
    })

    function setSort(key: string) {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
        } else {
            sortKey.value = key
            sortDir.value = 'asc'
        }
    }

    function clearFilters() {
        searchQuery.value = ''
        sortKey.value = null
        sortDir.value = 'asc'
        filterValues.value = Object.fromEntries(
            Object.keys(filterValues.value).map((k) => [k, 'all']),
        )
    }

    function setFilter(key: string, value: string) {
        filterValues.value = { ...filterValues.value, [key]: value }
    }

    return {
        searchQuery,
        sortKey,
        sortDir,
        filterValues,
        filteredAndSortedData,
        setSort,
        clearFilters,
        setFilter,
        sortableFields,
        filters,
    }
}
