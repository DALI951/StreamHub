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
                // hls.js auto-quality starts low and ramps up slowly; jump
                // straight to the best quality the source offers.
                if (hlsInstance.levels && hlsInstance.levels.length > 1) {
                    hlsInstance.currentLevel = hlsInstance.levels.length - 1;
                }
            });
            hlsInstance.on(Hls.Events.SUBTITLE_TRACKS_LOADED, () => {
                buildSubtitlePanel(video, container);
                autoEnableArabic(video);
            });
            hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    console.error('HLS fatal error:', data);
                    if (typeof window._pcOnPlayerError === 'function') window._pcOnPlayerError();
                    hlsInstance.destroy();
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = streamUrl;
            video.addEventListener('loadedmetadata', () => {
                video.play().catch(() => {});
                buildSubtitlePanel(video, container);
                autoEnableArabic(video);
            });
        }
    } else {
        video.src = streamUrl;
        video.addEventListener('loadedmetadata', () => {
            video.play().catch(() => {});
            buildSubtitlePanel(video, container);
            autoEnableArabic(video);
        });
    }

    loadArabicSubs(video, false);
}

/* ============ CUSTOM PLAYER CHROME ============ */

function buildIframeChrome(container) {
    container.classList.add('pc-root');

    const chrome = document.createElement('div');
    chrome.className = 'pc-chrome pc-iframe-chrome';
    const tt = (k, fb) => (typeof t === 'function' ? t(k) || fb : fb);
    chrome.innerHTML = `
        <div class="pc-scrim pc-scrim-iframe"></div>
        <div class="pc-loading pc-show">${ICONS.spinner}<span>${tt('loading', 'Loading...')}</span></div>
        <div class="pc-corner-buttons">
            <button class="pc-btn pc-fs" title="${tt('player_fullscreen', 'Fullscreen')}">${ICONS.fs}</button>
        </div>
    `;
    container.appendChild(chrome);

    const iframe = container.querySelector('iframe');
    if (iframe) {
        iframe.addEventListener('load', () => {
            chrome.querySelector('.pc-loading').classList.remove('pc-show');
        });
        iframe.addEventListener('error', () => {
            chrome.querySelector('.pc-loading').classList.remove('pc-show');
        });
    } else {
        chrome.querySelector('.pc-loading').classList.remove('pc-show');
    }

    // Embeds are cross-origin: we can't control them, and their own fs button
    // often doesn't work inside an iframe. So OUR fs button is a fully
    // transparent hit-zone sitting exactly over the embed's zoom button
    // (bottom-right) — looks like their icon, but ours works.
    chrome.classList.add('pc-visible');

    chrome.querySelector('.pc-fs').addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFullscreen(container);
    });
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
    const qualityBtn = chrome.querySelector('.pc-quality');
    const settingsBtn = chrome.querySelector('.pc-settings');
    const ccBtn = chrome.querySelector('.pc-cc');
    const fsBtn = chrome.querySelector('.pc-fs');

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
        if (video.getAttribute('src') && typeof window._pcOnPlayerError === 'function') window._pcOnPlayerError();
    });
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
    // spinner + controls visible immediately so a loading stream is never a
    // silent black box (hidden on 'playing'/'canplay'/'loadedmetadata')
    loading.classList.add('pc-show');
    setTimeout(() => loading.classList.remove('pc-show'), 12000);
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
    let activeIdx = -1;
    let tracks = '';
    const native = video.textTracks || [];
    for (let i = 0; i < native.length; i++) {
        const tr = native[i];
        if (tr.kind !== 'subtitles' && tr.kind !== 'captions') continue;
        if (tr.mode === 'showing') activeIdx = i;
        tracks += `<button class="sub-track ${tr.mode === 'showing' ? 'pc-on' : ''}" data-idx="${i}">${tr.label || 'Track ' + (i + 1)}</button>`;
    }
    if (!tracks) tracks = '<div class="sub-none">' + t('subs_none') + '</div>';
    const offCls = activeIdx === -1 ? 'pc-on' : '';
    const saved = subOffsetValue();
    panel.innerHTML = `
        <div class="sub-panel-title">${t('subs_title')}</div>
        <div class="sub-tracks">
            <button class="sub-track ${offCls}" data-idx="-1">${t('subs_off')}</button>
            ${tracks}
            <button class="sub-track" data-arabic="1">${t('subs_arabic')}</button>
        </div>
        <div class="sub-sync">
            <span class="sub-sync-label">${t('subs_sync')}</span>
            <button class="sub-sync-btn" data-sync="-5">−5</button>
            <button class="sub-sync-btn" data-sync="-0.5">−0.5</button>
            <span class="sub-sync-val" data-sync-val>${saved ? (saved > 0 ? '+' : '') + saved.toFixed(1) + 's' : '0.0s'}</span>
            <button class="sub-sync-btn" data-sync="0.5">+0.5</button>
            <button class="sub-sync-btn" data-sync="5">+5</button>
            <button class="sub-sync-btn" data-sync="auto">${t('subs_auto')}</button>
            <button class="sub-sync-btn" data-sync="0" title="${t('subs_reset')}">0</button>
        </div>
        <label class="sub-upload">
            <input type="file" accept=".vtt,.srt" class="sub-file">
            ${t('subs_upload')}
        </label>
    `;
    container.appendChild(panel);

    panel.querySelectorAll('.sub-track').forEach(b => {
        if (b.dataset.arabic) return;
        b.onclick = () => {
            const idx = parseInt(b.dataset.idx, 10);
            window._pcSubsPicked = true;
            for (const t of video.textTracks) t.mode = 'disabled';
            if (idx >= 0) {
                const tr = video.textTracks[idx];
                if (tr) tr.mode = 'showing';
            }
            panel.remove();
        };
    });

    const arBtn = panel.querySelector('[data-arabic]');
    if (arBtn) {
        arBtn.onclick = () => {
            panel.remove();
            loadArabicSubs(video, true);
        };
    }

    const valSpan = panel.querySelector('[data-sync-val]');
    panel.querySelectorAll('[data-sync]').forEach((b) => {
        b.onclick = async () => {
            const action = b.dataset.sync;
            const trackEl = showingTrackEl(video);
            if (!trackEl) {
                if (typeof showToast === 'function') showToast(t('subs_sync_none') || 'Select a subtitle first');
                return;
            }
            let rel;
            if (action === 'auto') {
                const track = trackEl.track;
                const need = autoFitOffset(video, track);
                if (!need) {
                    if (typeof showToast === 'function') showToast(t('subs_auto_none') || 'No auto-sync needed');
                    return;
                }
                rel = need - subOffsetValue();
            } else if (action === '0') {
                rel = -subOffsetValue();
            } else {
                rel = parseFloat(action);
            }
            if (!rel) return;
            const ok = await applyTrackOffset(video, trackEl, Math.round(rel * 1000));
            if (!ok) return;
            const val = Math.round((subOffsetValue() + rel) * 10) / 10;
            saveSubOffset(val);
            if (valSpan) valSpan.textContent = (val > 0 ? '+' : '') + val.toFixed(1) + 's';
            if (typeof showToast === 'function') showToast(t('subs_synced') + ' ' + (val > 0 ? '+' : '') + val.toFixed(1) + 's');
        };
    });

    const fileInput = panel.querySelector('.sub-file');
    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        window._pcSubsPicked = true;
        const reader = new FileReader();
        reader.onload = () => {
            const text = String(reader.result || '');
            const vtt = file.name.toLowerCase().endsWith('.srt') ? srtToVtt(text) : text;
            const blob = new Blob([vtt], { type: 'text/vtt' });
            const url = URL.createObjectURL(blob);
            const el = injectSubtitleTrack(video, url, file.name.replace(/\.(srt|vtt)$/i, ''));
            window._pcSubVttText = vtt;
            window._pcSubVttTrack = el;
            persistCustomSubtitle(vtt);
            panel.remove();
        };
        reader.readAsText(file);
    });
}

function subOffsetKey() {
    const q = typeof vidsrcQueryFromWatch === 'function' ? vidsrcQueryFromWatch() : null;
    if (!q || !q.q) return null;
    return 'pc_sub_off_' + (q.q + '|' + q.type).toLowerCase().replace(/[^a-z0-9|]/g, '');
}

function subOffsetValue() {
    try {
        const k = subOffsetKey();
        if (!k) return 0;
        const v = parseFloat(localStorage.getItem(k) || '');
        return Number.isFinite(v) ? v : 0;
    } catch (e) { return 0; }
}

function saveSubOffset(v) {
    try {
        const k = subOffsetKey();
        if (!k) return;
        if (v) localStorage.setItem(k, String(Math.round(v * 10) / 10));
        else localStorage.removeItem(k);
    } catch (e) { /* storage unavailable */ }
}

function shiftVttTimings(vtt, ms) {
    return vtt.replace(/(\d{2}):(\d{2}):(\d{2})\.(\d{3}) --> (\d{2}):(\d{2}):(\d{2})\.(\d{3})([^\n]*)/g,
        (m, h1, m1, s1, x1, h2, m2, s2, x2, tail) => {
            const fmt = (t) => {
                t = Math.max(0, Math.round(t));
                return `${String(Math.floor(t / 3600000)).padStart(2, '0')}:${String(Math.floor(t % 3600000 / 60000)).padStart(2, '0')}:${String(Math.floor(t % 60000 / 1000)).padStart(2, '0')}.${String(t % 1000).padStart(3, '0')}`;
            };
            return `${fmt(+h1 * 3600000 + +m1 * 60000 + +s1 * 1000 + +x1 + ms)} --> ${fmt(+h2 * 3600000 + +m2 * 60000 + +s2 * 1000 + +x2 + ms)}${tail}`;
        });
}

async function applyTrackOffset(video, trackEl, offsetMs) {
    if (!trackEl || !trackEl.src) return false;
    try {
        let text = window._pcSubVttTrack === trackEl ? window._pcSubVttText : null;
        if (text == null) text = await (await fetch(trackEl.src)).text();
        const shifted = shiftVttTimings(text, offsetMs);
        window._pcSubVttText = shifted;
        window._pcSubVttTrack = trackEl;
        const url = URL.createObjectURL(new Blob([shifted], { type: 'text/vtt' }));
        const nt = document.createElement('track');
        nt.kind = 'subtitles';
        nt.label = trackEl.label || 'العربية';
        nt.srclang = trackEl.srclang || 'und';
        nt.src = url;
        video.appendChild(nt);
        trackEl.remove();
        nt.addEventListener('load', () => {
            for (const tr of video.textTracks) tr.mode = tr === nt.track ? 'showing' : 'disabled';
        });
        return true;
    } catch (e) {
        return false;
    }
}

function showingTrackEl(video) {
    const trs = video.textTracks || [];
    for (let i = 0; i < trs.length; i++) {
        if (trs[i].mode === 'showing') {
            const els = video.querySelectorAll('track');
            return els[i] || null;
        }
    }
    return null;
}

function autoFitOffset(video, textTrack) {
    if (!video || !video.duration || !textTrack || !textTrack.cues || !textTrack.cues.length) return 0;
    const cues = Array.from(textTrack.cues);
    const first = cues[0].startTime;
    const delta = video.duration - cues[cues.length - 1].endTime;
    if (first < 15 && delta > 20 && delta < 300) return Math.round(delta);
    return 0;
}

function autoEnableArabic(video) {
    if (!video || window._pcSubsPicked) return;
    const trs = video.textTracks || [];
    for (let i = 0; i < trs.length; i++) {
        const tr = trs[i];
        if (tr.kind !== 'subtitles' && tr.kind !== 'captions') continue;
        if (!/ar|عرب|arab/i.test(tr.label || '')) continue;
        for (const t of trs) t.mode = 'disabled';
        tr.mode = 'showing';
        return;
    }
}

function injectSubtitleTrack(video, url, label) {
    window._pcSubsPicked = true;
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
    return track;
}

async function loadArabicSubs(video, force) {
    if (!video || !video.isConnected) return;
    if (!force && (window._pcSubsPicked || window._pcArabicTried)) return;
    window._pcArabicTried = true;
    const q = typeof vidsrcQueryFromWatch === 'function' ? vidsrcQueryFromWatch() : null;
    if (!q || !q.q) return;
    const toast = (msg) => { if (typeof showToast === 'function') showToast(msg); };
    try {
        const res = await fetch(`api/subs.php?q=${encodeURIComponent(q.q)}&type=${q.type}${q.season ? '&season=' + q.season + '&episode=' + q.episode : ''}`);
        const data = await res.json();
        if (!data.ok || !data.url || !video.isConnected) {
            if (force) toast(t('subs_arabic_error') || 'No Arabic subtitle found');
            return;
        }
        if (window._pcSubsPicked) return;
        for (const tr of video.textTracks || []) if (tr.mode === 'showing') return;
        const track = document.createElement('track');
        track.kind = 'subtitles';
        track.label = 'العربية';
        track.srclang = 'ar';
        track.src = data.url;
        video.appendChild(track);
        track.addEventListener('load', () => {
            if (window._pcSubsPicked) return;
            for (const t of video.textTracks) t.mode = t === track.track ? 'showing' : 'disabled';
            const hadSaved = subOffsetValue() !== 0;
            setTimeout(async () => {
                if (!video.isConnected) return;
                const off = hadSaved ? subOffsetValue() : autoFitOffset(video, track.track);
                if (!off) return;
                const applied = await applyTrackOffset(video, track, Math.round(off * 1000));
                if (applied) {
                    saveSubOffset(off);
                    toast(t('subs_synced') + ' ' + (off > 0 ? '+' : '') + off.toFixed(1) + 's');
                }
            }, 400);
        });
        track.addEventListener('error', () => {
            if (force) toast(t('subs_arabic_error') || 'No Arabic subtitle found');
        });
    } catch (e) {
        if (force) toast(t('subs_arabic_error') || 'No Arabic subtitle found');
    }
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