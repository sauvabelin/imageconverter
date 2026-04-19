export type DialogResult = {
    mode: 'preset' | 'custom'
    targetBytes: number
    maxLongEdge: number | null
    quality: number | null
    deleteOriginal: boolean
}
