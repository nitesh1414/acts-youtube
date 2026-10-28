<?php require_once 'config.php';
$settings = getSettings($pdo);

// Extract YouTube Video ID
function getYoutubeId($url) {
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user|shorts)\/))([^\?&\"'>]{11})/", $url, $matches);
    return $matches[1] ?? null;
}

$videoId = getYoutubeId($settings['youtube_link'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - <?= htmlspecialchars($settings['logo_text']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            border: none;
        }
        .check-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
            font-size: 50px;
            animation: popIn 0.6s ease-out;
        }
        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .video-container iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
        }
        .btn-subscribe {
            background: #FF0000;
            border: none;
            border-radius: 50px;
            padding: 14px 32px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-subscribe:hover {
            background: #CC0000;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255,0,0,0.3);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-4">
    <div class="card success-card col-12 col-lg-8 col-xl-7">
        <div class="card-body p-5 text-center">
            <div class="check-icon mb-4">✓</div>
            <h1 class="display-5 fw-bold text-success mb-2">Payment Successful!</h1>
            <p class="text-muted mb-4 fs-5">Thank you for your purchase. Enjoy the exclusive video below!</p>

            <?php if ($videoId): ?>
                <div class="video-container mb-4">
                    <iframe 
                        src="https://www.youtube.com/embed/<?= htmlspecialchars($videoId) ?>?autoplay=1&rel=0" 
                        title="Exclusive Video" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen>
                    </iframe>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-4">
                    ⚠️ Invalid or missing YouTube link. Please contact the administrator.
                </div>
            <?php endif; ?>

            <p class="fs-5 mb-4">Enjoyed this content? Don't forget to subscribe!</p>
            <?php if (!empty($settings['youtube_link'])): ?>
                <a href="<?= htmlspecialchars($settings['youtube_link']) ?>" target="_blank" class="btn btn-danger btn-subscribe text-white">
                    🔔 Subscribe to our Channel
                </a>
            <?php endif; ?>

            <div class="mt-4">
                <a href="index.php" class="text-muted text-decoration-none">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
