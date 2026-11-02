<?php
/**
 * Landing page.
 *
 * Order matters: visitors first see everything that is for sale — every video
 * with its own price and every named bundle — and only then the checkout card
 * for whatever they picked. When a single video is also part of a bundle, the
 * card points out what the bundle would cost and what it saves, but the buyer
 * always stays in control of what they pay for.
 *
 * @var array<string, mixed>            $settings
 * @var array<string, mixed>            $storefront videos, bundles, legacy
 * @var array<string, mixed>|null       $selected   product preselected for checkout
 */
$currency = (string) $settings['currency'];
$accessDays = (int) $settings['access_days'];
$demoMode = (int) $settings['demo_mode'] === 1;
$razorpayReady = app()->settings->isRazorpayConfigured();
$channel = (string) $settings['youtube_channel_url'];

$videos = $storefront['videos'];
$bundles = $storefront['bundles'];
$legacy = $storefront['legacy'];
$hasProducts = $selected !== null;
$totalVideos = count($videos);
$totalBundles = count($bundles);

$access = current_access_payment();
$ownedState = catalog_owned_state($access);
$ownedVideos = array_fill_keys($ownedState['videos'], true);    // video id => true
$ownedBundles = array_fill_keys($ownedState['bundles'], true);  // bought as a bundle
$coveredBundles = array_fill_keys($ownedState['covered'], true); // every video already owned
$ownedCount = count($ownedVideos);

$accessNote = $accessDays > 0
    ? 'Watch anytime for ' . $accessDays . ' day' . ($accessDays === 1 ? '' : 's')
    : 'Watch anytime — your access does not expire';

// Cheapest thing on sale, used for the "from ₹x" stat in the hero.
$fromPrice = 0.0;
foreach ($videos as $video) {
    $price = (float) $video['price'];
    $fromPrice = $fromPrice === 0.0 ? $price : min($fromPrice, $price);
}
foreach ($bundles as $bundle) {
    $price = (float) $bundle['price'];
    $fromPrice = $fromPrice === 0.0 ? $price : min($fromPrice, $price);
}

// Which bundle (if any) contains each video — cheapest bundle wins.
$bestBundle = [];
foreach ($videos as $video) {
    $bundle = catalog_best_bundle_for_video($storefront, (int) $video['id']);
    if ($bundle !== null) {
        $bestBundle[(int) $video['id']] = $bundle;
    }
}

// A buyer can land on a product they already paid for (bookmark, refresh,
// second visit). Selling it again would take their money for nothing.
$selectedOwned = false;
$selectedViaBundle = false;
if ($selected !== null) {
    $selectedId = (int) $selected['id'];
    if ($selected['type'] === 'bundle') {
        $selectedOwned = isset($ownedBundles[$selectedId]) || isset($coveredBundles[$selectedId]);
    } else {
        $selectedOwned = isset($ownedVideos[$selectedId]);
        if ($selectedOwned && isset($bestBundle[$selectedId])) {
            // Owned because of a bundle rather than bought on its own?
            $ownerBundleId = (int) $bestBundle[$selectedId]['id'];
            $selectedViaBundle = isset($ownedBundles[$ownerBundleId]) || isset($coveredBundles[$ownerBundleId]);
        }
    }
}
$selectedWatchId = 0;
if ($selected !== null && $selected['videos'] !== []) {
    $selectedWatchId = (int) $selected['videos'][0]['id'];
}

$priceLabel = $selected !== null ? money((float) $selected['price'], $currency) : '';
$videoCount = $selected !== null ? count($selected['videos']) : 0;
$periodLabel = $videoCount > 1 ? $videoCount . ' videos' : '1 video';

// What the savings box says before any JavaScript runs.
$selectedBundle = null;
if ($selected !== null && $selected['type'] === 'video' && isset($bestBundle[(int) $selected['id']])) {
    $selectedBundle = $bestBundle[(int) $selected['id']];
}
$selectedSavings = $selected !== null && $selected['type'] === 'bundle' && isset($selected['savings'])
    ? (float) $selected['savings']
    : 0.0;

/** Bundle → hint copy shared by the cards and the checkout box. */
$hint = function (array $bundle) use ($currency) {
    $count = max(1, (int) $bundle['video_count']);
    $price = money((float) $bundle['price'], $currency);
    $single = money((float) $bundle['single_total'], $currency);
    $savings = money((float) $bundle['savings'], $currency);
    $each = money((float) $bundle['price'] / $count, $currency);

    $text = $price . ' for ' . $count . ' videos';
    if ($count > 1) {
        $text .= ' · ' . $each . ' each';
    }
    $text .= ' (' . $single . ' separately)';

    return [
        'type'   => 'bundle',
        'id'     => (int) $bundle['id'],
        'name'   => (string) $bundle['name'],
        'price'  => (int) round((float) $bundle['price'] * 100),
        'label'  => $price,
        'period' => $count . ' video' . ($count === 1 ? '' : 's'),
        'badge'  => 'Bundle saves ' . $savings,
        'title'  => (string) $bundle['name'] . ' includes this video',
        'text'   => $text,
        'note'   => 'It costs more than this one video because you get '
            . ($count > 1 ? 'all ' . $count . ' videos' : 'the whole set')
            . ' — keep just this one if that is all you want.',
    ];
};

// One description of the offered bundle, reused by the badge, the text and the CTA.
$selectedHint = $selectedBundle !== null ? $hint($selectedBundle) : null;

/** Serialise a hint array into data-* attributes. */
$hintAttrs = function (array $hint, $prefix = 'suggest') {
    $attrs = '';
    foreach ($hint as $key => $value) {
        $attrs .= ' data-' . $prefix . '-' . $key . '="' . e((string) $value) . '"';
    }
    return $attrs;
};
?>
<section class="hero">
    <div class="wrap hero__solo">
        <span class="eyebrow"><?= icon('sparkles', 15) ?> Premium video library</span>
        <h1 class="hero__title">
            <?= e($settings['logo_text']) ?>
            <span class="hero__title-accent">unlocked in 30 seconds.</span>
        </h1>
        <p class="hero__lede"><?= e($settings['tagline']) ?></p>

        <ul class="hero__features">
            <li><?= icon('check-circle', 18) ?> Instant access right after payment</li>
            <li><?= icon('shield', 18) ?> Secure checkout — UPI, cards, netbanking &amp; wallets</li>
            <li><?= icon('clock', 18) ?> <?= e($accessNote) ?></li>
            <li><?= icon('tag', 18) ?> <?= $totalBundles > 0 ? 'Money-saving bundles first, single videos too' : 'Every video priced on its own' ?></li>
        </ul>

        <div class="hero__actions">
            <?php if ($hasProducts): ?>
                <?php if ($totalBundles > 0): ?>
                    <a class="btn btn--primary" href="#bundles">
                        <?= icon('tag', 17) ?> See the bundles first
                    </a>
                    <a class="btn btn--ghost" href="#library">
                        <?= icon('play', 17) ?> Browse all videos
                    </a>
                <?php else: ?>
                    <a class="btn btn--primary" href="#library">
                        <?= icon('play', 17) ?> Browse the videos
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($access !== null): ?>
                <a class="btn btn--ghost" href="<?= e(url('success.php')) ?>">
                    <?= icon('eye', 17) ?> Open my library
                </a>
            <?php endif; ?>
        </div>

        <?php if ($hasProducts): ?>
            <dl class="hero__stats">
                <div>
                    <dt><?= icon('youtube', 15) ?> Videos</dt>
                    <dd><?= $totalVideos > 0 ? $totalVideos : 1 ?></dd>
                </div>
                <?php if ($totalBundles > 0): ?>
                    <div>
                        <dt><?= icon('tag', 15) ?> Bundles</dt>
                        <dd><?= $totalBundles ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($fromPrice > 0): ?>
                    <div>
                        <dt><?= icon('wallet', 15) ?> From</dt>
                        <dd><?= e(money($fromPrice, $currency)) ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt><?= icon('clock', 15) ?> Access</dt>
                    <dd class="hero__stats-text"><?= $accessDays > 0 ? $accessDays . ' days' : 'No expiry' ?></dd>
                </div>
                <?php if ($ownedCount > 0): ?>
                    <div>
                        <dt><?= icon('check-circle', 15) ?> Yours</dt>
                        <dd class="hero__stats-text"><?= $ownedCount ?> owned</dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
    </div>
</section>

<?php if ($bundles !== []): ?>
    <section class="section" id="bundles">
        <div class="wrap section__inner">
            <header class="section__head">
                <span class="eyebrow">Start here · Bundles</span>
                <h2>More videos, one payment</h2>
                <p>A <strong>named bundle</strong> is several videos sold together. Pay once and every video in it stays in your library — always cheaper than buying the same videos one by one, and the saving is printed on each bundle.</p>
            </header>

            <div class="bundles">
                <?php foreach ($bundles as $bundle): ?>
                    <?php
                    $savings = (float) $bundle['savings'];
                    $bundleId = (int) $bundle['id'];
                    $bundleBought = isset($ownedBundles[$bundleId]);
                    $bundleCovered = isset($coveredBundles[$bundleId]);
                    $bundleYours = $bundleBought || $bundleCovered;
                    $bundleVideosOwned = 0;
                    foreach ($bundle['videos'] as $bundleVideo) {
                        if (isset($ownedVideos[(int) $bundleVideo['id']])) {
                            $bundleVideosOwned++;
                        }
                    }
                    $bundleWatchId = $bundle['videos'] !== [] ? (int) $bundle['videos'][0]['id'] : 0;
                    ?>
                    <article class="bundle-card <?= $bundleYours ? 'is-owned' : '' ?>" data-product-card="bundle:<?= $bundleId ?>">
                        <div class="bundle-card__head">
                            <div>
                                <span class="chip chip--secure"><?= icon('tag', 13) ?> Bundle · <?= (int) $bundle['video_count'] ?> videos</span>
                                <?php if ($bundleBought): ?>
                                    <span class="chip chip--owned"><?= icon('check', 13) ?> Purchased</span>
                                <?php elseif ($bundleCovered): ?>
                                    <span class="chip chip--owned"><?= icon('check', 13) ?> All videos owned</span>
                                <?php endif; ?>
                                <h3><?= e($bundle['name']) ?></h3>
                                <?php if (trim((string) $bundle['description']) !== ''): ?>
                                    <p class="muted small"><?= e($bundle['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="bundle-card__price">
                                <span class="strong"><?= e(money((float) $bundle['price'], $currency)) ?></span>
                                <?php if ($savings > 0): ?>
                                    <span class="muted small"><s><?= e(money((float) $bundle['single_total'], $currency)) ?></s></span>
                                    <span class="save-badge"><?= $bundleBought ? 'Saved ' : 'Save ' ?><?= e(money($savings, $currency)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <ul class="bundle-card__list">
                            <?php foreach ($bundle['videos'] as $video): ?>
                                <?php $rowOwned = isset($ownedVideos[(int) $video['id']]); ?>
                                <li class="<?= $rowOwned ? 'is-owned' : '' ?>">
                                    <?= icon('play', 14) ?>
                                    <span><?= e($video['title']) ?></span>
                                    <?php if ($rowOwned): ?>
                                        <span class="bundle-card__tick"><?= icon('check', 12) ?> Owned</span>
                                    <?php else: ?>
                                        <span class="muted tiny"><?= e(money((float) $video['price'], $currency)) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($bundleYours): ?>
                            <div class="bundle-card__foot">
                                <span class="muted small">
                                    <?php if ($bundleBought): ?>
                                        Purchased — <?= $bundleVideosOwned ?> of <?= (int) $bundle['video_count'] ?> videos in your library
                                    <?php else: ?>
                                        All <?= (int) $bundle['video_count'] ?> videos are already in your library
                                    <?php endif; ?>
                                </span>
                                <a class="btn btn--primary" href="<?= e(url('success.php?v=' . $bundleWatchId)) ?>">
                                    <?= icon('play', 16) ?> Watch
                                </a>
                            </div>
                        <?php else: ?>
                        <div class="bundle-card__foot">
                            <span class="muted small">One payment · <?= $accessDays > 0 ? $accessDays . ' days' : 'lifetime' ?> access</span>
                            <a class="btn btn--primary"
                               href="<?= e(url('?buy=bundle:' . (int) $bundle['id'] . '#checkout')) ?>"
                               data-buy
                               data-type="bundle"
                               data-id="<?= (int) $bundle['id'] ?>"
                               data-name="<?= e($bundle['name']) ?>"
                               data-price="<?= (int) round((float) $bundle['price'] * 100) ?>"
                               data-label="<?= e(money((float) $bundle['price'], $currency)) ?>"
                               data-period="<?= (int) $bundle['video_count'] ?> videos"
                               data-savings="<?= (int) round($savings * 100) ?>"
                               data-savings-badge="You save <?= e(money($savings, $currency)) ?>"
                               data-savings-title="<?= e($bundle['name']) ?>"
                               data-savings-text="<?= e(money((float) $bundle['price'], $currency)) ?> for <?= (int) $bundle['video_count'] ?> videos (<?= e(money((float) $bundle['single_total'], $currency)) ?> separately)"
                               data-savings-note="One payment unlocks every video in the bundle — or pick a single video above if that suits you better.">
                                Choose this bundle
                            </a>
                        </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($videos !== []): ?>
    <section class="section section--muted" id="library">
        <div class="wrap section__inner">
            <header class="section__head">
                <span class="eyebrow">Or buy a single video</span>
                <h2>Every video, its own price</h2>
                <p>Want just one? Each video is sold on its own — pay for that one and only that one is unlocked. If it is also part of a bundle we show you the bundle price and what it saves, so you can compare.</p>
            </header>

            <div class="catalog">
                <?php foreach ($videos as $video): ?>
                    <?php
                    $videoId = (int) $video['id'];
                    $owned = isset($ownedVideos[$videoId]);
                    $bundle = isset($bestBundle[$videoId]) ? $bestBundle[$videoId] : null;
                    $bundleYours = false;
                    if ($owned && $bundle !== null) {
                        $bundleYours = isset($ownedBundles[(int) $bundle['id']]) || isset($coveredBundles[(int) $bundle['id']]);
                    }
                    ?>
                    <article class="vcard <?= $owned ? 'is-owned' : '' ?>" data-product-card="video:<?= $videoId ?>">
                        <div class="vcard__thumb">
                            <?php if ($video['thumbnail'] !== ''): ?>
                                <img src="<?= e($video['thumbnail']) ?>" alt="" loading="lazy" width="320" height="180">
                            <?php else: ?>
                                <span class="vcard__placeholder"><?= icon('youtube', 30) ?></span>
                            <?php endif; ?>
                            <span class="vcard__price"><?= e(money((float) $video['price'], $currency)) ?></span>
                            <?php if ($owned): ?>
                                <span class="vcard__owned"><?= icon('check', 13) ?> Owned</span>
                            <?php endif; ?>
                        </div>
                        <div class="vcard__body">
                            <h3><?= e($video['title']) ?></h3>
                            <?php if (trim((string) $video['description']) !== ''): ?>
                                <p class="muted small"><?= e(text_limit((string) $video['description'], 130)) ?></p>
                            <?php endif; ?>

                            <?php if ($owned && $bundleYours): ?>
                                <p class="vcard__owned-note">
                                    <?= icon('check', 13) ?>
                                    Included in <strong><?= e($bundle['name']) ?></strong>
                                </p>
                            <?php elseif ($bundle !== null): ?>
                                <a class="vcard__bundle" href="#bundles">
                                    <?= icon('tag', 13) ?>
                                    <span>
                                        Also in <strong><?= e($bundle['name']) ?></strong> —
                                        <?= e(money((float) $bundle['price'], $currency)) ?> for
                                        <?= (int) $bundle['video_count'] ?> videos, save
                                        <?= e(money((float) $bundle['savings'], $currency)) ?>
                                    </span>
                                </a>
                            <?php endif; ?>

                            <div class="vcard__foot">
                                <span class="chip"><?= icon('play', 13) ?> Single video</span>
                                <?php if ($owned): ?>
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('success.php?v=' . $videoId)) ?>">Watch</a>
                                <?php else: ?>
                                    <a class="btn btn--primary btn--sm"
                                       href="<?= e(url('?buy=video:' . $videoId . '#checkout')) ?>"
                                       data-buy
                                       data-type="video"
                                       data-id="<?= $videoId ?>"
                                       data-name="<?= e($video['title']) ?>"
                                       data-price="<?= (int) round((float) $video['price'] * 100) ?>"
                                       data-label="<?= e(money((float) $video['price'], $currency)) ?>"
                                       data-period="1 video"<?= $bundle !== null ? $hintAttrs($hint($bundle)) : '' ?>>Buy this video</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasProducts): ?>
    <section class="section" id="checkout">
        <div class="wrap section__inner section__inner--narrow">
            <header class="section__head">
                <?php if ($selectedOwned): ?>
                    <span class="eyebrow">Your library</span>
                    <h2>You already own this</h2>
                    <p>Nothing to pay here. Open it any time, from any device — or pick something new above.</p>
                <?php else: ?>
                    <span class="eyebrow">Checkout</span>
                    <h2>Complete your purchase</h2>
                    <p>One payment. Your videos are added to a private library you can open again any time.</p>
                <?php endif; ?>
            </header>

            <?php if ($selectedOwned): ?>
                <div class="card card--pad owned-card" id="owned-card">
                    <span class="chip chip--owned"><?= icon('check', 13) ?> Already in your library</span>
                    <h3><?= e($selected['name']) ?></h3>
                    <p class="muted">
                        <?php if ($selected['type'] === 'bundle'): ?>
                            This bundle is already paid for — all <?= (int) $videoCount ?> videos inside it are unlocked.
                            You do not need to buy it again.
                        <?php elseif ($selectedViaBundle): ?>
                            You already own this video as part of a bundle. You do not need to buy it again.
                        <?php else: ?>
                            You already paid for this video. You do not need to buy it again.
                        <?php endif; ?>
                        <?php if ($accessDays > 0): ?>
                            Access stays open for <?= $accessDays ?> days from the payment date.
                        <?php else: ?>
                            Your access does not expire.
                        <?php endif; ?>
                    </p>
                    <div class="owned-card__actions">
                        <?php if ($selectedWatchId > 0): ?>
                            <a class="btn btn--primary" href="<?= e(url('success.php?v=' . $selectedWatchId)) ?>">
                                <?= icon('play', 17) ?> Watch now
                            </a>
                        <?php endif; ?>
                        <a class="btn btn--ghost" href="<?= e(url('success.php')) ?>">
                            <?= icon('eye', 17) ?> Open my library
                        </a>
                    </div>
                    <p class="form__foot">
                        <?= icon('shield', 15) ?>
                        Paid for — nothing more to pay. <?= $totalVideos ?> video<?= $totalVideos === 1 ? '' : 's' ?> and <?= $totalBundles ?> bundle<?= $totalBundles === 1 ? '' : 's' ?> are still on sale above.
                    </p>
                </div>
            <?php else: ?>
            <div class="checkout">
                <div class="card card--pad paycard">
                    <div class="paycard__head">
                        <div>
                            <p class="paycard__label">You are buying</p>
                            <p class="paycard__product" id="pay-product-name"><?= e($selected['name']) ?></p>
                            <p class="paycard__price">
                                <span id="pay-price"><?= e($priceLabel) ?></span>
                                <span class="paycard__period" id="pay-period">/ <?= e($periodLabel) ?></span>
                            </p>
                        </div>
                        <span class="chip chip--secure"><?= icon('lock', 14) ?> Secure</span>
                    </div>

                    <aside class="save-hint" id="save-hint"
                        <?= $selectedBundle === null && $selectedSavings <= 0 ? 'hidden' : '' ?>
                           data-hint="savings">
                        <span class="save-hint__badge" id="save-hint-badge">
                            <?= icon('tag', 14) ?>
                            <span id="save-hint-badge-text"><?php
                                if ($selectedHint !== null) {
                                    echo e($selectedHint['badge']);
                                } elseif ($selectedSavings > 0) {
                                    echo 'You save ' . e(money($selectedSavings, $currency));
                                }
                            ?></span>
                        </span>
                        <div class="save-hint__body">
                            <p class="save-hint__title" id="save-hint-title">
                                <?php if ($selectedHint !== null): ?>
                                    <?= e($selectedHint['title']) ?>
                                <?php elseif ($selectedSavings > 0): ?>
                                    <?= e($selected['name']) ?>
                                <?php endif; ?>
                            </p>
                            <p class="save-hint__text" id="save-hint-text">
                                <?php if ($selectedHint !== null): ?>
                                    <?= e($selectedHint['text']) ?>
                                <?php elseif ($selectedSavings > 0): ?>
                                    <?= e(money((float) $selected['price'], $currency)) ?> for <?= (int) $videoCount ?> videos
                                    (<?= e(money((float) $selected['price'] + $selectedSavings, $currency)) ?> separately)
                                <?php endif; ?>
                            </p>
                            <p class="save-hint__note muted small" id="save-hint-note">
                                <?php if ($selectedHint !== null): ?>
                                    <?= e($selectedHint['note']) ?>
                                <?php elseif ($selectedSavings > 0): ?>
                                    One payment unlocks every video in the bundle — or pick a single video above if that suits you better.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($selectedHint !== null): ?>
                            <button type="button" class="btn btn--ghost btn--sm save-hint__cta" id="save-hint-cta"
                                    data-buy
                                    data-type="bundle"
                                    data-id="<?= (int) $selectedHint['id'] ?>"
                                    data-name="<?= e($selectedHint['name']) ?>"
                                    data-price="<?= (int) $selectedHint['price'] ?>"
                                    data-label="<?= e($selectedHint['label']) ?>"
                                    data-period="<?= e($selectedHint['period']) ?>"
                                    data-savings="<?= (int) round((float) $selectedBundle['savings'] * 100) ?>"
                                    data-savings-badge="<?= e($selectedHint['badge']) ?>"
                                    data-savings-title="<?= e($selectedHint['name']) ?>"
                                    data-savings-text="<?= e($selectedHint['text']) ?>"
                                    data-savings-note="One payment unlocks every video in the bundle — or pick a single video above if that suits you better.">
                                Choose the bundle instead
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn--ghost btn--sm save-hint__cta" id="save-hint-cta" hidden data-buy>Choose the bundle instead</button>
                        <?php endif; ?>
                    </aside>

                    <?php if (!$razorpayReady && !$demoMode): ?>
                        <div class="alert alert--warning">
                            <?= icon('alert', 18) ?>
                            <span>Payments are not configured yet. Please check back soon or contact support.</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($demoMode): ?>
                        <div class="alert alert--info">
                            <?= icon('info', 18) ?>
                            <span><strong>Demo mode is on.</strong> No real money is charged; you can simulate a successful payment.</span>
                        </div>
                    <?php endif; ?>

                    <form id="payment-form" class="form" novalidate
                          data-csrf="<?= e($csrf) ?>"
                          data-endpoint="<?= e(url('api/create_order.php')) ?>"
                          data-demo-endpoint="<?= e(url('demo_pay.php')) ?>"
                          data-verify-endpoint="<?= e(url('api/verify_payment.php')) ?>"
                          data-success-url="<?= e(url('success.php')) ?>"
                          data-key="<?= e($settings['razorpay_key_id']) ?>"
                          data-amount-paise="<?= (int) round((float) $selected['price'] * 100) ?>"
                          data-currency="<?= e($currency) ?>"
                          data-site-name="<?= e($settings['site_name']) ?>"
                          data-amount-label="<?= e($priceLabel) ?>"
                          data-product-name="<?= e($selected['name']) ?>"
                          data-demo="<?= $demoMode ? '1' : '0' ?>">
                        <input type="hidden" name="item_type" id="item-type" value="<?= e($selected['type']) ?>">
                        <input type="hidden" name="item_id" id="item-id" value="<?= (int) $selected['id'] ?>">

                        <div class="field">
                            <label for="name">Full name</label>
                            <div class="field__control">
                                <?= icon('user', 18) ?>
                                <input id="name" name="name" type="text" autocomplete="name" placeholder="Your full name" required maxlength="120">
                            </div>
                        </div>
                        <div class="field">
                            <label for="email">Email address</label>
                            <div class="field__control">
                                <?= icon('mail', 18) ?>
                                <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="190">
                            </div>
                        </div>
                        <div class="field">
                            <label for="mobile">Mobile number</label>
                            <div class="field__control">
                                <?= icon('phone', 18) ?>
                                <input id="mobile" name="mobile" type="tel" autocomplete="tel" inputmode="tel" placeholder="98765 43210" required maxlength="20">
                            </div>
                        </div>

                        <div id="form-error" class="alert alert--danger" hidden></div>

                        <button type="submit" class="btn btn--primary btn--block btn--lg" id="pay-button"
                                <?= (!$razorpayReady && !$demoMode) ? 'disabled' : '' ?>>
                            <?= icon('lock', 18) ?>
                            <span class="btn__label">Pay <?= e($priceLabel) ?> &amp; unlock</span>
                        </button>

                        <p class="form__foot">
                            <?= icon('shield', 15) ?>
                            One-time payment. Your details are only used for the receipt and to restore access.
                        </p>
                    </form>
                </div>

                <div class="trust">
                    <span><?= icon('shield', 16) ?> 256-bit SSL</span>
                    <span><?= icon('zap', 16) ?> Instant delivery</span>
                    <span><?= icon('clock', 16) ?> <?= $accessDays > 0 ? $accessDays . '-day access' : 'Lifetime access' ?></span>
                    <span><?= icon('refresh', 16) ?> Restore any time</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!$hasProducts): ?>
    <section class="section" id="library">
        <div class="wrap section__inner section__inner--narrow">
            <div class="card card--pad state">
                <?= icon('youtube', 30) ?>
                <h2>The library is empty</h2>
                <p>
                    <?php if ($access !== null): ?>
                        You are signed in as a customer — your own videos appear under “My library”.
                    <?php elseif (app()->auth->user()): ?>
                        Add your first video in the admin panel and it will appear here instantly.
                    <?php else: ?>
                        Videos will appear here as soon as the owner publishes them.
                    <?php endif; ?>
                </p>
                <?php if (app()->auth->user()): ?>
                    <a class="btn btn--primary" href="<?= e(url('admin/videos.php')) ?>"><?= icon('youtube', 17) ?> Add a video</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasProducts): ?>
    <section class="section section--muted" id="how">
        <div class="wrap section__inner">
            <header class="section__head">
                <span class="eyebrow">How it works</span>
                <h2>Three steps to watch</h2>
            </header>
            <ol class="steps">
                <li class="step">
                    <span class="step__num">1</span>
                    <h3>Choose a video or bundle</h3>
                    <p>Each video has its own price. Bundles group several videos for less than buying them separately.</p>
                </li>
                <li class="step">
                    <span class="step__num">2</span>
                    <h3>Pay securely</h3>
                    <p>Complete the payment in the Razorpay window — UPI, cards, netbanking or wallets.</p>
                </li>
                <li class="step">
                    <span class="step__num">3</span>
                    <h3>Watch any time</h3>
                    <p>Your videos are added to your private library. Come back on any device with your e-mail and mobile number.</p>
                </li>
            </ol>
        </div>
    </section>
<?php endif; ?>

<section class="section" id="faq">
    <div class="wrap section__inner section__inner--narrow">
        <header class="section__head">
            <span class="eyebrow">FAQ</span>
            <h2>Questions, answered</h2>
        </header>

        <details class="faq" open>
            <summary>Can I buy just one video?</summary>
            <p>Yes. Every video is listed with its own price — pay for that one and only that one is unlocked.</p>
        </details>
        <details class="faq">
            <summary>What is a bundle?</summary>
            <p>A named set of videos sold together for one price. Everything inside is unlocked at once, and the bundle stays in your library. If a video you picked is also in a bundle, we show you the bundle price and saving — but you can always keep buying just the single video.</p>
        </details>
        <details class="faq">
            <summary>Is a bundle always the better deal?</summary>
            <p>Per video it is cheaper — but it costs more than one single video because you get more of them. Compare both options on the checkout card and pick what suits you.</p>
        </details>
        <details class="faq">
            <summary>How long can I watch?</summary>
            <p><?= $accessDays > 0
                ? 'Each purchase gives you ' . $accessDays . ' day' . ($accessDays === 1 ? '' : 's') . ' of access, starting the moment the payment is confirmed.'
                : 'Your purchases never expire — come back and watch whenever you like.' ?></p>
        </details>
        <details class="faq">
            <summary>I cleared my browser or changed device. Can I get access again?</summary>
            <p>Yes. Open <a href="<?= e(url('restore.php')) ?>">Restore access</a> and enter the same e-mail and mobile number you paid with. We re-issue your access instantly.</p>
        </details>
        <details class="faq">
            <summary>Which payment methods are supported?</summary>
            <p>UPI, credit/debit cards, netbanking and popular wallets — everything Razorpay supports in India.</p>
        </details>
        <details class="faq">
            <summary>Is my payment information safe?</summary>
            <p>Absolutely. The checkout is hosted by Razorpay (PCI-DSS compliant). We only receive a payment reference, never your card details.</p>
        </details>
        <?php if (!empty($settings['support_email']) || !empty($settings['support_phone'])): ?>
            <details class="faq">
                <summary>I still need help</summary>
                <p>
                    Write to
                    <?php if (!empty($settings['support_email'])): ?>
                        <a href="mailto:<?= e($settings['support_email']) ?>"><?= e($settings['support_email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['support_email']) && !empty($settings['support_phone'])): ?>, or call <?php endif; ?>
                    <?php if (!empty($settings['support_phone'])): ?>
                        <a href="tel:<?= e(normalize_mobile($settings['support_phone'])) ?>"><?= e(display_mobile($settings['support_phone'])) ?></a>
                    <?php endif; ?>
                    . Include your payment id if you have one.
                </p>
            </details>
        <?php endif; ?>
    </div>
</section>

<?php if ($channel !== ''): ?>
    <section class="section">
        <div class="wrap">
            <div class="cta">
                <div>
                    <h2>Enjoyed it? Join the channel.</h2>
                    <p>New videos drop every week. Subscribe so you never miss the next one.</p>
                </div>
                <a class="btn btn--youtube" href="<?= e($channel) ?>" target="_blank" rel="noopener">
                    <?= icon('youtube', 18) ?> Subscribe on YouTube
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>
