<?php
/** @var int    $statusCode */
/** @var string $heading */
/** @var string $message */
?>
<section class="section">
    <div class="wrap section__inner section__inner--narrow">
        <div class="card card--pad state state--error">
            <span class="state__code"><?= (int) $statusCode ?></span>
            <h1><?= e($heading) ?></h1>
            <p><?= e($message) ?></p>
            <div class="state__actions">
                <a class="btn btn--primary" href="<?= e(url('')) ?>"><?= icon('arrow-left', 18) ?> Back to home</a>
                <a class="btn btn--ghost" href="<?= e(url('restore.php')) ?>"><?= icon('refresh', 18) ?> Restore access</a>
            </div>
        </div>
    </div>
</section>
