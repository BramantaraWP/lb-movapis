<?php
header("Content-Type: application/json; charset=utf-8");

if (!isset($_GET['q'])) {
    echo json_encode(["error" => "query required"]);
    exit;
}

$q = urlencode($_GET['q']);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// fetch html
$html = file_get_contents("https://www.youtube.com/results?search_query=$q");

// ambil semua data video
preg_match_all(
    '/"videoId":"(.*?)".*?"title":\{"runs":\[\{"text":"(.*?)"\}\]\}.*?"longBylineText":\{"runs":\[\{"text":"(.*?)"\}\]/s',
    $html,
    $matches,
    PREG_SET_ORDER
);

$results = [];
$used = [];
$i = 0;

foreach ($matches as $m) {
    if (isset($used[$m[1]])) continue;
    $used[$m[1]] = true;

    if ($i++ < $offset) continue;
    if (count($results) >= $perPage) break;

    $videoId = $m[1];

    $results[] = [
        "videoId"   => $videoId,
        "title"     => html_entity_decode($m[2]),
        "channel"   => html_entity_decode($m[3]),
        "thumbnail" => "https://i.ytimg.com/vi/$videoId/hqdefault.jpg"
    ];
}

echo json_encode([
    "query" => urldecode($q),
    "page" => $page,
    "count" => count($results),
    "results" => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
