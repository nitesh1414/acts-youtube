<?php
/**
 * YouTube URL helpers.
 */

/**
 * Extract an 11-character YouTube video id from any common URL shape.
 *
 * @param  string|null $url
 * @return string|null
 */
function youtube_id($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    // A bare video id is accepted as well.
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }

    $patterns = [
        '#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|v/|shorts/|live/|e/))([A-Za-z0-9_-]{11})#i',
        '#youtu\.be/([A-Za-z0-9_-]{11})#i',
        '#youtube-nocookie\.com/embed/([A-Za-z0-9_-]{11})#i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }

    // Last resort: parse the query string of a full URL.
    $query = parse_url($url, PHP_URL_QUERY);
    if (is_string($query)) {
        parse_str($query, $params);
        if (!empty($params['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $params['v'])) {
            return (string) $params['v'];
        }
    }

    return null;
}

/**
 * Privacy-friendly embed URL (youtube-nocookie) for a video link.
 */
function youtube_embed_url($url, $autoplay = false)
{
    $id = youtube_id($url);
    if ($id === null) {
        return '';
    }
    $params = [
        'rel'            => '0',
        'modestbranding' => '1',
        'playsinline'    => '1',
    ];
    if ($autoplay) {
        $params['autoplay'] = '1';
    }
    return 'https://www.youtube-nocookie.com/embed/' . $id . '?' . http_build_query($params);
}

/**
 * Thumbnail URL for a video link.
 */
function youtube_thumbnail($url, $quality = 'hqdefault')
{
    $id = youtube_id($url);
    return $id === null ? '' : 'https://i.ytimg.com/vi/' . $id . '/' . $quality . '.jpg';
}

/**
 * Watch URL for a video link.
 */
function youtube_watch_url($url)
{
    $id = youtube_id($url);
    return $id === null ? '' : 'https://www.youtube.com/watch?v=' . $id;
}
