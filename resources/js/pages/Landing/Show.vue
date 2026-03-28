<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Section {
    id: number
    section_key: string
    title: string | null
    subtitle: string | null
    content: string | null
    items: any[] | null
    config: Record<string, any> | null
    image_url: string | null
}

interface Settings {
    site_title: string | null
    tagline: string | null
    logo: string | null
    favicon: string | null
    colors: Record<string, string>
    fonts: Record<string, string>
    social_links: Record<string, string>
    seo: Record<string, string>
}

interface BrandInfo {
    name: string
    slug: string
    logo: string | null
    phone: string | null
    email: string | null
    address: string | null
}

const props = defineProps<{
    brand: BrandInfo
    settings: Settings
    sections: Section[]
}>()

const heroSection = computed(() => props.sections.find(s => s.section_key === 'hero'))
const aboutSection = computed(() => props.sections.find(s => s.section_key === 'about'))
const servicesSection = computed(() => props.sections.find(s => s.section_key === 'services'))
const gallerySection = computed(() => props.sections.find(s => s.section_key === 'gallery'))
const testimonialsSection = computed(() => props.sections.find(s => s.section_key === 'testimonials'))
const contactSection = computed(() => props.sections.find(s => s.section_key === 'contact'))

const colors = computed(() => props.settings.colors ?? {})
const fonts = computed(() => props.settings.fonts ?? {})
const social = computed(() => props.settings.social_links ?? {})

const pageTitle = computed(() =>
    props.settings.seo?.meta_title || props.settings.site_title || props.brand.name
)
const pageDescription = computed(() =>
    props.settings.seo?.meta_description || props.settings.tagline || ''
)

const navLinks = computed(() =>
    props.sections
        .filter(s => s.section_key !== 'hero')
        .map(s => ({
            key: s.section_key,
            label: sectionLabels[s.section_key] ?? s.section_key,
        }))
)

const sectionLabels: Record<string, string> = {
    about: 'Tentang',
    services: 'Layanan',
    gallery: 'Galeri',
    testimonials: 'Testimoni',
    contact: 'Kontak',
}

const serviceIcons: Record<string, string> = {
    briefcase: 'M20 7h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM10 4h4v3h-4V4z',
    palette: 'M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10a2.5 2.5 0 002.5-2.5c0-.61-.23-1.21-.64-1.67-.08-.09-.13-.21-.13-.33 0-.35.29-.64.64-.64H16c3.31 0 6-2.69 6-6 0-4.96-4.49-9-10-9z',
    megaphone: 'M3 11l18-5v12L3 13v-2zm18 0V6',
    star: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
}

function scrollTo(key: string) {
    document.getElementById(`section-${key}`)?.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta name="description" :content="pageDescription" />
        <link v-if="settings.favicon" rel="icon" :href="settings.favicon" />
        <link
            rel="stylesheet"
            :href="`https://fonts.googleapis.com/css2?family=${fonts.heading?.replace(' ', '+')}:wght@400;500;600;700;800;900&family=${fonts.body?.replace(' ', '+')}:wght@300;400;500;600&display=swap`"
        />
    </Head>

    <div
        class="landing-page min-h-screen"
        :style="{
            '--color-primary': colors.primary ?? '#2563eb',
            '--color-secondary': colors.secondary ?? '#1e40af',
            '--color-accent': colors.accent ?? '#f59e0b',
            '--color-bg': colors.background ?? '#ffffff',
            '--color-text': colors.text ?? '#111827',
            '--font-heading': fonts.heading ?? 'Inter',
            '--font-body': fonts.body ?? 'Inter',
        } as Record<string, string>"
    >
        <!-- NAVBAR -->
        <nav class="landing-nav">
            <div class="landing-container flex items-center justify-between py-4">
                <a href="#" class="flex items-center gap-3" @click.prevent="scrollTo('hero')">
                    <img v-if="settings.logo" :src="settings.logo" alt="Logo" class="h-10 w-auto" />
                    <span class="landing-brand-name">{{ settings.site_title || brand.name }}</span>
                </a>
                <div class="hidden items-center gap-6 md:flex">
                    <button
                        v-for="link in navLinks"
                        :key="link.key"
                        class="landing-nav-link"
                        @click="scrollTo(link.key)"
                    >
                        {{ link.label }}
                    </button>
                </div>
            </div>
        </nav>

        <!-- HERO -->
        <section
            v-if="heroSection"
            id="section-hero"
            class="landing-hero"
            :style="heroSection.image_url ? { backgroundImage: `url(${heroSection.image_url})` } : {}"
        >
            <div
                class="landing-hero-overlay"
                :style="{ opacity: (heroSection.config?.overlay_opacity ?? 60) / 100 }"
            />
            <div class="landing-container landing-hero-content" :class="heroSection.config?.layout === 'left' ? 'items-start text-left' : 'items-center text-center'">
                <h1 class="landing-hero-title">{{ heroSection.title }}</h1>
                <p class="landing-hero-subtitle">{{ heroSection.subtitle }}</p>
                <p v-if="heroSection.content" class="landing-hero-desc">{{ heroSection.content }}</p>
                <div class="mt-8 flex gap-4">
                    <button class="landing-btn-primary" @click="scrollTo('contact')">Hubungi Kami</button>
                    <button class="landing-btn-outline" @click="scrollTo('services')">Lihat Layanan</button>
                </div>
            </div>
        </section>

        <!-- ABOUT -->
        <section v-if="aboutSection" id="section-about" class="landing-section">
            <div class="landing-container">
                <div class="landing-section-header">
                    <h2 class="landing-section-title">{{ aboutSection.title }}</h2>
                    <p class="landing-section-subtitle">{{ aboutSection.subtitle }}</p>
                </div>
                <div :class="['landing-about-grid', aboutSection.config?.layout === 'image-left' ? 'md:flex-row-reverse' : '']">
                    <div class="flex-1 space-y-4">
                        <p class="landing-text-body leading-relaxed whitespace-pre-line">{{ aboutSection.content }}</p>
                    </div>
                    <div v-if="aboutSection.image_url && aboutSection.config?.layout !== 'full-width'" class="flex-1">
                        <img :src="aboutSection.image_url" alt="About" class="rounded-2xl shadow-xl" />
                    </div>
                </div>
            </div>
        </section>

        <!-- SERVICES -->
        <section v-if="servicesSection" id="section-services" class="landing-section landing-section-alt">
            <div class="landing-container">
                <div class="landing-section-header">
                    <h2 class="landing-section-title">{{ servicesSection.title }}</h2>
                    <p class="landing-section-subtitle">{{ servicesSection.subtitle }}</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(item, idx) in (servicesSection.items ?? [])"
                        :key="idx"
                        class="landing-service-card"
                    >
                        <div class="landing-service-icon">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path :d="serviceIcons[item.icon] ?? serviceIcons.star" />
                            </svg>
                        </div>
                        <h3 class="landing-service-title">{{ item.title }}</h3>
                        <p class="landing-service-desc">{{ item.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- GALLERY -->
        <section v-if="gallerySection && (gallerySection.items ?? []).length > 0" id="section-gallery" class="landing-section">
            <div class="landing-container">
                <div class="landing-section-header">
                    <h2 class="landing-section-title">{{ gallerySection.title }}</h2>
                    <p class="landing-section-subtitle">{{ gallerySection.subtitle }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="(item, idx) in gallerySection.items"
                        :key="idx"
                        class="landing-gallery-item"
                    >
                        <img :src="item.url" :alt="item.caption || 'Gallery'" class="aspect-square w-full rounded-xl object-cover" />
                        <p v-if="item.caption" class="mt-2 text-center text-sm opacity-70">{{ item.caption }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- TESTIMONIALS -->
        <section v-if="testimonialsSection && (testimonialsSection.items ?? []).length > 0" id="section-testimonials" class="landing-section landing-section-alt">
            <div class="landing-container">
                <div class="landing-section-header">
                    <h2 class="landing-section-title">{{ testimonialsSection.title }}</h2>
                    <p class="landing-section-subtitle">{{ testimonialsSection.subtitle }}</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(item, idx) in testimonialsSection.items"
                        :key="idx"
                        class="landing-testimonial-card"
                    >
                        <div class="mb-3 flex gap-0.5">
                            <svg
                                v-for="s in 5"
                                :key="s"
                                class="h-4 w-4"
                                :class="s <= (item.rating ?? 5) ? 'landing-star-filled' : 'landing-star-empty'"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                            </svg>
                        </div>
                        <p class="landing-testimonial-text">"{{ item.content }}"</p>
                        <div class="mt-4 flex items-center gap-3">
                            <div class="landing-testimonial-avatar">{{ item.name?.charAt(0) ?? '?' }}</div>
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--color-text)">{{ item.name }}</p>
                                <p class="text-xs opacity-60">{{ item.role }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTACT -->
        <section v-if="contactSection" id="section-contact" class="landing-section">
            <div class="landing-container">
                <div class="landing-section-header">
                    <h2 class="landing-section-title">{{ contactSection.title }}</h2>
                    <p class="landing-section-subtitle">{{ contactSection.subtitle }}</p>
                </div>
                <div class="grid gap-8 md:grid-cols-2">
                    <div class="space-y-6">
                        <div v-if="contactSection.config?.address" class="landing-contact-item">
                            <div class="landing-contact-icon">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider opacity-50">Alamat</p>
                                <p class="landing-text-body">{{ contactSection.config.address }}</p>
                            </div>
                        </div>
                        <div v-if="contactSection.config?.phone" class="landing-contact-item">
                            <div class="landing-contact-icon">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider opacity-50">Telepon</p>
                                <p class="landing-text-body">{{ contactSection.config.phone }}</p>
                            </div>
                        </div>
                        <div v-if="contactSection.config?.email" class="landing-contact-item">
                            <div class="landing-contact-icon">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="2" y="4" width="20" height="16" rx="2" />
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 01-2.06 0L2 7" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider opacity-50">Email</p>
                                <p class="landing-text-body">{{ contactSection.config.email }}</p>
                            </div>
                        </div>
                        <div v-if="contactSection.config?.whatsapp">
                            <a
                                :href="`https://wa.me/${contactSection.config.whatsapp}`"
                                target="_blank"
                                class="landing-btn-whatsapp"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                                Chat via WhatsApp
                            </a>
                        </div>
                    </div>
                    <div v-if="contactSection.config?.map_embed">
                        <iframe
                            :src="contactSection.config.map_embed"
                            width="100%"
                            height="350"
                            style="border:0; border-radius: 1rem;"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="landing-footer">
            <div class="landing-container">
                <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                    <div class="flex items-center gap-3">
                        <img v-if="settings.logo" :src="settings.logo" alt="Logo" class="h-8 w-auto brightness-0 invert" />
                        <span class="font-semibold" style="color: #fff">{{ settings.site_title || brand.name }}</span>
                    </div>
                    <div class="flex gap-4">
                        <a v-if="social.instagram" :href="social.instagram" target="_blank" class="landing-social-link" aria-label="Instagram">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                        <a v-if="social.facebook" :href="social.facebook" target="_blank" class="landing-social-link" aria-label="Facebook">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a v-if="social.tiktok" :href="social.tiktok" target="_blank" class="landing-social-link" aria-label="TikTok">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                        </a>
                    </div>
                </div>
                <div class="mt-6 border-t border-white/10 pt-6 text-center text-xs opacity-50" style="color:#fff">
                    &copy; {{ new Date().getFullYear() }} {{ settings.site_title || brand.name }}. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* === LANDING PAGE STYLES (scoped via .landing-page class) === */
.landing-page {
    font-family: var(--font-body, 'Inter'), system-ui, sans-serif;
    color: var(--color-text, #111827);
    background-color: var(--color-bg, #ffffff);
}

.landing-container {
    max-width: 1200px;
    margin: 0 auto;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
}

/* Navbar */
.landing-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 50;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.landing-brand-name {
    font-family: var(--font-heading, 'Inter'), system-ui, sans-serif;
    font-weight: 700;
    font-size: 1.25rem;
    color: var(--color-text);
}

.landing-nav-link {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-text);
    opacity: 0.7;
    transition: opacity 0.2s;
    background: none;
    border: none;
    cursor: pointer;
}
.landing-nav-link:hover { opacity: 1; }

/* Hero */
.landing-hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-size: cover;
    background-position: center;
    background-color: var(--color-secondary, #1e40af);
    overflow: hidden;
}

.landing-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--color-secondary) 0%, var(--color-primary) 100%);
}

.landing-hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    padding-top: 6rem;
    padding-bottom: 4rem;
}

.landing-hero-title {
    font-family: var(--font-heading), system-ui, sans-serif;
    font-size: clamp(2.5rem, 6vw, 4.5rem);
    font-weight: 800;
    line-height: 1.1;
    color: #fff;
    max-width: 800px;
    letter-spacing: -0.02em;
}

.landing-hero-subtitle {
    font-size: clamp(1.125rem, 2.5vw, 1.5rem);
    color: rgba(255,255,255,0.85);
    max-width: 600px;
    margin-top: 1rem;
    font-weight: 400;
}

.landing-hero-desc {
    font-size: 1rem;
    color: rgba(255,255,255,0.7);
    max-width: 600px;
    margin-top: 1.5rem;
    line-height: 1.6;
}

/* Buttons */
.landing-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    background: var(--color-accent, #f59e0b);
    color: #fff;
    font-weight: 600;
    font-size: 0.9375rem;
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
}
.landing-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

.landing-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    background: transparent;
    color: #fff;
    font-weight: 600;
    font-size: 0.9375rem;
    border: 2px solid rgba(255,255,255,0.4);
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.2s;
}
.landing-btn-outline:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.7);
}

.landing-btn-whatsapp {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 2rem;
    background: #25D366;
    color: #fff;
    font-weight: 600;
    font-size: 0.9375rem;
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
}
.landing-btn-whatsapp:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37,211,102,0.3);
}

/* Sections */
.landing-section {
    padding: 6rem 0;
}

.landing-section-alt {
    background: linear-gradient(180deg, rgba(0,0,0,0.02) 0%, rgba(0,0,0,0.04) 100%);
}

.landing-section-header {
    text-align: center;
    margin-bottom: 3.5rem;
}

.landing-section-title {
    font-family: var(--font-heading), system-ui, sans-serif;
    font-size: clamp(1.75rem, 4vw, 2.5rem);
    font-weight: 700;
    color: var(--color-text);
    letter-spacing: -0.01em;
}

.landing-section-subtitle {
    color: var(--color-text);
    opacity: 0.6;
    font-size: 1.0625rem;
    margin-top: 0.5rem;
}

.landing-text-body {
    color: var(--color-text);
    opacity: 0.8;
    font-size: 1rem;
    line-height: 1.7;
}

/* About */
.landing-about-grid {
    display: flex;
    flex-direction: column;
    gap: 3rem;
    align-items: center;
}
@media (min-width: 768px) {
    .landing-about-grid {
        flex-direction: row;
    }
}

/* Service Cards */
.landing-service-card {
    padding: 2rem;
    background: var(--color-bg, #fff);
    border-radius: 1rem;
    border: 1px solid rgba(0,0,0,0.06);
    transition: transform 0.2s, box-shadow 0.2s;
}
.landing-service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
}

.landing-service-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    background: var(--color-primary);
    color: #fff;
    margin-bottom: 1rem;
}

.landing-service-title {
    font-family: var(--font-heading), system-ui, sans-serif;
    font-weight: 600;
    font-size: 1.125rem;
    color: var(--color-text);
    margin-bottom: 0.5rem;
}

.landing-service-desc {
    font-size: 0.9375rem;
    color: var(--color-text);
    opacity: 0.65;
    line-height: 1.6;
}

/* Gallery */
.landing-gallery-item {
    transition: transform 0.2s;
}
.landing-gallery-item:hover {
    transform: scale(1.03);
}

/* Testimonials */
.landing-testimonial-card {
    padding: 2rem;
    background: var(--color-bg, #fff);
    border-radius: 1rem;
    border: 1px solid rgba(0,0,0,0.06);
}

.landing-star-filled { color: var(--color-accent, #f59e0b); }
.landing-star-empty { color: #e5e7eb; }

.landing-testimonial-text {
    font-size: 0.9375rem;
    line-height: 1.7;
    color: var(--color-text);
    opacity: 0.8;
    font-style: italic;
}

.landing-testimonial-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    background: var(--color-primary);
    color: #fff;
    font-weight: 700;
    font-size: 0.875rem;
}

/* Contact */
.landing-contact-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.landing-contact-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    background: var(--color-primary);
    color: #fff;
    flex-shrink: 0;
}

/* Footer */
.landing-footer {
    padding: 2rem 0;
    background: var(--color-secondary, #1e40af);
}

.landing-social-link {
    color: rgba(255,255,255,0.6);
    transition: color 0.2s;
}
.landing-social-link:hover {
    color: #fff;
}
</style>
