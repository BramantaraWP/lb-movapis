<?php
// File ini untuk streaming video
$base_source = 'http://103.145.232.246/Data/movies/';
$file = isset($_GET['file']) ? urldecode($_GET['file']) : '';

if (empty($file)) {
    die('File not specified');
}

// Validasi file (hanya izinkan file video)
$allowed_ext = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'mpg', 'mpeg', '3gp'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    die('Invalid file type');
}

// URL video asli
$video_url = $base_source . $file;

// Setup streaming
header('Content-Type: video/' . $ext);
header('Cache-Control: max-age=31536000, public');
header('Accept-Ranges: bytes');

// Forward request ke server asli
$ch = curl_init($video_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
    // Forward headers tertentu
    $headers_to_forward = ['content-type', 'content-length', 'accept-ranges'];
    $parts = explode(':', $header, 2);
    
    if (count($parts) == 2) {
        $name = strtolower(trim($parts[0]));
        if (in_array($name, $headers_to_forward)) {
            header($name . ': ' . trim($parts[1]));
        }
    }
    return strlen($header);
});

curl_exec($ch);
curl_close($ch);
?>
