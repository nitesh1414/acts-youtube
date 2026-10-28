<?php
/**
 * Simple database-backed rate limiter / brute-force protection.
 *
 * Every hit is stored in `rate_events`; older events are pruned lazily.
 */
final class Throttle
{
    /** @var DatabaseInterface */
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Count hits for a bucket + identifier inside the sliding window.
     */
    public function hits($bucket, $identifier, $windowSeconds)
    {
        $since = date('Y-m-d H:i:s', time() - (int) $windowSeconds);
        $count = $this->db->value(
            'SELECT COUNT(*) FROM rate_events WHERE bucket = ? AND identifier = ? AND created_at >= ?',
            [$bucket, $identifier, $since],
            0
        );
        return (int) $count;
    }

    /**
     * Has the limit been reached?
     */
    public function tooMany($bucket, $identifier, $max, $windowSeconds)
    {
        return $this->hits($bucket, $identifier, $windowSeconds) >= (int) $max;
    }

    /**
     * Record a hit.
     */
    public function hit($bucket, $identifier)
    {
        try {
            $this->db->insert('rate_events', [
                'bucket'     => (string) $bucket,
                'identifier' => db_limit($identifier, 190),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Never let rate limiting break the request.
            app_log('warning', 'Could not record rate-limit hit: ' . $e->getMessage());
        }

        // Housekeeping: prune events older than 24 hours now and then.
        if (mt_rand(1, 50) === 1) {
            $this->prune();
        }
    }

    /**
     * Remove hits for a bucket + identifier (used after a successful login).
     */
    public function clear($bucket, $identifier)
    {
        try {
            $this->db->exec('DELETE FROM rate_events WHERE bucket = ? AND identifier = ?', [$bucket, $identifier]);
        } catch (Throwable $e) {
            app_log('warning', 'Could not clear rate-limit hits: ' . $e->getMessage());
        }
    }

    /**
     * Delete events older than a day.
     */
    public function prune()
    {
        try {
            $this->db->exec('DELETE FROM rate_events WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        } catch (Throwable $e) {
            // Ignore.
        }
    }

    /**
     * Seconds until the oldest hit leaves the window (for "try again in" hints).
     */
    public function retryAfter($bucket, $identifier, $windowSeconds)
    {
        $since = date('Y-m-d H:i:s', time() - (int) $windowSeconds);
        $oldest = $this->db->value(
            'SELECT MIN(created_at) FROM rate_events WHERE bucket = ? AND identifier = ? AND created_at >= ?',
            [$bucket, $identifier, $since]
        );
        if (!$oldest) {
            return 0;
        }
        $remaining = ((int) strtotime($oldest) + (int) $windowSeconds) - time();
        return max(0, $remaining);
    }
}
