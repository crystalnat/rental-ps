<script setup lang="ts">
import { ref, watch } from 'vue'
import { Star, MessageSquare, ThumbsUp, Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import axios from 'axios'

const props = defineProps<{
    orderId: number | null
    orderCode?: string
}>()

const emit = defineEmits<{
    close: []
}>()

const rating = ref(5)
const comment = ref('')
const isSubmitting = ref(false)
const isSuccess = ref(false)

async function submitFeedback() {
    if (!props.orderId) return
    isSubmitting.value = true
    try {
        await axios.post(`/admin/orders/${props.orderId}/feedback`, {
            rating: rating.value,
            comment: comment.value,
        })
        isSuccess.value = true
        setTimeout(() => {
            close()
        }, 1500)
    } catch (e) {
        console.error('Failed to submit feedback', e)
    } finally {
        isSubmitting.value = false
    }
}

function close() {
    isSuccess.value = false
    rating.value = 5
    comment.value = ''
    emit('close')
}
</script>

<template>
    <div v-if="orderId" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 transition-all backdrop-blur-sm">
        <div class="relative w-full max-w-sm overflow-hidden rounded-2xl bg-card shadow-2xl animate-in zoom-in-95 duration-200">
            <!-- Header/Banner -->
            <div class="bg-primary p-6 text-center text-primary-foreground">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                    <ThumbsUp class="h-6 w-6" />
                </div>
                <h3 class="text-lg font-bold">Terima Kasih!</h3>
                <p class="text-xs opacity-90">Pesanan {{ orderCode }} berhasil diselesaikan.</p>
            </div>

            <!-- Body -->
            <div class="p-6">
                <div v-if="!isSuccess">
                    <div class="mb-6 text-center">
                        <p class="mb-4 text-sm font-medium text-muted-foreground">Bagaimana pengalaman pelanggan?</p>
                        <div class="flex justify-center gap-2">
                            <button
                                v-for="i in 5"
                                :key="i"
                                @click="rating = i"
                                type="button"
                                class="transition-transform active:scale-95"
                            >
                                <Star
                                    class="h-8 w-8 transition-colors"
                                    :class="i <= rating ? 'fill-yellow-400 text-yellow-400' : 'text-muted'"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            <MessageSquare class="h-3 w-3" /> Catatan Tambahan (Opsional)
                        </div>
                        <Textarea
                            v-model="comment"
                            placeholder="Tuliskan feedback dari pelanggan di sini..."
                            rows="3"
                            class="resize-none text-sm"
                        />
                    </div>
                </div>

                <!-- Success State -->
                <div v-else class="flex flex-col items-center justify-center py-8 text-center animate-in fade-in zoom-in-50">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                        <ThumbsUp class="h-8 w-8" />
                    </div>
                    <h4 class="text-lg font-bold">Feedback Terkirim</h4>
                    <p class="text-sm text-muted-foreground">Terima kasih atas masukannya!</p>
                </div>
            </div>

            <!-- Footer -->
            <div v-if="!isSuccess" class="flex flex-col gap-2 border-t bg-muted/30 p-4">
                <Button 
                    class="w-full font-bold uppercase tracking-widest text-[11px]" 
                    @click="submitFeedback"
                    :disabled="isSubmitting"
                >
                    <Loader2 v-if="isSubmitting" class="mr-2 h-3 w-3 animate-spin" />
                    Kirim Feedback
                </Button>
                <button 
                    class="w-full py-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground hover:text-foreground"
                    @click="close"
                    :disabled="isSubmitting"
                >
                    Mungkin Nanti
                </button>
            </div>
        </div>
    </div>
</template>
