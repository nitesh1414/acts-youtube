<?php
/**
 * The video library: videos, bundles and what each payment unlocks.
 *
 * A bundle is just a named group of videos sold at one price. Purchases are
 * snapshotted into `payment_videos`, so editing a bundle later never changes
 * what an existing customer can watch.
 */

final class Catalog
{
    /** @var DatabaseInterface */
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // -------------------------------------------------------------------------
    // Videos
    // -------------------------------------------------------------------------

    /**
     * @param  bool $activeOnly
     * @return array<int, array<string, mixed>>
     */
    public function videos($activeOnly = false)
    {
        $sql = 'SELECT * FROM videos';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $rows = $this->db->all($sql . ' ORDER BY position, id');
        return array_map([$this, 'decorateVideo'], $rows);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function video($id)
    {
        $row = $this->db->first('SELECT * FROM videos WHERE id = ? LIMIT 1', [(int) $id]);
        return is_array($row) ? $this->decorateVideo($row) : null;
    }

    /**
     * @param  array<string, mixed> $data
     * @return int
     */
    public function createVideo(array $data)
    {
        return (int) $this->db->insert('videos', $this->videoRow($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateVideo($id, array $data)
    {
        $this->db->update('videos', $this->videoRow($data), 'id = ?', [(int) $id]);
    }

    /**
     * Remove a video. Videos that somebody paid for are only hidden (their
     * entitlement must keep pointing at something), the rest are deleted.
     *
     * @return array{mode:string, bundles:int}
     */
    public function deleteVideo($id)
    {
        $id = (int) $id;
        $purchases = (int) $this->db->value('SELECT COUNT(*) FROM payment_videos WHERE video_id = ?', [$id], 0);
        $bundleLinks = (int) $this->db->value('SELECT COUNT(*) FROM bundle_items WHERE video_id = ?', [$id], 0);

        if ($purchases > 0) {
            $this->db->update('videos', ['is_active' => 0, 'updated_at' => now()], 'id = ?', [$id]);
            return ['mode' => 'hidden', 'bundles' => $bundleLinks];
        }

        $this->db->exec('DELETE FROM bundle_items WHERE video_id = ?', [$id]);
        $this->db->exec('DELETE FROM videos WHERE id = ?', [$id]);
        return ['mode' => 'deleted', 'bundles' => $bundleLinks];
    }

    /**
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decorateVideo(array $row)
    {
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['is_active'] = (int) $row['is_active'] === 1;
        $row['position'] = (int) $row['position'];
        $row['youtube_id'] = youtube_id((string) $row['youtube_url']);
        $row['embed_url'] = youtube_embed_url((string) $row['youtube_url']);
        $row['watch_url'] = youtube_watch_url((string) $row['youtube_url']);
        $row['thumbnail'] = youtube_thumbnail((string) $row['youtube_url'], 'hqdefault');
        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function videoRow(array $data)
    {
        return [
            'title'       => db_limit((string) $data['title'], 160),
            'description' => trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            'youtube_url' => db_limit((string) $data['youtube_url'], 255),
            'price'       => number_format(max(0, (float) $data['price']), 2, '.', ''),
            'position'    => (int) $data['position'],
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
            'updated_at'  => now(),
        ];
    }

    /**
     * How many people own this video.
     */
    public function videoSales($videoId)
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM payment_videos WHERE video_id = ?', [(int) $videoId], 0);
    }

    /**
     * How many bundles include this video.
     */
    public function videoBundleCount($videoId)
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM bundle_items WHERE video_id = ?', [(int) $videoId], 0);
    }

    // -------------------------------------------------------------------------
    // Bundles
    // -------------------------------------------------------------------------

    /**
     * Bundles with their videos attached (`videos`, `video_ids`, `single_total`).
     *
     * @param  bool $activeOnly
     * @return array<int, array<string, mixed>>
     */
    public function bundles($activeOnly = false)
    {
        $sql = 'SELECT * FROM bundles';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $bundles = $this->db->all($sql . ' ORDER BY position, id');
        if ($bundles === []) {
            return [];
        }

        $videos = [];
        foreach ($this->videos(false) as $video) {
            $videos[(int) $video['id']] = $video;
        }

        $links = $this->db->all('SELECT * FROM bundle_items ORDER BY position, id');
        $byBundle = [];
        foreach ($links as $link) {
            $byBundle[(int) $link['bundle_id']][] = (int) $link['video_id'];
        }

        $out = [];
        foreach ($bundles as $bundle) {
            $out[] = $this->decorateBundle($bundle, isset($byBundle[(int) $bundle['id']]) ? $byBundle[(int) $bundle['id']] : [], $videos);
        }
        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bundle($id)
    {
        $row = $this->db->first('SELECT * FROM bundles WHERE id = ? LIMIT 1', [(int) $id]);
        if (!is_array($row)) {
            return null;
        }

        $videos = [];
        foreach ($this->videos(false) as $video) {
            $videos[(int) $video['id']] = $video;
        }

        $ids = [];
        foreach ($this->db->all('SELECT video_id FROM bundle_items WHERE bundle_id = ? ORDER BY position, id', [(int) $id]) as $link) {
            $ids[] = (int) $link['video_id'];
        }

        return $this->decorateBundle($row, $ids, $videos);
    }

    /**
     * @param  array<string, mixed>              $row
     * @param  array<int, int>                   $videoIds
     * @param  array<int, array<string, mixed>>  $videos
     * @return array<string, mixed>
     */
    private function decorateBundle(array $row, array $videoIds, array $videos)
    {
        $items = [];
        $singleTotal = 0.0;
        foreach ($videoIds as $videoId) {
            if (!isset($videos[$videoId])) {
                continue; // Deleted video: keep the bundle usable.
            }
            $items[] = $videos[$videoId];
            $singleTotal += (float) $videos[$videoId]['price'];
        }

        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['is_active'] = (int) $row['is_active'] === 1;
        $row['position'] = (int) $row['position'];
        $row['videos'] = $items;
        $row['video_ids'] = array_map(function ($video) {
            return (int) $video['id'];
        }, $items);
        $row['video_count'] = count($items);
        $row['single_total'] = $singleTotal;
        $row['savings'] = max(0.0, $singleTotal - (float) $row['price']);
        $row['thumbnail'] = isset($items[0]) ? $items[0]['thumbnail'] : '';
        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @param  array<int, int>      $videoIds
     * @return int
     */
    public function createBundle(array $data, array $videoIds)
    {
        $id = (int) $this->db->insert('bundles', $this->bundleRow($data));
        $this->setBundleVideos($id, $videoIds);
        return $id;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, int>      $videoIds
     */
    public function updateBundle($id, array $data, array $videoIds)
    {
        $this->db->update('bundles', $this->bundleRow($data), 'id = ?', [(int) $id]);
        $this->setBundleVideos((int) $id, $videoIds);
    }

    public function deleteBundle($id)
    {
        $this->db->exec('DELETE FROM bundle_items WHERE bundle_id = ?', [(int) $id]);
        $this->db->exec('DELETE FROM bundles WHERE id = ?', [(int) $id]);
    }

    /**
     * Replace the videos of a bundle.
     *
     * @param array<int, int> $videoIds
     */
    public function setBundleVideos($bundleId, array $videoIds)
    {
        $bundleId = (int) $bundleId;
        $this->db->exec('DELETE FROM bundle_items WHERE bundle_id = ?', [$bundleId]);

        $position = 0;
        foreach (array_unique(array_map('intval', $videoIds)) as $videoId) {
            if ($videoId <= 0) {
                continue;
            }
            $position++;
            $this->db->insert('bundle_items', [
                'bundle_id'  => $bundleId,
                'video_id'   => $videoId,
                'position'   => $position,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bundleRow(array $data)
    {
        return [
            'name'        => db_limit((string) $data['name'], 160),
            'description' => trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            'price'       => number_format(max(0, (float) $data['price']), 2, '.', ''),
            'position'    => (int) $data['position'],
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
            'updated_at'  => now(),
        ];
    }

    /**
     * How many times this bundle was sold.
     */
    public function bundleSales($bundleId)
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM payments WHERE product_type = 'bundle' AND product_id = ? AND status = 'success'",
            [(int) $bundleId],
            0
        );
    }

    // -------------------------------------------------------------------------
    // Entitlements
    // -------------------------------------------------------------------------

    /**
     * Give one payment access to a set of videos (idempotent).
     *
     * @param array<int, int> $videoIds
     */
    public function grantPayment($paymentId, array $videoIds)
    {
        $paymentId = (int) $paymentId;
        foreach (array_unique(array_map('intval', $videoIds)) as $videoId) {
            if ($videoId <= 0) {
                continue;
            }
            $exists = (int) $this->db->value(
                'SELECT COUNT(*) FROM payment_videos WHERE payment_id = ? AND video_id = ?',
                [$paymentId, $videoId],
                0
            );
            if ($exists > 0) {
                continue;
            }
            $this->db->insert('payment_videos', [
                'payment_id' => $paymentId,
                'video_id'   => $videoId,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Videos a payment unlocked, in bundle order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function videosForPayment($paymentId)
    {
        $ids = [];
        foreach ($this->db->all('SELECT video_id FROM payment_videos WHERE payment_id = ? ORDER BY id', [(int) $paymentId]) as $row) {
            $ids[] = (int) $row['video_id'];
        }
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $video = $this->video($id);
            if ($video !== null) {
                $out[] = $video;
            }
        }
        return $out;
    }

    public function paymentHasEntitlements($paymentId)
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM payment_videos WHERE payment_id = ?', [(int) $paymentId], 0) > 0;
    }

    /**
     * Library totals for the admin dashboard.
     *
     * @return array{videos:int, bundles:int}
     */
    public function counts()
    {
        return [
            'videos'  => (int) $this->db->value('SELECT COUNT(*) FROM videos WHERE is_active = 1', [], 0),
            'bundles' => (int) $this->db->value('SELECT COUNT(*) FROM bundles WHERE is_active = 1', [], 0),
        ];
    }
}

// -----------------------------------------------------------------------------
// Checkout helpers
// -----------------------------------------------------------------------------

/**
 * A product the visitor can buy: a single video or a bundle.
 *
 * @param  string $type  "video" | "bundle" | "single" (legacy)
 * @param  int    $id
 * @param  bool   $activeOnly  false when granting access to a product that was
 *                             switched off while its payment was in flight.
 * @return array<string, mixed>|null
 */
function catalog_product($type, $id, $activeOnly = true)
{
    $catalog = app()->catalog;
    $type = strtolower(trim((string) $type));
    $id = (int) $id;

    if ($type === 'video') {
        $video = $catalog->video($id);
        if ($video === null || ($activeOnly && !$video['is_active'])) {
            return null;
        }
        return [
            'type'      => 'video',
            'id'        => (int) $video['id'],
            'name'      => (string) $video['title'],
            'price'     => (float) $video['price'],
            'videos'    => [$video],
            'thumbnail' => (string) $video['thumbnail'],
            'description' => (string) ($video['description'] ?? ''),
        ];
    }

    if ($type === 'bundle') {
        $bundle = $catalog->bundle($id);
        if ($bundle === null || $bundle['videos'] === [] || ($activeOnly && !$bundle['is_active'])) {
            return null;
        }
        return [
            'type'      => 'bundle',
            'id'        => (int) $bundle['id'],
            'name'      => (string) $bundle['name'],
            'price'     => (float) $bundle['price'],
            'videos'    => $bundle['videos'],
            'thumbnail' => (string) $bundle['thumbnail'],
            'description' => (string) ($bundle['description'] ?? ''),
        ];
    }

    return null;
}

/**
 * The single-video product used by installs that never had a library (or by a
 * visitor opening a v2 link): the YouTube URL + price from site settings.
 *
 * @return array<string, mixed>|null
 */
function catalog_legacy_product()
{
    $settings = app()->settings->all();
    $url = trim((string) $settings['youtube_video_url']);
    if ($url === '') {
        return null;
    }

    $video = [
        'id'          => 0,
        'title'       => (string) $settings['site_name'],
        'description' => (string) $settings['tagline'],
        'youtube_url' => $url,
        'youtube_id'  => youtube_id($url),
        'embed_url'   => youtube_embed_url($url),
        'watch_url'   => youtube_watch_url($url),
        'thumbnail'   => youtube_thumbnail($url, 'hqdefault'),
        'price'       => (float) $settings['amount'],
        'is_active'   => true,
    ];

    return [
        'type'        => 'single',
        'id'          => 0,
        'name'        => (string) $settings['site_name'],
        'price'       => (float) $settings['amount'],
        'videos'      => [$video],
        'thumbnail'   => (string) $video['thumbnail'],
        'description' => (string) $settings['tagline'],
    ];
}

/**
 * Everything the landing page sells, in display order.
 *
 * @return array{videos:array<int,array<string,mixed>>, bundles:array<int,array<string,mixed>>, legacy:array<string,mixed>|null}
 */
function catalog_storefront()
{
    $catalog = app()->catalog;
    $videos = $catalog->videos(true);
    $bundles = $catalog->bundles(true);

    // A bundle whose videos were all removed cannot be bought.
    $bundles = array_values(array_filter($bundles, function ($bundle) {
        return $bundle['videos'] !== [];
    }));

    $legacy = ($videos === [] && $bundles === []) ? catalog_legacy_product() : null;

    return ['videos' => $videos, 'bundles' => $bundles, 'legacy' => $legacy];
}

/**
 * Unlock everything the payment bought. Safe to call more than once.
 *
 * @param array<string, mixed> $payment
 */
function catalog_grant_for_payment(array $payment)
{
    $catalog = app()->catalog;
    $paymentId = (int) $payment['id'];

    if ($catalog->paymentHasEntitlements($paymentId)) {
        return; // Already unlocked (repeat verification, webhook, retry…).
    }

    $type = isset($payment['product_type']) ? (string) $payment['product_type'] : '';
    $productId = isset($payment['product_id']) ? (int) $payment['product_id'] : 0;

    // Inactive products still unlock: the customer paid before it was hidden.
    $product = $type !== '' && $type !== 'single' ? catalog_product($type, $productId, false) : null;
    if ($product === null) {
        return; // Legacy payment: access falls back to the settings video.
    }

    $videoIds = [];
    foreach ($product['videos'] as $video) {
        $videoIds[] = (int) $video['id'];
    }
    $catalog->grantPayment($paymentId, $videoIds);
}

/**
 * Videos a buyer may watch. Handles payments from before the library existed.
 *
 * @param  array<string, mixed> $payment
 * @return array<int, array<string, mixed>>
 */
function catalog_videos_for_payment(array $payment)
{
    $videos = app()->catalog->videosForPayment((int) $payment['id']);
    if ($videos !== []) {
        return $videos;
    }

    $legacy = catalog_legacy_product();
    return $legacy === null ? [] : $legacy['videos'];
}

/**
 * Everything one buyer may watch: every video from every successful payment
 * they made with the same e-mail + mobile, newest purchase first, no duplicates.
 *
 * @param  array<string, mixed> $payment
 * @return array<int, array<string, mixed>>
 */
function catalog_library_for_payment(array $payment)
{
    $payments = app()->payments->allSuccessfulFor(
        isset($payment['user_email']) ? (string) $payment['user_email'] : '',
        isset($payment['user_mobile']) ? (string) $payment['user_mobile'] : ''
    );
    if ($payments === []) {
        $payments = [$payment];
    }

    $videos = [];
    $seen = [];
    foreach (array_reverse($payments) as $row) { // newest purchase first
        foreach (catalog_videos_for_payment($row) as $video) {
            $key = (int) $video['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $videos[] = $video;
        }
    }
    return $videos;
}

/**
 * Best product to preselect on the landing page.
 *
 * @param  array<string, mixed>       $storefront
 * @param  string                     $requested "video:3" / "bundle:1"
 * @return array<string, mixed>|null
 */
function catalog_selected_product(array $storefront, $requested = '')
{
    $requested = trim((string) $requested);
    if (preg_match('/^(video|bundle)[:_-]?(\d+)$/i', $requested, $m)) {
        $type = strtolower($m[1]);
        foreach ($storefront[$type === 'video' ? 'videos' : 'bundles'] as $item) {
            if ((int) $item['id'] === (int) $m[2]) {
                return [
                    'type'        => $type,
                    'id'          => (int) $item['id'],
                    'name'        => (string) ($type === 'video' ? $item['title'] : $item['name']),
                    'price'       => (float) $item['price'],
                    'videos'      => $type === 'video' ? [$item] : $item['videos'],
                    'thumbnail'   => (string) $item['thumbnail'],
                    'description' => (string) ($item['description'] ?? ''),
                ];
            }
        }
    }

    if (!empty($storefront['videos'])) {
        $video = $storefront['videos'][0];
        return [
            'type'        => 'video',
            'id'          => (int) $video['id'],
            'name'        => (string) $video['title'],
            'price'       => (float) $video['price'],
            'videos'      => [$video],
            'thumbnail'   => (string) $video['thumbnail'],
            'description' => (string) ($video['description'] ?? ''),
        ];
    }

    if (!empty($storefront['bundles'])) {
        $bundle = $storefront['bundles'][0];
        return [
            'type'        => 'bundle',
            'id'          => (int) $bundle['id'],
            'name'        => (string) $bundle['name'],
            'price'       => (float) $bundle['price'],
            'videos'      => $bundle['videos'],
            'thumbnail'   => (string) $bundle['thumbnail'],
            'description' => (string) ($bundle['description'] ?? ''),
        ];
    }

    return $storefront['legacy'] ?? null;
}
