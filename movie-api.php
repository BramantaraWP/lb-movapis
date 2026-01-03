<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Konfigurasi
$base_url = 'http://103.145.232.246/Data/movies/';
$your_domain = $_SERVER['HTTP_HOST']; // Domain saat ini
$search_query = isset($_GET['s']) ? trim($_GET['s']) : '';

// Fungsi untuk fetch HTML dari URL
function fetchHTML($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// Fungsi untuk extract video files
function extractMoviesFromHTML($html, $base_url) {
    $movies = [];
    $dom = new DOMDocument();
    
    @$dom->loadHTML($html);
    $links = $dom->getElementsByTagName('a');
    
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $text = trim($link->textContent);
        
        // Skip parent directory
        if ($href === '../' || $href === '..') continue;
        
        // Cek jika ini file video
        $video_extensions = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'mpg', 'mpeg', '3gp'];
        $extension = strtolower(pathinfo($href, PATHINFO_EXTENSION));
        
        if (in_array($extension, $video_extensions)) {
            $movies[] = [
                'filename' => $href,
                'name' => $text ?: $href,
                'extension' => $extension
            ];
        }
    }
    
    return $movies;
}

// Fungsi untuk generate thumbnail
function generateThumbnail($title) {
    // URL placeholder untuk thumbnail (bisa diganti dengan API thumbnail)
    $encoded_title = urlencode($title);
    return "https://via.placeholder.com/300x450/1a1a1a/ffffff?text=" . urlencode(substr($title, 0, 20));
}

// Fungsi untuk cari film
function searchMovies($movies, $query) {
    if (empty($query)) return $movies;
    
    $filtered = [];
    $query_lower = strtolower($query);
    
    foreach ($movies as $movie) {
        if (stripos($movie['name'], $query_lower) !== false || 
            stripos($movie['filename'], $query_lower) !== false) {
            $filtered[] = $movie;
        }
    }
    
    return $filtered;
}

// Main logic
try {
    // Fetch data dari source
    $html = fetchHTML($base_url);
    
    if (!$html) {
        throw new Exception("Failed to fetch data from source");
    }
    
    // Extract movies
    $movies = extractMoviesFromHTML($html, $base_url);
    
    // Apply search filter jika ada
    if (!empty($search_query)) {
        $movies = searchMovies($movies, $search_query);
    }
    
    // Format response
    $response = [];
    foreach ($movies as $movie) {
        // Generate title (hapus extension)
        $title = pathinfo($movie['name'], PATHINFO_FILENAME);
        $title = str_replace(['_', '-'], ' ', $title); // Ganti underscore/dash dengan spasi
        $title = ucwords($title); // Kapitalisasi setiap kata
        
        // Generate video URL (dibungkus dengan domain kita)
        $video_path = urlencode($movie['filename']);
        $video_url = "https://{$your_domain}/stream.php?file={$video_path}";
        
        // Generate thumbnail
        $thumbnail = generateThumbnail($title);
        
        $response[] = [
            'Title' => $title,
            'Video' => $video_url,
            'thumbnail' => $thumbnail,
            'filename' => $movie['filename'],
            'type' => $movie['extension']
        ];
    }
    
    // Output JSON
    echo json_encode([
        'success' => true,
        'total_results' => count($response),
        'search_query' => $search_query ?: 'all',
        'domain' => $your_domain,
        'data' => $response
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
