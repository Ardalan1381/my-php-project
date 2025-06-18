<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Moderator') {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT username, role FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$username = $current_user['username'];
$role = $current_user['role'];

// دریافت کاربرانی که در انتظار تأیید هویت هستن
$stmt = $db->prepare("SELECT user_id, username, `F-Name`, `L-Name`, video_path, verification_status 
                      FROM Users WHERE verification_status = 'Not Verified' AND video_path IS NOT NULL");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// مدیریت تأیید/رد درخواست
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = $_POST['user_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($target_user_id && in_array($action, ['confirm', 'reject'])) {
        $new_status = $action === 'confirm' ? 'Confirm' : 'Rejected';
        $stmt = $db->prepare("UPDATE Users SET verification_status = :status WHERE user_id = :user_id");
        $stmt->execute(['status' => $new_status, 'user_id' => $target_user_id]);
        header("Location: /site/admin/verification_requests.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست‌های احراز هویت - آستور</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('/site/assets/fonts/Vazir-Regular.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        body {
            font-family: 'Vazir', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
        }
        .card {
            background: rgba(75, 85, 99, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .header {
            background: linear-gradient(90deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        .btn-action {
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-action:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: right;
        }
        th {
            background: rgba(55, 65, 81, 0.9);
        }
        tr:nth-child(even) {
            background: rgba(55, 65, 81, 0.3);
        }
        .popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .popup-content {
            background: #1e293b;
            padding: 1rem;
            border-radius: 16px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
        }
        video {
            max-width: 100%;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <!-- هدر -->
        <header class="header p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-50">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-2xl text-blue-400 mr-2"></i>
                <h1 class="text-xl font-bold text-white">آستور</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300 text-sm"><?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
                <a href="/site/admin/users.php" class="text-white hover:text-blue-300"><i class="fas fa-arrow-right text-lg"></i></a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <div class="card p-6">
                <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-video mr-2 text-blue-400"></i> درخواست‌های احراز هویت</h2>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نام کاربری</th>
                                <th>نام</th>
                                <th>نام خانوادگی</th>
                                <th>ویدیو</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($request['username']); ?></td>
                                    <td><?php echo htmlspecialchars($request['F-Name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['L-Name']); ?></td>
                                    <td>
                                        <button class="btn-action text-blue-300 hover:text-blue-100" onclick="showVideo('<?php echo htmlspecialchars($request['video_path']); ?>')">نمایش</button>
                                    </td>
                                    <td>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                            <button type="submit" name="action" value="confirm" class="btn-action text-green-300 hover:text-green-100 mr-2"><i class="fas fa-check"></i></button>
                                            <button type="submit" name="action" value="reject" class="btn-action text-red-300 hover:text-red-100"><i class="fas fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">درخواستی یافت نشد!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- پاپ‌آپ ویدیو -->
    <div id="videoPopup" class="popup">
        <div class="popup-content">
            <button onclick="hideVideo()" class="text-red-400 hover:text-red-300 mb-2"><i class="fas fa-times"></i> بستن</button>
            <video controls id="popupVideo">
                <source id="videoSource" src="" type="video/mp4">
                مرورگر شما از ویدیو پشتیبانی نمی‌کند.
            </video>
        </div>
    </div>

    <script>
        function showVideo(src) {
            const popup = document.getElementById('videoPopup');
            const videoSource = document.getElementById('videoSource');
            const video = document.getElementById('popupVideo');
            videoSource.src = src;
            video.load();
            popup.style.display = 'flex';
        }

        function hideVideo() {
            const popup = document.getElementById('videoPopup');
            const video = document.getElementById('popupVideo');
            video.pause();
            popup.style.display = 'none';
        }
    </script>
</body>
</html>