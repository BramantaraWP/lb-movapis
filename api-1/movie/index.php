<?php
// =================== KONFIGURASI ===================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// File yang berisi list movie
$movie_files = [
    '/movie/text/list/movie1.txt',
    '/movie/text/list/movie2.txt', 
    '/movie/text/list/movie3.txt',
    '/movie/text/list/movies.txt',
    '/movie/text/list/movie.txt',
    '/movie/text/list/film.txt',
    '/movie/text/list/video.txt'
];

// Parameter
$search = isset($_GET['s']) ? trim(urldecode($_GET['s'])) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

// Ekstensi video
$video_extensions = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'mpg', 'mpeg', '3gp'];

// =================== FUNGSI UTAMA ===================

/**
 * Main function
 */
function handleRequest($movie_files, $search, $page, $limit, $extensions) {
    // Cari file movie yang ada
    $available_files = [];
    foreach ($movie_files as $file) {
        if (file_exists($file)) {
            $available_files[] = $file;
        }
    }
    
    if (empty($available_files)) {
        return [
            'status' => 'error',
            'message' => 'No movie files found. Please create movie1.txt, movie2.txt, etc.',
            'files_checked' => $movie_files
        ];
    }
    
    // Baca semua link dari file
    $all_links = [];
    foreach ($available_files as $file) {
        $links = readMovieFile($file);
        $all_links = array_merge($all_links, $links);
    }
    
    // Filter hanya link video
    $video_links = filterVideoLinks($all_links, $extensions);
    
    // Jika ada search query
    if (!empty($search)) {
        return searchMovies($video_links, $search);
    }
    
    // Jika tidak ada search, tampilkan semua dengan pagination
    return listMovies($video_links, $page, $limit);
}

/**
 * Baca file movie.txt
 */
function readMovieFile($filename) {
    $links = [];
    
    if (!file_exists($filename)) {
        return $links;
    }
    
    $content = file_get_contents($filename);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip komentar dan line kosong
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Validasi URL
        if (filter_var($line, FILTER_VALIDATE_URL)) {
            $links[] = [
                'url' => $line,
                'filename' => basename($line),
                'source_file' => $filename
            ];
        }
    }
    
    return $links;
}

/**
 * Filter hanya link video
 */
function filterVideoLinks($links, $extensions) {
    $video_links = [];
    
    foreach ($links as $link) {
        $ext = strtolower(pathinfo($link['url'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $extensions)) {
            $video_links[] = $link;
        }
    }
    
    return $video_links;
}

/**
 * Cari film berdasarkan keyword
 */
function searchMovies($video_links, $search) {
    $found_movies = [];
    $search_lower = strtolower($search);
    
    foreach ($video_links as $link) {
        $filename = $link['filename'];
        $filename_lower = strtolower($filename);
        
        // Cek apakah filename mengandung kata search
        if (strpos($filename_lower, $search_lower) !== false) {
            $found_movies[] = formatMovieData($link);
        }
    }
    
    if (empty($found_movies)) {
        return [
            'status' => 'success',
            'search_query' => $search,
            'found' => false,
            'message' => 'Movie Not Found🙁',
            'total_checked' => count($video_links),
            'results' => 0
        ];
    }
    
    return [
        'status' => 'success',
        'search_query' => $search,
        'found' => true,
        'results' => count($found_movies),
        'data' => $found_movies
    ];
}

/**
 * List semua film dengan pagination
 */
function listMovies($video_links, $page, $limit) {
    $total = count($video_links);
    $total_pages = ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    
    // Paginate
    $paginated_links = array_slice($video_links, $offset, $limit);
    
    // Format data
    $movies = [];
    foreach ($paginated_links as $link) {
        $movies[] = formatMovieData($link);
    }
    
    return [
        'status' => 'success',
        'action' => 'list',
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $total_pages,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'data' => $movies
    ];
}

/**
 * Format data movie
 */
function formatMovieData($link) {
    $url = $link['url'];
    $filename = $link['filename'];
    $title = formatTitle($filename);
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    
    return [
        'Title' => $title,
        'Video' => $url,
        'thumbnail' => generateThumbnail($title),
        'filename' => $filename,
        'extension' => $extension,
        'source_file' => $link['source_file'],
        'quality' => detectQuality($filename),
        'size' => 'N/A'
    ];
}

/**
 * Format judul dari filename
 */
function formatTitle($filename) {
    // Hapus extension
    $title = pathinfo($filename, PATHINFO_FILENAME);
    
    // Ganti separator dengan spasi
    $title = str_replace(['_', '-', '.', '+'], ' ', $title);
    
    // Hapus kualitas video
    $title = preg_replace('/\b(720p|1080p|2160p|4k|hd|fhd|uhd|bluray|webrip|brrip|dvdrip|x264|x265|hevc|aac|ac3)\b/i', '', $title);
    
    // Hapus tahun
    $title = preg_replace('/\((\d{4})\)/', '', $title);
    
    // Hapus multiple spaces
    $title = preg_replace('/\s+/', ' ', $title);
    
    // Capitalize
    $title = ucwords(trim($title));
    
    return $title;
}

/**
 * Generate thumbnail
 */
function generateThumbnail($title) {
    $short_title = substr($title, 0, 30);
    $encoded = urlencode($short_title);
    return "https://via.placeholder.com/300x450/1a1a1a/ffffff?text=" . $encoded;
}

/**
 * Detect quality
 */
function detectQuality($filename) {
    $patterns = [
        '2160p' => '4K',
        '1080p' => 'Full HD', 
        '720p' => 'HD',
        '480p' => 'SD'
    ];
    
    foreach ($patterns as $pattern => $quality) {
        if (stripos($filename, $pattern) !== false) {
            return $quality;
        }
    }
    
    return 'Unknown';
}

// =================== EXECUTE ===================

try {
    $response = handleRequest($movie_files, $search, $page, $limit, $video_extensions);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
