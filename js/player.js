let hlsInstance = null;

function initPlayer(streamUrl, streamType, containerId) {
    destroyPlayer();
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';
    addFullscreenButton(container);

    if (streamType === 'iframe') {
        const iframe = document.createElement('iframe');
        iframe.src = streamUrl;
        iframe.className = 'stream-frame';
        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('mozallowfullscreen', 'true');
        iframe.setAttribute('webkitallowfullscreen', 'true');
        container.appendChild(iframe);

        container.ondblclick = (e) => {
            e.preventDefault();
            toggleFullscreen(iframe);
        };
        addSubtitleButton(container, true);
        return;
    }

    const video = document.createElement('video');
    video.id = 'videoPlayer';
    video.controls = true;
    video.autoplay = true;
    video.playsInline = true;
    video.className = 'stream-video';
    video.ondblclick = (e) => {
        e.preventDefault();
        toggleFullscreen(video);
    };
    container.appendChild(video);

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
                addQualitySelector(hlsInstance, video);
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

function addQualitySelector(hls, video) {
    if (!hls.levels || hls.levels.length <= 1) return;
    const parent = video.parentElement;
    parent.style.position = 'relative';
    const btn = document.createElement('button');
    btn.className = 'absolute top-2 right-2 bg-black/70 text-white text-xs px-3 py-1.5 rounded-lg border border-white/20 hover:bg-red-600 transition z-10';
    btn.textContent = 'Auto';
    btn.onclick = () => {
        const levels = hls.levels;
        const current = hls.currentLevel;
        const next = current + 1;
        if (next >= levels.length) {
            hls.currentLevel = -1;
            btn.textContent = 'Auto';
        } else {
            hls.currentLevel = next;
            btn.textContent = levels[next].height + 'p';
        }
    };
    parent.appendChild(btn);
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
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const media = container.querySelector('video') || container.querySelector('iframe');
        if (media) toggleFullscreen(media);
    });
    container.appendChild(btn);
}

function buildSubtitlePanel(video, container) {
    if (container.querySelector('.sub-btn')) return;
    addSubtitleButton(container, false, video);
}

function addSubtitleButton(container, disabled, video) {
    const btn = document.createElement('button');
    btn.className = 'sub-btn' + (disabled ? ' sub-btn-disabled' : '');
    btn.title = disabled ? t('subs_unavailable') : 'Subtitles';
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4M13 15h4M7 11h2M11 11h6"/></svg>';
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (disabled || !video) return;
        openSubtitlePanel(video, container, btn);
    });
    container.appendChild(btn);
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
        if (!vtt || !video.isConnected) return;
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
