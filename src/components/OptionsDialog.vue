<script setup lang="ts">
import { ref, computed } from 'vue'
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
</script>

<template>
    <form class="image-converter__options-dialog" @submit.prevent="onSubmit">
        <fieldset class="mode">
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

        <label v-if="mode === 'preset'">
            {{ t('imageconverter', 'Target size (KB)') }}
            <input v-model.number="targetKb" type="number" min="1" />
        </label>

        <template v-else>
            <label>
                {{ t('imageconverter', 'Max long edge (px)') }}
                <input v-model.number="maxLongEdge" type="number" min="16" max="10000" />
            </label>
            <label>
                {{ t('imageconverter', 'Quality') }}
                <input v-model.number="quality" type="range" min="1" max="100" />
                <span>{{ quality }}</span>
            </label>
        </template>

        <label>
            <input v-model="deleteOriginal" type="checkbox" />
            {{ t('imageconverter', 'Move original to trash on success') }}
        </label>

        <p class="info">{{ t('imageconverter', 'Applies to {n} selected file(s)', { n: fileCount }) }}</p>

        <div class="actions">
            <button type="button" @click="emit('cancel')">{{ t('imageconverter', 'Cancel') }}</button>
            <button type="submit" :disabled="submitDisabled">{{ t('imageconverter', 'Convert') }}</button>
        </div>
    </form>
</template>

<style scoped>
.image-converter__options-dialog {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px;
    min-width: 320px;
}
.image-converter__options-dialog label {
    display: flex;
    align-items: center;
    gap: 8px;
}
.image-converter__options-dialog .info {
    opacity: 0.8;
    font-size: 0.9em;
    margin: 0;
}
.image-converter__options-dialog .actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
}
</style>
