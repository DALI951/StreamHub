const API_BASE = 'api';

let appState = {
    currentView: 'home',
    lastQuery: '',
    sources: [],
};

document.addEventListener('DOMContentLoaded', () => {
    loadSources();
    handleHash();
    window.addEventListener('hashchange', handleHash);
    document.getElementById('searchInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') performSearch();
    });
});

function getSource(url) {
    try {
        const host = new URL(url).hostname.toLowerCase().replace(/^www\./, '');
        for (const s of appState.sources || []) {
            try {
                const baseHost = new URL(s.base_url).hostname.toLowerCase().replace(/^www\./, '');
                if (host === baseHost || host.endsWith('.' + baseHost) || baseHost.endsWith('.' + host)) {
                    return s.name;
                }
            } catch (e) {}
        }
        return host.replace(/\.(live|com|net|ws|top|win|show|fan|tv)$/i, '').replace(/^tv\d+\./i, '');
    } catch (e) { return ''; }
}

function getSlug(url) {
    try {
        const path = new URL(url).pathname;
        return path.startsWith('/') ? path.slice(1) : path;
    } catch(e) { return ''; }
}

function handleHash() {
    const hash = location.hash.slice(1) || '';
    if (!hash) { showHome(); return; }

    if (hash.startsWith('search/')) {
        try { doSearch(decodeURIComponent(hash.slice(7))); } catch(e) { showHome(); }
        return;
    }

    const slash = hash.indexOf('/');
    if (slash === -1) { showHome(); return; }

    const head = hash.slice(0, slash);
    const rest = hash.slice(slash + 1);

    if (head === 'watch') {
        const s2 = rest.indexOf('/');
        if (s2 === -1) { showHome(); return; }
        showWatchBySlug(rest.slice(0, s2), rest.slice(s2 + 1));
        return;
    }

    const source = head;
    const slug = rest;

    if (source && slug) {
        const isEpisode = /Ø§Ù„Ø­Ù„Ù‚Ø©|Ø­Ù„Ù‚Ù‡|s\d+e\d+|episode|\/watch\//i.test(slug);
        if (isEpisode) showWatchBySlug(source, slug);
        else showDetailBySlug(source, slug);
        return;
    }
    showHome();
}

function navigateTo(view, param) {
    if (view === 'home') { location.hash = ''; return; }
    if (view === 'search') { location.hash = 'search/' + encodeURIComponent(param); return; }

    const source = getSource(param);
    const slug = getSlug(param);
    if (source && slug) {
        location.hash = (view === 'watch' ? 'watch/' : '') + source + '/' + slug;
    } else {
        location.hash = 'search/';
    }
}

function performSearch() {
    const q = document.getElementById('searchInput').value.trim();
    if (q) navigateTo('search', q);
}

async function loadSources() {
    try {
        const res = await fetch(`${API_BASE}/sources.php`);
        const data = await res.json();
        const DEAD = new Set(['faselhd', 'akwam', 'cima4u', 'topcinema', 'mycima', 'arabseed']);
        appState.sources = (data.sources || []).filter(s => !DEAD.has(s.name));
        const select = document.getElementById('sourceSelect');
        select.innerHTML = `<option value="">${t('all_sources')}</option>`;
        appState.sources.forEach(s => {
            select.innerHTML += `<option value="${s.name}">${sourceLabel(s.name)}</option>`;
        });
    } catch (e) {}
}

function showLoading() { document.getElementById('loading').classList.remove('hidden'); }
function hideLoading() { document.getElementById('loading').classList.add('hidden'); }

const SOURCE_LABELS = {
    egydead: 'Server 1', faselhd: 'Server 2', akwam: 'Server 3', cima4u: 'Server 4',
    topcinema: 'Server 5', mycima: 'Server 6', arabseed: 'Server 7', blkom: 'Server 8',
};
const sourceLabel = (n) => SOURCE_LABELS[n] || 'Server';
const imgUrl = (u) => u && !u.startsWith('api/') && !u.startsWith('data:') ? 'api/proxy.php?url=' + encodeURIComponent(u) : u;

function showHome() {
    appState.currentView = 'home';
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="hero-gradient relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 text-center">
                <h1 class="text-5xl md:text-7xl font-bold mb-3">
                    <span class="text-red-500">Stream</span>Hub
                </h1>
                <p class="text-gray-400 text-lg mb-8">${t('search_placeholder')}</p>
                <div class="max-w-lg mx-auto">
                    <input id="homeSearch" type="text" placeholder="${t('search_placeholder')}"
                        class="w-full bg-gray-900/80 border border-gray-800 rounded-full px-6 py-4 text-lg focus:outline-none focus:border-red-500/50 transition placeholder-gray-500"
                        onkeydown="if(event.key==='Enter'&&this.value.trim()){navigateTo('search',this.value)}">
                </div>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 py-8" id="homeRows">
            <div class="flex items-center justify-center py-16 text-gray-500">
                <div class="w-8 h-8 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
        </div>
    `;
    loadHome();
}

function cardHTML(r, delay = 0, fixed = false) {
    const goWatch = r.type === 'episode';
    return `
        <div class="card-hover cursor-pointer animate-in ${fixed ? 'shrink-0 w-36 sm:w-40 md:w-44' : ''}"
             style="animation-delay:${delay * 30}ms"
             onclick="navigateTo('${goWatch ? 'watch' : 'detail'}', '${r.url.replace(/'/g, "\\'")}')">
            <div class="relative rounded-lg overflow-hidden bg-gray-900 aspect-[2/3] ${fixed ? '' : 'w-full'}">
                ${r.poster ? `<img src="${imgUrl(r.poster)}" alt="${r.title}" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">` :
                    `<div class="w-full h-full flex items-center justify-center"><svg class="w-10 h-10 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></div>`}
                <div class="poster-gradient absolute inset-0"></div>
                <div class="play-overlay">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <span class="source-badge absolute top-2 left-2">HD</span>
                <div class="absolute bottom-0 left-0 right-0 p-3">
                    <h3 class="font-semibold text-sm line-clamp-2">${r.title}</h3>
                </div>
            </div>
        </div>
    `;
}

async function loadHome() {
    try {
        const res = await fetch('api/home.php');
        const data = await res.json();
        const cats = data.categories || [];
        if (!cats.length) throw new Error('empty');
        window._homeCats = cats;
        buildHome(cats);
    } catch (e) {
        const el = document.getElementById('homeRows');
        if (el) el.innerHTML = `<div class="empty-state"><p>${t('no_results')}</p><p class="text-sm mt-1">${t('try_different')}</p></div>`;
    }
}

function catTitle(key) {
    const m = { fresh: t('fresh'), movies: t('movies'), series: t('series'), anime: t('anime') };
    return m[key] || key;
}

function buildHome(cats) {
    const container = document.getElementById('homeRows');
    if (!container) return;
    const featured = (cats.find(c => c.key === 'fresh') || cats[0] || {}).items?.[0];
    let html = '';
    if (featured) {
        const fUrl = imgUrl(featured.poster);
        const fType = featured.type === 'episode' ? t('episodes') : (featured.type === 'series' ? t('type_series') : t('type_movie'));
        html += `
        <div class="home-hero relative rounded-2xl overflow-hidden mb-10">
            <div class="absolute inset-0 bg-cover bg-center blur-sm scale-110" style="background-image:url('${fUrl}')"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/70 to-gray-950/20"></div>
            <div class="relative p-8 md:p-12 max-w-2xl">
                <span class="text-xs px-2 py-1 bg-red-600 rounded-full font-semibold">${fType}</span>
                <h2 class="text-3xl md:text-5xl font-bold mt-4 mb-6 line-clamp-2">${featured.title}</h2>
                <button onclick="navigateTo('${featured.type === 'episode' ? 'watch' : 'detail'}', '${featured.url.replace(/'/g, "\\'")}')"
                    class="px-8 py-3 bg-red-600 hover:bg-red-700 rounded-xl font-semibold transition flex items-center gap-2 w-fit">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    ${t('watch_now')}
                </button>
            </div>
        </div>`;
    }
    html += cats.map((cat, ci) => `
        <section class="mb-10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <span class="w-1 h-6 bg-red-600 rounded-full"></span>${catTitle(cat.key)}
                </h3>
            </div>
            <div class="home-row flex gap-4 overflow-x-auto pb-4 -mx-4 px-4 scrollbar-thin">
                ${cat.items.map((it, i) => cardHTML(it, ci * 30 + i, true)).join('')}
            </div>
        </section>`).join('');
    container.innerHTML = html;
}

async function doSearch(query) {
    appState.currentView = 'search';
    appState.lastQuery = query;
    document.getElementById('searchInput').value = query;

    document.getElementById('app').innerHTML = `
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button onclick="navigateTo('home')" class="text-gray-400 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-2xl font-bold">${t('search_results')}: <span class="text-red-500">${query}</span></h2>
            </div>
            <div id="results" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"></div>
        </div>
    `;

    const source = document.getElementById('sourceSelect').value;
    let url = `${API_BASE}/search.php?q=${encodeURIComponent(query)}`;
    if (source) url += `&source=${source}`;

    showLoading();
    try {
        const res = await fetch(url);
        const data = await res.json();
        renderResults(data.results || []);
    } catch (e) {
        document.getElementById('results').innerHTML = `<p class="text-gray-500 col-span-full text-center">${t('no_results')}</p>`;
    }
    hideLoading();
}

function renderResults(results) {
    const container = document.getElementById('results');
    if (!results.length) {
        container.innerHTML = `<div class="empty-state col-span-full">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <p>${t('no_results')}</p><p class="text-sm mt-1">${t('try_different')}</p>
        </div>`;
        return;
    }
    container.innerHTML = results.map((r, i) => cardHTML(r, i)).join('');
}

function showToast(msg) {
    let el = document.getElementById('toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast';
        document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
}

function extractSeasonName(url) {
    try {
        const slug = decodeURIComponent(new URL(url).pathname.split('/').filter(Boolean).pop() || '');
        const m = slug.match(/(?:Ø§Ù…ÙˆØ³Ù…|Ø§Ù„Ù…ÙˆØ³Ù…)[-_]([^-[\]]+)/);
        if (m) {
            const names = {'Ø§Ù„Ø§ÙˆÙ„':'1','Ø§Ù„Ø«Ø§Ù†ÙŠ':'2','Ø§Ù„Ø«Ø§Ù„Ø«':'3','Ø§Ù„Ø±Ø§Ø¨Ø¹':'4','Ø§Ù„Ø®Ø§Ù…Ø³':'5','Ø§Ù„Ø³Ø§Ø¯Ø³':'6','Ø§Ù„Ø³Ø§Ø¨Ø¹':'7','Ø§Ù„Ø«Ø§Ù…Ù†':'8','Ø§Ù„ØªØ§Ø³Ø¹':'9','Ø§Ù„Ø¹Ø§Ø´Ø±':'10'};
            const arabic = m[1].trim();
            return names[arabic] || arabic;
        }
        const em = slug.match(/season[_-]?(\d+)/i);
        if (em) return em[1];
    } catch(e) {}
    return null;
}

async function showDetailBySlug(source, slug) {
    showLoading();
    try {
        const res = await fetch(`${API_BASE}/details.php?source=${source}&slug=${encodeURIComponent(slug)}`);
        const data = await res.json();
        if (data.error) {
            document.getElementById('app').innerHTML = `<div class="text-center py-20 text-gray-500">${data.error}</div>`;
            hideLoading();
            return;
        }
        renderDetail(data.details);
    } catch (e) {
        document.getElementById('app').innerHTML = `<div class="text-center py-20 text-gray-500">Error loading details</div>`;
    }
    hideLoading();
}

async function showWatchBySlug(source, slug) {
    showLoading();
    try {
        const res = await fetch(`${API_BASE}/details.php?source=${source}&slug=${encodeURIComponent(slug)}`);
        const data = await res.json();
        if (data.error || !data.details || !data.details.url) {
            document.getElementById('app').innerHTML = `<div class="text-center py-20 text-gray-500">${data.error || 'Not found'}</div>`;
            hideLoading();
            return;
        }
        showWatch(data.details.url, data.details.title || '');
    } catch (e) {
        document.getElementById('app').innerHTML = `<div class="text-center py-20 text-gray-500">Error</div>`;
        hideLoading();
    }
}

function renderDetail(d) {
    const app = document.getElementById('app');

    if (d.type === 'season' && d.episodes && d.episodes.length) {
        app.innerHTML = `
            <div class="max-w-5xl mx-auto px-4 py-8 animate-in">
                <button onclick="history.back()" class="mb-6 text-gray-400 hover:text-white transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    ${t('back')}
                </button>
                <div class="flex flex-col md:flex-row gap-8 mb-8">
                    <div class="w-full md:w-72 flex-shrink-0">
                        ${d.poster ? `<img src="${imgUrl(d.poster)}" alt="${d.title}" class="w-full rounded-xl shadow-2xl">` :
                            `<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl flex items-center justify-center"><svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></div>`}
                    </div>
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold mb-2">${d.title}</h1>
                        <span class="source-badge source-${d.source}">${d.source}</span>
                        ${d.description ? `<p class="text-gray-400 leading-relaxed mt-4">${d.description}</p>` : ''}
                    </div>
                </div>
                <h2 class="text-xl font-bold mb-4">${t('episodes')} (${d.episodes.length})</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    ${d.episodes.map((ep, i) => `
<button onclick="navigateTo('watch', '${ep.url.replace(/'/g, "\\'")}'  )"
                            class="episode-btn bg-gray-900 border border-gray-800 hover:border-red-500/50 rounded-xl p-4 text-center transition animate-in"
                            style="animation-delay:${i * 20}ms">
                            <span class="text-2xl font-bold text-red-500">${ep.number}</span>
                            <span class="text-xs text-gray-500 mt-1 block">${t('episode')}</span>
                        </button>
                    `).join('')}
                </div>
            </div>
        `;
    } else if (d.type === 'series' && d.seasons && d.seasons.length) {
        app.innerHTML = `
            <div class="max-w-5xl mx-auto px-4 py-8 animate-in">
                <button onclick="history.back()" class="mb-6 text-gray-400 hover:text-white transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    ${t('back')}
                </button>
                <div class="flex flex-col md:flex-row gap-8 mb-8">
                    <div class="w-full md:w-72 flex-shrink-0">
                        ${d.poster ? `<img src="${imgUrl(d.poster)}" alt="${d.title}" class="w-full rounded-xl shadow-2xl">` :
                            `<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl flex items-center justify-center"><svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></div>`}
                    </div>
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold mb-2">${d.title}</h1>
                        <span class="source-badge">HD</span>
                        ${d.description ? `<p class="text-gray-400 leading-relaxed mt-4">${d.description}</p>` : ''}
                    </div>
                </div>
                <h2 class="text-xl font-bold mb-4">${t('seasons')} (${d.seasons.length})</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    ${d.seasons.map((s, i) => {
                        const sn = extractSeasonName(s.url) || (i + 1);
                        return `
<button onclick="navigateTo('detail', '${s.url.replace(/'/g, "\\'")}'  )"
                            class="episode-btn bg-gray-900 border border-gray-800 hover:border-red-500/50 rounded-xl p-4 text-center transition animate-in"
                            style="animation-delay:${i * 20}ms">
                            <span class="text-2xl font-bold text-red-500">${sn}</span>
                            <span class="text-xs text-gray-500 mt-1 block">${t('season')}</span>
                        </button>`;
                    }).join('')}
                </div>
            </div>
        `;
    } else {
        app.innerHTML = `
            <div class="max-w-5xl mx-auto px-4 py-8 animate-in">
                <button onclick="history.back()" class="mb-6 text-gray-400 hover:text-white transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    ${t('back')}
                </button>
                <div class="flex flex-col md:flex-row gap-8">
                    <div class="w-full md:w-72 flex-shrink-0">
                        ${d.poster ? `<img src="${imgUrl(d.poster)}" alt="${d.title}" class="w-full rounded-xl shadow-2xl">` :
                            `<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl flex items-center justify-center"><svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></div>`}
                    </div>
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold mb-2">${d.title}</h1>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <span class="source-badge">HD</span>
                            <span class="text-xs px-2 py-1 bg-gray-800 rounded">${d.type === 'series' ? t('type_series') : t('type_movie')}</span>
                            ${d.year ? `<span class="text-xs px-2 py-1 bg-gray-800 rounded">${d.year}</span>` : ''}
                        </div>
                        ${d.description ? `<p class="text-gray-400 leading-relaxed mb-6">${d.description}</p>` : ''}
                        <button onclick="navigateTo('watch', '${d.url.replace(/'/g, "\\'")}'  )"
                            class="px-8 py-3 bg-red-600 hover:bg-red-700 rounded-xl font-semibold transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            ${t('watch_now')}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
}

async function showWatch(url, title) {
    appState.currentView = 'watch';
    const app = document.getElementById('app');
    showLoading();
    // Set early: the auto-VidSrc fallback below needs these.
    window._watchPageUrl = url;
    window._watchTitle = title || '';
    window._unwrapped = {};

    try {
        const res = await fetch(`${API_BASE}/streams.php?url=${encodeURIComponent(url)}`);
        const data = await res.json();
        let streams = (data.streams || []).filter(s => !/morencius|earnvids/i.test(s.stream_url || ''));
        const embedFallback = streams.filter((s) => s.stream_type === 'iframe');

        if (!streams.length) {
            app.innerHTML = `<div class="text-center py-20 text-gray-500">${t('no_results')}</div>`;
            hideLoading();
            return;
        }

        const probes = await Promise.all(streams.map((s) => probeStream(s)));
        let alive = streams.filter((_, i) => probes[i]);
        let dropped = streams.length - alive.length;
        if (dropped > 0) {
            // Signed CDN tokens go stale fast -> re-scrape fresh streams and
            // re-probe them before giving up (self-healing).
            try {
                const freshRes = await fetch(`${API_BASE}/streams.php?url=${encodeURIComponent(url)}&fresh=1`);
                const freshData = await freshRes.json();
                const freshStreams = (freshData.streams || []).filter((s) => !/morencius|earnvids/i.test(s.stream_url || ''));
                if (freshStreams.length) {
                    const fProbes = await Promise.all(freshStreams.map((s) => probeStream(s)));
                    const fAlive = freshStreams.filter((_, i) => fProbes[i]);
                    if (fAlive.length) {
                        alive = fAlive;
                        dropped = freshStreams.length - fAlive.length;
                        streams = freshStreams;
                    }
                }
            } catch (e) { /* keep the original list */ }
            if (dropped > 0) {
                showToast(dropped + ' ' + (t('streams_unavailable') || 'unavailable stream(s) removed'));
            }
        }
        streams = alive;
        if (!streams.length) {
            // Last resort if VidSrc's retries exhaust: init the embed anyway.
            window._pcEmbedFallback = () => {
                if (!embedFallback.length) return;
                const c = document.getElementById('playerContainer');
                if (c && !c.querySelector('video, iframe')) {
                    initPlayer(embedFallback[0].stream_url, 'iframe', 'playerContainer');
                }
            };
            // No direct streams survived. Auto VidSrc (our own player, zero
            // watermark) with automatic retries until something plays.
            app.innerHTML = `
                <div class="max-w-6xl mx-auto px-4 py-6 animate-in">
                    <button onclick="history.back()" class="mb-4 text-gray-400 hover:text-white transition flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        <span>${t('back')}</span>
                    </button>
                    ${title ? `<h1 class="text-2xl font-bold mb-4">${title}</h1>` : ''}
                    <div id="playerContainer" class="player-wrap w-full rounded-xl overflow-hidden bg-black mb-6"></div>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button onclick="switchVidsrc()" data-vidsrc="1"
                            class="stream-btn px-3 py-1.5 rounded-lg text-sm transition bg-red-600 text-white"
                            title="VidSrc (English)">
                            VidSrc <span class="stream-dot"></span>
                        </button>
                    </div>
                    <div id="watchExtras"></div>
                </div>
            `;
            autoVidsrc();
            renderWatchExtras();
            hideLoading();
            return;
        }
        if (!streams.length) {
            app.innerHTML = `<div class="text-center py-20 text-gray-500">${t('no_results')}</div>`;
            hideLoading();
            return;
        }

        app.innerHTML = `
            <div class="max-w-6xl mx-auto px-4 py-6 animate-in">
                <button onclick="history.back()" class="mb-4 text-gray-400 hover:text-white transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span>${t('back')}</span>
                </button>
                ${title ? `<h1 class="text-2xl font-bold mb-4">${title}</h1>` : ''}
                <div id="playerContainer" class="player-wrap w-full rounded-xl overflow-hidden bg-black mb-6"></div>
                <div class="flex flex-wrap gap-2 mb-6">
                    <span class="text-sm text-gray-500">${t('quality_select')}:</span>
                    ${streams.map((s, i) => `
                        <button onclick="switchStream(${i})"
                            class="stream-btn px-3 py-1.5 rounded-lg text-sm transition ${i === 0 ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}"
                            data-index="${i}" data-direct="0"
                            title="${s.server_name || ''}">
                            ${s.quality_label || 'Auto'}
                            <span class="stream-dot"></span>
                        </button>
                    `).join('')}
                    <button onclick="switchVidsrc()" data-vidsrc="1"
                        class="stream-btn px-3 py-1.5 rounded-lg text-sm transition bg-gray-800 text-gray-400 hover:text-white"
                        title="VidSrc (English)">
                        VidSrc <span class="stream-dot"></span>
                    </button>
                </div>
                <div id="watchExtras"></div>
            </div>
        `;

        window._streams = streams;
        window._unwrapped = {};
        window._watchPageUrl = url;
        window._watchTitle = title || '';

        const unwrapped = await Promise.all(streams.map((s, i) => tryUnwrap(s, i)));
        const qval = (c) => { const m = (c.s.quality_label || '').match(/(\d{3,4})p/); return m ? parseInt(m[1], 10) : 0; };
        const candidates = streams
            .map((s, i) => ({ s, i, u: unwrapped[i], direct: !!(unwrapped[i]) || s.stream_type !== 'iframe' }))
            .filter((c) => c.direct)
            .sort((a, b) => (qval(b) - qval(a)) || a.i - b.i);
        const fallback = streams.findIndex((s) => s.stream_type !== 'iframe');
        const pick = candidates.length ? candidates[0].i : (fallback >= 0 ? fallback : 0);
        updateStreamBadges();
        document.querySelectorAll('.stream-btn').forEach((btn, i) => {
            btn.className = `stream-btn px-3 py-1.5 rounded-lg text-sm transition ${i === pick ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}`;
        });
        const playable = unwrapped[pick] || streams[pick];
        const playerUrl = playable.isUnwrapped
            ? playable.stream_url
            : (playable.stream_type === 'hls' || playable.stream_type === 'mp4')
                ? (window.Installer ? Installer.proxify(playable.stream_url, playable.referer) : playable.stream_url)
                : playable.stream_url;
        if (pick !== 0) {
            const q = streams[pick].quality_label || 'Auto';
            showToast(`${t('now_playing')}: ${q} · ${unwrapped[pick] || streams[pick].stream_type !== 'iframe' ? t('direct') : t('external')}`);
        }
        initPlayer(playerUrl, playable.stream_type, 'playerContainer');
        restoreSubtitles(document.getElementById('videoPlayer'));
        startInstaller(playable, url, title);
        window._pcOnPlayerError = () => autoVidsrc();
        watchPlayback();
        renderWatchExtras();
    } catch (e) {
        app.innerHTML = `<div class="text-center py-20 text-gray-500">Error loading streams</div>`;
    }
    hideLoading();
}

function updateStreamBadges() {
    document.querySelectorAll('.stream-btn').forEach((btn, i) => {
        const direct = !!(window._unwrapped && window._unwrapped[i]);
        btn.setAttribute('data-direct', direct ? '1' : '0');
        btn.title = direct ? t('direct') : t('external');
    });
}

const AR_ORDINALS = { 'الاول': 1, 'الأول': 1, 'الثاني': 2, 'الثالث': 3, 'الرابع': 4, 'الخامس': 5, 'السادس': 6, 'السابع': 7, 'الثامن': 8, 'التاسع': 9, 'العاشر': 10, 'الحادي عشر': 11, 'الثاني عشر': 12 };
const AR_ORD_WORDS = { 1: 'الأول', 2: 'الثاني', 3: 'الثالث', 4: 'الرابع', 5: 'الخامس', 6: 'السادس', 7: 'السابع', 8: 'الثامن', 9: 'التاسع', 10: 'العاشر', 11: 'الحادي عشر', 12: 'الثاني عشر' };
const titleCase = (s) => s.split(' ').map((w) => w ? w[0].toUpperCase() + w.slice(1) : w).join(' ');

function watchCtx() {
    const url = window._watchPageUrl || '';
    const m = url.match(/-s(\d+)e(\d+)/i);
    if (m) return { watchUrl: url, season: parseInt(m[1], 10), episode: parseInt(m[2], 10), seriesStem: url.replace(/-s\d+e\d+/i, '') };
    const ar = url.match(/([^/]+)-الموسم-([^-]+)-الحلقة-?(\d+)/i);
    if (ar) {
        const sn = AR_ORDINALS[ar[2]] || parseInt(ar[2], 10) || 1;
        return { watchUrl: url, season: sn, episode: parseInt(ar[3], 10) || 1, seriesStem: ar[1], arabic: true };
    }
    return { watchUrl: url, season: null, episode: null, seriesStem: null };
}

function seasonOrdinal(u) {
    const d = decodeURIComponent(u);
    const ar = d.match(/الموسم-([^-]+)/);
    if (ar && AR_ORDINALS[ar[1]]) return AR_ORDINALS[ar[1]];
    const em = d.match(/s(\d+)/i);
    return em ? parseInt(em[1], 10) : 999;
}

async function renderWatchExtras() {
    const host = document.getElementById('watchExtras');
    if (!host) return;
    const ctx = watchCtx();
    if (!ctx.seriesStem) return;
    const stem = decodeURIComponent(ctx.seriesStem).replace(/^مسلسل-?/, '');
    let html = '';

    if (ctx.season && ctx.arabic) {
        const word = AR_ORD_WORDS[ctx.season];
        if (word) {
            try {
                const res = await fetch(`${API_BASE}/details.php?source=egydead&slug=${encodeURIComponent('egydead/season/' + ctx.seriesStem + '-الموسم-' + word)}`);
                const data = await res.json();
                const eps = (data.details && data.details.episodes) || [];
                if (eps.length) {
                    const cur = eps.findIndex((e) => decodeURIComponent(e.url) === decodeURIComponent(ctx.watchUrl));
                    const canPrev = cur > 0;
                    const canNext = cur >= 0 && cur < eps.length - 1;
                    const jump = (k) => `navigateTo('watch','${eps[k].url.replace(/'/g, "\\'")}')`;
                    html += `<div class="flex gap-3 mb-2">
                        <button onclick="${canPrev ? jump(cur - 1) : 'null'}" class="watch-nav ${canPrev ? 'watch-nav-on' : 'watch-nav-off'}">← ${t('prev_episode')}</button>
                        <button onclick="${canNext ? jump(cur + 1) : 'null'}" class="watch-nav ${canNext ? 'watch-nav-on' : 'watch-nav-off'}">${t('next_episode')} →</button>
                    </div>`;
                    html += `<h2 class="text-lg font-bold mt-8 mb-3">${t('episodes')} (${eps.length})</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-8">` +
                        eps.map((ep, k) => `
                        <button onclick="${jump(k)}"
                            class="episode-btn bg-gray-900 border rounded-xl p-4 text-center transition ${k === cur ? 'border-red-500 bg-red-500/10' : 'border-gray-800 hover:border-red-500/50'}">
                            <span class="block text-2xl font-bold">${k + 1}</span>
                            <span class="text-xs text-gray-500 mt-1 block">${t('episode')} ${k + 1}</span>
                        </button>`).join('') + `</div>`;
                }
            } catch (e) { /* season list unavailable */ }
        }
    }

    try {
        const res = await fetch(`${API_BASE}/search.php?q=${encodeURIComponent(stem)}`);
        const data = await res.json();
        const seasons = (data.results || [])
            .filter((r) => r.type === 'season' && decodeURIComponent(r.url).includes(stem))
            .sort((a, b) => seasonOrdinal(a.url) - seasonOrdinal(b.url));
        if (seasons.length > 1) {
            html += `<h2 class="text-lg font-bold mb-3">${t('seasons')}</h2>
                <div class="flex flex-wrap gap-2 mb-8">` +
                seasons.map((s) => `
                    <button onclick="navigateTo('detail','${s.url.replace(/'/g, "\\'")}')"
                        class="px-4 py-2 rounded-lg text-sm transition ${seasonOrdinal(s.url) === ctx.season ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'}">${t('season')} ${seasonOrdinal(s.url)}</button>`).join('') + `</div>`;
        }
    } catch (e) { /* seasons unavailable */ }

    try {
        const res = await fetch(`${API_BASE}/home.php`);
        const data = await res.json();
        const items = (data.categories && data.categories.length ? data.categories[0].items : []).slice(0, 12);
        if (items.length) {
            html += `<h2 class="text-lg font-bold mb-3">${t('more_like')}</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">` +
                items.map((it) => `
                    <button onclick="navigateTo('${it.type === 'episode' ? 'watch' : 'detail'}','${it.url.replace(/'/g, "\\'")}')"
                        class="group text-left">
                        ${it.poster ? `<img src="${imgUrl(it.poster)}" loading="lazy" class="w-full aspect-[2/3] object-cover rounded-xl bg-gray-900 group-hover:opacity-80 transition">` : '<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl"></div>'}
                        <span class="block text-xs text-gray-400 mt-2 line-clamp-2">${it.title}</span>
                    </button>`).join('') + `</div>`;
        }
    } catch (e) { /* more-like unavailable */ }

    host.innerHTML = html;
}

function vidsrcQueryFromWatch() {
    const url = window._watchPageUrl || '';
    const title = window._watchTitle || '';
    const m = url.match(/-s(\d+)e(\d+)/i);
    if (m) return { q: title, type: 'tv', season: m[1], episode: m[2] };
    const ar = url.match(/\/(?:episode|movie)\/([^/]*?)-الموسم-([^-]+)-الحلقة-?(\d+)/i);
    if (ar) {
        const eng = decodeURIComponent(ar[1]).replace(/^مسلسل-?/, '').replace(/-/g, ' ').trim();
        const season = AR_ORDINALS[ar[2]] || parseInt(ar[2], 10) || 1;
        return { q: eng ? titleCase(eng) : title, type: 'tv', season, episode: parseInt(ar[3], 10) || 1 };
    }
    const eng2 = url.match(/\/(?:episode|movie)\/([^/]*?)\//i);
    const q = eng2 && eng2[1] && eng2[1].length > 2 && eng2[1].length < 60 ? titleCase(eng2[1].replace(/-/g, ' ').trim()) : title;
    return { q, type: 'movie' };
}

async function playVidsrc() {
    const q = vidsrcQueryFromWatch();
    const btn = document.querySelector('.stream-btn[data-vidsrc]');
    if (btn) { btn.classList.add('opacity-50'); btn.disabled = true; }
    try {
        const res = await fetch(`api/vidsrc.php?q=${encodeURIComponent(q.q)}&type=${q.type}${q.season ? '&season=' + q.season + '&episode=' + q.episode : ''}`);
        const data = await res.json();
        if (!data.ok) { showToast('VidSrc: ' + (data.error || 'unavailable')); return false; }
        initPlayer(data.url, data.type || 'hls', 'playerContainer');
        showToast(`Now playing: ${data.quality_label || 'VidSrc'}`);
        restoreSubtitles(document.getElementById('videoPlayer'));
        return true;
    } catch (e) {
        showToast('VidSrc: ' + (t('player_error') || 'error'));
        return false;
    } finally {
        if (btn) { btn.classList.remove('opacity-50'); btn.disabled = false; }
    }
}

async function switchVidsrc() {
    window._pcUserSwitchAt = Date.now();
    await playVidsrc();
}

let _pcWatchTimer = null;
let _pcVidsrcAttempts = 0;

// Watch the active player: if the video never starts (or dies mid-playback)
// or the active source is a dead embed, auto-press VidSrc until something
// actually plays.
function watchPlayback() {
    const video = document.getElementById('videoPlayer');
    const iframe = document.querySelector('#playerContainer iframe');
    clearTimeout(_pcWatchTimer);
    if (video) {
        window._pcPlaying = !video.paused;
        const started = () => { window._pcPlaying = true; };
        video.removeEventListener('playing', started);
        video.addEventListener('playing', started);
        _pcWatchTimer = setTimeout(() => {
            if (window._pcPlaying) return;
            if (Date.now() - (window._pcUserSwitchAt || 0) < 20000) { watchPlayback(); return; }
            showToast(t('stream_failed') || 'Stream not playing — switching source');
            autoVidsrc();
        }, 15000);
    } else if (iframe) {
        // embeds can't be observed cross-origin: auto-try VidSrc over them
        window._pcPlaying = false;
        _pcWatchTimer = setTimeout(() => {
            if (Date.now() - (window._pcUserSwitchAt || 0) < 20000) return;
            autoVidsrc();
        }, 6000);
        iframe.addEventListener('error', () => autoVidsrc());
    } else {
        window._pcPlaying = false;
    }
}

async function autoVidsrc() {
    const video = document.getElementById('videoPlayer');
    if (window._pcPlaying && video) return;
    if (window._pcVidsrcBusy) return;
    window._pcVidsrcBusy = true;
    let ok = false;
    try {
        ok = await playVidsrc();
    } finally {
        window._pcVidsrcBusy = false;
    }
    if (ok) {
        _pcVidsrcAttempts = 0;
        watchPlayback();
        return true;
    }
    if (_pcVidsrcAttempts < 6) {
        _pcVidsrcAttempts++;
        showToast('VidSrc ' + (t('retrying') || 'retrying…') + ' (' + _pcVidsrcAttempts + '/6)');
        clearTimeout(_pcWatchTimer);
        _pcWatchTimer = setTimeout(autoVidsrc, 5000);
    } else {
        showToast(t('embed_fallback') || 'No working stream');
        if (typeof window._pcEmbedFallback === 'function') window._pcEmbedFallback();
    }
    return false;
}

async function probeStream(stream) {    try {
        const ctl = new AbortController();
        const to = setTimeout(() => ctl.abort(), 9000);
        const res = await fetch(`api/probe.php?url=${encodeURIComponent(stream.stream_url)}&type=${encodeURIComponent(stream.stream_type || 'iframe')}${stream.referer ? '&referer=' + encodeURIComponent(stream.referer) : ''}`, { signal: ctl.signal });
        clearTimeout(to);
        const data = await res.json();
        return data.ok !== false;
    } catch (e) {
        return true;
    }
}

async function tryUnwrap(stream, index) {
    if (stream.stream_type !== 'iframe') return null;
    if (window._unwrapped && window._unwrapped[index]) return window._unwrapped[index];
    try {
        const res = await fetch(`api/unwrap.php?url=${encodeURIComponent(stream.stream_url)}`);
        const data = await res.json();
        if (!data.ok) return null;
        const out = {
            ...stream,
            stream_type: data.type,
            stream_url: data.url,
            quality_label: stream.quality_label || 'Auto',
            isUnwrapped: true,
        };
        if (window._unwrapped) window._unwrapped[index] = out;
        return out;
    } catch (e) {
        return null;
    }
}

function startInstaller(stream, pageUrl, title) {
    if (!window.Installer) return;
    const container = document.getElementById('playerContainer');
    if (stream.stream_type === 'iframe') {
        Installer.notAvailable(container);
        return;
    }
    const realUrl = stream.isUnwrapped && window.Installer
        ? (Installer.deproxify ? Installer.deproxify(stream.stream_url) : stream.stream_url)
        : stream.stream_url;
    if (stream.stream_type === 'hls') {
        Installer.attachHls(hlsInstance, {
            originalUrl: realUrl,
            ref: stream.referer || '',
            title: title || 'episode',
            streamType: 'hls',
            container,
        });
    } else if (stream.stream_type === 'mp4') {
        Installer.attach({
            originalUrl: realUrl,
            ref: stream.referer || '',
            title: title || 'episode',
            streamType: 'mp4',
            container,
        });
    }
}

function switchStream(index) {
    window._pcUserSwitchAt = Date.now();
    const stream = window._streams[index];
    if (!stream) return;
    if (window.Installer) Installer.stop();
    const container = document.getElementById('playerContainer');
    container.innerHTML = '<div class="stream-loading"></div>';
    tryUnwrap(stream, index).then((u) => {
        const playable = u || stream;
        const playerUrl = playable.isUnwrapped
            ? playable.stream_url
            : (playable.stream_type === 'hls' || playable.stream_type === 'mp4')
                ? (window.Installer ? Installer.proxify(playable.stream_url, playable.referer) : playable.stream_url)
                : playable.stream_url;
        initPlayer(playerUrl, playable.stream_type, 'playerContainer');
        restoreSubtitles(document.getElementById('videoPlayer'));
        startInstaller(playable, window._watchPageUrl || '', window._watchTitle || '');
        watchPlayback();
        const q = playable.quality_label || 'Auto';
        showToast(`${t('now_playing')}: ${q} · ${u ? t('direct') : t('external')}`);
    });
    document.querySelectorAll('.stream-btn').forEach((btn, i) => {
        btn.className = `stream-btn px-3 py-1.5 rounded-lg text-sm transition ${i === index ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}`;
    });
    updateStreamBadges();
}
