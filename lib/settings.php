<?php
/**
 * Site settings: a single row in the `settings` table, with safe defaults.
 */
final class Settings
{
    const DEFAULTS = [
        'id'                     => 1,
        'site_name'              => 'PayAccess',
        'logo_path'              => '',
        'logo_text'              => 'PayAccess',
        'tagline'                => 'Get instant access to our exclusive video',
        'amount'                 => '199.00',
        'currency'               => 'INR',
        'razorpay_key_id'        => '',
        'razorpay_key_secret'    => '',
        'razorpay_webhook_secret'=> '',
        'youtube_video_url'      => '',
        'youtube_channel_url'    => '',
        'support_email'          => '',
        'support_phone'          => '',
        'access_days'            => 30,
        'demo_mode'              => 0,
        'theme_color'            => '#4f46e5',
        'updated_at'             => null,
    ];

    /** @var DatabaseInterface */
    private $db;

    /** @var array<string, mixed>|null */
    private $cache = null;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * All settings merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function all()
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $row = [];
        try {
            $row = $this->db->first('SELECT * FROM settings WHERE id = 1 LIMIT 1');
        } catch (Throwable $e) {
            app_log('warning', 'Could not load settings: ' . $e->getMessage());
        }

        $settings = self::DEFAULTS;
        if (is_array($row)) {
            foreach ($row as $key => $value) {
                $settings[$key] = $value;
            }
        }

        $settings['access_days'] = max(1, (int) $settings['access_days']);
        $settings['demo_mode'] = (int) $settings['demo_mode'] === 1 ? 1 : 0;
        $settings['amount'] = number_format((float) $settings['amount'], 2, '.', '');
        $settings['logo_path'] = (string) $settings['logo_path'];
        $settings['logo_text'] = trim((string) $settings['logo_text']) !== '' ? trim((string) $settings['logo_text']) : (string) $settings['site_name'];
        if (!preg_match('/^#[0-9a-f]{6}$/i', (string) $settings['theme_color'])) {
            $settings['theme_color'] = self::DEFAULTS['theme_color'];
        }

        $this->cache = $settings;
        return $this->cache;
    }

    /**
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $all = $this->all();
        return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : $default;
    }

    public function refresh()
    {
        $this->cache = null;
        return $this->all();
    }

    public function hasLogo()
    {
        $path = $this->all()['logo_path'];
        return $path !== '' && is_file(APP_ROOT . '/' . ltrim($path, '/'));
    }

    /**
     * Public URL of the uploaded logo ('' when none is set).
     */
    public function logoUrl()
    {
        if (!$this->hasLogo()) {
            return '';
        }
        return media_url(ltrim($this->all()['logo_path'], '/'));
    }

    /**
     * Absolute path of the uploaded logo.
     */
    public function logoAbsolutePath()
    {
        $path = $this->all()['logo_path'];
        return $path === '' ? '' : APP_ROOT . '/' . ltrim($path, '/');
    }

    /**
     * Where uploaded logos are stored.
     */
    public static function logoDirectory()
    {
        return APP_ROOT . '/uploads/logo';
    }

    public function isRazorpayConfigured()
    {
        $all = $this->all();
        return trim((string) $all['razorpay_key_id']) !== '' && trim((string) $all['razorpay_key_secret']) !== '';
    }

    public function amount()
    {
        return (float) $this->all()['amount'];
    }

    public function currency()
    {
        $currency = strtoupper((string) $this->all()['currency']);
        return $currency !== '' ? $currency : 'INR';
    }

    public function accessDays()
    {
        return max(1, (int) $this->all()['access_days']);
    }

    public function isDemoMode()
    {
        return (int) $this->all()['demo_mode'] === 1;
    }

    /**
     * Validate raw settings input.
     *
     * @param  array<string, mixed> $input
     * @return array{values:array<string,mixed>, errors:array<string,string>}
     */
    public static function validate(array $input)
    {
        $errors = [];
        $current = self::DEFAULTS;

        $value = function ($key, $default = '') use ($input) {
            return isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : $default;
        };

        $siteName = $value('site_name');
        if ($siteName === '' || text_length($siteName) > 120) {
            $errors['site_name'] = 'Enter the site name (maximum 120 characters).';
        }

        $logoText = $value('logo_text');
        if (text_length($logoText) > 120) {
            $errors['logo_text'] = 'Logo text can be at most 120 characters.';
        }

        $tagline = $value('tagline');
        if (text_length($tagline) > 190) {
            $errors['tagline'] = 'Tagline can be at most 190 characters.';
        }

        $amountRaw = $value('amount', $current['amount']);
        if (!is_numeric($amountRaw)) {
            $errors['amount'] = 'Enter a valid amount.';
        } else {
            $amount = round((float) $amountRaw, 2);
            if ($amount < 1) {
                $errors['amount'] = 'The amount must be at least 1.';
            } elseif ($amount > 500000) {
                $errors['amount'] = 'The amount cannot exceed 5,00,000.';
            }
        }

        $accessDaysRaw = $value('access_days', (string) $current['access_days']);
        if (!ctype_digit($accessDaysRaw) || (int) $accessDaysRaw < 1 || (int) $accessDaysRaw > 3650) {
            $errors['access_days'] = 'Access duration must be between 1 and 3650 days.';
        }

        $youtubeVideo = $value('youtube_video_url');
        if ($youtubeVideo !== '' && youtube_id($youtubeVideo) === null) {
            $errors['youtube_video_url'] = 'Enter a valid YouTube video link (e.g. https://www.youtube.com/watch?v=VIDEO_ID).';
        }

        $channelUrl = $value('youtube_channel_url');
        if ($channelUrl !== '' && (!filter_var($channelUrl, FILTER_VALIDATE_URL) || stripos($channelUrl, 'youtube.com') === false && stripos($channelUrl, 'youtu.be') === false)) {
            $errors['youtube_channel_url'] = 'Enter a valid YouTube channel URL.';
        }

        $supportEmail = $value('support_email');
        if ($supportEmail !== '' && !valid_email($supportEmail)) {
            $errors['support_email'] = 'Enter a valid support e-mail address.';
        }

        $supportPhone = $value('support_phone');
        if ($supportPhone !== '' && !valid_mobile($supportPhone)) {
            $errors['support_phone'] = 'Enter a valid support phone number.';
        }

        $keyId = $value('razorpay_key_id');
        if (text_length($keyId) > 120) {
            $errors['razorpay_key_id'] = 'The Razorpay Key ID is too long.';
        }

        $keySecret = $value('razorpay_key_secret');
        if (text_length($keySecret) > 190) {
            $errors['razorpay_key_secret'] = 'The Razorpay Key Secret is too long.';
        }

        $webhookSecret = $value('razorpay_webhook_secret');
        if (text_length($webhookSecret) > 190) {
            $errors['razorpay_webhook_secret'] = 'The webhook secret is too long.';
        }

        $themeColor = $value('theme_color', $current['theme_color']);
        if (!preg_match('/^#[0-9a-f]{6}$/i', $themeColor)) {
            $errors['theme_color'] = 'Choose a valid colour.';
        }

        $values = [
            'site_name'               => db_limit($siteName, 120),
            'logo_text'               => db_limit($logoText !== '' ? $logoText : $siteName, 120),
            'tagline'                 => db_limit($tagline, 190),
            'amount'                  => isset($amount) ? number_format($amount, 2, '.', '') : number_format((float) $current['amount'], 2, '.', ''),
            'access_days'             => isset($accessDaysRaw) && ctype_digit($accessDaysRaw) ? (int) $accessDaysRaw : (int) $current['access_days'],
            'youtube_video_url'       => db_limit($youtubeVideo, 255),
            'youtube_channel_url'     => db_limit($channelUrl, 255),
            'support_email'           => db_limit($supportEmail, 190),
            'support_phone'           => db_limit($supportPhone, 30),
            'razorpay_key_id'         => db_limit($keyId, 120),
            'razorpay_key_secret'     => db_limit($keySecret, 190),
            'razorpay_webhook_secret' => db_limit($webhookSecret, 190),
            'theme_color'             => strtolower($themeColor),
            'currency'                => 'INR',
        ];

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Save settings from an admin form (including an optional logo upload).
     *
     * @param  array<string, mixed> $input
     * @param  array<string, mixed> $files
     * @return array{ok:bool, errors:array<string,string>, message:string}
     */
    public function save(array $input, array $files = [])
    {
        $result = self::validate($input);
        $values = $result['values'];
        $errors = $result['errors'];

        $current = $this->all();
        $oldLogoRelative = (string) $current['logo_path'];
        $newLogoRelative = $oldLogoRelative;
        $uploadedFileAbsolute = '';
        $removeLogo = !empty($input['remove_logo']);

        // The Razorpay secret is masked in the UI: keep the stored value when the
        // field is submitted unchanged.
        if (isset($input['razorpay_key_secret']) && (string) $input['razorpay_key_secret'] === '') {
            $values['razorpay_key_secret'] = (string) $current['razorpay_key_secret'];
        }
        if (isset($input['razorpay_webhook_secret']) && (string) $input['razorpay_webhook_secret'] === '') {
            $values['razorpay_webhook_secret'] = (string) $current['razorpay_webhook_secret'];
        }

        // --- Logo upload -----------------------------------------------------
        $hasUpload = isset($files['logo']) && is_array($files['logo'])
            && isset($files['logo']['error']) && (int) $files['logo']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            $upload = ImageUploader::fromFilesArray($files['logo'], self::logoDirectory(), [
                'max_bytes' => defined('LOGO_MAX_BYTES') ? LOGO_MAX_BYTES : 2097152,
                'prefix'    => 'logo',
                'max_dimension' => 6000,
            ]);

            if (!$upload['ok']) {
                $errors['logo'] = $upload['error'];
            } else {
                $uploadedFileAbsolute = $upload['path'];
                $newLogoRelative = ImageUploader::relativePath($upload['path']);
            }
        } elseif ($removeLogo) {
            $newLogoRelative = '';
        }

        if (!empty($errors)) {
            if ($uploadedFileAbsolute !== '') {
                ImageUploader::delete($uploadedFileAbsolute, APP_ROOT . '/uploads');
            }
            return ['ok' => false, 'errors' => $errors, 'message' => 'Please correct the highlighted fields.'];
        }

        $values['logo_path'] = $newLogoRelative;
        $values['demo_mode'] = !empty($input['demo_mode']) ? 1 : 0;
        $values['updated_at'] = now();

        try {
            $exists = $this->db->first('SELECT id FROM settings WHERE id = 1 LIMIT 1');
            if ($exists) {
                $this->db->update('settings', $values, 'id = 1');
            } else {
                $values['id'] = 1;
                $this->db->insert('settings', $values);
            }
        } catch (Throwable $e) {
            if ($uploadedFileAbsolute !== '') {
                ImageUploader::delete($uploadedFileAbsolute, APP_ROOT . '/uploads');
            }
            app_log('error', 'Could not save settings: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['general' => 'The settings could not be saved. Please try again.'], 'message' => 'Save failed.'];
        }

        // Remove the previous logo file when it was replaced or cleared.
        if ($oldLogoRelative !== '' && $oldLogoRelative !== $newLogoRelative) {
            $oldAbsolute = APP_ROOT . '/' . ltrim($oldLogoRelative, '/');
            ImageUploader::delete($oldAbsolute, APP_ROOT . '/uploads');
        }

        $this->refresh();

        return ['ok' => true, 'errors' => [], 'message' => 'Settings saved successfully.'];
    }
}
