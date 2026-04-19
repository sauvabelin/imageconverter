/**
 * Run `worker(item)` over every item, with at most `limit` in-flight promises.
 * Resolves with an array of { ok, value | error } in input order.
 */
export type WorkerResult<T> =
    | { ok: true; value: T }
    | { ok: false; error: unknown }

export async function runBounded<Item, T>(
    items: readonly Item[],
    limit: number,
    worker: (item: Item, index: number) => Promise<T>,
): Promise<WorkerResult<T>[]> {
    const results: WorkerResult<T>[] = new Array(items.length)
    let cursor = 0

    async function pump(): Promise<void> {
        while (true) {
            const i = cursor++
            if (i >= items.length) return
            try {
                results[i] = { ok: true, value: await worker(items[i], i) }
            } catch (error) {
                results[i] = { ok: false, error }
            }
        }
    }

    const pumps: Promise<void>[] = []
    for (let i = 0; i < Math.min(limit, items.length); i++) {
        pumps.push(pump())
    }
    await Promise.all(pumps)
    return results
}
