<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import ScrollScene from '@/components/landing/ScrollScene.vue'

const props = defineProps<{
    brand: { name: string; phone: string | null; address: string | null }
    whatsappUrl: string | null
    loginUrl: string
}>()

// Booking via WhatsApp dimatikan sementara; ubah ke true untuk menyalakan lagi
const BOOKING_ON = false
const bookHref = computed(() => props.whatsappUrl ?? '#harga')
const bookExternal = computed(() => !!props.whatsappUrl)
const mapsHref = computed(
    () => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.brand.address ?? 'SPOT Rental PS5 Duren Tiga')}`,
)
const mapQuery = computed(() => props.brand.address || 'SPOT Rental PS5 Duren Tiga, Jakarta Selatan')
const mapEmbed = computed(() => `https://www.google.com/maps?q=${encodeURIComponent(mapQuery.value)}&output=embed`)
const address = computed(() => props.brand.address ?? 'Duren Tiga, Jakarta Selatan')

const steps = [
    ...(BOOKING_ON ? [{ verb: 'Chat', text: 'Kirim tanggal, jam, dan unit yang kamu mau lewat WhatsApp.' }] : [{ verb: 'Datang', text: 'Langsung ke lokasi. Unit siap dan stik sudah dicas.' }]),
    { verb: 'Pilih', text: 'Pilih PS5 Reguler, PS5 VIP, atau PS4 sesuai selera.' },
    { verb: 'Main', text: 'Bayar sesuai jam main. Mau nambah jam? Bilang ke kasir.' },
]
const games = [
    { name: 'EA FC', genre: 'Sepak bola', cover: '/images/games/ea-fc.jpg' },
    { name: 'GTA V', genre: 'Open world', cover: '/images/games/gta-v.jpg' },
    { name: 'Tekken 8', genre: 'Fighting', cover: '/images/games/tekken-8.jpg' },
    { name: 'Mortal Kombat', genre: 'Fighting', cover: '/images/games/mortal-kombat.jpg' },
    { name: 'God of War', genre: 'Petualangan', cover: '/images/games/god-of-war.jpg' },
    { name: 'Spider-Man', genre: 'Aksi', cover: '/images/games/spider-man.jpg' },
    { name: 'Call of Duty', genre: 'Tembak', cover: '/images/games/call-of-duty.jpg' },
    { name: 'NBA 2K', genre: 'Basket', cover: '/images/games/nba-2k.jpg' },
]
// Foto asli: isi URL (mis. '/img/ruangan.jpg') dan ilustrasi otomatis diganti <img>.
const photos = { room: '', place: '' }
const facilities = ['AC dingin', 'TV besar', 'Stik terawat', 'Charger', 'WiFi', 'Snack dan minuman']

const sections = [
    { id: 'hero', label: 'Beranda' },
    { id: 'harga', label: 'Harga' },
    { id: 'game', label: 'Game' },
    { id: 'fasilitas', label: 'Fasilitas' },
    { id: 'cara', label: 'Cara main' },
    { id: 'lokasi', label: 'Lokasi' },
]
const INK = '#17130f'
const ACC = '#ff5a1f'
const positions = [{ disc: INK }, { disc: ACC }, { disc: INK }, { disc: INK }, { disc: INK }, { disc: ACC }]

const root = ref<HTMLElement | null>(null)
let io: IntersectionObserver | null = null

onMounted(() => {
    const els = root.value?.querySelectorAll<HTMLElement>('[data-reveal]') ?? []
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        els.forEach((el) => el.classList.add('is-in'))
        return
    }
    io = new IntersectionObserver(
        (entries) =>
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-in')
                    io?.unobserve(e.target)
                }
            }),
        { threshold: 0.15 },
    )
    els.forEach((el) => io!.observe(el))
})
onBeforeUnmount(() => io?.disconnect())
</script>

<template>
    <Head title="SPOT | Rental PS5 Duren Tiga">
        <meta
            name="description"
            content="SPOT, rental PlayStation di Duren Tiga. PS5 mulai Rp10.000 per jam, buka Senin sampai Kamis 11.00 sampai 23.00, Jumat sampai Minggu 11.00 sampai 02.00."
        />
        <link rel="canonical" href="https://spodurentiga.com/" />
        <meta property="og:title" content="SPOT | Rental PS5 Duren Tiga" />
        <meta property="og:description" content="PS5 mulai Rp10.000 per jam. Senin-Kamis 11.00-23.00, Jumat-Minggu 11.00-02.00." />
        <meta property="og:url" content="https://spodurentiga.com/" />
        <meta property="og:type" content="website" />
    </Head>

    <div ref="root" class="lp">
        <header class="lp-nav">
            <a href="#hero" class="lp-brand">SPOT</a>
            <nav class="lp-nav-links">
                <a href="#harga">Harga</a>
                <a href="#game">Game</a>
                <a href="#fasilitas">Fasilitas</a>
                <a href="#lokasi">Lokasi</a>
                <a :href="loginUrl" class="lp-btn lp-btn-sm lp-btn-ghost">Login</a>
                <a v-if="BOOKING_ON" :href="bookHref" class="lp-btn lp-btn-sm" :target="bookExternal ? '_blank' : undefined" rel="noopener"><svg class="wa" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg><span>Booking</span></a>
            </nav>
        </header>

        <ScrollScene :sections="sections" :positions="positions">
        <!-- HERO (cream) -->
        <section id="hero" class="blk lp-hero">
            <div class="in in-r">
                <div class="copy">
                    <p class="eyebrow">Rental PS5 Duren Tiga</p>
                    <h1>Main PS mulai <em>Rp10 ribu</em>.</h1>
                    <p class="lead">PS4 setengah jam cuma Rp10.000. Ruangan ber-AC, TV besar, stik terawat. Buka Senin-Kamis 11.00-23.00, Jumat-Minggu 11.00-02.00.</p>
                    <div class="cta">
                        <a v-if="BOOKING_ON" :href="bookHref" class="lp-btn" :target="bookExternal ? '_blank' : undefined" rel="noopener"><svg class="wa" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>Booking via WhatsApp</a>
                        <a href="#harga" class="lp-btn lp-btn-ghost">Lihat harga</a>
                    </div>
                </div>
                <div class="stage" data-stage>
                    <div class="hero-price">
                        <strong>10<small>rb</small></strong>
                        <span>PS4, 30 menit</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- HARGA (ink) -->
        <section id="harga" class="blk blk-ink">
            <div class="in in-l">
                <div class="stage" data-stage></div>
                <div class="copy">
                    <h2 data-reveal>Pilih unit, bayar per jam.</h2>
                    <div class="bento">
                        <article class="tile t-main" data-reveal>
                            <h3>PS4</h3>
                            <p class="price">Rp15.000<small>/jam</small></p>
                            <p>Mulai Rp10.000 untuk 30 menit. Paling hemat untuk main santai.</p>
                        </article>
                        <article class="tile t-vip" data-reveal>
                            <h3>PS5 Reguler</h3>
                            <p class="price">Rp30.000<small>/jam</small></p>
                            <p>Dua stik, cocok berdua atau bertiga. Semua game di rak boleh dipilih.</p>
                        </article>
                        <article class="tile t-ps4" data-reveal>
                            <h3>PS5 VIP</h3>
                            <p class="price">Rp50.000<small>/jam</small></p>
                            <p>Ruangan lebih lega dan privat.</p>
                        </article>
                        <article class="tile t-nobar" data-reveal>
                            <div>
                                <h3>Nintendo Switch</h3>
                                <p>Main santai berdua, cocok untuk semua umur.</p>
                            </div>
                            <p class="price">Rp30.000<small>/jam</small></p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <!-- GAME (cream) -->
        <section id="game" class="blk">
            <div class="in in-r">
                <div class="copy">
                    <h2 data-reveal>Game yang siap dimainkan.</h2>
                    <ul class="games">
                        <li v-for="g in games" :key="g.name" :class="{ poster: g.cover }" data-reveal>
                            <img v-if="g.cover" :src="g.cover" :alt="`Cover game ${g.name}`" width="360" height="480" loading="lazy" decoding="async" />
                            <b>{{ g.name }}</b>
                            <span>{{ g.genre }}</span>
                        </li>
                    </ul>
                    <p class="note">Daftar bisa berubah. Cari judul lain? Tanya kasir.</p>
                </div>
                <div class="stage" data-stage></div>
            </div>
        </section>

        <!-- FASILITAS (accent) -->
        <section id="fasilitas" class="blk blk-acc">
            <div class="in in-l">
                <div class="stage" data-stage></div>
                <div class="copy">
                    <h2 data-reveal>Nyaman dari jam pertama.</h2>
                    <!-- FOTO SLOT: ganti photos.room di script dengan foto ruangan asli -->
                    <img v-if="photos.room" class="room" :src="photos.room" alt="Ruang main SPOT dengan TV dan konsol" width="1200" height="875" loading="lazy" decoding="async" />
                    <ul class="chips" data-reveal>
                        <li v-for="f in facilities" :key="f">{{ f }}</li>
                    </ul>
                    <p class="hours" data-reveal>Senin-Kamis 11.00-23.00 · Jumat-Minggu 11.00-02.00</p>
                </div>
            </div>
        </section>

        <!-- CARA (cream) -->
        <section id="cara" class="blk">
            <div class="in in-r">
                <div class="copy">
                    <h2 data-reveal>Tiga langkah, langsung main.</h2>
                    <ol class="steps">
                        <li v-for="s in steps" :key="s.verb" data-reveal>
                            <b>{{ s.verb }}</b>
                            <span>{{ s.text }}</span>
                        </li>
                    </ol>
                    <div class="cta" data-reveal>
                        <a v-if="BOOKING_ON" :href="bookHref" class="lp-btn" :target="bookExternal ? '_blank' : undefined" rel="noopener"><svg class="wa" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>Booking via WhatsApp</a>
                    </div>
                </div>
                <div class="stage" data-stage></div>
            </div>
        </section>

        <!-- LOKASI (ink) -->
        <section id="lokasi" class="blk blk-ink">
            <div class="in in-l">
                <div class="stage" data-stage></div>
                <div class="copy">
                    <h2 data-reveal>Tinggal mampir.</h2>
                    <div class="mapcard" data-reveal>
                        <iframe class="mapframe" :src="mapEmbed" title="Peta lokasi SPOT" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                        <div class="mapinfo">
                            <b>SPOT Rental PS5</b>
                            <p>{{ address }}</p>
                            <p>Senin-Kamis 11.00-23.00. Jumat-Minggu 11.00-02.00.<template v-if="brand.phone"> Telepon {{ brand.phone }}.</template></p>
                        </div>
                    </div>
                    <div class="cta" data-reveal>
                        <a :href="mapsHref" class="lp-btn" target="_blank" rel="noopener">Buka peta</a>
                        <a v-if="BOOKING_ON" :href="bookHref" class="lp-btn lp-btn-ghost" :target="bookExternal ? '_blank' : undefined" rel="noopener"><svg class="wa" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>Booking via WhatsApp</a>
                        <a :href="loginUrl" class="lp-btn lp-btn-ghost">Login</a>
                    </div>
                    <!-- FOTO SLOT: isi photos.place untuk foto tampak depan -->
                    <img v-if="photos.place" class="scene scene-s" :src="photos.place" alt="Tampak depan SPOT" />
                </div>
            </div>
        </section>
        </ScrollScene>

        <footer class="lp-foot">
            <span>SPOT, Rental PS5 Duren Tiga</span>
            <a :href="loginUrl">Login Staf</a>
        </footer>
    </div>
</template>

<style scoped>
.lp {
    --cream: #f3efe8; --cream2: #e8e1d3; --ink: #17130f; --acc: #ff5a1f; --r: 20px;
    background: var(--cream); color: var(--ink); min-height: 100dvh; overflow-x: clip;
    font-family: ui-sans-serif, system-ui, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}
.lp a { color: inherit; text-decoration: none; }
.lp-nav {
    position: sticky; top: 0; z-index: 20; display: flex; justify-content: space-between; align-items: center;
    height: 64px; padding: 0 24px; background: rgb(243 239 232 / 0.92); backdrop-filter: blur(8px);
    border-bottom: 2px solid var(--ink);
}
.lp-brand { font-weight: 900; font-size: 1.6rem; letter-spacing: -0.06em; }
.lp-nav-links { display: flex; gap: 22px; align-items: center; font-size: 14px; font-weight: 600; }
.lp-nav-links a:not(.lp-btn):hover { color: var(--acc); }
@media (max-width: 720px) { .lp-nav-links a:not(.lp-btn) { display: none; } .lp-nav-links { gap: 8px; } }

.lp-btn {
    display: inline-flex; align-items: center; justify-content: center; white-space: nowrap;
    padding: 14px 24px; border-radius: 999px; font-weight: 800; background: var(--acc); color: var(--ink) !important;
    border: 2px solid var(--ink); transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s;
}
.lp-btn:hover { transform: translate(-2px, -2px); box-shadow: 3px 3px 0 var(--ink); }
.lp-btn:active { transform: scale(0.97); box-shadow: none; }
.lp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; }
.wa { flex: none; }
.lp-btn-sm { padding: 7px 16px; font-size: 14px; }
.lp-btn-ghost { background: transparent; color: inherit !important; border-color: currentColor; }
.blk-ink .lp-btn { border-color: var(--cream); }
.blk-ink .lp-btn:hover { box-shadow: 3px 3px 0 var(--cream); }
.blk-ink .lp-btn-ghost { border-color: var(--cream); color: var(--cream) !important; }
.lp-nav .lp-btn-ghost { color: var(--ink) !important; }
.cta { display: flex; flex-wrap: wrap; gap: 12px; }
.lead { font-size: 1.15rem; line-height: 1.55; max-width: 44ch; margin-bottom: 14px; }
.eyebrow { font-size: 13px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 18px; }

.blk { min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; padding: 64px 0; position: relative; background: var(--cream); }
.blk-ink { background: var(--ink); color: var(--cream); }
.blk-acc { background: var(--acc); color: var(--ink); }
.lp-hero { background: var(--cream) radial-gradient(circle at 1px 1px, rgb(23 19 15 / 0.16) 1.5px, transparent 0) 0 0 / 26px 26px; padding-top: 56px; }
.in { width: 100%; max-width: 1240px; margin: 0 auto; padding: 0 24px; display: grid; gap: 40px; align-items: center; }
@media (min-width: 900px) {
    .in { padding: 0 64px 0 48px; }
    .in-r { grid-template-columns: minmax(0, 1fr) 400px; column-gap: 72px; }
    .in-l { grid-template-columns: 400px minmax(0, 1fr); column-gap: 72px; }
}
@media (max-width: 899px) { .stage { display: none; } .in { gap: 0; } .blk { padding: 48px 0; min-height: 0; } }
.stage { position: relative; align-self: stretch; min-height: 380px; }
.in .copy { min-width: 0; }
.blk h2 { font-size: clamp(2.2rem, 5.2vw, 4rem); line-height: 0.98; letter-spacing: -0.05em; font-weight: 900; margin-bottom: 32px; max-width: 14ch; }

.lp-hero h1 { font-size: clamp(2.4rem, 4.6vw, 4.2rem); line-height: 1.08; letter-spacing: -0.04em; font-weight: 900; margin-bottom: 28px; max-width: 16ch; }
.lp-hero h1 em { font-style: normal; display: inline-block; white-space: nowrap; background: var(--acc); padding: 0 0.14em 0.04em; margin-top: 0.06em; border-radius: 12px; line-height: 1.05; }
.lp-hero .lead { margin-bottom: 28px; }
.hero-price {
    position: absolute; left: 50%; bottom: 8px; transform: translateX(-50%) rotate(-4deg);
    background: var(--acc); border: 2px solid var(--ink); border-radius: var(--r); padding: 14px 26px 12px; line-height: 1; text-align: center;
    box-shadow: 5px 5px 0 var(--ink); z-index: 6;
}
.hero-price strong { font-size: 3.2rem; letter-spacing: -0.05em; font-weight: 900; }
.hero-price small { font-size: 1.4rem; }
.hero-price span { display: block; font-size: 13px; margin-top: 6px; font-weight: 800; }

.bento { display: grid; gap: 14px; grid-template-columns: 1fr; }
.tile { border-radius: var(--r); padding: 26px; display: flex; flex-direction: column; gap: 8px; border: 2px solid var(--cream); }
.tile h3 { font-size: 1.1rem; font-weight: 800; }
.tile p { line-height: 1.45; }
.tile .price { font-size: 2rem; font-weight: 900; letter-spacing: -0.04em; }
.tile .price small { font-size: 0.9rem; font-weight: 600; opacity: 0.7; letter-spacing: 0; }
.t-main { background: var(--acc); color: var(--ink); border-color: var(--acc); justify-content: center; }
.t-main .price { font-size: clamp(2.6rem, 5vw, 3.8rem); }
.t-vip { background: var(--cream); color: var(--ink); }
.t-ps4 { background: var(--cream2); color: var(--ink); }
.t-nobar { flex-direction: row; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px; border-style: dashed; }
@media (min-width: 720px) {
    .bento { grid-template-columns: 1.3fr 1fr; }
    .t-main { grid-row: span 2; }
    .t-nobar { grid-column: 1 / -1; }
}

.games { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (min-width: 640px) { .games { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.games li { border: 2px solid var(--ink); border-radius: var(--r); padding: 18px 16px; display: flex; flex-direction: column; gap: 6px; background: var(--cream2); min-height: 108px; justify-content: space-between; transition: transform 0.25s, background 0.25s; }
.games li:hover { background: var(--acc); transform: translateY(-3px) rotate(-1deg); }
.games li.poster { position: relative; padding: 0; overflow: hidden; aspect-ratio: 3 / 4; min-height: 0; justify-content: flex-end; background: var(--ink); }
.games li.poster img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.games li.poster b, .games li.poster span { position: relative; color: #fff; padding: 0 12px; }
.games li.poster b { padding-top: 40px; margin-bottom: -6px; background: none; }
.games li.poster span { padding-bottom: 12px; opacity: 0.85; }
.games li.poster::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 60%; background: linear-gradient(to top, rgba(20,16,12,0.92), transparent); }
.games li.poster b, .games li.poster span { z-index: 1; }
.games li.poster:hover { background: var(--ink); }
.games b { font-size: 1.15rem; font-weight: 900; letter-spacing: -0.03em; line-height: 1.1; }
.games span { font-size: 13px; font-weight: 600; opacity: 0.7; }
.note { margin-top: 20px; font-weight: 600; }

.room { display: block; width: 100%; height: auto; aspect-ratio: 4 / 3; object-fit: cover; border: 2px solid var(--ink); border-radius: var(--r); margin-bottom: 16px; }
@media (min-width: 640px) { .room { aspect-ratio: 16 / 10; } }
.chips { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.chips li { background: var(--cream); border: 2px solid var(--ink); border-radius: 999px; padding: 6px 14px; font-weight: 700; font-size: 14px; }
.hours { font-weight: 800; }
.scene { display: block; width: 100%; height: auto; max-height: 240px; object-fit: cover; border: 2px solid var(--ink); border-radius: var(--r); }
.scene-s { margin-top: 24px; max-width: 560px; }
.mapcard { display: grid; grid-template-columns: minmax(0, 1fr); border: 2px solid var(--cream); border-radius: var(--r); overflow: hidden; margin-bottom: 24px; max-width: 620px; }
.mapframe { display: block; width: 100%; aspect-ratio: 4 / 3; border: 0; background: #2a231b; }
@media (min-width: 640px) { .mapframe { aspect-ratio: 16 / 10; } }
.mapinfo { padding: 22px; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.mapinfo b { font-size: 1.3rem; font-weight: 900; letter-spacing: -0.03em; }
.mapinfo p { line-height: 1.5; }

.steps { list-style: none; padding: 0; margin-bottom: 28px; }
.steps li { display: grid; grid-template-columns: minmax(9.5rem, auto) 1fr; gap: 8px 28px; align-items: baseline; padding: 22px 0; border-top: 2px solid var(--ink); }
.steps li:last-child { border-bottom: 2px solid var(--ink); }
.steps b { font-size: clamp(1.8rem, 3.4vw, 2.6rem); letter-spacing: -0.05em; font-weight: 900; }
.steps span { line-height: 1.5; max-width: 36ch; }
@media (max-width: 560px) { .steps li { grid-template-columns: 1fr; } }

.lp-foot { background: #0e0c0a; color: var(--cream); padding: 26px 24px 34px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; }
.lp-foot a { opacity: 0.7; padding: 12px 4px; }
.lp-foot a:hover { opacity: 1; }

[data-reveal] { opacity: 0; transform: translateY(20px); transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
[data-reveal].is-in { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) { [data-reveal] { opacity: 1; transform: none; transition: none; } .lp-btn, .games li { transition: none; } }
</style>

<style scoped>
.lp-nav { padding-left: max(16px, env(safe-area-inset-left)); padding-right: max(16px, env(safe-area-inset-right)); }
.lp-foot { padding-bottom: max(34px, env(safe-area-inset-bottom)); }
.lp-btn-sm { min-height: 44px; }
@media (max-width: 899px) { .lp-btn { min-height: 48px; } .in { padding-left: max(20px, env(safe-area-inset-left)); padding-right: max(20px, env(safe-area-inset-right)); } }
@media (max-width: 380px) {
    .lp-nav { padding: 0 10px; } .lp-brand { font-size: 1.3rem; }
    .lp-btn-sm { padding: 7px 11px; font-size: 13px; }
    .lp-hero h1 { font-size: 2.5rem; } .tile { padding: 20px; } .cta .lp-btn { flex: 1 1 100%; }
}
</style>

<style scoped>
.lp-brand { display: inline-flex; align-items: center; min-height: 44px; }
.lp-nav-links a:not(.lp-btn) { display: inline-flex; align-items: center; min-height: 44px; }
@media (max-width: 720px) { .lp-nav-links a:not(.lp-btn) { display: none; } }
</style>
