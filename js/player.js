let hlsInstance = null;

function initPlayer(streamUrl, streamType, containerId) {
    destroyPlayer();
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';

    if (streamType === 'iframe') {
        const iframe = document.createElement('iframe');
        iframe.src = streamUrl;
        iframe.style.cssText = 'width:100%;height:75vh;border:none;border-radius:8px;display:block;background:#000;';
        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('mozallowfullscreen', 'true');
        iframe.setAttribute('webkitallowfullscreen', 'true');
        container.appendChild(iframe);

        container.ondblclick = () => {
            if (document.fullscreenElement) document.exitFullscreen();
            else container.requestFullscreen().catch(() => {});
        };
        return;
    }

    const video = document.createElement('video');
    video.id = 'videoPlayer';
    video.controls = true;
    video.autoplay = true;
    video.playsInline = true;
    video.style.cssText = 'width:100%;max-height:75vh;background:#000;border-radius:8px;display:block;';
    container.appendChild(video);

    if (streamType === 'hls' || streamUrl.includes('.m3u8')) {
        if (Hls.isSupported()) {
            const hlsConfig = {
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 30,
                maxMaxBufferLength: 600,
                startFragPrefetch: true,
            };
            if (window.Installer) Object.assign(hlsConfig, Installer.hlsConfig());
            hlsInstance = new Hls(hlsConfig);
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
