<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const emit = defineEmits<{ (e: 'ready'): void; (e: 'fail'): void }>()
const host = ref<HTMLDivElement | null>(null)
let cleanup: (() => void) | null = null
let disposed = false

onMounted(async () => {
    const el = host.value
    if (!el) return
    try {
        const c = document.createElement('canvas')
        if (!(c.getContext('webgl2') || c.getContext('webgl'))) throw new Error('no webgl')
        const THREE = await import('three')
        if (disposed) return

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' })
        renderer.setClearColor(0x000000, 0)
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, window.innerWidth < 900 ? 1.25 : 1.75))
        el.appendChild(renderer.domElement)
        renderer.domElement.style.cssText = 'width:100%;height:100%;display:block'

        const scene = new THREE.Scene()
        const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 50)
        camera.position.set(0, 0, 11.5)
        scene.add(new THREE.HemisphereLight(0xffffff, 0xd8d0c4, 1.8))
        const key = new THREE.DirectionalLight(0xffffff, 2.2)
        key.position.set(3, 5, 6)
        scene.add(key)
        const rim = new THREE.PointLight(0xff5a1f, 18, 30)
        rim.position.set(-4, -2, 3)
        scene.add(rim)

        const geos: any[] = []
        const mats: any[] = []
        const G = (g: any) => {
            geos.push(g)
            return g
        }
        const M = (color: number, rough = 0.45, metal = 0.05) => {
            const m = new THREE.MeshStandardMaterial({ color, roughness: rough, metalness: metal })
            mats.push(m)
            return m
        }
        const slate = M(0xece8e1, 0.55)
        const dark = M(0x1f1b17, 0.6)
        const blue = M(0xff5a1f, 0.35)
        const white = M(0x2a2622, 0.4)

        const pad = new THREE.Group()
        scene.add(pad)

        // body: bean outline extruded
        const s = new THREE.Shape()
        s.moveTo(-1.5, 0.75)
        s.bezierCurveTo(-1.2, 1.05, 1.2, 1.05, 1.5, 0.75)
        s.bezierCurveTo(2.5, 0.6, 3.0, -1.4, 2.35, -1.75)
        s.bezierCurveTo(1.7, -2.05, 1.3, -1.2, 0.85, -0.6)
        s.bezierCurveTo(0.4, -0.25, -0.4, -0.25, -0.85, -0.6)
        s.bezierCurveTo(-1.3, -1.2, -1.7, -2.05, -2.35, -1.75)
        s.bezierCurveTo(-3.0, -1.4, -2.5, 0.6, -1.5, 0.75)
        const bodyG = G(new THREE.ExtrudeGeometry(s, { depth: 0.5, bevelEnabled: true, bevelThickness: 0.2, bevelSize: 0.2, bevelSegments: 3, curveSegments: 16 }))
        bodyG.center()
        const body = new THREE.Mesh(bodyG, slate)
        body.position.y = -0.1
        pad.add(body)
        const z = 0.5

        const tp = new THREE.Mesh(G(new THREE.BoxGeometry(1.5, 0.5, 0.06)), dark)
        tp.position.set(0, 0.35, z)
        pad.add(tp)

        const flat = (shape: any, mat: any, x: number, y: number) => {
            const m = new THREE.Mesh(G(new THREE.ExtrudeGeometry(shape, { depth: 0.08, bevelEnabled: false })), mat)
            m.position.set(x, y, z + 0.1)
            pad.add(m)
        }
        const ring = (r: number, w: number) => {
            const sh = new THREE.Shape()
            sh.absarc(0, 0, r, 0, Math.PI * 2, false)
            const h = new THREE.Path()
            h.absarc(0, 0, r - w, 0, Math.PI * 2, true)
            sh.holes.push(h)
            return sh
        }
        const poly = (pts: number[][]) => {
            const sh = new THREE.Shape()
            pts.forEach(([x, y], i) => (i ? sh.lineTo(x, y) : sh.moveTo(x, y)))
            sh.closePath()
            return sh
        }
        const polyPath = (pts: number[][]) => {
            const p = new THREE.Path()
            pts.forEach(([x, y], i) => (i ? p.lineTo(x, y) : p.moveTo(x, y)))
            p.closePath()
            return p
        }

        // face buttons
        const fx = 1.55
        const fy = -0.1
        const d = 0.5
        ;[[0, d], [d, 0], [0, -d], [-d, 0]].forEach(([x, y]) => {
            const b = new THREE.Mesh(G(new THREE.CylinderGeometry(0.27, 0.27, 0.12, 32)), white)
            b.rotation.x = Math.PI / 2
            b.position.set(fx + x, fy + y, z + 0.04)
            pad.add(b)
        })
        const tri = poly([[0, 0.15], [-0.14, -0.1], [0.14, -0.1]])
        tri.holes.push(polyPath([[0, 0.07], [0.07, -0.05], [-0.07, -0.05]]))
        flat(tri, blue, fx, fy + d + 0.02)
        flat(ring(0.14, 0.05), blue, fx + d, fy)
        const a = 0.15
        const t = 0.035
        flat(
            poly([[-a, -a + t], [-a + t, -a], [0, -t * 1.4], [a - t, -a], [a, -a + t], [t * 1.4, 0], [a, a - t], [a - t, a], [0, t * 1.4], [-a + t, a], [-a, a - t], [-t * 1.4, 0]]),
            blue, fx, fy - d,
        )
        const sq = poly([[-0.12, -0.12], [0.12, -0.12], [0.12, 0.12], [-0.12, 0.12]])
        sq.holes.push(polyPath([[-0.07, -0.07], [-0.07, 0.07], [0.07, 0.07], [0.07, -0.07]]))
        flat(sq, blue, fx - d, fy)

        // d-pad
        const w = 0.16
        const l = 0.46
        const dp = poly([[-w, l], [w, l], [w, w], [l, w], [l, -w], [w, -w], [w, -l], [-w, -l], [-w, -w], [-l, -w], [-l, w], [-w, w]])
        const dpm = new THREE.Mesh(G(new THREE.ExtrudeGeometry(dp, { depth: 0.12, bevelEnabled: true, bevelThickness: 0.03, bevelSize: 0.03, bevelSegments: 3 })), white)
        dpm.position.set(-1.55, fy, z)
        pad.add(dpm)

        // thumbsticks
        ;[[-0.75, -0.95], [0.75, -0.95]].forEach(([x, y]) => {
            const parts: [number, number, number, number, any][] = [
                [0.34, 0.34, 0.02, 0.06, dark],
                [0.12, 0.14, 0.14, 0.2, dark],
                [0.27, 0.25, 0.28, 0.14, blue],
            ]
            parts.forEach(([rt, rb, zz, h, mat]) => {
                const m = new THREE.Mesh(G(new THREE.CylinderGeometry(rt, rb, h, 32)), mat)
                m.rotation.x = Math.PI / 2
                m.position.set(x, y, z + zz)
                pad.add(m)
            })
        })

        const floaters: any[] = []

        const resize = () => {
            const cw = el.clientWidth || 1
            const ch = el.clientHeight || 1
            renderer.setSize(cw, ch, false)
            camera.aspect = cw / ch
            camera.updateProjectionMatrix()
            if (!raf) frame(performance.now())
        }

        const ease = (v: number) => 1 - Math.pow(1 - v, 3)
        const clamp = (v: number) => Math.min(1, Math.max(0, v))
        const start = performance.now()
        let visible = true
        let tabVisible = !document.hidden
        let raf = 0
        const INTRO = 1.6

        const frame = (now: number) => {
            raf = 0
            const tt = (now - start) / 1000
            const intro = reduce ? 1 : ease(clamp(tt / INTRO))
            const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight)
            const p = reduce ? 0 : clamp(window.scrollY / max)
            pad.rotation.y = -0.35 + (1 - intro) * Math.PI * 3 + Math.sin(p * Math.PI * 4) * 0.7 + (reduce ? 0 : Math.sin(tt * 0.6) * 0.18)
            pad.rotation.x = 0.25 + Math.sin(p * Math.PI * 3) * 0.3 + (reduce ? 0 : Math.cos(tt * 0.5) * 0.06)
            pad.rotation.z = -0.08 + Math.sin(p * Math.PI * 2) * 0.2
            pad.position.y = reduce ? 0 : Math.sin(tt * 1.1) * 0.15
            pad.scale.setScalar(0.4 + 0.6 * intro)
            camera.position.set(0, 0, 11.5)
            camera.lookAt(0, 0, 0)
            floaters.forEach((f) => {
                const k = f.userData.i
                f.position.y = f.userData.y + (reduce ? 0 : Math.sin(tt * 0.8 + k) * 0.2) + Math.sin(p * 6 + k) * 0.3
                f.rotation.x = reduce ? k : tt * 0.4 + k
                f.rotation.y = reduce ? k : tt * 0.5 + k
                f.scale.setScalar(intro)
            })
            renderer.render(scene, camera)
            const animating = !reduce && (tt < INTRO + 0.1 || true)
            if (animating) schedule()
        }
        const schedule = () => {
            if (!raf && visible && tabVisible && !disposed) raf = requestAnimationFrame(frame)
        }

        resize()
        const ro = new ResizeObserver(resize)
        ro.observe(el)
        const io = new IntersectionObserver((es) => {
            visible = es[0].isIntersecting
            if (visible) schedule()
        })
        io.observe(el)
        const onVis = () => {
            tabVisible = !document.hidden
            if (tabVisible) schedule()
        }
        document.addEventListener('visibilitychange', onVis)

        emit('ready')

        cleanup = () => {
            cancelAnimationFrame(raf)
            io.disconnect()
            ro.disconnect()
            document.removeEventListener('visibilitychange', onVis)
            geos.forEach((g) => g.dispose())
            mats.forEach((m) => m.dispose())
            renderer.dispose()
            renderer.domElement.remove()
        }
    } catch {
        emit('fail')
    }
})

onBeforeUnmount(() => {
    disposed = true
    cleanup?.()
})
</script>

<template>
    <div ref="host" class="hero-scene"></div>
</template>

<style scoped>
.hero-scene { position: absolute; inset: 0; pointer-events: none; }
</style>
