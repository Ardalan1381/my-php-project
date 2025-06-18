<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// چک کردن وضعیت کاربر
$user_logged_in = isset($_SESSION['user_id']);
if ($user_logged_in) {
    $stmt = $db->prepare("SELECT `F-Name` FROM Users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | آستور</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
        .header {
            background: linear-gradient(90deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        .nav-link {
            transition: color 0.3s ease, transform 0.2s ease;
            text-align: center;
        }
        .nav-link:hover {
            color: #93c5fd;
            transform: scale(1.05);
        }
        .btn {
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .mobile-menu {
            background: rgba(75, 85, 99, 0.95);
            backdrop-filter: blur(10px);
        }
        .ls_home_as {
            margin-left: 24px;
        }
    </style>
</head>
<body>
    <header class="header sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex items-center justify-between">
            <!-- لوگو -->
            <a href="/site/index.php" class="flex items-center space-x-3 transform hover:scale-105 transition-all duration-300">
                <i class="fas fa-shield-alt text-3xl text-blue-400"></i>
                <span class="text-2xl font-bold text-white">آستور</span>
            </a>

            <!-- منوی اصلی (دسکتاپ) -->
            <nav class="hidden md:flex items-center justify-center flex-1">
                <div class="flex items-center space-x-8">
                    <a href="/site/index.php" class="nav-link text-white font-medium ls_home_as">خانه</a>
                    <a href="/site/hotels.php" class="nav-link text-white font-medium">هتل‌ها</a>
                    <a href="/site/about.php" class="nav-link text-white font-medium">درباره ما</a>
                </div>
            </nav>

            <!-- دکمه‌های ورود/خروج (دسکتاپ) -->
            <div class="hidden md:flex items-center space-x-4">
                <?php if ($user_logged_in): ?>
                    <span class="text-blue-400 font-medium"><?php echo htmlspecialchars($current_user['F-Name']); ?></span>
                    <a href="/site/dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt mr-2"></i> داشبورد</a>
                    <a href="/site/logout.php" class="btn bg-red-600 text-white px-5 py-2 rounded-full hover:bg-red-700"><i class="fas fa-sign-out-alt mr-2"></i> خروج</a>
                <?php else: ?>
                    <a href="/site/login.php" class="btn bg-blue-600 text-white px-5 py-2 rounded-full hover:bg-blue-700"><i class="fas fa-sign-in-alt mr-2"></i> ورود</a>
                    <a href="/site/register.php" class="btn bg-blue-400 text-white px-5 py-2 rounded-full hover:bg-blue-500"><i class="fas fa-user-plus mr-2"></i> ثبت‌نام</a>
                <?php endif; ?>
            </div>

            <!-- دکمه منوی موبایل -->
            <button id="mobile-menu-btn" class="md:hidden text-white focus:outline-none">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>

        <!-- منوی موبایل -->
        <div id="mobile-menu" class="hidden md:hidden mobile-menu shadow-lg">
            <div class="px-6 py-6 space-y-6">
                <a href="/site/index.php" class="block text-white hover:text-blue-400 font-medium transition-colors duration-250">خانه</a>
                <a href="/site/hotels.php" class="block text-white hover:text-blue-400 font-medium transition-colors duration-250">هتل‌ها</a>
                <a href="/site/about.php" class="block text-white hover:text-blue-400 font-medium transition-colors duration-250">درباره ما</a>
                <?php if ($user_logged_in): ?>
                    <div class="border-t border-gray-600 pt-4 space-y-6">
                        <span class="block text-blue-400 font-medium"><?php echo htmlspecialchars($current_user['F-Name']); ?></span>
                        <a href="/site/dashboard.php" class="block text-white hover:text-blue-400 transition-colors duration-250"><i class="fas fa-tachometer-alt mr-2"></i> داشبورد</a>
                        <a href="/site/logout.php" class="block text-red-400 hover:text-red-500 transition-colors duration-250"><i class="fas fa-sign-out-alt mr-2"></i> خروج</a>
                    </div>
                <?php else: ?>
                    <div class="border-t border-gray-600 pt-4 space-y-6">
                        <a href="/site/login.php" class="block text-blue-400 hover:text-blue-500 transition-colors duration-250"><i class="fas fa-sign-in-alt mr-2"></i> ورود</a>
                        <a href="/site/register.php" class="block text-blue-400 hover:text-blue-500 transition-colors duration-250"><i class="fas fa-user-plus mr-2"></i> ثبت‌نام</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>