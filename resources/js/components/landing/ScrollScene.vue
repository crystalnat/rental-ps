<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import HeroScene from './HeroScene.vue'

// Each section may contain an element [data-stage]: the reserved column where the 3D object lives.
export interface ScenePos { disc: string; opacity?: number; scale?: number }
const props = defineProps<{
    sections: { id: string; label: string }[]
    positions: ScenePos[]
}>()

const active = ref(0)
const progress = ref(0)
const labelKey = ref(0)
const showLabel = ref(false)
const mobile = ref(false)
const sceneOk = ref(false)
const sceneFail = ref(false)
const stageX = ref<number[]>([])
const stageOk = ref<boolean[]>([])
const moving = ref(false)
let movT = 0
let idleT = 0

let els: HTMLElement[] = []
let raf = 0
let mq: MediaQueryList | null = null

const measureStages = () => {
    stageX.value = els.map((el) => {
        const st = el.querySelector<HTMLElement>('[data-stage]')
        if (!st) return window.innerWidth / 2
        const r = st.getBoundingClientRect()
        return r.left + r.width / 2
    })
    stageOk.value = els.map((el) => !!el.querySelector('[data-stage]'))
}
const measure = () => {
    raf = 0
    const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight)
    progress.value = Math.min(1, Math.max(0, window.scrollY / max))
    const mid = window.innerHeight / 2
    let best = 0
    let bestD = Infinity
    els.forEach((el, i) => {
        const r = el.getBoundingClientRect()
        const d = Math.abs(r.top + r.height / 2 - mid)
        if (d < bestD) { bestD = d; best = i }
    })
    if (best !== active.value) {
        active.value = best
        moving.value = true
        clearTimeout(movT)
        movT = window.setTimeout(() => { moving.value = false }, 700)
        labelKey.value++
        showLabel.value = true
    }
}
const settle = () => { measureStages(); measure(); moving.value = false }
const onScroll = () => {
    if (!raf) raf = requestAnimationFrame(measure)
    clearTimeout(idleT)
    idleT = window.setTimeout(settle, 160)
}
const onResize = () => { measureStages(); onScroll() }
const onMq = () => { mobile.value = !!mq?.matches; measureStages() }

const go = (i: number) => els[i]?.scrollIntoView({ behavior: 'smooth', block: 'start' })

const layerStyle = () => {
    const p = props.positions[active.value] ?? props.positions[0]
    if (mobile.value) {
        return { transform: 'translate(calc(50vw - 50%), calc(50vh - 50%)) scale(1.15)', opacity: 0.1 }
    }
    const x = stageX.value[active.value] ?? window.innerWidth * 0.75
    return {
        transform: `translate(calc(${x}px - 50%), calc(50vh - 50%)) scale(${p.scale ?? 1})`,
        opacity: stageOk.value[active.value] === false ? 0 : moving.value ? 0.25 : p.opacity ?? 1,
    }
}
const disc = () => props.positions[active.value]?.disc ?? '#17130f'

onMounted(() => {
    els = props.sections.map((s) => document.getElementById(s.id)).filter(Boolean) as HTMLElement[]
    mq = window.matchMedia('(max-width: 899px)')
    onMq()
    mq.addEventListener('change', onMq)
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onResize, { passive: true })
    measure()
    showLabel.value = false
    setTimeout(onResize, 400)
})
onBeforeUnmount(() => {
    cancelAnimationFrame(raf)
    clearTimeout(idleT); clearTimeout(movT)
    mq?.removeEventListener('change', onMq)
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onResize)
})
</script>

<template>
    <div class="ss">
        <div class="ss-bar" aria-hidden="true"><i :style="{ transform: `scaleX(${progress})` }"></i></div>

        <div class="ss-layer" :style="layerStyle()" aria-hidden="true">
            <div class="ss-box">
                <span class="ss-disc" :style="{ background: disc() }"></span>
                <HeroScene v-if="!sceneFail" @ready="sceneOk = true" @fail="sceneFail = true" />
                <div v-if="!sceneOk" class="pad">
                    <i class="b b-tri"></i><i class="b b-cir"></i><i class="b b-crs"></i><i class="b b-sqr"></i>
                </div>
            </div>
        </div>

        <nav class="ss-dots" aria-label="Bagian halaman">
            <button
                v-for="(s, i) in sections"
                :key="s.id"
                type="button"
                :class="{ on: i === active }"
                :aria-label="s.label"
                :aria-current="i === active"
                @click="go(i)"
            ></button>
            <span v-if="showLabel" :key="labelKey" class="ss-label">{{ sections[active]?.label }}</span>
        </nav>

        <div class="ss-content"><slot /></div>
    </div>
</template>

<style scoped>
.ss-bar { position: fixed; top: 0; left: 0; right: 0; height: 4px; z-index: 40; pointer-events: none; }
.ss-bar i { display: block; height: 100%; background: #ff5a1f; transform-origin: 0 50%; transform: scaleX(0); }

.ss-layer {
    position: fixed; left: 0; top: 0; z-index: 5; pointer-events: none;
    width: clamp(260px, 44vmin, 360px); aspect-ratio: 1;
    transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.35s ease;
    will-change: transform;
}
.ss-box { position: relative; width: 100%; height: 100%; display: grid; place-items: center; }
.ss-disc { position: absolute; inset: 6%; border-radius: 50%; transition: background 0.8s ease; }
.ss-content { position: relative; z-index: 2; }
@media (max-width: 899px) { .ss-disc { display: none; } }

.ss-dots {
    position: fixed; right: 16px; top: 50%; transform: translateY(-50%); z-index: 30;
    display: none; flex-direction: column; gap: 12px; align-items: center;
    padding: 10px 6px; border-radius: 999px; background: rgb(243 239 232 / 0.85); border: 2px solid #17130f;
}
@media (min-width: 900px) { .ss-dots { display: flex; } }
.ss-dots button {
    width: 10px; height: 10px; border-radius: 50%; border: 2px solid #17130f; background: transparent; padding: 0; cursor: pointer;
    transition: transform 0.3s, background 0.3s;
}
.ss-dots button.on { background: #ff5a1f; transform: scale(1.4); }
.ss-label {
    position: absolute; right: 34px; top: 50%; margin-top: -13px; white-space: nowrap;
    font-size: 12px; font-weight: 700; color: #17130f; background: #f3efe8; border: 2px solid #17130f;
    padding: 3px 10px; border-radius: 999px; opacity: 0;
    animation: fadeOutLabel 1.8s ease-out both;
}
@keyframes fadeOutLabel { 0% { opacity: 1; } 70% { opacity: 1; } 100% { opacity: 0; } }

.pad { position: relative; width: 56%; aspect-ratio: 1; animation: bob 6s ease-in-out infinite; }
.b { position: absolute; width: 34%; aspect-ratio: 1; border-radius: 50%; border: 6px solid #ff5a1f; box-sizing: border-box; }
.b-tri { left: 33%; top: 0; clip-path: polygon(50% 8%, 96% 92%, 4% 92%); background: #ff5a1f; border: 0; }
.b-cir { right: 0; top: 33%; }
.b-crs { left: 33%; bottom: 0; background:
    linear-gradient(45deg, transparent 43%, #ff5a1f 43% 57%, transparent 57%),
    linear-gradient(-45deg, transparent 43%, #ff5a1f 43% 57%, transparent 57%); border: 0; border-radius: 0; }
.b-sqr { left: 0; top: 33%; border-radius: 8px; }
@keyframes bob { 50% { transform: translateY(-10px) rotate(2deg); } }

@media (prefers-reduced-motion: reduce) {
    .ss-layer { transition: none; }
    .pad, .ss-label { animation: none; }
}
</style>
