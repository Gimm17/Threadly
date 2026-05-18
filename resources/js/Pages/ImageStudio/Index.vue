<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import {
    IconPhoto,
    IconEdit,
    IconMessageCircle,
    IconPhotoScan,
    IconHeart,
    IconHeartFilled,
    IconTrash,
    IconDownload,
    IconLoader2,
    IconSparkles,
    IconUpload,
    IconX,
} from '@tabler/icons-vue'

const props = defineProps({
    media: Object,
    styles: Array,
    aspectRatios: Array,
    qualities: Array,
    backgrounds: Array,
})

// Tabs
const tabs = [
    { id: 'poster', label: 'Poster', icon: IconSparkles, description: 'Generate poster social media dengan prompt brand dan overlay teks rapi' },
    { id: 'text-to-image', label: 'Advanced', icon: IconPhoto, description: 'Generate gambar dari deskripsi teks via endpoint model yang sesuai' },
    { id: 'image-edit', label: 'Edit Gambar', icon: IconEdit, description: 'Edit gambar yang sudah ada via /v1/images/edits' },
    { id: 'chat-generation', label: 'Chat Advanced', icon: IconMessageCircle, description: 'Generate gambar via multimodal chat completions' },
    { id: 'reference-generation', label: 'Dari Referensi', icon: IconPhotoScan, description: 'Generate gambar berdasarkan referensi via multimodal chat' },
]
const activeTab = ref('poster')

// Shared state
const loading = ref(false)
const error = ref('')
const generatedImage = ref(null)

// Poster Form
const posterForm = ref({
    prompt: '',
    headline: '',
    style: 'minimalist',
    aspect_ratio: '4:5',
    quality: 'auto',
    background: 'auto',
    allow_ai_text: false,
})

// Text-to-image form
const t2iForm = ref({
    prompt: '',
    style: 'realistic',
    aspect_ratio: '1:1',
    quality: 'auto',
    background: 'auto',
})

// Image edit form
const editForm = ref({
    prompt: '',
    image: null,
    aspect_ratio: '1:1',
})
const editPreview = ref(null)

// Chat generation form
const chatForm = ref({
    prompt: '',
    style: 'realistic',
    aspect_ratio: '1:1',
})

// Reference generation form
const refForm = ref({
    prompt: '',
    reference_image: null,
    reference_url: '',
    aspect_ratio: '1:1',
})
const refPreview = ref(null)

// Gallery
const showGallery = ref(true)

// Handlers

function handleFileSelect(event, formType) {
    const file = event.target.files[0]
    if (!file) return

    if (formType === 'edit') {
        editForm.value.image = file
        editPreview.value = URL.createObjectURL(file)
    } else if (formType === 'reference') {
        refForm.value.reference_image = file
        refForm.value.reference_url = ''
        refPreview.value = URL.createObjectURL(file)
    }
}

function removeFile(formType) {
    if (formType === 'edit') {
        editForm.value.image = null
        editPreview.value = null
    } else if (formType === 'reference') {
        refForm.value.reference_image = null
        refPreview.value = null
    }
}

async function generateTextToImage() {
    if (!t2iForm.value.prompt.trim()) return
    loading.value = true
    error.value = ''
    generatedImage.value = null

    try {
        const payload = { ...t2iForm.value }
        const { data } = await axios.post(route('image-studio.generate'), payload)
        if (data.success) {
            generatedImage.value = data.media
            router.reload({ only: ['media'] })
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal generate gambar.'
    } finally {
        loading.value = false
    }
}

async function generatePoster() {
    if (!posterForm.value.prompt.trim()) return
    loading.value = true
    error.value = ''
    generatedImage.value = null

    try {
        const { data } = await axios.post(route('image-studio.poster'), { ...posterForm.value })
        if (data.success) {
            generatedImage.value = data.media
            if (data.queued) {
                pollMedia(data.media.id)
            } else {
                router.reload({ only: ['media'] })
            }
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal generate poster.'
    } finally {
        loading.value = false
    }
}

function pollMedia(id, attempt = 0) {
    if (!id || attempt > 30) return
    window.setTimeout(async () => {
        try {
            const { data } = await axios.get(route('image-studio.jobs.show', id))
            generatedImage.value = data.media
            if (data.media?.generation_status === 'queued') {
                pollMedia(id, attempt + 1)
            } else {
                router.reload({ only: ['media'] })
            }
        } catch (e) {
            error.value = 'Gagal mengambil status poster.'
        }
    }, 3000)
}

async function editImage() {
    if (!editForm.value.prompt.trim() || !editForm.value.image) return
    loading.value = true
    error.value = ''
    generatedImage.value = null

    const formData = new FormData()
    formData.append('prompt', editForm.value.prompt)
    formData.append('image', editForm.value.image)
    formData.append('aspect_ratio', editForm.value.aspect_ratio)

    try {
        const { data } = await axios.post(route('image-studio.edit'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        if (data.success) {
            generatedImage.value = data.media
            router.reload({ only: ['media'] })
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal edit gambar.'
    } finally {
        loading.value = false
    }
}

async function generateFromChat() {
    if (!chatForm.value.prompt.trim()) return
    loading.value = true
    error.value = ''
    generatedImage.value = null

    try {
        const payload = { ...chatForm.value }
        const { data } = await axios.post(route('image-studio.generate-chat'), payload)
        if (data.success) {
            generatedImage.value = data.media
            router.reload({ only: ['media'] })
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal generate gambar via chat.'
    } finally {
        loading.value = false
    }
}

async function generateFromReference() {
    if (!refForm.value.prompt.trim()) return
    if (!refForm.value.reference_image && !refForm.value.reference_url) {
        error.value = 'Upload gambar referensi atau masukkan URL.'
        return
    }
    loading.value = true
    error.value = ''
    generatedImage.value = null

    const formData = new FormData()
    formData.append('prompt', refForm.value.prompt)
    formData.append('aspect_ratio', refForm.value.aspect_ratio)
    if (refForm.value.reference_image) {
        formData.append('reference_image', refForm.value.reference_image)
    } else {
        formData.append('reference_url', refForm.value.reference_url)
    }

    try {
        const { data } = await axios.post(route('image-studio.generate-reference'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        if (data.success) {
            generatedImage.value = data.media
            router.reload({ only: ['media'] })
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal generate dari referensi.'
    } finally {
        loading.value = false
    }
}

async function toggleFavorite(item) {
    try {
        const { data } = await axios.post(route('image-studio.favorite', item.id))
        item.is_favorite = data.is_favorite
    } catch (e) {
        // silent
    }
}

function deleteMedia(item) {
    if (!confirm('Hapus gambar ini?')) return
    router.delete(route('image-studio.destroy', item.id))
}

async function downloadImage(item) {
    if (!hasOverlay(item)) {
        const link = document.createElement('a')
        link.href = item.url
        link.download = item.file_name || 'ai-generated.png'
        link.click()
        return
    }

    try {
        const image = await loadImage(item.url)
        const canvas = document.createElement('canvas')
        canvas.width = image.naturalWidth || image.width
        canvas.height = image.naturalHeight || image.height

        const ctx = canvas.getContext('2d')
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height)
        drawOverlay(ctx, canvas.width, canvas.height, item.overlay_config)

        const link = document.createElement('a')
        const baseName = (item.file_name || 'ai-generated.png').replace(/\.[^.]+$/, '')
        link.href = canvas.toDataURL('image/png')
        link.download = `${baseName}-overlay.png`
        link.click()
    } catch (e) {
        const link = document.createElement('a')
        link.href = item.url
        link.download = item.file_name || 'ai-generated.png'
        link.click()
    }
}

const currentForm = computed(() => {
    switch (activeTab.value) {
        case 'poster': return generatePoster
        case 'text-to-image': return generateTextToImage
        case 'image-edit': return editImage
        case 'chat-generation': return generateFromChat
        case 'reference-generation': return generateFromReference
        default: return generateTextToImage
    }
})

const modeLabel = computed(() => {
    return tabs.find(t => t.id === activeTab.value)?.label || 'Generate'
})

function getModeLabel(mode) {
    const labels = {
        'poster': 'Poster',
        'text-to-image': 'Text to Image',
        'advanced': 'Advanced',
        'image-edit': 'Image Edit',
        'chat-generation': 'Chat',
        'reference-generation': 'Reference',
    }
    return labels[mode] || mode
}

function hasOverlay(item) {
    return Boolean(item?.overlay_config?.enabled && item.overlay_config?.headline)
}

function overlayClass(item) {
    const placement = item?.overlay_config?.placement || 'lower_third'
    return [
        'pointer-events-none absolute left-3 right-3 z-10 rounded-lg bg-slate-950/72 px-3 py-2 text-white shadow-lg backdrop-blur-sm',
        placement === 'top' ? 'top-3' : 'bottom-3',
    ]
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image()
        image.crossOrigin = 'anonymous'
        image.onload = () => resolve(image)
        image.onerror = reject
        image.src = url
    })
}

function roundedRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2)
    ctx.beginPath()
    ctx.moveTo(x + r, y)
    ctx.lineTo(x + width - r, y)
    ctx.quadraticCurveTo(x + width, y, x + width, y + r)
    ctx.lineTo(x + width, y + height - r)
    ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height)
    ctx.lineTo(x + r, y + height)
    ctx.quadraticCurveTo(x, y + height, x, y + height - r)
    ctx.lineTo(x, y + r)
    ctx.quadraticCurveTo(x, y, x + r, y)
    ctx.closePath()
}

function wrapLines(ctx, text, maxWidth) {
    const words = text.split(/\s+/).filter(Boolean)
    const lines = []
    let line = ''

    words.forEach((word) => {
        const next = line ? `${line} ${word}` : word
        if (ctx.measureText(next).width <= maxWidth) {
            line = next
        } else {
            if (line) lines.push(line)
            line = word
        }
    })

    if (line) lines.push(line)
    return lines
}

function drawOverlay(ctx, width, height, overlay) {
    const headline = overlay?.headline || ''
    if (!headline) return

    const handle = overlay?.brand_handle ? `@${overlay.brand_handle}` : ''
    const padding = Math.round(width * 0.055)
    const boxPadding = Math.round(width * 0.035)
    const boxWidth = width - padding * 2
    const headlineSize = Math.max(34, Math.round(width * 0.052))
    const handleSize = Math.max(18, Math.round(width * 0.026))

    ctx.font = `700 ${headlineSize}px Inter, Arial, sans-serif`
    const lines = wrapLines(ctx, headline, boxWidth - boxPadding * 2).slice(0, 3)
    const lineHeight = Math.round(headlineSize * 1.18)
    const handleHeight = handle ? Math.round(handleSize * 1.5) : 0
    const boxHeight = boxPadding * 2 + lines.length * lineHeight + handleHeight
    const y = (overlay?.placement === 'top')
        ? padding
        : height - padding - boxHeight

    ctx.fillStyle = 'rgba(8, 19, 38, 0.78)'
    roundedRect(ctx, padding, y, boxWidth, boxHeight, Math.round(width * 0.02))
    ctx.fill()

    ctx.fillStyle = '#ffffff'
    let textY = y + boxPadding + headlineSize
    lines.forEach((line) => {
        ctx.fillText(line, padding + boxPadding, textY)
        textY += lineHeight
    })

    if (handle) {
        ctx.font = `600 ${handleSize}px Inter, Arial, sans-serif`
        ctx.fillStyle = '#b7f7c8'
        ctx.fillText(handle, padding + boxPadding, y + boxHeight - boxPadding)
    }
}
</script>

<template>
    <Head title="Image Studio" />

    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary flex items-center gap-2">
                <IconSparkles class="w-6 h-6" />
                Image Studio
            </h2>
        </template>

        <!-- Subheader / Description -->
        <div class="mb-6">
            <p class="text-body-sm text-on-surface-variant">
                Generate, edit, dan remix gambar dengan AI - powered by TokenRouter
            </p>
        </div>

        <div class="space-y-6">
            <!-- Tabs -->
            <div class="card p-0 overflow-hidden">
                <div class="flex border-b border-outline-variant/30 overflow-x-auto bg-surface-container-lowest">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id; error = ''; generatedImage = null"
                        :class="[
                            'flex items-center gap-2 px-5 py-3.5 text-sm font-semibold whitespace-nowrap transition-colors border-b-2 -mb-px',
                            activeTab === tab.id
                                ? 'border-primary text-primary bg-primary/5'
                                : 'border-transparent text-on-surface-variant hover:text-on-background hover:bg-surface-container'
                        ]"
                    >
                        <component :is="tab.icon" class="w-4.5 h-4.5" />
                        {{ tab.label }}
                    </button>
                </div>

                <div class="p-6">
                    <!-- Tab Description -->
                    <p class="text-sm font-medium text-on-background mb-5">
                        {{ tabs.find(t => t.id === activeTab)?.description }}
                    </p>

                    <!-- TAB 1: Text to Image -->
                    <div v-if="activeTab === 'poster'" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-body-sm font-semibold text-on-background mb-1.5">Brief Poster</label>
                                <textarea
                                    v-model="posterForm.prompt"
                                    rows="4"
                                    class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                    placeholder="Contoh: Poster edukasi AI untuk UMKM agar kerja admin lebih cepat dan rapi"
                                />
                            </div>
                            <div>
                                <label class="block text-body-sm font-semibold text-on-background mb-1.5">Headline Overlay</label>
                                <input
                                    v-model="posterForm.headline"
                                    type="text"
                                    maxlength="80"
                                    class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                    placeholder="Contoh: AI Bantu UMKM Naik Kelas"
                                />
                                <label class="mt-4 flex items-center gap-2 text-sm text-on-background">
                                    <input v-model="posterForm.allow_ai_text" type="checkbox" class="rounded border-outline-variant text-secondary focus:ring-secondary" />
                                    Render headline langsung di gambar AI
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Style</label>
                                <select v-model="posterForm.style" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="s in styles" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Aspect Ratio</label>
                                <select v-model="posterForm.aspect_ratio" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="a in aspectRatios" :key="a.value" :value="a.value">{{ a.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Quality</label>
                                <select v-model="posterForm.quality" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="q in qualities" :key="q.value" :value="q.value">{{ q.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Background</label>
                                <select v-model="posterForm.background" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="b in backgrounds" :key="b.value" :value="b.value">{{ b.label }}</option>
                                </select>
                            </div>
                        </div>

                        <button
                            @click="generatePoster"
                            :disabled="loading || !posterForm.prompt.trim()"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-container text-on-primary-container hover:brightness-105 disabled:opacity-50 font-bold rounded-lg text-sm transition-all"
                        >
                            <IconLoader2 v-if="loading" class="w-4 h-4 animate-spin" />
                            <IconSparkles v-else class="w-4 h-4" />
                            Generate Poster
                        </button>
                    </div>

                    <div v-if="activeTab === 'text-to-image'" class="space-y-4">
                        <div>
                            <label class="block text-body-sm font-semibold text-on-background mb-1.5">Prompt</label>
                            <textarea
                                v-model="t2iForm.prompt"
                                rows="3"
                                class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                placeholder="Deskripsikan gambar yang ingin Anda buat..."
                            />
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Style</label>
                                <select v-model="t2iForm.style" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="s in styles" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Aspect Ratio</label>
                                <select v-model="t2iForm.aspect_ratio" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="a in aspectRatios" :key="a.value" :value="a.value">{{ a.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Quality</label>
                                <select v-model="t2iForm.quality" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="q in qualities" :key="q.value" :value="q.value">{{ q.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Background</label>
                                <select v-model="t2iForm.background" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="b in backgrounds" :key="b.value" :value="b.value">{{ b.label }}</option>
                                </select>
                            </div>
                        </div>

                        <button
                            @click="generateTextToImage"
                            :disabled="loading || !t2iForm.prompt.trim()"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-container text-on-primary-container hover:brightness-105 disabled:opacity-50 font-bold rounded-lg text-sm transition-all"
                        >
                            <IconLoader2 v-if="loading" class="w-4 h-4 animate-spin" />
                            <IconSparkles v-else class="w-4 h-4" />
                            Generate Gambar
                        </button>
                    </div>

                    <!-- TAB 2: Image Edit -->
                    <div v-if="activeTab === 'image-edit'" class="space-y-4">
                        <div>
                            <label class="block text-body-sm font-semibold text-on-background mb-1.5">Upload Gambar</label>
                            <div
                                v-if="!editPreview"
                                class="border-2 border-dashed border-outline-variant rounded-lg p-8 text-center cursor-pointer hover:border-secondary hover:bg-secondary/5 transition"
                                @click="$refs.editFileInput.click()"
                            >
                                <IconUpload class="w-8 h-8 mx-auto text-on-surface-variant mb-2" />
                                <p class="text-sm text-on-background font-medium">Klik untuk upload gambar</p>
                                <p class="text-xs text-on-surface-variant mt-1">PNG, JPG, WEBP - max 10MB</p>
                            </div>
                            <div v-else class="relative inline-block">
                                <img :src="editPreview" class="max-h-48 rounded-lg border border-outline-variant/30" />
                                <button @click="removeFile('edit')" class="absolute -top-2 -right-2 bg-error text-on-error rounded-full p-1 hover:brightness-110 shadow">
                                    <IconX class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <input ref="editFileInput" type="file" accept="image/*" class="hidden" @change="handleFileSelect($event, 'edit')" />
                        </div>

                        <div>
                            <label class="block text-body-sm font-semibold text-on-background mb-1.5">Instruksi Edit</label>
                            <textarea
                                v-model="editForm.prompt"
                                rows="3"
                                class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                placeholder="Contoh: Ubah latar belakang menjadi pemandangan pantai..."
                            />
                        </div>

                        <div class="max-w-xs">
                            <label class="block text-label-caps text-on-surface-variant mb-1">Aspect Ratio</label>
                            <select v-model="editForm.aspect_ratio" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                <option v-for="a in aspectRatios" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </select>
                        </div>

                        <button
                            @click="editImage"
                            :disabled="loading || !editForm.prompt.trim() || !editForm.image"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-container text-on-primary-container hover:brightness-105 disabled:opacity-50 font-bold rounded-lg text-sm transition-all"
                        >
                            <IconLoader2 v-if="loading" class="w-4 h-4 animate-spin" />
                            <IconEdit v-else class="w-4 h-4" />
                            Edit Gambar
                        </button>
                    </div>

                    <!-- TAB 3: Chat Generation -->
                    <div v-if="activeTab === 'chat-generation'" class="space-y-4">
                        <div>
                            <label class="block text-body-sm font-semibold text-on-background mb-1.5">Prompt</label>
                            <textarea
                                v-model="chatForm.prompt"
                                rows="3"
                                class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                placeholder="Deskripsikan gambar yang ingin Anda buat via chat completions..."
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4 max-w-md">
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Style</label>
                                <select v-model="chatForm.style" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="s in styles" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-label-caps text-on-surface-variant mb-1">Aspect Ratio</label>
                                <select v-model="chatForm.aspect_ratio" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                    <option v-for="a in aspectRatios" :key="a.value" :value="a.value">{{ a.label }}</option>
                                </select>
                            </div>
                        </div>

                        <button
                            @click="generateFromChat"
                            :disabled="loading || !chatForm.prompt.trim()"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-container text-on-primary-container hover:brightness-105 disabled:opacity-50 font-bold rounded-lg text-sm transition-all"
                        >
                            <IconLoader2 v-if="loading" class="w-4 h-4 animate-spin" />
                            <IconMessageCircle v-else class="w-4 h-4" />
                            Generate via Chat
                        </button>
                    </div>

                    <!-- TAB 4: Reference Generation -->
                    <div v-if="activeTab === 'reference-generation'" class="space-y-4">
                        <div>
                            <label class="block text-body-sm font-semibold text-on-background mb-1.5">Gambar Referensi</label>
                            <div class="space-y-3">
                                <!-- File Upload -->
                                <div
                                    v-if="!refPreview"
                                    class="border-2 border-dashed border-outline-variant rounded-lg p-6 text-center cursor-pointer hover:border-secondary hover:bg-secondary/5 transition"
                                    @click="$refs.refFileInput.click()"
                                >
                                    <IconUpload class="w-7 h-7 mx-auto text-on-surface-variant mb-2" />
                                    <p class="text-sm text-on-background font-medium">Upload gambar referensi</p>
                                    <p class="text-xs text-on-surface-variant mt-1">PNG, JPG, WEBP - max 10MB</p>
                                </div>
                                <div v-else class="relative inline-block">
                                    <img :src="refPreview" class="max-h-40 rounded-lg border border-outline-variant/30" />
                                    <button @click="removeFile('reference')" class="absolute -top-2 -right-2 bg-error text-on-error rounded-full p-1 hover:brightness-110 shadow">
                                        <IconX class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <input ref="refFileInput" type="file" accept="image/*" class="hidden" @change="handleFileSelect($event, 'reference')" />

                                <!-- OR URL -->
                                <div v-if="!refForm.reference_image" class="flex items-center gap-3">
                                    <span class="text-xs text-on-surface-variant uppercase font-bold">atau</span>
                                    <input
                                        v-model="refForm.reference_url"
                                        type="url"
                                        class="flex-1 rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                        placeholder="Paste URL gambar referensi..."
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-body-sm font-semibold text-on-background mb-1.5">Prompt Instruksi</label>
                            <textarea
                                v-model="refForm.prompt"
                                rows="3"
                                class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none"
                                placeholder="Contoh: Buat gambar serupa tapi dengan gaya watercolor..."
                            />
                        </div>

                        <div class="max-w-xs">
                            <label class="block text-label-caps text-on-surface-variant mb-1">Aspect Ratio</label>
                            <select v-model="refForm.aspect_ratio" class="w-full rounded-lg border-outline-variant px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none">
                                <option v-for="a in aspectRatios" :key="a.value" :value="a.value">{{ a.label }}</option>
                            </select>
                        </div>

                        <button
                            @click="generateFromReference"
                            :disabled="loading || !refForm.prompt.trim() || (!refForm.reference_image && !refForm.reference_url)"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-container text-on-primary-container hover:brightness-105 disabled:opacity-50 font-bold rounded-lg text-sm transition-all"
                        >
                            <IconLoader2 v-if="loading" class="w-4 h-4 animate-spin" />
                            <IconPhotoScan v-else class="w-4 h-4" />
                            Generate dari Referensi
                        </button>
                    </div>

                    <!-- Loading Overlay -->
                    <div v-if="loading" class="mt-6 flex items-center gap-3 p-4 bg-tertiary-fixed rounded-lg border border-tertiary-fixed-dim">
                        <IconLoader2 class="w-5 h-5 text-on-tertiary-container animate-spin" />
                        <div>
                            <p class="text-sm font-bold text-on-tertiary-container">Sedang generate gambar...</p>
                            <p class="text-xs text-on-tertiary-container/80">Proses ini bisa memakan waktu 30-120 detik</p>
                        </div>
                    </div>

                    <!-- Error -->
                    <div v-if="error" class="mt-4 p-4 bg-error-container border border-error-container/50 rounded-lg">
                        <p class="text-sm font-medium text-on-error-container">{{ error }}</p>
                    </div>

                    <!-- Generated Result -->
                    <div v-if="generatedImage && !loading" class="mt-6 p-4 bg-success/10 rounded-lg border border-success/30">
                        <p class="text-sm font-bold text-success mb-3">Gambar berhasil dibuat!</p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div v-if="generatedImage.url" class="relative inline-block overflow-hidden rounded-lg shadow-sm">
                                <img :src="generatedImage.url" class="max-h-64" />
                                <div v-if="hasOverlay(generatedImage)" :class="overlayClass(generatedImage)">
                                    <p class="text-sm sm:text-base font-bold leading-tight">{{ generatedImage.overlay_config.headline }}</p>
                                    <p class="mt-1 text-[11px] font-semibold text-green-200">@{{ generatedImage.overlay_config.brand_handle }}</p>
                                </div>
                            </div>
                            <div v-else class="w-48 h-48 rounded-lg border border-outline-variant/30 bg-surface-container flex items-center justify-center text-sm text-on-surface-variant">
                                {{ generatedImage.generation_status === 'queued' ? 'Dalam antrean AI...' : 'Belum ada file gambar' }}
                            </div>
                            <div class="space-y-1 text-sm text-on-background">
                                <p><span class="font-bold text-on-surface-variant">Mode:</span> {{ getModeLabel(generatedImage.generation_mode) }}</p>
                                <p><span class="font-bold text-on-surface-variant">Status:</span> {{ generatedImage.generation_status || 'completed' }}</p>
                                <p v-if="generatedImage.style"><span class="font-bold text-on-surface-variant">Style:</span> {{ generatedImage.style }}</p>
                                <p><span class="font-bold text-on-surface-variant">Ratio:</span> {{ generatedImage.aspect_ratio }}</p>
                                <p v-if="generatedImage.estimated_cost"><span class="font-bold text-on-surface-variant">Est. cost:</span> ${{ generatedImage.estimated_cost }}</p>
                                <p class="text-xs text-on-surface-variant mt-2">{{ generatedImage.created_at }}</p>
                                <button @click="downloadImage(generatedImage)" class="inline-flex items-center gap-1.5 mt-2 text-primary hover:text-on-primary-container text-sm font-bold transition-colors">
                                    <IconDownload class="w-4 h-4" /> Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gallery -->
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/30 bg-surface-container-lowest">
                    <h2 class="text-headline-md text-on-background">Galeri Gambar</h2>
                    <button @click="showGallery = !showGallery" class="text-sm font-semibold text-secondary hover:text-on-secondary-container transition-colors">
                        {{ showGallery ? 'Sembunyikan' : 'Tampilkan' }}
                    </button>
                </div>

                <div v-if="showGallery" class="p-6 bg-surface">
                    <div v-if="media?.data?.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div
                            v-for="item in media.data"
                            :key="item.id"
                            class="group relative rounded-xl overflow-hidden border border-outline-variant/30 bg-surface-container-lowest shadow-sm hover:shadow-md transition-all"
                        >
                            <div v-if="item.url" class="relative">
                                <img
                                    :src="item.url"
                                    :alt="item.prompt"
                                    class="w-full aspect-square object-cover"
                                    loading="lazy"
                                />
                                <div v-if="hasOverlay(item)" :class="overlayClass(item)">
                                    <p class="text-[11px] font-bold leading-tight line-clamp-2">{{ item.overlay_config.headline }}</p>
                                    <p class="mt-0.5 text-[9px] font-semibold text-green-200">@{{ item.overlay_config.brand_handle }}</p>
                                </div>
                            </div>
                            <div v-else class="w-full aspect-square bg-surface-container flex items-center justify-center text-xs text-on-surface-variant">
                                {{ item.generation_status || 'queued' }}
                            </div>

                            <!-- Overlay -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/60 transition-all flex items-end opacity-0 group-hover:opacity-100">
                                <div class="w-full p-3 space-y-2">
                                    <p class="text-white text-xs font-medium line-clamp-2">{{ item.prompt }}</p>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-white bg-black/40 backdrop-blur-sm px-2 py-0.5 rounded-full border border-white/20">
                                            {{ getModeLabel(item.generation_mode || 'text-to-image') }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <button @click.stop="toggleFavorite(item)" class="p-1 rounded hover:bg-white/20 transition">
                                                <IconHeartFilled v-if="item.is_favorite" class="w-4 h-4 text-red-400" />
                                                <IconHeart v-else class="w-4 h-4 text-white/90" />
                                            </button>
                                            <button @click.stop="downloadImage(item)" class="p-1 rounded hover:bg-white/20 transition">
                                                <IconDownload class="w-4 h-4 text-white/90" />
                                            </button>
                                            <button @click.stop="deleteMedia(item)" class="p-1 rounded hover:bg-white/20 transition">
                                                <IconTrash class="w-4 h-4 text-white/90" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-12">
                        <IconPhoto class="w-12 h-12 mx-auto text-on-surface-variant/50 mb-3" />
                        <p class="text-sm font-semibold text-on-background">Belum ada gambar yang digenerate.</p>
                        <p class="text-xs text-on-surface-variant mt-1">Mulai generate gambar pertama Anda di tab di atas.</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="media?.links?.length > 3" class="mt-8 flex justify-center gap-1">
                        <template v-for="link in media.links" :key="link.label">
                            <button
                                v-if="link.url"
                                @click="router.get(link.url)"
                                :class="[
                                    'px-3 py-1.5 text-sm rounded-lg border transition font-medium',
                                    link.active
                                        ? 'bg-primary-container text-on-primary-container border-primary-container'
                                        : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant/30 hover:bg-surface-container hover:text-on-background'
                                ]"
                                v-html="link.label"
                            />
                            <span v-else class="px-3 py-1.5 text-sm text-on-surface-variant/50 font-medium" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
