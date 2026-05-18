import {
    FileAction,
    Node,
    NodeStatus,
    registerFileAction,
} from '@nextcloud/files'
import type { View } from '@nextcloud/files'
import { getClient, getDefaultPropfind, getRootPath } from '@nextcloud/files/dav'
import { emit } from '@nextcloud/event-bus'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { createApp, h } from 'vue'
import '@nextcloud/dialogs/style.css'
import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import imageIcon from '@mdi/svg/svg/image-edit-outline.svg?raw'

import OptionsDialog from './components/OptionsDialog.vue'
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

type SuccessResponse = {
    result: 'converted'
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
        const host = document.createElement('div')
        document.body.appendChild(host)
        let settled = false
        const finish = (value: DialogResult | null) => {
            if (settled) return
            settled = true
            app.unmount()
            host.remove()
            resolve(value)
        }
        const app = createApp({
            render: () =>
                h(OptionsDialog, {
                    fileCount,
                    onSubmit: (value: DialogResult) => finish(value),
                    onCancel: () => finish(null),
                }),
        })
        app.mount(host)
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

    const davClient = getClient()
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
        const json = (await response.json()) as SuccessResponse
        bytesInTotal += json.bytesIn
        bytesOutTotal += json.bytesOut

        // Refresh the converted file in the Files app. v3 has no resultToNode
        // helper; emit raw event with the dav stat and let listeners handle it.
        await davClient.stat(getRootPath() + dirname + json.newFilename, {
            details: true,
            data: getDefaultPropfind(),
        })
        emit('files:node:updated', node)
        if (settings.deleteOriginal && json.originalTrashed) {
            emit('files:node:deleted', node)
        }
        return true
    })

    nodes.forEach((n) => {
        n.status = undefined
    })

    const successes = outcomes.filter((o) => o.ok).length
    const failures = outcomes.length - successes

    if (failures === 0) {
        const saved = formatBytes(bytesInTotal - bytesOutTotal)
        showSuccess(t('imageconverter', '{n} converted (saved {saved})', { n: successes, saved }))
    } else if (successes === 0) {
        showError(t('imageconverter', 'Conversion failed for all {n} files', { n: failures }))
    } else {
        showError(t('imageconverter', '{s} converted, {f} failed', { s: successes, f: failures }))
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
