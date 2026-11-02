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
 * @param  string $type  "video" | "bundle"
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
            'type'        => 'bundle',
            'id'          => (int) $bundle['id'],
            'name'        => (string) $bundle['name'],
            'price'       => (float) $bundle['price'],
            'videos'      => $bundle['videos'],
            'thumbnail'   => (string) $bundle['thumbnail'],
            'description' => (string) ($bundle['description'] ?? ''),
            'single_total' => (float) $bundle['single_total'],
            'savings'      => (float) $bundle['savings'],
            'video_count'  => (int) $bundle['video_count'],
        ];
    }

    return null;
}

/**
 * Everything the landing page sells, in display order.
 *
 * There is no fallback product: if the owner has not published a video or a
 * bundle, nothing is on sale and the page says so.
 *
 * @return array{videos:array<int,array<string,mixed>>, bundles:array<int,array<string,mixed>>}
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

    return ['videos' => $videos, 'bundles' => $bundles];
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
    $product = $type !== '' ? catalog_product($type, $productId, false) : null;
    if ($product === null) {
        return; // A payment that predates the library: update.php links those.
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
    return app()->catalog->videosForPayment((int) $payment['id']);
}

/**
 * Everything one buyer may watch: every video from every successful payment
 * they made with the same mobile number, newest purchase first, no duplicates.
 *
 * @param  array<string, mixed> $payment
 * @return array<int, array<string, mixed>>
 */
function catalog_library_for_payment(array $payment)
{
    $payments = app()->payments->allSuccessfulForMobile(
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
 * Every successful purchase of the same buyer (same mobile number), oldest
 * first. An access token belongs to one payment, but a library belongs to the
 * person: buying a bundle and later a single video must merge.
 *
 * @param  array<string, mixed> $payment
 * @return array<int, array<string, mixed>>
 */
function catalog_buyer_payments($payment)
{
    if (!is_array($payment)) {
        return [];
    }

    // A pending or failed payment must never make anything look bought.
    if (isset($payment['status']) && (string) $payment['status'] !== Payments::STATUS_SUCCESS) {
        return [];
    }

    // Identity is the mobile number: the e-mail is optional at checkout, so it
    // can never be part of the lookup.
    $payments = app()->payments->allSuccessfulForMobile(
        isset($payment['user_mobile']) ? (string) $payment['user_mobile'] : ''
    );

    return $payments === [] ? [$payment] : $payments;
}

/**
 * What a visitor already owns, as id sets the storefront can test.
 *
 * A bundle purchase unlocks every video inside it, so those single-video cards
 * are marked owned too; and a bundle whose videos were all bought separately is
 * reported as "covered" so it is never sold twice.
 *
 * @param  array<string, mixed>|null $payment  current access payment (or null)
 * @return array{videos: array<int, int>, bundles: array<int, int>, covered: array<int, int>}
 *         videos  — every video id in the buyer's library
 *         bundles — bundle ids that were bought as a bundle
 *         covered — bundle ids whose every video is already in the library
 */
function catalog_owned_state($payment)
{
    $state = ['videos' => [], 'bundles' => [], 'covered' => []];
    if (!is_array($payment)) {
        return $state;
    }

    $videos = [];
    $bundles = [];
    foreach (catalog_buyer_payments($payment) as $row) {
        foreach (catalog_videos_for_payment($row) as $video) {
            $videos[(int) $video['id']] = true;
        }

        $type = isset($row['product_type']) ? strtolower((string) $row['product_type']) : '';
        $id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
        if ($type === 'bundle' && $id > 0) {
            $bundles[$id] = true;
        }
    }

    $state['videos'] = array_keys($videos);
    $state['bundles'] = array_keys($bundles);

    foreach (app()->catalog->bundles(false) as $bundle) {
        $ids = array_map('intval', $bundle['video_ids']);
        if ($ids === []) {
            continue;
        }
        $ownsAll = true;
        foreach ($ids as $id) {
            if (!isset($videos[$id])) {
                $ownsAll = false;
                break;
            }
        }
        if ($ownsAll) {
            $state['covered'][] = (int) $bundle['id'];
        }
    }

    return $state;
}

/**
 * Bundles that contain one video, cheapest first (tie-break: bigger saving).
 *
 * @param  array<string, mixed> $storefront
 * @param  int                  $videoId
 * @return array<int, array<string, mixed>>
 */
function catalog_bundles_for_video(array $storefront, $videoId)
{
    $videoId = (int) $videoId;
    $matches = [];
    foreach ($storefront['bundles'] as $bundle) {
        if (in_array($videoId, $bundle['video_ids'], true)) {
            $matches[] = $bundle;
        }
    }

    usort($matches, function ($a, $b) {
        if ((float) $a['price'] === (float) $b['price']) {
            return (float) $b['savings'] <=> (float) $a['savings'];
        }
        return (float) $a['price'] <=> (float) $b['price'];
    });

    return $matches;
}

/**
 * The bundle to offer someone who picked a single video ("you could also get
 * these videos for …"), or null when that video is not in any bundle.
 *
 * @param  array<string, mixed> $storefront
 * @param  int                  $videoId
 * @return array<string, mixed>|null
 */
function catalog_best_bundle_for_video(array $storefront, $videoId)
{
    $bundles = catalog_bundles_for_video($storefront, $videoId);
    return $bundles === [] ? null : $bundles[0];
}

/**
 * Best product to preselect on the landing page.
 *
 * @param  array<string, mixed>       $storefront
 * @param  string                     $requested "video:3" / "bundle:1"
 * @return array<string, mixed>|null
 */
/**
 * Normalise a storefront row (video or bundle) into the product shape the
 * checkout uses, keeping the bundle economics for the savings message.
 *
 * @param  string                $type  video|bundle
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function catalog_product_from_item($type, array $item)
{
    $isBundle = $type === 'bundle';
    $videos = $isBundle ? $item['videos'] : [$item];
    $singleTotal = $isBundle
        ? (float) $item['single_total']
        : (float) $item['price'];
    $price = (float) $item['price'];

    return [
        'type'        => $type,
        'id'          => (int) $item['id'],
        'name'        => (string) ($isBundle ? $item['name'] : $item['title']),
        'price'       => $price,
        'videos'      => $videos,
        'thumbnail'   => (string) $item['thumbnail'],
        'description' => (string) ($item['description'] ?? ''),
        'video_count' => count($videos),
        'single_total' => $singleTotal,
        'savings'     => $isBundle ? max(0.0, $singleTotal - $price) : 0.0,
    ];
}

function catalog_selected_product(array $storefront, $requested = '')
{
    $requested = trim((string) $requested);
    if (preg_match('/^(video|bundle)[:_-]?(\d+)$/i', $requested, $m)) {
        $type = strtolower($m[1]);
        foreach ($storefront[$type === 'video' ? 'videos' : 'bundles'] as $item) {
            if ((int) $item['id'] === (int) $m[2]) {
                return catalog_product_from_item($type, $item);
            }
        }
    }

    if (!empty($storefront['videos'])) {
        return catalog_product_from_item('video', $storefront['videos'][0]);
    }

    if (!empty($storefront['bundles'])) {
        return catalog_product_from_item('bundle', $storefront['bundles'][0]);
    }

    return null;
}
