<?php
// url_check.php

// Check if cURL is installed
if (!function_exists('curl_init')) {
    die("<h2>Error:</h2><p>PHP cURL extension is not enabled. Please enable it to use this script.</p>");
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

function checkUrl($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['Error', "cURL error: $error"];
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    return [$status, $finalUrl];
}

function tryCheckUrl($url) {
    // Try HTTPS first if scheme is missing or HTTP
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    if (!validateUrl($url)) {
        return ['Error', 'Invalid URL format'];
    }

    list($status, $finalUrl) = checkUrl($url);

    // If HTTPS fails, try HTTP fallback (only if originally missing scheme or HTTPS)
    if (($status === 'Error' || $status >= 400) && strpos($url, 'https://') === 0) {
        $httpUrl = 'http://' . substr($url, 8);
        if (validateUrl($httpUrl)) {
            list($status2, $finalUrl2) = checkUrl($httpUrl);
            if ($status2 !== 'Error') {
                return [$status2, $finalUrl2];
            }
        }
    }

    return [$status, $finalUrl];
}

$results = [];
$errorMessage = '';
$maxUrls = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['urls'] ?? ''))) {
        $errorMessage = 'Please enter at least one URL.';
    } else {
        $inputUrls = preg_split("/\r\n|\n|\r/", trim($_POST['urls']));
        $inputUrls = array_filter(array_map('trim', $inputUrls)); // Clean empty lines

        if (count($inputUrls) > $maxUrls) {
            $errorMessage = "You can check up to $maxUrls URLs at a time. Please reduce your input.";
        } else {
            foreach ($inputUrls as $url) {
                $results[$url] = tryCheckUrl($url);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>URL HTTP Status & Redirect Checker</title>
<style>
    body { font-family: Arial, sans-serif; margin: 2rem; background: #fafafa; color: #333; }
    textarea { width: 100%; height: 150px; font-family: monospace; font-size: 14px; padding: 8px; box-sizing: border-box; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; background: #fff; }
    th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: left; }
    th { background-color: #f0f0f0; }
    .error { color: red; margin-top: 1rem; }
    button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
    h1 { margin-bottom: 0.5rem; }
</style>
</head>
<body>

<h1>URL HTTP Status & Redirect Checker</h1>

<form method="post" action="">
    <label for="urls">Enter URLs (one per line):</label><br />
    <textarea id="urls" name="urls" placeholder="https://example.com"></textarea><br />
    <button type="submit">Check URLs</button>
</form>

<?php if ($errorMessage): ?>
    <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>

<?php if ($results): ?>
    <h2>Results</h2>
    <table>
        <thead>
            <tr>
                <th>Original URL</th>
                <th>HTTP Status</th>
                <th>Final URL or Error</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $url => $result): ?>
                <tr>
                    <td><?= htmlspecialchars($url) ?></td>
                    <td><?= htmlspecialchars($result[0]) ?></td>
                    <td><?= htmlspecialchars($result[1]) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>