// Transient memory only. Web NFC has no tag-removal event: require a quiet gap
// before accepting the same reference again; every repeated reading extends it.
export class ScanLatch {
    private last: string | null = null;
    private seen = 0;
    private quietMs: number;
    constructor(quietMs: number) {
        if (!Number.isFinite(quietMs) || quietMs < 1) throw new Error('Invalid scan interval.');
        this.quietMs = quietMs;
    }
    accept(reference: string, at = performance.now()): boolean {
        const repeated = this.last === reference && at - this.seen < this.quietMs;
        this.last = reference;
        this.seen = at;
        return !repeated;
    }
    clear() {
        this.last = null;
        this.seen = 0;
    }
}
