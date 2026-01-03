<?php
// =================== KONFIGURASI ===================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Base URL website film
$base_url = 'http://103.145.232.246/Data/movies/';

// Ekstensi video yang dicari
$video_extensions = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'mpg', 'mpeg', '3gp'];

// Parameter pencarian
$search = isset($_GET['s']) ? trim(urldecode($_GET['s'])) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;

// =================== FUNGSI UTAMA ===================

/**
 * Fungsi utama untuk mencari film
 */
function searchMovieEverywhere($base_url, $search, $extensions) {
    $result = [
        'found' => false,
        'movie' => null,
        'searched_folders' => 0,
        'total_files_checked' => 0
    ];
    
    // Jika tidak ada search query, tampilkan error
    if (empty($search)) {
        return [
            'status' => 'error',
            'message' => 'Please provide search query (?s=moviename)'
        ];
    }
    
    echo "🔍 Searching for: \"$search\"\n";
    
    // Mulai pencarian dari base_url
    $folders_to_search = [$base_url];
    $folders_searched = [];
    $files_checked = 0;
    
    while (!empty($folders_to_search)) {
        $current_folder = array_shift($folders_to_search);
        
        // Skip jika folder sudah dicari
        if (in_array($current_folder, $folders_searched)) {
            continue;
        }
        
        echo "📂 Searching in: " . $current_folder . "\n";
        $folders_searched[] = $current_folder;
        $result['searched_folders']++;
        
        // Ambil konten folder
        $html = fetchHTML($current_folder);
        if (!$html) {
            continue;
        }
        
        // Parse semua link di folder
        $links = parseLinks($html, $current_folder);
        
        foreach ($links as $link) {
            $files_checked++;
            $result['total_files_checked']++;
            
            // Cek apakah ini file video
            $ext = strtolower(pathinfo($link['href'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $extensions)) {
                // Cek apakah nama file mengandung kata pencarian
                $filename = pathinfo($link['href'], PATHINFO_FILENAME);
                $full_path = $link['full_url'];
                
                if (stripos($filename, $search) !== false || 
                    stripos($link['text'], $search) !== false) {
                    
                    echo "✅ FOUND: " . $filename . "\n";
                    
                    $result['found'] = true;
                    $result['movie'] = [
                        'Title' => formatTitle($filename),
                        'Video' => $full_path,
                        'thumbnail' => generateThumbnail($filename),
                        'filename' => basename($full_path),
                        'extension' => $ext,
                        'folder' => $current_folder,
                        'match_type' => 'filename'
                    ];
                    
                    // Hentikan pencarian jika sudah ditemukan
                    return buildResponse($result, $search);
                }
                
                // Coba download dan scan metadata jika perlu (opsional)
                // $metadata = checkVideoMetadata($full_path, $search);
                // if ($metadata['contains']) {
                //     $result['found'] = true;
                //     $result['movie'] = $metadata;
                //     return buildResponse($result, $search);
                // }
            }
            
            // Jika ini adalah folder/directory, tambahkan ke list pencarian
            elseif (isDirectoryLink($link['href'])) {
                $new_folder = $current_folder . $link['href'];
                if (!in_array($new_folder, $folders_to_search) && 
                    !in_array($new_folder, $folders_searched)) {
                    $folders_to_search[] = $new_folder;
                }
            }
            
            // Batasi jumlah file yang dicek (prevent infinite)
            if ($files_checked > 1000) {
                echo "⚠️  Limit reached: Checked 1000 files\n";
                return buildResponse($result, $search);
            }
        }
    }
    
    // Jika sampai sini, film tidak ditemukan
    return buildResponse($result, $search);
}

/**
 * Fetch HTML dari URL
 */
function fetchHTML($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FAILONERROR => true
    ]);
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && !empty($html)) {
        return $html;
    }
    
    return false;
}

/**
 * Parse semua link dari HTML
 */
function parseLinks($html, $base_path) {
    $links = [];
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    
    $anchor_tags = $dom->getElementsByTagName('a');
    
    foreach ($anchor_tags as $tag) {
        $href = $tag->getAttribute('href');
        $text = trim($tag->textContent);
        
        // Skip parent directory
        if ($href == '../' || $href == '..' || empty($href)) {
            continue;
        }
        
        // Skip query strings atau anchor links
        if (strpos($href, '?') !== false || strpos($href, '#') !== false) {
            continue;
        }
        
        // Buat full URL
        if (strpos($href, 'http') === 0) {
            $full_url = $href;
        } else {
            $full_url = rtrim($base_path, '/') . '/' . $href;
        }
        
        $links[] = [
            'href' => $href,
            'text' => $text,
            'full_url' => $full_url
        ];
    }
    
    return $links;
}

/**
 * Cek apakah link adalah directory
 */
function isDirectoryLink($href) {
    // Directory biasanya tidak punya extension dan berakhir dengan slash
    $ext = pathinfo($href, PATHINFO_EXTENSION);
    return empty($ext) && $href != '../' && $href != '..';
}

/**
 * Format judul yang lebih rapi
 */
function formatTitle($filename) {
    $title = $filename;
    
    // Ganti underscore, dash dengan spasi
    $title = str_replace(['_', '-', '.', '+'], ' ', $title);
    
    // Hapus kualitas video (720p, 1080p, dll)
    $title = preg_replace('/\b(720p|1080p|2160p|4k|hd|fhd|uhd|bluray|webrip|brrip|dvdrip|x264|x265|hevc|aac|ac3)\b/i', '', $title);
    
    // Hapus tahun dalam kurung
    $title = preg_replace('/\((\d{4})\)/', '', $title);
    
    // Hapus multiple spaces
    $title = preg_replace('/\s+/', ' ', $title);
    
    // Capitalize words
    $title = ucwords(trim($title));
    
    return $title;
}

/**
 * Generate thumbnail URL
 */
function generateThumbnail($title) {
    // Gunakan placeholder atau API thumbnail
    $encoded_title = urlencode(substr($title, 0, 30));
    return "https://via.placeholder.com/300x450/1a1a1a/ffffff?text=" . $encoded_title;
}

/**
 * Build response JSON
 */
function buildResponse($result, $search) {
    if ($result['found'] && $result['movie']) {
        return [
            'status' => 'success',
            'search_query' => $search,
            'found' => true,
            'movie' => $result['movie'],
            'search_stats' => [
                'folders_searched' => $result['searched_folders'],
                'files_checked' => $result['total_files_checked']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } else {
        return [
            'status' => 'success',
            'search_query' => $search,
            'found' => false,
            'message' => 'Movie Not Found🙁',
            'search_stats' => [
                'folders_searched' => $result['searched_folders'],
                'files_checked' => $result['total_files_checked']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Fungsi untuk list semua film dalam folder (tanpa search)
 */
function listAllMovies($base_url, $page, $limit, $extensions) {
    $movies = [];
    $folders_to_search = [$base_url];
    $folders_searched = [];
    $all_videos = [];
    
    // Batasi hanya 3 folder pertama untuk performance
    $max_folders = 3;
    
    while (!empty($folders_to_search) && count($folders_searched) < $max_folders) {
        $current_folder = array_shift($folders_to_search);
        
        if (in_array($current_folder, $folders_searched)) {
            continue;
        }
        
        $folders_searched[] = $current_folder;
        $html = fetchHTML($current_folder);
        
        if (!$html) {
            continue;
        }
        
        $links = parseLinks($html, $current_folder);
        
        foreach ($links as $link) {
            $ext = strtolower(pathinfo($link['href'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $extensions)) {
                $filename = pathinfo($link['href'], PATHINFO_FILENAME);
                $all_videos[] = [
                    'Title' => formatTitle($filename),
                    'Video' => $link['full_url'],
                    'thumbnail' => generateThumbnail($filename),
                    'filename' => basename($link['full_url']),
                    'extension' => $ext,
                    'folder' => $current_folder
                ];
            }
        }
    }
    
    // Pagination
    $total = count($all_videos);
    $total_pages = ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    $paginated = array_slice($all_videos, $offset, $limit);
    
    return [
        'status' => 'success',
        'action' => 'list',
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $total_pages,
        'movies' => $paginated,
        'folders_searched' => count($folders_searched)
    ];
}

// =================== LOGIC UTAMA ===================

try {
    // Tentukan action berdasarkan parameter
    if (!empty($search)) {
        // Mode SEARCH - cari film spesifik
        $response = searchMovieEverywhere($base_url, $search, $video_extensions);
    } else {
        // Mode LIST - tampilkan semua film
        $response = listAllMovies($base_url, $page, $limit, $video_extensions);
    }
    
    // Output JSON
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
