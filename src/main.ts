import {
    FileAction,
    Node,
    NodeStatus,
    registerFileAction,
} from '@nextcloud/files'
import type { View } from '@nextcloud/files'
import {
    davGetClient,
    davGetDefaultPropfind,
    davGetRootPath,
    davResultToNode,
} from '@nextcloud/files'
import { emit } from '@nextcloud/event-bus'
import { showSuccess, showError } from '@nextcloud/dialogs'
// (No Vue runtime needed — we render the options modal with vanilla DOM
//  via the browser's native <dialog> element, which avoids z-index, focus
//  trap, backdrop, and ESC-handling pitfalls of a hand-rolled overlay.)
import '@nextcloud/dialogs/style.css'
import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import imageIcon from '@mdi/svg/svg/image-edit-outline.svg?raw'

import type { DialogResult } from './components/OptionsDialog.types'
import { runBounded } from './concurrency'

const BASE_URL = generateUrl('/apps/imageconverter')
const CONCURRENCY = 4
const DEFAULT_TARGET_BYTES = 1024 * 1024

const SUPPORTED_MIMES = new Set([
    'image/jpeg',
    'image/pjpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/bmp',
    'image/x-ms-bmp',
    'image/tiff',
    'image/heic',
    'image/heif',
    'image/avif',
])

type Payload = {
    id: number
    filename: string
    mode: 'preset' | 'custom'
    targetBytes: number
    maxLongEdge?: number
    quality?: number
    deleteOriginal: boolean
}

type BackendResponse = {
    result: 'converted' | 'skipped'
    reason?: string
    newFilename: string
    bytesIn: number
    bytesOut: number
    widthOut: number
    heightOut: number
    qualityUsed: number
    originalTrashed: boolean
}

function allSupported(nodes: Node[]): boolean {
    return nodes.length > 0 && nodes.every((n) => !!n.mime && SUPPORTED_MIMES.has(n.mime))
}

function presetSettings(): DialogResult {
    return {
        mode: 'preset',
        targetBytes: DEFAULT_TARGET_BYTES,
        maxLongEdge: null,
        quality: null,
        deleteOriginal: true,
    }
}

function openOptionsDialog(fileCount: number): Promise<DialogResult | null> {
    return new Promise((resolve) => {
        const dlg = document.createElement('dialog')
        dlg.className = 'imageconverter-options-dialog'
        // Inline styles so we don't depend on the stylesheet being loaded.
        dlg.style.cssText = [
            'border: none',
            'border-radius: 8px',
            'padding: 0',
            'background: var(--color-main-background, #fff)',
            'color: var(--color-main-text, #222)',
            'box-shadow: 0 8px 32px rgba(0,0,0,0.4)',
            'min-width: 360px',
            'max-width: 90vw',
        ].join(';')
        dlg.innerHTML = `
            <form method="dialog" style="display:flex;flex-direction:column;gap:12px;padding:16px;">
                <h2 style="margin:0 0 4px;font-size:1.1rem;">${t('imageconverter', 'Convert images')}</h2>
                <fieldset style="border:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;">
                    <legend style="font-weight:bold;padding:0;">${t('imageconverter', 'Conversion mode')}</legend>
                    <label><input type="radio" name="mode" value="preset" checked> ${t('imageconverter', 'Target size')}</label>
                    <label><input type="radio" name="mode" value="custom"> ${t('imageconverter', 'Custom settings')}</label>
                </fieldset>
                <label data-field="preset" style="display:flex;flex-direction:column;gap:4px;">
                    <span>${t('imageconverter', 'Target size (KB)')}</span>
                    <input type="number" name="targetKb" value="1024" min="1">
                </label>
                <label data-field="custom" style="display:none;flex-direction:column;gap:4px;">
                    <span>${t('imageconverter', 'Max long edge (px)')}</span>
                    <input type="number" name="maxLongEdge" value="2048" min="16" max="10000">
                </label>
                <label data-field="custom" style="display:none;flex-direction:column;gap:4px;">
                    <span>${t('imageconverter', 'Quality')}: <output name="qualityOut">82</output></span>
                    <input type="range" name="quality" value="82" min="1" max="100">
                </label>
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="deleteOriginal" checked>
                    ${t('imageconverter', 'Move original to trash on success')}
                </label>
                <p style="opacity:0.8;font-size:0.9em;margin:0;">${t('imageconverter', 'Applies to {n} selected file(s)', { n: fileCount })}</p>
                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" data-action="cancel" class="button">${t('imageconverter', 'Cancel')}</button>
                    <button type="submit" data-action="submit" class="button primary">${t('imageconverter', 'Convert')}</button>
                </div>
            </form>
        `

        document.body.appendChild(dlg)

        const form = dlg.querySelector('form')!
        const qualityInput = form.querySelector<HTMLInputElement>('input[name="quality"]')!
        const qualityOut = form.querySelector<HTMLOutputElement>('output[name="qualityOut"]')!
        qualityInput.addEventListener('input', () => {
            qualityOut.value = qualityInput.value
        })

        const presetFields = form.querySelectorAll<HTMLElement>('[data-field="preset"]')
        const customFields = form.querySelectorAll<HTMLElement>('[data-field="custom"]')
        form.querySelectorAll<HTMLInputElement>('input[name="mode"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                const isPreset = (form.elements.namedItem('mode') as RadioNodeList).value === 'preset'
                presetFields.forEach((el) => (el.style.display = isPreset ? 'flex' : 'none'))
                customFields.forEach((el) => (el.style.display = isPreset ? 'none' : 'flex'))
            })
        })

        let settled = false
        const finish = (value: DialogResult | null) => {
            if (settled) return
            settled = true
            dlg.close()
            dlg.remove()
            resolve(value)
        }

        form.querySelector<HTMLButtonElement>('[data-action="cancel"]')!.addEventListener('click', () => {
            finish(null)
        })
        dlg.addEventListener('cancel', (e) => {
            e.preventDefault()
            finish(null)
        })
        form.addEventListener('submit', (e) => {
            e.preventDefault()
            const data = new FormData(form)
            const mode = String(data.get('mode')) as 'preset' | 'custom'
            const targetKb = Number(data.get('targetKb') || 1024)
            finish({
                mode,
                targetBytes: targetKb * 1024,
                maxLongEdge: mode === 'custom' ? Number(data.get('maxLongEdge')) : null,
                quality: mode === 'custom' ? Number(data.get('quality')) : null,
                deleteOriginal: data.get('deleteOriginal') !== null,
            })
        })

        dlg.showModal()
    })
}

function dirnameWithTrailingSlash(dir: string): string {
    return dir.endsWith('/') ? dir : dir + '/'
}

function formatBytes(n: number): string {
    if (n < 1024) return n + ' B'
    if (n < 1024 * 1024) return (n / 1024).toFixed(0) + ' KB'
    if (n < 1024 * 1024 * 1024) return (n / (1024 * 1024)).toFixed(1) + ' MB'
    return (n / (1024 * 1024 * 1024)).toFixed(2) + ' GB'
}

async function runConversionBatch(
    nodes: Node[],
    dir: string,
    settings: DialogResult,
): Promise<(boolean | null)[]> {
    const dirname = dirnameWithTrailingSlash(dir)
    const token = getRequestToken()
    if (!token) {
        showError(t('imageconverter', 'Unable to get a request token'))
        return nodes.map(() => false)
    }

    nodes.forEach((n) => {
        n.status = NodeStatus.LOADING
    })

    const davClient = davGetClient()
    let bytesInTotal = 0
    let bytesOutTotal = 0

    const outcomes = await runBounded(nodes, CONCURRENCY, async (node) => {
        const fileId = Number(node.fileid ?? (node as unknown as { id: number }).id)
        const payload: Payload = {
            id: fileId,
            filename: node.basename,
            mode: settings.mode,
            targetBytes: settings.targetBytes,
            deleteOriginal: settings.deleteOriginal,
            ...(settings.mode === 'custom'
                ? {
                      maxLongEdge: settings.maxLongEdge ?? undefined,
                      quality: settings.quality ?? undefined,
                  }
                : {}),
        }

        const response = await fetch(BASE_URL + '/convert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                requesttoken: token,
            },
            body: JSON.stringify(payload),
        })
        if (!response.ok) {
            const text = await response.text()
            throw new Error(`Backend returned HTTP ${response.status}: ${text}`)
        }
        const json = (await response.json()) as BackendResponse
        bytesInTotal += json.bytesIn
        bytesOutTotal += json.bytesOut

        if (json.result === 'skipped') {
            return 'skipped'
        }

        // Make the newly-written file appear in the Files list without a reload.
        // davResultToNode turns the WebDAV propfind result into a Node instance
        // that the Files app's pinia store accepts via files:node:created.
        const stat = (await davClient.stat(davGetRootPath() + dirname + json.newFilename, {
            details: true,
            data: davGetDefaultPropfind(),
        })) as { data: unknown }
        emit('files:node:created', davResultToNode(stat.data))
        if (settings.deleteOriginal && json.originalTrashed) {
            emit('files:node:deleted', node)
        }
        return true
    })

    nodes.forEach((n) => {
        n.status = undefined
    })

    const converted = outcomes.filter((o) => o.ok && o.value === true).length
    const skipped = outcomes.filter((o) => o.ok && o.value === 'skipped').length
    const failures = outcomes.filter((o) => !o.ok).length
    const saved = formatBytes(bytesInTotal - bytesOutTotal)

    if (failures === 0 && skipped === 0) {
        showSuccess(t('imageconverter', '{n} converted (saved {saved})', { n: converted, saved }))
    } else if (failures === 0) {
        showSuccess(
            t('imageconverter', '{c} converted, {s} skipped (already small) — saved {saved}', {
                c: converted,
                s: skipped,
                saved,
            }),
        )
    } else if (converted === 0 && skipped === 0) {
        showError(t('imageconverter', 'Conversion failed for all {n} files', { n: failures }))
    } else {
        showError(
            t('imageconverter', '{c} converted, {s} skipped, {f} failed', {
                c: converted,
                s: skipped,
                f: failures,
            }),
        )
    }

    outcomes.forEach((o, i) => {
        if (!o.ok) {
            console.error(`imageconverter: ${nodes[i].basename} failed:`, o.error)
        }
    })

    return outcomes.map((o) => (o.ok ? true : false))
}

registerFileAction(
    new FileAction({
        id: 'convertImage',
        displayName: () => t('imageconverter', 'Convert to JPEG (~1 MB)'),
        iconSvgInline: () => imageIcon,
        enabled: (nodes: Node[], _view: View) => allSupported(nodes),
        async exec(file: Node, _view: View, dir: string) {
            const results = await runConversionBatch([file], dir, presetSettings())
            return results[0] ?? false
        },
        async execBatch(files: Node[], _view: View, dir: string) {
            return runConversionBatch(files, dir, presetSettings())
        },
    }),
)

registerFileAction(
    new FileAction({
        id: 'convertImageWithOptions',
        displayName: () => t('imageconverter', 'Convert to JPEG with options…'),
        iconSvgInline: () => imageIcon,
        enabled: (nodes: Node[], _view: View) => allSupported(nodes),
        async exec(file: Node, _view: View, dir: string) {
            const settings = await openOptionsDialog(1)
            if (!settings) return null
            const results = await runConversionBatch([file], dir, settings)
            return results[0] ?? false
        },
        async execBatch(files: Node[], _view: View, dir: string) {
            const settings = await openOptionsDialog(files.length)
            if (!settings) return files.map(() => null)
            return runConversionBatch(files, dir, settings)
        },
    }),
)
