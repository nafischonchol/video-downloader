<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Video Downloader – Facebook &amp; TikTok</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1877f2 0%, #fe2c55 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
        }

        .card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 560px;
        }

        .logo-area {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #1877f2 0%, #fe2c55 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }

        .logo-icon svg {
            width: 36px;
            height: 36px;
            fill: #fff;
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 0.25rem;
        }

        .subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            margin: 0;
        }

        .input-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.75rem;
        }

        .url-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }

        .url-input:focus {
            border-color: #1877f2;
        }

        .url-input.error {
            border-color: #ef4444;
        }

        .btn-fetch {
            padding: 0.75rem 1.4rem;
            background: #1877f2;
            color: #fff;
            border: none;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            white-space: nowrap;
        }

        .btn-fetch:hover {
            background: #1565d8;
        }

        .btn-fetch:active {
            transform: scale(0.98);
        }

        .btn-fetch:disabled {
            background: #93c5fd;
            cursor: not-allowed;
        }

        .error-msg {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: none;
        }

        .error-msg.visible {
            display: block;
        }

        .spinner {
            display: none;
            text-align: center;
            margin-top: 1.5rem;
        }

        .spinner.visible {
            display: block;
        }

        .spinner-ring {
            width: 40px;
            height: 40px;
            border: 4px solid #e5e7eb;
            border-top-color: #1877f2;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .result-card {
            display: none;
            margin-top: 1.75rem;
            background: #f8faff;
            border: 1px solid #dbeafe;
            border-radius: 1rem;
            padding: 1.25rem;
        }

        .result-card.visible {
            display: block;
        }

        .video-info {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .video-thumbnail {
            width: 96px;
            height: 64px;
            object-fit: cover;
            border-radius: 0.5rem;
            flex-shrink: 0;
            background: #e5e7eb;
        }

        .video-thumbnail-placeholder {
            width: 96px;
            height: 64px;
            background: #dbeafe;
            border-radius: 0.5rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-thumbnail-placeholder svg {
            width: 32px;
            height: 32px;
            fill: #1877f2;
            opacity: 0.5;
        }

        .video-title {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1a1a2e;
            line-height: 1.4;
            word-break: break-word;
        }

        .download-btns {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-download {
            flex: 1;
            min-width: 120px;
            padding: 0.7rem 1rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: background 0.2s, transform 0.1s;
            text-decoration: none;
        }

        .btn-download:active {
            transform: scale(0.98);
        }

        .btn-hd {
            background: #1877f2;
            color: #fff;
        }

        .btn-hd:hover {
            background: #1565d8;
        }

        .btn-sd {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-sd:hover {
            background: #d1d5db;
        }

        .btn-download.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .quality-badge {
            font-size: 0.7rem;
            padding: 0.1rem 0.4rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.25);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hint {
            text-align: center;
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 1.25rem;
        }

        .downloading-msg {
            display: none;
            text-align: center;
            font-size: 0.875rem;
            color: #1877f2;
            margin-top: 0.75rem;
            font-weight: 500;
        }

        .downloading-msg.visible {
            display: block;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-area">
            <div class="logo-icon">
                <!-- Play / download icon -->
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 16l-5-5 1.41-1.41L11 13.17V4h2v9.17l2.59-2.58L17 11zm-7 4h14v2H5z"/>
                </svg>
            </div>
            <h1>Video Downloader</h1>
            <p class="subtitle">Download Facebook &amp; TikTok videos in HD or SD</p>
        </div>

        <div class="input-group">
            <input
                type="url"
                id="videoUrl"
                class="url-input"
                placeholder="https://www.facebook.com/watch?v=… or https://www.tiktok.com/@user/video/…"
                autocomplete="off"
                spellcheck="false"
            >
            <button id="fetchBtn" class="btn-fetch" onclick="fetchVideoInfo()">Fetch</button>
        </div>
        <p id="errorMsg" class="error-msg"></p>

        <div id="spinner" class="spinner">
            <div class="spinner-ring"></div>
            <p style="color:#6b7280;font-size:0.875rem;margin-top:0.5rem;">Fetching video info…</p>
        </div>

        <div id="resultCard" class="result-card">
            <div class="video-info">
                <div id="thumbnailContainer"></div>
                <p id="videoTitle" class="video-title"></p>
            </div>
            <div class="download-btns">
                <button id="hdBtn" class="btn-download btn-hd" onclick="triggerDownload('hd')">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 16l-5-5 1.41-1.41L11 13.17V4h2v9.17l2.59-2.58L17 11zm-7 4h14v2H5z"/>
                    </svg>
                    HD <span class="quality-badge">High</span>
                </button>
                <button id="sdBtn" class="btn-download btn-sd" onclick="triggerDownload('sd')">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 16l-5-5 1.41-1.41L11 13.17V4h2v9.17l2.59-2.58L17 11zm-7 4h14v2H5z"/>
                    </svg>
                    SD <span class="quality-badge">Low</span>
                </button>
            </div>
            <p id="downloadingMsg" class="downloading-msg">⏬ Download started…</p>
        </div>

        <p class="hint">Only works with public Facebook and TikTok videos</p>
    </div>

    <script>
        let videoData = null;

        function setError(msg) {
            const el = document.getElementById('errorMsg');
            const input = document.getElementById('videoUrl');
            el.textContent = msg;
            el.classList.toggle('visible', !!msg);
            input.classList.toggle('error', !!msg);
        }

        function setSpinner(show) {
            document.getElementById('spinner').classList.toggle('visible', show);
        }

        function setResult(data) {
            videoData = data;

            // Title
            const defaultTitle = data.platform === 'tiktok' ? 'TikTok Video' : 'Facebook Video';
            document.getElementById('videoTitle').textContent = data.title || defaultTitle;

            // Thumbnail - use DOM methods to avoid XSS
            const container = document.getElementById('thumbnailContainer');
            container.innerHTML = '';
            if (data.thumbnail) {
                const img = document.createElement('img');
                img.src = data.thumbnail;
                img.className = 'video-thumbnail';
                img.alt = 'Thumbnail';
                img.addEventListener('error', function () {
                    container.innerHTML = '';
                    container.appendChild(buildPlaceholder());
                });
                container.appendChild(img);
            } else {
                container.appendChild(buildPlaceholder());
            }

            // Buttons
            const hdBtn = document.getElementById('hdBtn');
            const sdBtn = document.getElementById('sdBtn');
            hdBtn.classList.toggle('disabled', !data.hd);
            sdBtn.classList.toggle('disabled', !data.sd);

            document.getElementById('downloadingMsg').classList.remove('visible');
            document.getElementById('resultCard').classList.add('visible');
        }

        function buildPlaceholder() {
            const div = document.createElement('div');
            div.className = 'video-thumbnail-placeholder';
            div.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/></svg>';
            return div;
        }

        async function fetchVideoInfo() {
            const url = document.getElementById('videoUrl').value.trim();
            setError('');
            document.getElementById('resultCard').classList.remove('visible');
            document.getElementById('fetchBtn').disabled = true;

            if (!url) {
                setError('Please enter a Facebook or TikTok video URL.');
                document.getElementById('fetchBtn').disabled = false;
                return;
            }

            setSpinner(true);

            try {
                const res = await fetch('{{ route('video.fetch') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ url }),
                });

                const data = await res.json();

                if (!res.ok) {
                    setError(data.error || 'Something went wrong. Please try again.');
                } else {
                    setResult(data);
                }
            } catch (e) {
                setError('Network error. Please check your connection and try again.');
            } finally {
                setSpinner(false);
                document.getElementById('fetchBtn').disabled = false;
            }
        }

        async function triggerDownload(quality) {
            if (!videoData) return;
            const videoUrl = quality === 'hd' ? videoData.hd : videoData.sd;
            if (!videoUrl) return;

            document.getElementById('downloadingMsg').classList.add('visible');

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('video.download') }}';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;

            const urlInput = document.createElement('input');
            urlInput.type = 'hidden';
            urlInput.name = 'video_url';
            urlInput.value = videoUrl;

            const qualityInput = document.createElement('input');
            qualityInput.type = 'hidden';
            qualityInput.name = 'quality';
            qualityInput.value = quality;

            const platformInput = document.createElement('input');
            platformInput.type = 'hidden';
            platformInput.name = 'platform';
            platformInput.value = videoData.platform || 'facebook';

            form.appendChild(csrfInput);
            form.appendChild(urlInput);
            form.appendChild(qualityInput);
            form.appendChild(platformInput);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Allow pressing Enter to fetch
        document.getElementById('videoUrl').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') fetchVideoInfo();
        });
    </script>
</body>
</html>
