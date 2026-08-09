let hlsInstance = null;

function initPlayer(streamUrl, streamType, containerId) {
    destroyPlayer();
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';

    if (streamType === 'iframe') {
        const iframe = document.createElement('iframe');
        iframe.src = streamUrl;
        iframe.className = 'w-full rounded-lg bg-black';
        iframe.style.width = '100%';
        iframe.style.height = '70vh';
        iframe.style.border = 'none';
        iframe.allow = 'autoplay; fullscreen; picture-in-picture';
        iframe.allowFullscreen = true;
        container.appendChild(iframe);
        return;
    }

    const video = document.createElement('video');
    video.id = 'videoPlayer';
    video.controls = true;
    video.autoplay = true;
    video.playsInline = true;
    video.className = 'w-full rounded-lg bg-black';
    video.style.maxHeight = '70vh';
    container.appendChild(video);

    if (streamType === 'hls' || streamUrl.includes('.m3u8')) {
        if (Hls.isSupported()) {
            hlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 30,
                maxMaxBufferLength: 600,
                startFragPrefetch: true,
            });
            hlsInstance.loadSource(streamUrl);
            hlsInstance.attachMedia(video);
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                video.play().catch(() => {});
                addQualitySelector(hlsInstance, video);
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
    }
}

function addQualitySelector(hls, video) {
    if (!hls.levels || hls.levels.length <= 1) return;
    const controls = video.parentElement;
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
    controls.style.position = 'relative';
    controls.appendChild(btn);
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
