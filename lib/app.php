<?php
/**
 * Tiny service container: one place to reach the database, settings, auth and
 * the payment repository.
 */
final class App
{
    /** @var DatabaseInterface */
    public $db;

    /** @var Settings */
    public $settings;

    /** @var Throttle */
    public $throttle;

    /** @var Auth */
    public $auth;

    /** @var Payments */
    public $payments;

    /** @var Catalog */
    public $catalog;

    /** @var RazorpayClient|null */
    private $razorpay = null;

    /** @var bool */
    private $razorpayResolved = false;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
        $this->settings = new Settings($db);
        $this->throttle = new Throttle($db);
        $this->auth = new Auth($db, $this->throttle);
        $this->payments = new Payments($db);
        $this->catalog = new Catalog($db);
    }

    /**
     * Razorpay client based on the saved keys (null when not configured).
     *
     * @return RazorpayClient|null
     */
    public function razorpay()
    {
        if (!$this->razorpayResolved) {
            $this->razorpayResolved = true;
            $this->razorpay = RazorpayClient::fromSettings($this->settings);
        }
        return $this->razorpay;
    }

    /**
     * Reset the cached Razorpay client (after settings were changed).
     */
    public function forgetRazorpay()
    {
        $this->razorpay = null;
        $this->razorpayResolved = false;
    }
}

/**
 * Global access to the container (or set it in tests).
 *
 * @return App|null
 */
function app(?App $instance = null)
{
    static $app = null;
    if ($instance !== null) {
        $app = $instance;
    }
    return $app;
}
