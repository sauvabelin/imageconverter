<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import type { DialogResult } from './OptionsDialog.types'

defineProps<{
    fileCount: number
}>()

const emit = defineEmits<{
    (e: 'submit', value: DialogResult): void
    (e: 'cancel'): void
}>()

const mode = ref<'preset' | 'custom'>('preset')
const targetKb = ref<number>(1024)
const maxLongEdge = ref<number>(2048)
const quality = ref<number>(82)
const deleteOriginal = ref<boolean>(true)

const submitDisabled = computed(() => {
    if (mode.value === 'preset') {
        return targetKb.value <= 0
    }
    return (
        maxLongEdge.value < 16 ||
        maxLongEdge.value > 10000 ||
        quality.value < 1 ||
        quality.value > 100
    )
})

function onSubmit() {
    emit('submit', {
        mode: mode.value,
        targetBytes: targetKb.value * 1024,
        maxLongEdge: mode.value === 'custom' ? maxLongEdge.value : null,
        quality: mode.value === 'custom' ? quality.value : null,
        deleteOriginal: deleteOriginal.value,
    })
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('cancel')
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
    <div class="ic-modal__backdrop" role="presentation" @click.self="emit('cancel')">
        <div class="ic-modal__card" role="dialog" aria-modal="true" :aria-label="t('imageconverter', 'Convert images')">
            <header class="ic-modal__header">
                <h2>{{ t('imageconverter', 'Convert images') }}</h2>
                <button type="button" class="ic-modal__close" :aria-label="t('imageconverter', 'Close')" @click="emit('cancel')">×</button>
            </header>

            <form class="ic-modal__body" @submit.prevent="onSubmit">
                <fieldset class="ic-modal__mode">
                    <legend>{{ t('imageconverter', 'Conversion mode') }}</legend>
                    <label>
                        <input v-model="mode" type="radio" value="preset" />
                        {{ t('imageconverter', 'Target size') }}
                    </label>
                    <label>
                        <input v-model="mode" type="radio" value="custom" />
                        {{ t('imageconverter', 'Custom settings') }}
                    </label>
                </fieldset>

                <label v-if="mode === 'preset'" class="ic-modal__field">
                    <span>{{ t('imageconverter', 'Target size (KB)') }}</span>
                    <input v-model.number="targetKb" type="number" min="1" />
                </label>

                <template v-else>
                    <label class="ic-modal__field">
                        <span>{{ t('imageconverter', 'Max long edge (px)') }}</span>
                        <input v-model.number="maxLongEdge" type="number" min="16" max="10000" />
                    </label>
                    <label class="ic-modal__field">
                        <span>{{ t('imageconverter', 'Quality') }}: {{ quality }}</span>
                        <input v-model.number="quality" type="range" min="1" max="100" />
                    </label>
                </template>

                <label class="ic-modal__check">
                    <input v-model="deleteOriginal" type="checkbox" />
                    {{ t('imageconverter', 'Move original to trash on success') }}
                </label>

                <p class="ic-modal__info">{{ t('imageconverter', 'Applies to {n} selected file(s)', { n: fileCount }) }}</p>

                <div class="ic-modal__actions">
                    <button type="button" class="button" @click="emit('cancel')">{{ t('imageconverter', 'Cancel') }}</button>
                    <button type="submit" class="button primary" :disabled="submitDisabled">{{ t('imageconverter', 'Convert') }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.ic-modal__backdrop {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}
.ic-modal__card {
    background: var(--color-main-background, #fff);
    color: var(--color-main-text, #222);
    border-radius: var(--border-radius-large, 8px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    min-width: 360px;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.ic-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--color-border, #ddd);
}
.ic-modal__header h2 {
    margin: 0;
    font-size: 1.1rem;
}
.ic-modal__close {
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: inherit;
    padding: 0 4px;
}
.ic-modal__body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow-y: auto;
}
.ic-modal__mode {
    border: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.ic-modal__mode legend {
    font-weight: bold;
    padding: 0;
    margin-bottom: 4px;
}
.ic-modal__field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.ic-modal__field input[type="number"],
.ic-modal__field input[type="range"] {
    width: 100%;
}
.ic-modal__check {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ic-modal__info {
    opacity: 0.8;
    font-size: 0.9em;
    margin: 0;
}
.ic-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
}
</style>
