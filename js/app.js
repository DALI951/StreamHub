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
    document.getElementById('app').innerHTML = `
        <div class="hero-gradient min-h-[60vh] flex items-center justify-center text-center px-4">
            <div>
                <h1 class="text-5xl md:text-7xl font-bold mb-4">
                    <span class="text-red-500">Stream</span>Hub
                </h1>
                <p class="text-gray-400 text-lg mb-8" data-i18n="search_placeholder">${t('search_placeholder')}</p>
                <div class="max-w-lg mx-auto">
                    <input id="homeSearch" type="text" placeholder="${t('search_placeholder')}"
                        class="w-full bg-gray-900 border border-gray-800 rounded-full px-6 py-4 text-lg focus:outline-none focus:border-red-500/50 transition placeholder-gray-500"
                        onkeydown="if(event.key==='Enter'){navigateTo('search',this.value)}">
                </div>
                <div class="mt-8 flex flex-wrap gap-3 justify-center">
                    <span class="text-sm text-gray-500">${t('all_sources')}</span>
                </div>
            </div>
        </div>
    `;
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
    container.innerHTML = results.map((r, i) => `
        <div class="card-hover cursor-pointer animate-in" style="animation-delay:${i * 30}ms"
             onclick="navigateTo('detail', '${r.url.replace(/'/g, "\\'")}'  )">
            <div class="relative rounded-lg overflow-hidden bg-gray-900 aspect-[2/3]">
                ${r.poster ? `<img src="${imgUrl(r.poster)}" alt="${r.title}" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">` :
                    `<div class="w-full h-full flex items-center justify-center text-gray-700 text-4xl">ðŸŽ¬</div>`}
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
    `).join('');
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
                            `<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl flex items-center justify-center text-6xl">ðŸŽ¬</div>`}
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
                            <div class="text-2xl mb-2">â–¶</div>
                            <span class="text-sm font-medium">${t('episode')} ${ep.number}</span>
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
                            `<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl flex items-center justify-center text-6xl">ðŸŽ¬</div>`}
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
                            <div class="text-2xl mb-2">ðŸ“</div>
                            <span class="text-sm font-medium">${t('season')} ${sn}</span>
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
                            `<div class="w-full aspect-[2/3] bg-gray-900 rounded-xl flex items-center justify-center text-6xl">ðŸŽ¬</div>`}
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

    try {
        const res = await fetch(`${API_BASE}/streams.php?url=${encodeURIComponent(url)}`);
        const data = await res.json();
        const streams = data.streams || [];

        if (!streams.length) {
            app.innerHTML = `<div class="text-center py-20 text-gray-500">${t('no_results')}</div>`;
            hideLoading();
            return;
        }

        const servers = [...new Set(streams.map(s => s.server_name || 'Server'))];
        const bestStream = streams[0];

        app.innerHTML = `
            <div class="max-w-6xl mx-auto px-4 py-6 animate-in">
                <button onclick="history.back()" class="mb-4 text-gray-400 hover:text-white transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span>${t('back')}</span>
                </button>
                ${title ? `<h1 class="text-2xl font-bold mb-4">${title}</h1>` : ''}
                <div id="playerContainer" class="player-wrap w-full rounded-xl overflow-hidden bg-black mb-6"></div>
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="text-sm text-gray-500">${t('server')}:</span>
                    ${servers.map((s, i) => `
                        <button onclick="switchServer(${i})"
                            class="server-btn px-3 py-1.5 rounded-lg text-sm transition ${i === 0 ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}"
                            data-server="${s}">
                            ${t('server')} ${i + 1}
                        </button>
                    `).join('')}
                </div>
                <div class="flex flex-wrap gap-2 mb-6">
                    <span class="text-sm text-gray-500">${t('quality_select')}:</span>
                    ${streams.map((s, i) => `
                        <button onclick="switchStream(${i})"
                            class="stream-btn px-3 py-1.5 rounded-lg text-sm transition ${i === 0 ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}"
                            data-index="${i}">
                            ${s.quality_label || 'Auto'}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;

        window._streams = streams;
        window._watchPageUrl = url;
        window._watchTitle = title || '';
        initPlayer(bestStream.stream_type === 'hls' || bestStream.stream_type === 'mp4'
            ? window.Installer ? Installer.proxify(bestStream.stream_url, bestStream.referer) : bestStream.stream_url
            : bestStream.stream_url,
            bestStream.stream_type, 'playerContainer');
        restoreSubtitles(document.getElementById('videoPlayer'));
        startInstaller(bestStream, url, title);
    } catch (e) {
        app.innerHTML = `<div class="text-center py-20 text-gray-500">Error loading streams</div>`;
    }
    hideLoading();
}

function startInstaller(stream, pageUrl, title) {
    if (!window.Installer) return;
    const container = document.getElementById('playerContainer');
    if (stream.stream_type === 'iframe') {
        Installer.notAvailable(container);
        return;
    }
    if (stream.stream_type === 'hls') {
        Installer.attachHls(hlsInstance, {
            originalUrl: stream.stream_url,
            ref: stream.referer || '',
            title: title || 'episode',
            streamType: 'hls',
            container,
        });
    } else if (stream.stream_type === 'mp4') {
        Installer.attach({
            originalUrl: stream.stream_url,
            ref: stream.referer || '',
            title: title || 'episode',
            streamType: 'mp4',
            container,
        });
    }
}

function switchStream(index) {
    const stream = window._streams[index];
    if (!stream) return;
    if (window.Installer) Installer.stop();
    initPlayer(stream.stream_type === 'hls' || stream.stream_type === 'mp4'
        ? window.Installer ? Installer.proxify(stream.stream_url, stream.referer) : stream.stream_url
        : stream.stream_url,
        stream.stream_type, 'playerContainer');
    restoreSubtitles(document.getElementById('videoPlayer'));
    startInstaller(stream, window._watchPageUrl || '', window._watchTitle || '');
    document.querySelectorAll('.stream-btn').forEach((btn, i) => {
        btn.className = `stream-btn px-3 py-1.5 rounded-lg text-sm transition ${i === index ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}`;
    });
}

function switchServer(index) {
    document.querySelectorAll('.server-btn').forEach((btn, i) => {
        btn.className = `server-btn px-3 py-1.5 rounded-lg text-sm transition ${i === index ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'}`;
    });
}
