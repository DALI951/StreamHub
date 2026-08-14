let hlsInstance = null;

function initPlayer(streamUrl, streamType, containerId) {
    destroyPlayer();
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';

    if (streamType === 'iframe') {
        const iframe = document.createElement('iframe');
        iframe.src = streamUrl;
        iframe.className = 'stream-frame';
        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('mozallowfullscreen', 'true');
        iframe.setAttribute('webkitallowfullscreen', 'true');
        container.appendChild(iframe);

        buildIframeChrome(container);
        return;
    }

    const video = document.createElement('video');
    video.id = 'videoPlayer';
    video.autoplay = true;
    video.playsInline = true;
    video.className = 'stream-video';
    container.appendChild(video);

    buildPlayerChrome(video, container);

    if (streamType === 'hls' || streamUrl.includes('.m3u8')) {
        if (Hls.isSupported()) {
            const hlsConfig = {
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 30,
                maxMaxBufferLength: 600,
                startFragPrefetch: true,
                subtitleDisplay: true,
                enableWebVTT: true,
                enableCEA608Captions: true,
            };
            if (window.Installer) Object.assign(hlsConfig, Installer.hlsConfig());
            hlsInstance = new Hls(hlsConfig);
            hlsInstance.loadSource(streamUrl);
            hlsInstance.attachMedia(video);
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                video.play().catch(() => {});
                setupQualityMenu(hlsInstance);
            });
            hlsInstance.on(Hls.Events.SUBTITLE_TRACKS_LOADED, () => {
                buildSubtitlePanel(video, container);
            });
            hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    console.error('HLS fatal error:', data);
                    hlsInstance.destroy();
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = streamUrl;
            video.addEventListener('loadedmetadata', () => video.play().catch(() => {}));
        }
    } else {
        video.src = streamUrl;
        video.addEventListener('loadedmetadata', () => video.play().catch(() => {}));
        buildSubtitlePanel(video, container);
    }
}

/* ============ CUSTOM PLAYER CHROME ============ */

function buildIframeChrome(container) {
    container.classList.add('pc-root');
    let hideTimer = null;

    const chrome = document.createElement('div');
    chrome.className = 'pc-chrome pc-iframe-chrome';
    const tt = (k, fb) => (typeof t === 'function' ? t(k) || fb : fb);
    chrome.innerHTML = `
        <div class="pc-scrim pc-scrim-iframe"></div>
        <div class="pc-brand">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            <span>StreamHub Player</span>
        </div>
        <div class="pc-controls pc-controls-iframe">
            <div class="pc-buttons">
                <span class="pc-time pc-iframe-note">${tt('external', 'External player')}</span>
                <div class="pc-spacer"></div>
                <button class="pc-btn pc-sub-disabled" title="${tt('subs_unavailable', 'Subtitles not available')}">${ICONS.cc}</button>
                <button class="pc-btn pc-fs" title="${tt('player_fullscreen', 'Fullscreen')}">${ICONS.fs}</button>
            </div>
        </div>
    `;
    container.appendChild(chrome);

    // iframe embeds keep their own video; our bottom bar stays VISIBLE and
    // covers the embed's controls so only our chrome is shown.
    const keepChrome = () => {
        chrome.classList.add('pc-visible');
        container.classList.remove('pc-idle');
        clearTimeout(hideTimer);
    };

    chrome.querySelector('.pc-fs').addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFullscreen(container);
    });
    chrome.querySelector('.pc-sub-disabled').addEventListener('click', (e) => {
        e.stopPropagation();
    });

    container.addEventListener('mousemove', keepChrome);
    container.addEventListener('touchstart', keepChrome, { passive: true });
    container.addEventListener('dblclick', (e) => {
        e.preventDefault();
        toggleFullscreen(container);
    });
    keepChrome();
}

const ICONS = {
    play: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
    pause: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>',
    vol: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" stroke="none"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a9 9 0 0 1 0 14"/></svg>',
    mute: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" stroke="none"/><path d="M23 9l-6 6"/><path d="M17 9l6 6"/></svg>',
    fs: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>',
    gear: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    cc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4M13 15h4M7 11h2M11 11h6"/></svg>',
    spinner: '<svg class="pc-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.56"/></svg>',
    crop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
};

function buildPlayerChrome(video, container) {
    container.classList.add('pc-root');
    let hideTimer = null;
    let wasMuted = false;

    const chrome = document.createElement('div');
    chrome.className = 'pc-chrome';
    const tt = (k, fb) => (typeof t === 'function' ? t(k) || fb : fb);
    chrome.innerHTML = `
        <div class="pc-scrim"></div>
        <div class="pc-brand">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            <span>StreamHub Player</span>
        </div>
        <div class="pc-bigplay" title="${tt('player_play', 'Play')}">${ICONS.play}</div>
        <div class="pc-loading">${ICONS.spinner}<span>${tt('loading', 'Loading...')}</span></div>
        <div class="pc-controls">
            <div class="pc-seek-row">
                <div class="pc-seek">
                    <div class="pc-buffer"></div>
                    <div class="pc-progress"></div>
                    <div class="pc-scrub"></div>
                </div>
                <span class="pc-hover-time"></span>
            </div>
            <div class="pc-buttons">
                <button class="pc-btn pc-play" title="${tt('player_play', 'Play')}">${ICONS.play}</button>
                <button class="pc-btn pc-mute" title="${tt('player_mute', 'Mute')}">${ICONS.vol}</button>
                <input type="range" class="pc-vol" min="0" max="100" value="100">
                <span class="pc-time">0:00 / 0:00</span>
                <div class="pc-spacer"></div>
                <button class="pc-btn pc-crop" title="${tt('player_crop', 'Remove watermark (crop)')}">${ICONS.crop}</button>
                <button class="pc-btn pc-quality" style="display:none" title="${tt('player_quality', 'Quality')}">Auto</button>
                <button class="pc-btn pc-settings" title="${tt('player_settings', 'Settings')}">${ICONS.gear}</button>
                <button class="pc-btn pc-cc" title="${tt('player_cc', 'Subtitles')}">${ICONS.cc}</button>
                <button class="pc-btn pc-fs" title="${tt('player_fullscreen', 'Fullscreen')}">${ICONS.fs}</button>
            </div>
        </div>
    `;
    container.appendChild(chrome);

    const bigplay = chrome.querySelector('.pc-bigplay');
    const loading = chrome.querySelector('.pc-loading');
    const playBtn = chrome.querySelector('.pc-play');
    const seek = chrome.querySelector('.pc-seek');
    const buffer = chrome.querySelector('.pc-buffer');
    const progress = chrome.querySelector('.pc-progress');
    const scrub = chrome.querySelector('.pc-scrub');
    const timeEl = chrome.querySelector('.pc-time');
    const hoverTime = chrome.querySelector('.pc-hover-time');
    const muteBtn = chrome.querySelector('.pc-mute');
    const volRange = chrome.querySelector('.pc-vol');
    const cropBtn = chrome.querySelector('.pc-crop');
    const qualityBtn = chrome.querySelector('.pc-quality');
    const settingsBtn = chrome.querySelector('.pc-settings');
    const ccBtn = chrome.querySelector('.pc-cc');
    const fsBtn = chrome.querySelector('.pc-fs');

    if (localStorage.getItem('pc_crop') === '1') {
        container.classList.add('pc-cropped');
        cropBtn.classList.add('pc-active');
    }

    const fmt = (s) => {
        if (!isFinite(s)) s = 0;
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = Math.floor(s % 60);
        const mm = h ? String(m).padStart(2, '0') : m;
        return (h ? h + ':' : '') + mm + ':' + String(sec).padStart(2, '0');
    };

    const setPlayIcon = (playing) => {
        playBtn.innerHTML = playing ? ICONS.pause : ICONS.play;
        bigplay.innerHTML = playing ? ICONS.pause : ICONS.play;
        bigplay.classList.toggle('pc-hidden', playing);
    };

    const showChrome = () => {
        chrome.classList.add('pc-visible');
        container.classList.remove('pc-idle');
        clearTimeout(hideTimer);
        if (!video.paused) {
            hideTimer = setTimeout(() => {
                const menu = container.querySelector('.pc-menu');
                if (menu && menu.classList.contains('pc-open')) return;
                chrome.classList.remove('pc-visible');
                container.classList.add('pc-idle');
            }, 3000);
        }
    };

    const togglePlay = () => {
        if (video.paused) video.play().catch(() => {});
        else video.pause();
    };

    playBtn.onclick = togglePlay;
    bigplay.onclick = togglePlay;

    video.addEventListener('play', () => { setPlayIcon(true); showChrome(); });
    video.addEventListener('pause', () => { setPlayIcon(false); showChrome(); });
    video.addEventListener('click', () => { togglePlay(); showChrome(); });
    video.addEventListener('dblclick', (e) => {
        e.preventDefault();
        toggleFullscreen(container);
    });
    container.addEventListener('mousemove', showChrome);
    container.addEventListener('touchstart', showChrome, { passive: true });

    video.addEventListener('timeupdate', () => {
        const d = video.duration || 0;
        const c = video.currentTime || 0;
        timeEl.textContent = fmt(c) + ' / ' + fmt(d);
        const pct = d ? (c / d) * 100 : 0;
        progress.style.width = pct + '%';
        scrub.style.left = 'calc(' + pct + '% - 5px)';
    });
    video.addEventListener('progress', () => {
        let max = 0;
        try {
            for (let i = 0; i < video.buffered.length; i++) {
                max = Math.max(max, video.buffered.end(i));
            }
        } catch (e) { }
        const d = video.duration || 1;
        buffer.style.width = (max / d) * 100 + '%';
    });
    video.addEventListener('waiting', () => loading.classList.add('pc-show'));
    video.addEventListener('playing', () => loading.classList.remove('pc-show'));
    video.addEventListener('canplay', () => loading.classList.remove('pc-show'));
    video.addEventListener('loadedmetadata', () => loading.classList.remove('pc-show'));
    video.addEventListener('error', () => {
        loading.classList.remove('pc-show');
        bigplay.classList.remove('pc-hidden');
    });

    let seeking = false;
    seek.addEventListener('mousedown', (e) => { seeking = true; });
    document.addEventListener('mouseup', () => { seeking = false; });
    const seekTo = (clientX) => {
        const rect = seek.getBoundingClientRect();
        const pct = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
        if (video.duration) video.currentTime = pct * video.duration;
    };
    seek.addEventListener('click', (e) => seekTo(e.clientX));
    seek.addEventListener('mousemove', (e) => {
        const rect = seek.getBoundingClientRect();
        const pct = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
        if (video.duration) {
            hoverTime.textContent = fmt(pct * video.duration);
            hoverTime.style.display = 'block';
            hoverTime.style.left = pct * 100 + '%';
        }
        if (seeking) seekTo(e.clientX);
    });
    seek.addEventListener('mouseleave', () => { hoverTime.style.display = 'none'; });
    seek.addEventListener('touchstart', (e) => { seeking = true; seekTo(e.touches[0].clientX); }, { passive: true });
    seek.addEventListener('touchmove', (e) => { seekTo(e.touches[0].clientX); }, { passive: true });
    seek.addEventListener('touchend', () => { seeking = false; });

    muteBtn.onclick = () => {
        if (video.volume === 0) {
            video.volume = wasMuted ? 1 : Math.max(volRange.value / 100, 0.1);
            video.muted = false;
        } else {
            wasMuted = true;
            video.muted = true;
            video.volume = 0;
        }
        volRange.value = video.volume * 100;
        muteBtn.innerHTML = video.volume === 0 ? ICONS.mute : ICONS.vol;
    };
    volRange.addEventListener('input', () => {
        video.volume = volRange.value / 100;
        video.muted = video.volume === 0;
        muteBtn.innerHTML = video.volume === 0 ? ICONS.mute : ICONS.vol;
    });
    video.addEventListener('volumechange', () => {
        volRange.value = video.volume * 100;
        muteBtn.innerHTML = video.volume === 0 ? ICONS.mute : ICONS.vol;
    });

    cropBtn.onclick = () => {
        const on = container.classList.toggle('pc-cropped');
        localStorage.setItem('pc_crop', on ? '1' : '0');
        cropBtn.classList.toggle('pc-active', on);
        showChrome();
    };

    settingsBtn.onclick = (e) => {
        e.stopPropagation();
        const menu = getSettingsMenu();
        const qmenu = container.querySelector('.pc-qmenu');
        if (qmenu) qmenu.classList.remove('pc-open');
        menu.classList.toggle('pc-open');
        showChrome();
    };
    document.addEventListener('click', (e) => {
        container.querySelectorAll('.pc-menu').forEach((m) => {
            if (m.classList.contains('pc-open') && !m.contains(e.target) && e.target !== settingsBtn && e.target !== qualityBtn) {
                m.classList.remove('pc-open');
            }
        });
    });

    fsBtn.onclick = () => toggleFullscreen(container);

    qualityBtn.onclick = (e) => {
        e.stopPropagation();
        const menu = buildQualityMenu(container, hlsInstance, qualityBtn);
        if (!menu) return;
        const smenu = container.querySelector('.pc-menu');
        if (smenu) smenu.classList.remove('pc-open');
        menu.classList.toggle('pc-open');
        showChrome();
    };

    ccBtn.onclick = (e) => {
        e.stopPropagation();
        if (container.querySelector('.sub-btn')) {
            container.querySelector('.sub-btn').click();
        } else {
            openSubtitlePanel(video, container, ccBtn);
        }
    };

    document.addEventListener('keydown', (e) => {
        const active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) return;
        if (!container.isConnected) return;
        if (document.fullscreenElement && !container.contains(document.fullscreenElement)) return;
        switch (e.key.toLowerCase()) {
            case ' ':
            case 'k':
                e.preventDefault();
                togglePlay();
                break;
            case 'arrowleft':
                e.preventDefault();
                video.currentTime -= 10;
                break;
            case 'arrowright':
                e.preventDefault();
                video.currentTime += 10;
                break;
            case 'arrowup':
                e.preventDefault();
                video.volume = Math.min(1, video.volume + 0.1);
                break;
            case 'arrowdown':
                e.preventDefault();
                video.volume = Math.max(0, video.volume - 0.1);
                break;
            case 'm':
                video.muted = !video.muted;
                break;
            case 'f':
                toggleFullscreen(container);
                break;
        }
    });

    addSubtitleButton(container, false, video, ccBtn);
    chrome.querySelector('.pc-cc').style.display = 'none';
    video.controls = false;
    showChrome();
}

function getSettingsMenu() {
    const container = document.querySelector('.pc-root');
    if (!container) return null;
    let menu = container.querySelector('.pc-menu');
    if (!menu) {
        menu = document.createElement('div');
        menu.className = 'pc-menu';
        container.appendChild(menu);
    }
    const video = document.getElementById('videoPlayer');
    const tt = (k, fb) => (typeof t === 'function' ? t(k) || fb : fb);
    const hls = window._pcHls;
    let html = `<div class="pc-menu-sec">${tt('player_quality', 'Quality')}</div><div class="pc-menu-list">`;
    const curQ = hls ? hls.currentLevel : -1;
    html += `<button class="pc-menu-item ${curQ === -1 ? 'pc-on' : ''}" data-q="-1"><span>Auto${hls && hls.autoLevelEnabled && hls.levels.length > 1 ? ' · ' + hls.levels[hls.currentLevel]?.height + 'p' : ''}</span><i>${ICONS.check}</i></button>`;
    if (hls && hls.levels && hls.levels.length > 1) {
        hls.levels.forEach((lvl, i) => {
            html += `<button class="pc-menu-item ${curQ === i ? 'pc-on' : ''}" data-q="${i}"><span>${lvl.height || 'HD'}p${lvl.bitrate ? ' <em>' + Math.round(lvl.bitrate / 1000) + ' kbps</em>' : ''}</span><i>${ICONS.check}</i></button>`;
        });
    } else {
        html += `<button class="pc-menu-item pc-disabled" data-q="-2"><span>${hls ? tt('player_quality', 'Quality') : 'Source'}</span><i>${ICONS.check}</i></button>`;
    }
    html += `</div><div class="pc-menu-sec">${tt('player_speed', 'Playback speed')}</div><div class="pc-menu-list">`;
    const speeds = [0.5, 0.75, 1, 1.25, 1.5, 2];
    speeds.forEach((s) => {
        html += `<button class="pc-menu-item ${Math.abs((video ? video.playbackRate : 1) - s) < 0.01 ? 'pc-on' : ''}" data-s="${s}"><span>${s}x</span><i>${ICONS.check}</i></button>`;
    });
    html += `</div><div class="pc-menu-sec">${tt('seek', 'Seek')}</div><div class="pc-menu-list">`;
    html += `<button class="pc-menu-item" data-seek="-10"><span>−10s</span><i>${ICONS.check}</i></button>`;
    html += `<button class="pc-menu-item" data-seek="10"><span>+10s</span><i>${ICONS.check}</i></button>`;
    html += `</div>`;
    menu.innerHTML = html;
    menu.querySelectorAll('[data-q]').forEach((b) => {
        b.onclick = () => {
            const i = parseInt(b.dataset.q, 10);
            if (window._pcHls && i >= -1) window._pcHls.currentLevel = i;
            getSettingsMenu();
        };
    });
    menu.querySelectorAll('[data-s]').forEach((b) => {
        b.onclick = () => {
            const v = document.getElementById('videoPlayer');
            if (v) v.playbackRate = parseFloat(b.dataset.s);
            getSettingsMenu();
        };
    });
    menu.querySelectorAll('[data-seek]').forEach((b) => {
        b.onclick = () => {
            const v = document.getElementById('videoPlayer');
            if (v && v.duration) v.currentTime = Math.min(Math.max(v.currentTime + parseInt(b.dataset.seek, 10), 0), v.duration);
            getSettingsMenu();
        };
    });
    return menu;
}

function setupQualityMenu(hls) {
    window._pcHls = hls;
    const btn = document.querySelector('.pc-quality');
    if (!btn) return;
    const levels = hls.levels || [];
    if (levels.length > 1) {
        btn.style.display = 'flex';
        const label = () => {
            if (hls.autoLevelEnabled || hls.currentLevel === -1) return 'Auto';
            const lvl = levels[hls.currentLevel];
            return lvl && lvl.height ? lvl.height + 'p' : 'Auto';
        };
        btn.textContent = label();
        hls.on(Hls.Events.LEVEL_SWITCHED, () => { btn.textContent = label(); });
    } else {
        btn.style.display = 'none';
    }
}

function buildQualityMenu(container, hls, anchorBtn) {
    container.querySelectorAll('.pc-qmenu').forEach((m) => m.remove());
    if (!hls || !hls.levels || hls.levels.length < 2) return null;
    const tt = (k, fb) => (typeof t === 'function' ? t(k) || fb : fb);
    const menu = document.createElement('div');
    menu.className = 'pc-menu pc-qmenu';
    const cur = hls.currentLevel;
    let html = `<div class="pc-menu-sec">${tt('player_quality', 'Quality')}</div><div class="pc-menu-list">`;
    html += `<button class="pc-menu-item ${cur === -1 ? 'pc-on' : ''}" data-q="-1"><span>Auto</span><i>${ICONS.check}</i></button>`;
    hls.levels.forEach((lvl, i) => {
        html += `<button class="pc-menu-item ${cur === i ? 'pc-on' : ''}" data-q="${i}"><span>${lvl.height || 'HD'}p</span><i>${ICONS.check}</i></button>`;
    });
    html += '</div>';
    menu.innerHTML = html;
    container.appendChild(menu);
    menu.querySelectorAll('[data-q]').forEach((b) => {
        b.onclick = () => {
            hls.currentLevel = parseInt(b.dataset.q, 10);
            menu.classList.remove('pc-open');
        };
    });
    return menu;
}

function toggleFullscreen(el) {
    const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
    if (fsEl) {
        const exit = document.exitFullscreen || document.webkitExitFullscreen;
        if (exit) exit.call(document).catch(() => {});
        return;
    }
    if (el.tagName === 'VIDEO' && el.webkitEnterFullscreen) {
        try { el.webkitEnterFullscreen(); return; } catch (e) { /* fall through */ }
    }
    const req = el.requestFullscreen || el.webkitRequestFullscreen;
    if (req) {
        const p = req.call(el);
        if (p && p.catch) p.catch(() => {});
    }
}

function addFullscreenButton(container) {
    const btn = document.createElement('button');
    btn.className = 'fs-btn';
    btn.title = 'Fullscreen';
    btn.innerHTML = ICONS.fs;
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFullscreen(container);
    });
    container.appendChild(btn);
}

function buildSubtitlePanel(video, container) {
    if (container.querySelector('.sub-btn')) return;
    addSubtitleButton(container, false, video);
}

function addSubtitleButton(container, disabled, video, hostEl) {
    const host = hostEl ? hostEl.parentElement : container;
    if (host.querySelector('.sub-btn')) return;
    const btn = document.createElement('button');
    btn.className = 'sub-btn' + (disabled ? ' sub-btn-disabled' : '') + ' pc-btn';
    btn.title = disabled ? t('subs_unavailable') : 'Subtitles';
    btn.innerHTML = ICONS.cc;
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (disabled || !video) return;
        openSubtitlePanel(video, container, btn);
    });
    if (hostEl && hostEl.nextSibling) {
        host.insertBefore(btn, hostEl.nextSibling);
    } else {
        host.appendChild(btn);
    }
}

function openSubtitlePanel(video, container, btn) {
    const existing = container.querySelector('.sub-panel');
    if (existing) { existing.remove(); return; }
    const panel = document.createElement('div');
    panel.className = 'sub-panel';
    let tracks = '';
    const native = video.textTracks || [];
    for (let i = 0; i < native.length; i++) {
        const tr = native[i];
        if (tr.kind !== 'subtitles' && tr.kind !== 'captions') continue;
        tracks += `<button class="sub-track" data-idx="${i}">${tr.label || 'Track ' + (i + 1)}</button>`;
    }
    if (!tracks) tracks = '<div class="sub-none">' + t('subs_none') + '</div>';
    panel.innerHTML = `
        <div class="sub-panel-title">${t('subs_title')}</div>
        <div class="sub-tracks">${tracks}</div>
        <label class="sub-upload">
            <input type="file" accept=".vtt,.srt" class="sub-file">
            ${t('subs_upload')}
        </label>
    `;
    container.appendChild(panel);

    panel.querySelectorAll('.sub-track').forEach(b => {
        b.onclick = () => {
            const idx = parseInt(b.dataset.idx, 10);
            const tr = video.textTracks[idx];
            for (const t of video.textTracks) t.mode = 'disabled';
            if (tr) tr.mode = 'showing';
            saveSubtitleChoice(null);
            panel.remove();
        };
    });

    const fileInput = panel.querySelector('.sub-file');
    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            const text = String(reader.result || '');
            const vtt = file.name.toLowerCase().endsWith('.srt') ? srtToVtt(text) : text;
            const blob = new Blob([vtt], { type: 'text/vtt' });
            const url = URL.createObjectURL(blob);
            injectSubtitleTrack(video, url, file.name.replace(/\.(srt|vtt)$/i, ''));
            persistCustomSubtitle(vtt);
            panel.remove();
        };
        reader.readAsText(file);
    });
}

function injectSubtitleTrack(video, url, label) {
    for (const tr of video.textTracks) tr.mode = 'disabled';
    const track = document.createElement('track');
    track.kind = 'subtitles';
    track.label = label || 'Custom';
    track.srclang = 'und';
    track.src = url;
    track.default = true;
    video.appendChild(track);
    track.addEventListener('load', () => {
        for (const t of video.textTracks) t.mode = t === track.track ? 'showing' : 'disabled';
    });
}

function srtToVtt(srt) {
    const blocks = srt.replace(/\r/g, '').split(/\n{2,}/);
    const out = ['WEBVTT', ''];
    for (const b of blocks) {
        const lines = b.split('\n');
        if (!lines[0] || !/^\d+$/.test(lines[0].trim())) continue;
        let timeIdx = 1;
        while (timeIdx < lines.length && !lines[timeIdx].includes('-->')) timeIdx++;
        if (timeIdx >= lines.length) continue;
        const time = lines[timeIdx].replace(/(\d),(\d)/g, '$1.$2');
        const cue = lines.slice(timeIdx + 1).join('\n').trim();
        if (!cue) continue;
        out.push(time, cue, '');
    }
    return out.join('\n');
}

function persistCustomSubtitle(vtt) {
    const ep = window._watchPageUrl || '';
    if (!ep || !window.Installer) return;
    const key = 'subs|' + ep;
    Installer.dbPutSubs(key, vtt);
}

function restoreSubtitles(video) {
    const ep = window._watchPageUrl || '';
    if (!ep || !window.Installer) return;
    Installer.dbGetSubs('subs|' + ep).then((vtt) => {
        if (!vtt || !video || !video.isConnected) return;
        const blob = new Blob([vtt], { type: 'text/vtt' });
        injectSubtitleTrack(video, URL.createObjectURL(blob), 'Saved subtitles');
    });
}

function destroyPlayer() {
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }
    const video = document.getElementById('videoPlayer');
    if (video) {
        video.pause();
        video.removeAttribute('src');
        video.load();
    }
    const iframe = document.querySelector('#playerContainer iframe');
    if (iframe) {
        iframe.src = 'about:blank';
    }
}