const Installer = (() => {
    const DB_NAME = 'streamhub_installer';
    const DB_VER = 1;
    const CONCURRENCY = 4;
    const CHUNK = 4 * 1024 * 1024;

    let db = null;
    let current = null;
    let speedTimer = null;

    function openDb() {
        return new Promise((resolve, reject) => {
            if (db) return resolve(db);
            const req = indexedDB.open(DB_NAME, DB_VER);
            req.onupgradeneeded = () => {
                const d = req.result;
                if (!d.objectStoreNames.contains('episodes')) d.createObjectStore('episodes', { keyPath: 'id' });
                if (!d.objectStoreNames.contains('segments')) d.createObjectStore('segments', { keyPath: 'id' });
            };
            req.onsuccess = () => { db = req.result; resolve(db); };
            req.onerror = () => reject(req.error);
        });
    }

    function idbGet(store, key) {
        return new Promise((resolve) => {
            if (!db) return resolve(null);
            const r = db.transaction(store, 'readonly').objectStore(store).get(key);
            r.onsuccess = () => resolve(r.result);
            r.onerror = () => resolve(null);
        });
    }

    function idbPut(store, val) {
        return new Promise((resolve) => {
            if (!db) return resolve(false);
            const r = db.transaction(store, 'readwrite').objectStore(store).put(val);
            r.onsuccess = () => resolve(true);
            r.onerror = () => resolve(false);
        });
    }

    const proxify = (u, ref) => 'api/proxy.php?url=' + encodeURIComponent(u) + (ref ? '&ref=' + encodeURIComponent(ref) : '');
    const deproxify = (u) => { try { return new URLSearchParams(new URL(u, location.href).search).get('url') || u; } catch (e) { return u; } };

    async function fetchThroughProxy(origUrl, ref, range) {
        const res = await fetch(proxify(origUrl, ref), { headers: range ? { Range: range } : {} });
        if (!res.ok && res.status !== 206) throw new Error('proxy ' + res.status);
        return res;
    }

    function fmtBytes(n) {
        if (n >= 1024 * 1024 * 1024) return (n / 1024 / 1024 / 1024).toFixed(1) + ' GB';
        if (n >= 1024 * 1024) return Math.round(n / 1024 / 1024) + ' MB';
        if (n >= 1024) return Math.round(n / 1024) + ' KB';
        return n + ' B';
    }

    // ---------------- custom hls.js loader ----------------
    class IDBLoader {
        constructor() { this.aborted = false; }
        load(context, config, callbacks) {
            const isFrag = context && (context.type === 'fragment' || context.type === 'initSegment' || context.type === 'muxedFrag');
            if (!isFrag || !current) {
                this._network(context, config, callbacks);
                return;
            }
            const orig = deproxify(context.url);
            const key = current.id + '|' + orig;
            idbGet('segments', key).then((row) => {
                if (this.aborted) return;
                if (row && row.blob) {
                    row.blob.arrayBuffer().then((buf) => {
                        if (this.aborted) return;
                        const stats = { trequest: performance.now(), tfirst: performance.now(), tload: performance.now(), total: buf.byteLength };
                        callbacks.onSuccess({ url: context.url, data: buf }, stats);
                    });
                    return;
                }
                this._network(context, config, callbacks, orig, key);
            });
        }
        _network(context, config, callbacks, orig, key) {
            const url = context.url;
            fetch(url)
                .then((res) => {
                    if (!res.ok) throw new Error('http ' + res.status);
                    return res.arrayBuffer();
                })
                .then((buf) => {
                    if (this.aborted) return;
                    if (orig && key && current) {
                        const blob = new Blob([buf]);
                        idbPut('segments', { id: key, epId: current.id, bytes: buf.byteLength, blob, ts: Date.now() }).catch(() => {});
                    }
                    const stats = { trequest: performance.now(), tfirst: performance.now(), tload: performance.now(), total: buf.byteLength };
                    callbacks.onSuccess({ url, data: buf }, stats);
                })
                .catch((err) => {
                    if (this.aborted) return;
                    callbacks.onError({ code: 2, text: err.message || 'load error' }, context);
                });
        }
        abort() { this.aborted = true; }
        destroy() { this.aborted = true; }
    }

    // ---------------- UI ----------------
    function buildUI(container) {
        let bar = document.getElementById('installerBar');
        if (bar) bar.remove();
        bar = document.createElement('div');
        bar.id = 'installerBar';
        bar.className = 'installer-bar';
        bar.innerHTML =
            '<div class="installer-row">' +
            '<span class="installer-label">' + t('installer_status') + '</span>' +
            '<div class="installer-track"><div class="installer-fill" id="installerFill" style="width:0%"></div></div>' +
            '<span class="installer-text" id="installerText">0%</span>' +
            '<button class="installer-save hidden" id="installerSave">' + t('installer_save') + '</button>' +
            '</div>';
        container.appendChild(bar);
        return bar;
    }

    function render() {
        const fill = document.getElementById('installerFill');
        const text = document.getElementById('installerText');
        const save = document.getElementById('installerSave');
        if (!current || !fill) return;
        const total = current.totalBytes || 1;
        const pct = Math.min(100, Math.round((current.storedBytes / total) * 100));
        fill.style.width = pct + '%';
        if (current.status === 'done') {
            text.textContent = t('installer_done') + ' (' + fmtBytes(current.storedBytes) + ')';
            if (save) save.classList.remove('hidden');
            return;
        }
        const speed = current.speedBps || 0;
        const left = Math.max(0, total - current.storedBytes);
        let s = pct + '% · ' + fmtBytes(current.storedBytes) + ' / ' + fmtBytes(total);
        if (speed > 0) {
            s += ' · ' + fmtBytes(speed) + '/s';
            const eta = left / speed;
            if (eta > 0 && eta < 3600) s += ' · ETA ' + Math.max(1, Math.round(eta)) + 's';
        }
        text.textContent = s;
    }

    function setStatus(msg) {
        const text = document.getElementById('installerText');
        if (text) text.textContent = msg;
    }

    // ---------------- engine ----------------
    function trackSpeed() {
        if (speedTimer) clearInterval(speedTimer);
        if (!current) return;
        current.lastBytes = current.storedBytes;
        speedTimer = setInterval(() => {
            if (!current) return;
            current.speedBps = Math.max(0, current.storedBytes - (current.lastBytes || 0));
            current.lastBytes = current.storedBytes;
            render();
        }, 1000);
    }

    async function downloadList(list) {
        await openDb();
        if (!current || list.length === 0) return;
        let idx = 0;
        const workers = [];
        for (let w = 0; w < Math.min(CONCURRENCY, list.length); w++) {
            workers.push((async () => {
                while (idx < list.length && current) {
                    const item = list[idx++];
                    const key = current.id + '|' + item.orig;
                    const existing = await idbGet('segments', key);
                    if (existing) {
                        if (current) { current.storedBytes += existing.bytes; current.segDone += 1; render(); }
                        continue;
                    }
                    try {
                        const res = await fetchThroughProxy(item.orig, current.ref);
                        const blob = await res.blob();
                        const ok = await idbPut('segments', { id: key, epId: current.id, bytes: blob.size, blob, ts: Date.now() });
                        if (ok && current) {
                            current.storedBytes += blob.size;
                            current.segDone += 1;
                            render();
                        }
                    } catch (e) { /* expired token / offline — try next */ }
                }
            })());
        }
        await Promise.all(workers);
        if (current && current.segTotal > 0 && current.segDone >= current.segTotal) {
            current.status = 'done';
            render();
        }
    }

    function hookHls(hls, opts) {
        current.hls = hls;
        const onManifest = () => {
            if (!current) return;
            const levels = hls.levels || [];
            const level = levels[Math.max(0, hls.currentLevel || 0)];
            const details = level && level.details;
            if (!details || !details.fragments) return;
            const seen = new Set();
            const frags = [];
            let totalBytes = 0;
            for (const f of details.fragments) {
                const orig = deproxify(f.url);
                if (seen.has(orig)) continue;
                seen.add(orig);
                frags.push({ orig, relSn: f.relSn, byteLength: f.byteLength || 0 });
                totalBytes += f.byteLength || 0;
            }
            if (current.levelFrags && current.levelFrags.length === frags.length &&
                frags.length > 0 && current.levelFrags[0].orig === frags[0].orig) {
                return; // same level re-loaded — keep progress
            }
            current.levelFrags = frags;
            current.segTotal = frags.length;
            current.totalBytes = totalBytes || Math.max(0, current.storedBytes);
            current.segDone = 0;
            current.status = 'installing';
            render();
            if (frags.length > 0) downloadList(frags);
        };
        hls.on(Hls.Events.MANIFEST_PARSED, onManifest);
        hls.on(Hls.Events.LEVEL_SWITCHED, onManifest);
        hls.on(Hls.Events.LEVEL_LOADED, onManifest);
    }

    function attachMp4(opts) {
        current.chunked = true;
        (async () => {
            try {
                await openDb();
                if (!current) return;
                const head = await fetch(proxify(opts.originalUrl, opts.ref) + '&head=1');
                const cl = parseInt(head.headers.get('content-length') || '0', 10);
                if (!cl) { current.status = 'done'; render(); return; }
                current.totalSize = cl;
                current.totalBytes = cl;
                const chunks = Math.ceil(cl / CHUNK);
                current.segTotal = chunks;
                current.segDone = 0;
                render();
                let idx = 0;
                const workers = [];
                for (let w = 0; w < 2; w++) {
                    workers.push((async () => {
                        while (idx < chunks && current) {
                            const i = idx++;
                            const key = current.id + '|chunk-' + i;
                            const start = i * CHUNK;
                            const end = Math.min(cl - 1, start + CHUNK - 1);
                            const existing = await idbGet('segments', key);
                            if (existing) {
                                if (current) { current.storedBytes += existing.bytes; current.segDone += 1; render(); }
                                continue;
                            }
                            try {
                                const res = await fetchThroughProxy(opts.originalUrl, opts.ref, 'bytes=' + start + '-' + end);
                                const blob = await res.blob();
                                const ok = await idbPut('segments', { id: key, epId: current.id, bytes: blob.size, blob, ts: Date.now() });
                                if (ok && current) {
                                    current.storedBytes += blob.size;
                                    current.segDone += 1;
                                    render();
                                }
                            } catch (e) { }
                        }
                    })());
                }
                await Promise.all(workers);
                if (current) { current.status = 'done'; render(); }
            } catch (e) {
                if (current) { current.status = 'failed'; setStatus(t('installer_failed')); }
            }
        })();
    }

    async function exportVideo(opts) {
        if (!current || current.status !== 'done') return;
        if (current.chunked) {
            const a = document.createElement('a');
            a.href = proxify(opts.originalUrl, opts.ref) + '&dl=1';
            a.download = (opts.title || 'video') + '.mp4';
            a.click();
            return;
        }
        const ordered = current.levelFrags.slice().sort((a, b) => a.relSn - b.relSn);
        const parts = [];
        for (const f of ordered) {
            const row = await idbGet('segments', current.id + '|' + f.orig);
            if (row && row.blob) parts.push(row.blob);
        }
        if (parts.length < ordered.length) { setStatus(t('installer_failed')); return; }
        const first = new Uint8Array(await parts[0].slice(0, 1).arrayBuffer());
        const isTs = first[0] === 0x47;
        const merged = new Blob(parts, { type: isTs ? 'video/mp2t' : 'video/mp4' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(merged);
        a.download = (opts.title || 'video') + (isTs ? '.ts' : '.mp4');
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 10000);
    }

    // ---------------- public API ----------------
    return {
        proxify,
        hlsConfig() { return { loader: IDBLoader, pLoader: IDBLoader }; },
        attach(opts) {
            if (current) this.stop();
            current = {
                id: opts.originalUrl,
                title: opts.title || 'video',
                ref: opts.ref || '',
                type: opts.streamType,
                storedBytes: 0,
                totalBytes: 0,
                segTotal: 0,
                segDone: 0,
                speedBps: 0,
                status: 'installing',
                chunked: false,
            };
            buildUI(opts.container);
            const save = document.getElementById('installerSave');
            if (save) save.onclick = () => exportVideo(opts);
            trackSpeed();
            if (opts.streamType === 'mp4') attachMp4(opts);
        },
        attachHls(hls, opts) {
            this.attach(opts);
            hookHls(hls, opts);
        },
        notAvailable(container) {
            if (current) this.stop();
            const bar = buildUI(container);
            const text = bar.querySelector('#installerText');
            if (text) text.textContent = t('installer_unavailable');
            const fill = bar.querySelector('#installerFill');
            if (fill) fill.style.display = 'none';
        },
        stop() {
            current = null;
            if (speedTimer) { clearInterval(speedTimer); speedTimer = null; }
            const bar = document.getElementById('installerBar');
            if (bar) bar.remove();
        },
        isActive() { return !!current; },
    };
})();
