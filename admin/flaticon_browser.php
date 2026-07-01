<?php
// admin/flaticon_browser.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    exit('Unauthorized');
}

$target = isset($_GET['url']) ? $_GET['url'] : 'https://www.flaticon.com/';

// Basic validation
if (strpos($target, 'http') !== 0) {
    $target = 'https://' . $target;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// Execute curl
$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    exit('Error loading website: ' . htmlspecialchars($error));
}

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

// Forward response content type header, but remove security block headers (framing blocks)
$header_lines = explode("\r\n", $headers);
foreach ($header_lines as $line) {
    if (empty($line)) continue;
    if (stripos($line, 'X-Frame-Options') === 0) continue;
    if (stripos($line, 'Content-Security-Policy') === 0) continue;
    if (stripos($line, 'Transfer-Encoding') === 0) continue;
    if (stripos($line, 'Content-Encoding') === 0) continue; // prevent double compression issues
    if (stripos($line, 'content-type:') === 0) {
        header($line);
    }
}

// Inject iframe click and form interceptor javascript
$js_interceptor = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Intercept clicks on links
    document.body.addEventListener('click', function(e) {
        var a = e.target.closest('a');
        if (a && a.href) {
            // If it is an external link, proxy it through our flaticon_browser.php
            if (a.href.indexOf('http') === 0) {
                e.preventDefault();
                window.location.href = 'flaticon_browser.php?url=' + encodeURIComponent(a.href);
            }
        }
    });

    // Intercept form submissions (e.g. search forms)
    document.body.addEventListener('submit', function(e) {
        var form = e.target;
        var actionUrl = form.action || window.location.href;
        
        // Convert relative action to absolute URL
        if (actionUrl.indexOf('http') !== 0) {
            var base = new URL(window.location.href);
            actionUrl = new URL(actionUrl, base.origin + base.pathname).href;
        }

        var method = (form.method || 'get').toLowerCase();
        if (method === 'get') {
            e.preventDefault();
            var params = new URLSearchParams(new FormData(form)).toString();
            window.location.href = 'flaticon_browser.php?url=' + encodeURIComponent(actionUrl + '?' + params);
        }
    });

    // ===== Hover to Copy Image Link Feature =====
    var style = document.createElement('style');
    style.innerHTML = `
        .flaticon-copy-btn {
            position: absolute !important;
            z-index: 2147483647 !important;
            background: #2563eb !important;
            color: #fff !important;
            border: none !important;
            padding: 6px 12px !important;
            border-radius: 20px !important;
            font-size: 11px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            display: none;
            align-items: center !important;
            gap: 4px !important;
            transition: all 0.2s ease !important;
            font-family: system-ui, -apple-system, sans-serif !important;
            pointer-events: auto !important;
        }
        .flaticon-copy-btn:hover {
            transform: scale(1.05) !important;
            background: #1d4ed8 !important;
        }
    `;
    document.head.appendChild(style);

    var copyBtn = document.createElement('button');
    copyBtn.className = 'flaticon-copy-btn';
    copyBtn.innerHTML = '📋 Copy Link';
    document.body.appendChild(copyBtn);

    var currentImg = null;

    document.addEventListener('mousemove', function(e) {
        var img = e.target.closest('img');
        if (img && img.src) {
            currentImg = img;
            var rect = img.getBoundingClientRect();
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

            copyBtn.style.top = (rect.top + scrollTop + 6) + 'px';
            copyBtn.style.left = (rect.left + scrollLeft + rect.width - 95) + 'px';
            copyBtn.style.display = 'flex';
        } else if (e.target !== copyBtn) {
            copyBtn.style.display = 'none';
        }
    });

    copyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (currentImg && currentImg.src) {
            var url = currentImg.src;
            
            navigator.clipboard.writeText(url).then(function() {
                copyBtn.innerHTML = '✅ Copied!';
                copyBtn.style.background = '#10b981';
                
                if (window.parent) {
                    var parentInput = window.parent.document.getElementById('imgUrl');
                    if (parentInput) {
                        parentInput.value = url;
                        parentInput.style.borderColor = '#10b981';
                        setTimeout(function() { parentInput.style.borderColor = ''; }, 1000);
                    }
                }

                setTimeout(function() {
                    copyBtn.innerHTML = '📋 Copy Link';
                    copyBtn.style.background = '#2563eb';
                    copyBtn.style.display = 'none';
                }, 1500);
            });
        }
    });
});
</script>
";

// Inject before </head> or </body>
if (strpos($body, '</head>') !== false) {
    $body = str_ireplace('</head>', $js_interceptor . '</head>', $body);
} else {
    $body = $js_interceptor . $body;
}

echo $body;
