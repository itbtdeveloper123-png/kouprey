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
