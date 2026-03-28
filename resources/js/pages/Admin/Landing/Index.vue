<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Textarea } from '@/components/ui/textarea'
import {
    Globe, Eye, EyeOff, Save, Plus, Trash2, Upload, ExternalLink,
    Palette, Type, Share2, Search as SearchIcon, GripVertical,
    Image as ImageIcon, ChevronDown, ChevronUp, Settings2,
} from 'lucide-vue-next'
import type { PageProps } from '@/types'

interface SectionItem {
    id: number
    section_key: string
    title: string | null
    subtitle: string | null
    content: string | null
    items: any[] | null
    config: Record<string, any> | null
    image: string | null
    image_url?: string | null
    sort_order: number
    is_visible: boolean
}

interface LandingSettings {
    id: number
    brand_id: number
    site_title: string | null
    tagline: string | null
    logo: string | null
    favicon: string | null
    colors: Record<string, string> | null
    fonts: Record<string, string> | null
    social_links: Record<string, string> | null
    seo: Record<string, string> | null
    is_published: boolean
}

const props = defineProps<{
    settings: LandingSettings
    sections: SectionItem[]
}>()

const page = usePage<PageProps>()
const brandSlug = computed(() => page.props.auth.user?.brand?.slug ?? '')

const activeTab = ref<'settings' | 'sections'>('sections')
const expandedSection = ref<number | null>(null)
const savingSettings = ref(false)
const savingSectionId = ref<number | null>(null)

// ===  Settings Form ===
const settingsForm = useForm({
    site_title: props.settings.site_title ?? '',
    tagline: props.settings.tagline ?? '',
    colors: props.settings.colors ?? {
        primary: '#2563eb',
        secondary: '#1e40af',
        accent: '#f59e0b',
        background: '#ffffff',
        text: '#111827',
    },
    fonts: props.settings.fonts ?? { heading: 'Inter', body: 'Inter' },
    social_links: props.settings.social_links ?? { instagram: '', facebook: '', whatsapp: '', tiktok: '' },
    seo: props.settings.seo ?? { meta_title: '', meta_description: '', og_image: '' },
    is_published: props.settings.is_published,
})

function saveSettings() {
    savingSettings.value = true
    settingsForm.put('/admin/landing/settings', {
        preserveScroll: true,
        onFinish: () => { savingSettings.value = false },
    })
}

function togglePublish() {
    settingsForm.is_published = !settingsForm.is_published
    saveSettings()
}

// === Section Editing ===
const sectionForms = ref<Record<number, any>>({})

function getSectionForm(section: SectionItem) {
    if (!sectionForms.value[section.id]) {
        sectionForms.value[section.id] = {
            title: section.title ?? '',
            subtitle: section.subtitle ?? '',
            content: section.content ?? '',
            items: JSON.parse(JSON.stringify(section.items ?? [])),
            config: JSON.parse(JSON.stringify(section.config ?? {})),
            is_visible: section.is_visible,
        }
    }
    return sectionForms.value[section.id]
}

function saveSection(section: SectionItem) {
    const form = getSectionForm(section)
    savingSectionId.value = section.id
    router.put(`/admin/landing/sections/${section.id}`, form, {
        preserveScroll: true,
        onFinish: () => { savingSectionId.value = null },
    })
}

function toggleSectionVisibility(section: SectionItem) {
    const form = getSectionForm(section)
    form.is_visible = !form.is_visible
    saveSection(section)
}

function toggleExpand(id: number) {
    expandedSection.value = expandedSection.value === id ? null : id
}

const sectionLabels: Record<string, string> = {
    hero: 'Hero Banner',
    about: 'Tentang Kami',
    services: 'Layanan',
    gallery: 'Galeri',
    testimonials: 'Testimoni',
    contact: 'Kontak',
}

const sectionIcons: Record<string, string> = {
    hero: 'sparkles',
    about: 'info',
    services: 'briefcase',
    gallery: 'image',
    testimonials: 'message-circle',
    contact: 'phone',
}

// === Service Items Management ===
function addServiceItem(sectionId: number) {
    const form = sectionForms.value[sectionId]
    if (!form) return
    if (!form.items) form.items = []
    form.items.push({ title: '', description: '', icon: 'star' })
}

function removeServiceItem(sectionId: number, index: number) {
    const form = sectionForms.value[sectionId]
    if (!form?.items) return
    form.items.splice(index, 1)
}

// === Testimonial Items Management ===
function addTestimonialItem(sectionId: number) {
    const form = sectionForms.value[sectionId]
    if (!form) return
    if (!form.items) form.items = []
    form.items.push({ name: '', role: '', content: '', rating: 5 })
}

function removeTestimonialItem(sectionId: number, index: number) {
    const form = sectionForms.value[sectionId]
    if (!form?.items) return
    form.items.splice(index, 1)
}

// === Gallery Items Management ===
function addGalleryItem(sectionId: number) {
    const form = sectionForms.value[sectionId]
    if (!form) return
    if (!form.items) form.items = []
    form.items.push({ url: '', caption: '' })
}

function removeGalleryItem(sectionId: number, index: number) {
    const form = sectionForms.value[sectionId]
    if (!form?.items) return
    form.items.splice(index, 1)
}

async function uploadGalleryImage(sectionId: number, index: number, event: Event) {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return
    const formData = new FormData()
    formData.append('image', input.files[0])
    try {
        const res = await fetch('/admin/landing/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                Accept: 'application/json',
            },
        })
        const json = await res.json()
        if (json.success) {
            const form = sectionForms.value[sectionId]
            if (form?.items?.[index]) {
                form.items[index].url = json.url
            }
        }
    } catch {
        // Upload failed silently
    }
}

const fontOptions = [
    'Inter', 'Roboto', 'Poppins', 'Outfit', 'Plus Jakarta Sans',
    'Montserrat', 'Open Sans', 'Lato', 'Nunito', 'DM Sans',
]
</script>

<template>
    <AdminLayout title="Landing Page CMS">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Landing Page</h1>
                <p class="text-sm text-muted-foreground">Kelola tampilan company profile yang dilihat pelanggan Anda.</p>
            </div>
            <div class="flex items-center gap-2">
                <Badge :variant="settingsForm.is_published ? 'default' : 'secondary'" class="text-xs">
                    {{ settingsForm.is_published ? 'Published' : 'Draft' }}
                </Badge>
                <Button variant="outline" size="sm" @click="togglePublish">
                    <component :is="settingsForm.is_published ? EyeOff : Eye" class="mr-2 h-4 w-4" />
                    {{ settingsForm.is_published ? 'Unpublish' : 'Publish' }}
                </Button>
                <Button
                    v-if="settingsForm.is_published && brandSlug"
                    variant="outline"
                    size="sm"
                    as="a"
                    :href="`/p/${brandSlug}`"
                    target="_blank"
                >
                    <ExternalLink class="mr-2 h-4 w-4" />
                    Lihat
                </Button>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex gap-1 rounded-lg border bg-muted/30 p-1">
            <button
                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                :class="activeTab === 'sections' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                @click="activeTab = 'sections'"
            >
                <Globe class="mr-2 inline h-4 w-4" /> Konten Halaman
            </button>
            <button
                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors"
                :class="activeTab === 'settings' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                @click="activeTab = 'settings'"
            >
                <Settings2 class="mr-2 inline h-4 w-4" /> Pengaturan & Tema
            </button>
        </div>

        <!-- ===  SECTIONS TAB === -->
        <div v-if="activeTab === 'sections'" class="space-y-3">
            <Card
                v-for="section in sections"
                :key="section.id"
                class="overflow-hidden transition-shadow duration-200"
                :class="expandedSection === section.id ? 'shadow-lg ring-1 ring-primary/20' : ''"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/30"
                    @click="toggleExpand(section.id)"
                >
                    <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ sectionLabels[section.section_key] ?? section.section_key }}</span>
                            <Badge v-if="!getSectionForm(section).is_visible" variant="outline" class="text-[10px]">Tersembunyi</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ section.title || 'Belum ada judul' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md p-1.5 transition-colors hover:bg-muted"
                            @click.stop="toggleSectionVisibility(section)"
                            :title="getSectionForm(section).is_visible ? 'Sembunyikan' : 'Tampilkan'"
                        >
                            <component :is="getSectionForm(section).is_visible ? Eye : EyeOff" class="h-4 w-4 text-muted-foreground" />
                        </button>
                        <component :is="expandedSection === section.id ? ChevronUp : ChevronDown" class="h-4 w-4 text-muted-foreground" />
                    </div>
                </button>

                <Transition name="slide">
                    <div v-if="expandedSection === section.id" class="border-t px-5 py-5">
                        <!-- Common Fields -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Judul</Label>
                                <Input v-model="getSectionForm(section).title" placeholder="Judul section" />
                            </div>
                            <div>
                                <Label>Subjudul</Label>
                                <Input v-model="getSectionForm(section).subtitle" placeholder="Keterangan singkat" />
                            </div>
                        </div>

                        <div v-if="['hero', 'about'].includes(section.section_key)" class="mt-4">
                            <Label>Deskripsi</Label>
                            <Textarea v-model="getSectionForm(section).content" rows="4" placeholder="Tulis deskripsi di sini..." />
                        </div>

                        <!-- Hero Config -->
                        <div v-if="section.section_key === 'hero'" class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Layout</Label>
                                <select
                                    v-model="getSectionForm(section).config.layout"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="center">Tengah</option>
                                    <option value="left">Kiri</option>
                                </select>
                            </div>
                            <div>
                                <Label>Overlay Opacity (%)</Label>
                                <Input v-model.number="getSectionForm(section).config.overlay_opacity" type="number" min="0" max="100" />
                            </div>
                        </div>

                        <!-- About Config -->
                        <div v-if="section.section_key === 'about'" class="mt-4">
                            <Label>Layout Gambar</Label>
                            <select
                                v-model="getSectionForm(section).config.layout"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="image-right">Gambar di Kanan</option>
                                <option value="image-left">Gambar di Kiri</option>
                                <option value="full-width">Full Width</option>
                            </select>
                        </div>

                        <!-- Services Items -->
                        <div v-if="section.section_key === 'services'" class="mt-4 space-y-3">
                            <Label>Daftar Layanan</Label>
                            <div
                                v-for="(item, idx) in getSectionForm(section).items"
                                :key="idx"
                                class="flex gap-3 rounded-lg border bg-muted/20 p-3"
                            >
                                <div class="flex-1 space-y-2">
                                    <Input v-model="item.title" placeholder="Nama Layanan" />
                                    <Textarea v-model="item.description" rows="2" placeholder="Deskripsi singkat" />
                                    <Input v-model="item.icon" placeholder="Ikon (briefcase, palette, megaphone, star, ...)" />
                                </div>
                                <button type="button" @click="removeServiceItem(section.id, idx)" class="shrink-0 self-start rounded-md p-1 text-destructive hover:bg-destructive/10">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <Button variant="outline" size="sm" @click="addServiceItem(section.id)">
                                <Plus class="mr-2 h-4 w-4" /> Tambah Layanan
                            </Button>
                        </div>

                        <!-- Gallery Items -->
                        <div v-if="section.section_key === 'gallery'" class="mt-4 space-y-3">
                            <Label>Gambar Galeri</Label>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div
                                    v-for="(item, idx) in getSectionForm(section).items"
                                    :key="idx"
                                    class="group relative overflow-hidden rounded-lg border bg-muted/20"
                                >
                                    <img
                                        v-if="item.url"
                                        :src="item.url"
                                        :alt="item.caption || 'Gallery'"
                                        class="aspect-square w-full object-cover"
                                    />
                                    <div v-else class="flex aspect-square items-center justify-center">
                                        <ImageIcon class="h-8 w-8 text-muted-foreground/30" />
                                    </div>
                                    <div class="p-2">
                                        <Input v-model="item.caption" placeholder="Caption" class="text-xs" />
                                        <label class="mt-1 flex cursor-pointer items-center gap-1 text-[10px] text-primary hover:underline">
                                            <Upload class="h-3 w-3" /> Upload
                                            <input type="file" accept="image/*" class="hidden" @change="uploadGalleryImage(section.id, idx, $event)" />
                                        </label>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeGalleryItem(section.id, idx)"
                                        class="absolute right-1 top-1 rounded-full bg-destructive p-1 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" @click="addGalleryItem(section.id)">
                                <Plus class="mr-2 h-4 w-4" /> Tambah Gambar
                            </Button>
                        </div>

                        <!-- Testimonial Items -->
                        <div v-if="section.section_key === 'testimonials'" class="mt-4 space-y-3">
                            <Label>Daftar Testimoni</Label>
                            <div
                                v-for="(item, idx) in getSectionForm(section).items"
                                :key="idx"
                                class="flex gap-3 rounded-lg border bg-muted/20 p-3"
                            >
                                <div class="flex-1 space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <Input v-model="item.name" placeholder="Nama" />
                                        <Input v-model="item.role" placeholder="Jabatan / Perusahaan" />
                                    </div>
                                    <Textarea v-model="item.content" rows="2" placeholder="Isi testimoni" />
                                    <div class="flex items-center gap-2">
                                        <Label class="text-xs">Rating:</Label>
                                        <select v-model.number="item.rating" class="rounded border px-2 py-1 text-sm">
                                            <option :value="5">5</option>
                                            <option :value="4">4</option>
                                            <option :value="3">3</option>
                                            <option :value="2">2</option>
                                            <option :value="1">1</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" @click="removeTestimonialItem(section.id, idx)" class="shrink-0 self-start rounded-md p-1 text-destructive hover:bg-destructive/10">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <Button variant="outline" size="sm" @click="addTestimonialItem(section.id)">
                                <Plus class="mr-2 h-4 w-4" /> Tambah Testimoni
                            </Button>
                        </div>

                        <!-- Contact Config -->
                        <div v-if="section.section_key === 'contact'" class="mt-4 space-y-3">
                            <Label>Informasi Kontak</Label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label class="text-xs">Alamat</Label>
                                    <Input v-model="getSectionForm(section).config.address" placeholder="Alamat lengkap" />
                                </div>
                                <div>
                                    <Label class="text-xs">Telepon</Label>
                                    <Input v-model="getSectionForm(section).config.phone" placeholder="+62 xxx" />
                                </div>
                                <div>
                                    <Label class="text-xs">Email</Label>
                                    <Input v-model="getSectionForm(section).config.email" placeholder="email@domain.com" />
                                </div>
                                <div>
                                    <Label class="text-xs">WhatsApp</Label>
                                    <Input v-model="getSectionForm(section).config.whatsapp" placeholder="6281xxx (tanpa +)" />
                                </div>
                            </div>
                            <div>
                                <Label class="text-xs">Google Maps Embed URL</Label>
                                <Input v-model="getSectionForm(section).config.map_embed" placeholder="https://www.google.com/maps/embed?..." />
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <Button @click="saveSection(section)" :disabled="savingSectionId === section.id">
                                <Save class="mr-2 h-4 w-4" />
                                {{ savingSectionId === section.id ? 'Menyimpan...' : 'Simpan Section' }}
                            </Button>
                        </div>
                    </div>
                </Transition>
            </Card>
        </div>

        <!-- === SETTINGS TAB === -->
        <div v-if="activeTab === 'settings'" class="space-y-6">
            <!-- Branding -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Type class="h-4 w-4" /> Branding
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Nama Situs</Label>
                            <Input v-model="settingsForm.site_title" placeholder="Nama brand / perusahaan" />
                        </div>
                        <div>
                            <Label>Tagline</Label>
                            <Input v-model="settingsForm.tagline" placeholder="Slogan singkat" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Colors -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Palette class="h-4 w-4" /> Warna Tema
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                        <div v-for="(val, key) in settingsForm.colors" :key="key">
                            <Label class="text-xs capitalize">{{ key }}</Label>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="settingsForm.colors[key]" class="h-9 w-9 cursor-pointer rounded border" />
                                <Input v-model="settingsForm.colors[key]" class="text-xs font-mono" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Typography -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Type class="h-4 w-4" /> Tipografi
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Font Heading</Label>
                            <select v-model="settingsForm.fonts.heading" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option v-for="f in fontOptions" :key="f" :value="f">{{ f }}</option>
                            </select>
                        </div>
                        <div>
                            <Label>Font Body</Label>
                            <select v-model="settingsForm.fonts.body" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option v-for="f in fontOptions" :key="f" :value="f">{{ f }}</option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Social Links -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Share2 class="h-4 w-4" /> Media Sosial
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Instagram</Label>
                            <Input v-model="settingsForm.social_links.instagram" placeholder="https://instagram.com/..." />
                        </div>
                        <div>
                            <Label>Facebook</Label>
                            <Input v-model="settingsForm.social_links.facebook" placeholder="https://facebook.com/..." />
                        </div>
                        <div>
                            <Label>WhatsApp</Label>
                            <Input v-model="settingsForm.social_links.whatsapp" placeholder="6281xxx" />
                        </div>
                        <div>
                            <Label>TikTok</Label>
                            <Input v-model="settingsForm.social_links.tiktok" placeholder="https://tiktok.com/@..." />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- SEO -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <SearchIcon class="h-4 w-4" /> SEO
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label>Meta Title</Label>
                        <Input v-model="settingsForm.seo.meta_title" placeholder="Judul yang muncul di Google" />
                    </div>
                    <div>
                        <Label>Meta Description</Label>
                        <Textarea v-model="settingsForm.seo.meta_description" rows="2" placeholder="Deskripsi singkat halaman untuk SEO" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button size="lg" @click="saveSettings" :disabled="savingSettings">
                    <Save class="mr-2 h-4 w-4" />
                    {{ savingSettings ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </Button>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
    max-height: 2000px;
    overflow: hidden;
}
.slide-enter-from,
.slide-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}
</style>
