<?php
session_start();
require_once __DIR__ . '/includes/db.php';
$page_title = "پروفایل - آستور";
include __DIR__ . '/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT username, `F-Name`, `L-Name`, Phone, address, video_path, verification_status FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = filter_input(INPUT_POST, 'fname', FILTER_SANITIZE_STRING);
    $lname = filter_input(INPUT_POST, 'lname', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $video = $_FILES['video'] ?? null;

    $updates = [
        'fname' => $fname,
        'lname' => $lname,
        'address' => $address,
        'user_id' => $user_id
    ];

    // اعتبارسنجی
    if (empty($fname) || empty($lname)) {
        $errors[] = "نام و نام خانوادگی اجباری هستند.";
    }

    // آپلود ویدیو فقط برای Not Verified
    if ($video && $video['error'] === UPLOAD_ERR_OK) {
        if ($user['verification_status'] !== 'Not Verified') {
            $errors[] = "شما قبلاً احراز هویت شده‌اید یا درخواست شما در حال بررسی است.";
        } else {
            $allowed_types = ['video/mp4', 'video/mpeg', 'video/webm'];
            $max_size = 10 * 1024 * 1024; // 10MB
            if (!in_array($video['type'], $allowed_types)) {
                $errors[] = "فقط فایل‌های ویدیویی (MP4, MPEG, WebM) مجاز هستند.";
            } elseif ($video['size'] > $max_size) {
                $errors[] = "حجم ویدیو باید کمتر از 10 مگابایت باشد.";
            } else {
                $upload_dir = __DIR__ . '/uploads/verification/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $video_name = uniqid() . '_' . basename($video['name']);
                $video_path = '/site/uploads/verification/' . $video_name;
                if (move_uploaded_file($video['tmp_name'], $upload_dir . $video_name)) {
                    $updates['video_path'] = $video_path;
                } else {
                    $errors[] = "خطا در آپلود ویدیو!";
                }
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE Users SET 
                `F-Name` = :fname, 
                `L-Name` = :lname, 
                address = :address" . (isset($updates['video_path']) ? ", video_path = :video_path" : "") . " 
                WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute($updates);
        $success = true;

        // آپدیت اطلاعات کاربر برای نمایش
        $stmt = $db->prepare("SELECT username, `F-Name`, `L-Name`, Phone, address, video_path, verification_status FROM Users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
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
        input, textarea {
            background: #374151;
            color: #e2e8f0;
            border: 1px solid #4b5563;
            border-radius: 8px;
            padding: 0.5rem;
            width: 100%;
        }
        .error {
            background: #ef4444;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .success {
            background: #10b981;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
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
                <span class="text-gray-300 text-sm"><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="/site/logout.php" class="text-red-400 hover:text-red-300"><i class="fas fa-sign-out-alt text-lg"></i></a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <div class="card p-6 max-w-2xl mx-auto">
                <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-user mr-2 text-blue-400"></i> پروفایل شما</h2>
                
                <?php if ($success): ?>
                    <div class="success text-white">پروفایل با موفقیت به‌روزرسانی شد!</div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="error text-white">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($user['verification_status'] === 'Not Verified' && !$user['video_path']): ?>
                    <div class="text-yellow-300 bg-yellow-900 bg-opacity-20 p-4 rounded-lg mb-4">لطفاً ویدیوی احراز هویت خود را آپلود کنید.</div>
                <?php elseif ($user['verification_status'] === 'Confirm'): ?>
                    <div class="text-green-300 bg-green-900 bg-opacity-20 p-4 rounded-lg mb-4">شما تأیید شده‌اید!</div>
                <?php elseif ($user['verification_status'] === 'Rejected'): ?>
                    <div class="text-red-300 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-4">درخواست احراز هویت شما رد شده است.</div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-300">نام</label>
                        <input type="text" name="fname" value="<?php echo htmlspecialchars($user['F-Name']); ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">نام خانوادگی</label>
                        <input type="text" name="lname" value="<?php echo htmlspecialchars($user['L-Name']); ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">شماره تلفن</label>
                        <input type="text" value="<?php echo '0' . htmlspecialchars($user['Phone']); ?>" readonly class="bg-gray-600 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">آدرس</label>
                        <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <?php if ($user['verification_status'] === 'Not Verified'): ?>
                        <div>
                            <label class="block text-sm text-gray-300">ویدیوی احراز هویت (حداکثر 10 مگابایت)</label>
                            <?php if ($user['video_path']): ?>
                                <video controls class="mb-2">
                                    <source src="<?php echo htmlspecialchars($user['video_path']); ?>" type="video/mp4">
                                    مرورگر شما از ویدیو پشتیبانی نمی‌کند.
                                </video>
                                <p class="text-gray-400 text-sm">ویدیوی شما در انتظار تأیید است.</p>
                            <?php else: ?>
                                <input type="file" name="video" accept="video/*">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn-action bg-blue-500 text-white p-2 rounded-lg w-full hover:bg-blue-600"><i class="fas fa-save mr-2"></i> ذخیره</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>