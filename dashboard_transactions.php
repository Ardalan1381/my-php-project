<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

// فعال کردن لاگ‌های PHP برای دیباگ
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// تنظیم مسیر لاگ‌ها
$log_dir = __DIR__ . '/../logs';
$log_file = $log_dir . '/php_errors.log';

// بررسی وجود پوشه logs و ایجاد آن در صورت عدم وجود
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// تنظیم مسیر لاگ
ini_set('error_log', $log_file);

function debug_log($message) {
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/php_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    if (is_writable($log_dir)) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $log_file);
    }
}

if (!isset($_SESSION['user_id'])) {
    debug_log("User not logged in");
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> لطفاً وارد شوید!</p>';
    header('Location: /site/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
debug_log("User ID: $user_id");

// بررسی وجود کاربر
try {
    $stmt = $db->prepare("SELECT `F-Name`, `L-Name`, balance FROM Users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        debug_log("User not found for user_id: $user_id");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> کاربر یافت نشد!</p>';
        header('Location: /site/login.php');
        exit;
    }
    debug_log("User found: " . json_encode($user));
} catch (Exception $e) {
    debug_log("Error fetching user: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری اطلاعات کاربر: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/login.php');
    exit;
}

// تنظیمات صفحه‌بندی
$per_page = 10; // تعداد تراکنش‌ها در هر صفحه
$page = isset($_GET['page']) ? max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)) : 1;
$offset = ($page - 1) * $per_page;

// فیلترها
$filter_type = isset($_GET['filter_type']) ? filter_input(INPUT_GET, 'filter_type', FILTER_SANITIZE_STRING) : 'all';
$filter_start_date = isset($_GET['start_date']) ? filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) : '';
$filter_end_date = isset($_GET['end_date']) ? filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) : '';

$conditions = [];
$params = ['user_id' => $user_id];

// فیلتر نوع تراکنش
if ($filter_type !== 'all') {
    $conditions[] = "transaction_type = :transaction_type";
    $params['transaction_type'] = $filter_type;
}

// فیلتر بازه زمانی
if ($filter_start_date) {
    $conditions[] = "transaction_date >= :start_date";
    $params['start_date'] = $filter_start_date;
}
if ($filter_end_date) {
    $conditions[] = "transaction_date <= :end_date";
    $params['end_date'] = $filter_end_date . ' 23:59:59';
}

// ساخت کوئری با شرایط
$where_clause = !empty($conditions) ? 'AND ' . implode(' AND ', $conditions) : '';

// شمارش کل تراکنش‌ها برای صفحه‌بندی
try {
    $count_query = "SELECT COUNT(*) FROM Wallet_Transactions WHERE user_id = :user_id $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_transactions = $stmt->fetchColumn();
    $total_pages = ceil($total_transactions / $per_page);
    debug_log("Total transactions: $total_transactions, Total pages: $total_pages");
} catch (Exception $e) {
    debug_log("Error counting transactions: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در شمارش تراکنش‌ها: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// بارگذاری تراکنش‌ها
try {
    $query = "SELECT amount, transaction_type, description, transaction_date 
              FROM Wallet_Transactions 
              WHERE user_id = :user_id $where_clause 
              ORDER BY transaction_date DESC 
              LIMIT :offset, :per_page";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        if ($key !== 'user_id') {
            $stmt->bindValue(":$key", $value);
        }
    }
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log("Transactions fetched: " . count($transactions));
} catch (Exception $e) {
    debug_log("Error fetching transactions: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری تراکنش‌ها: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// تابع برای انتخاب آیکون و ترجمه نوع تراکنش
function getTransactionDetails($type) {
    switch ($type) {
        case 'DEPOSIT':
            return ['icon' => 'fa-arrow-down', 'label' => 'واریز'];
        case 'WITHDRAW':
            return ['icon' => 'fa-arrow-up', 'label' => 'برداشت'];
        case 'BOOKING':
            return ['icon' => 'fa-hotel', 'label' => 'رزرو'];
        case 'REDEEM':
            return ['icon' => 'fa-gift', 'label' => 'کد تخفیف'];
        case 'REFUND':
            return ['icon' => 'fa-undo', 'label' => 'عودت'];
        case 'CANCELLATION_REFUND':
            return ['icon' => 'fa-undo', 'label' => 'عودت (لغو رزرو)'];
        default:
            return ['icon' => 'fa-exchange-alt', 'label' => $type];
    }
}

// تابع برای تبدیل تاریخ میلادی به شمسی (روش ساده)
function toPersianDate($datetime) {
    $date = new DateTime($datetime);
    $timestamp = $date->getTimestamp();
    
    // تبدیل به تاریخ شمسی با استفاده از تابع jdf (فرض می‌کنیم داری)
    if (function_exists('jdate')) {
        return jdate('Y/m/d H:i', $timestamp);
    }
    
    // اگه jdf در دسترس نیست، تاریخ میلادی رو با فرمت بهتر برگردون
    return $date->format('Y-m-d H:i');
}

// هدر برای جلوگیری از کش
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// هدر صفحه
$page_title = "تراکنش‌ها - آستور";
include __DIR__ . '/header.php';
?>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-6"><i class="fas fa-wallet mr-2 text-blue-400"></i> تراکنش‌های شما</h2>

        <!-- نمایش پیام‌های موفقیت یا خطا -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="animate-fade-in">
                <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="animate-fade-in">
                <?php echo $_SESSION['error_message']; ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- نمایش موجودی کاربر -->
        <div class="mb-6 p-4 bg-gray-700 bg-opacity-50 rounded-lg">
            <p class="text-gray-300 text-sm sm:text-base"><strong>موجودی شما:</strong> <?php echo number_format($user['balance'] ?? 0) . ' تومان'; ?></p>
        </div>

        <!-- فرم فیلتر -->
        <form method="GET" action="/site/dashboard_transactions.php" class="mb-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
            <select name="filter_type" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>همه تراکنش‌ها</option>
                <option value="DEPOSIT" <?php echo $filter_type === 'DEPOSIT' ? 'selected' : ''; ?>>واریز</option>
                <option value="WITHDRAW" <?php echo $filter_type === 'WITHDRAW' ? 'selected' : ''; ?>>برداشت</option>
                <option value="BOOKING" <?php echo $filter_type === 'BOOKING' ? 'selected' : ''; ?>>رزرو</option>
                <option value="REDEEM" <?php echo $filter_type === 'REDEEM' ? 'selected' : ''; ?>>کد تخفیف</option>
                <option value="REFUND" <?php echo $filter_type === 'REFUND' ? 'selected' : ''; ?>>عودت</option>
                <option value="CANCELLATION_REFUND" <?php echo $filter_type === 'CANCELLATION_REFUND' ? 'selected' : ''; ?>>عودت (لغو رزرو)</option>
            </select>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base">
                <i class="fas fa-filter mr-2"></i> فیلتر
            </button>
        </form>

        <!-- لیست تراکنش‌ها -->
        <div class="space-y-6">
            <?php if (empty($transactions)): ?>
                <div class="text-center p-6 bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md border border-gray-700">
                    <i class="fas fa-wallet text-gray-400 text-3xl sm:text-4xl mb-4"></i>
                    <p class="text-gray-300 text-base sm:text-lg">هنوز تراکنشی ندارید!</p>
                    <p class="text-sm text-gray-500 mt-2">با رزرو یا استفاده از کد تخفیف شروع کنید.</p>
                </div>
            <?php else: ?>
                <?php foreach ($transactions as $transaction): ?>
                    <?php $details = getTransactionDetails($transaction['transaction_type']); ?>
                    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-4 sm:p-6 rounded-xl shadow-md border border-gray-700 hover:shadow-lg transition-all duration-300">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-4 sm:space-y-0">
                            <div class="flex items-center space-x-4">
                                <i class="fas <?php echo $details['icon']; ?> text-blue-400 text-xl sm:text-2xl"></i>
                                <div>
                                    <p class="text-white font-semibold text-base sm:text-lg"><?php echo htmlspecialchars($transaction['description']); ?></p>
                                    <p class="text-gray-400 text-xs sm:text-sm"><?php echo toPersianDate($transaction['transaction_date']); ?></p>
                                    <p class="text-gray-500 text-xs sm:text-sm"><?php echo $details['label']; ?></p>
                                </div>
                            </div>
                            <div class="text-left sm:text-right">
                                <?php $amount_class = $transaction['amount'] >= 0 ? 'text-green-400' : 'text-red-400'; ?>
                                <p class="<?php echo $amount_class; ?> font-semibold text-base sm:text-lg"><?php echo number_format($transaction['amount']) . ' تومان'; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center space-x-2">
                <?php if ($page > 1): ?>
                    <a href="/site/dashboard_transactions.php?page=<?php echo $page - 1; ?>&filter_type=<?php echo urlencode($filter_type); ?>&start_date=<?php echo urlencode($filter_start_date); ?>&end_date=<?php echo urlencode($filter_end_date); ?>" 
                       class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-all duration-300">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="/site/dashboard_transactions.php?page=<?php echo $i; ?>&filter_type=<?php echo urlencode($filter_type); ?>&start_date=<?php echo urlencode($filter_start_date); ?>&end_date=<?php echo urlencode($filter_end_date); ?>" 
                       class="px-4 py-2 rounded-lg <?php echo $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-700 text-white hover:bg-gray-600'; ?> transition-all duration-300">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="/site/dashboard_transactions.php?page=<?php echo $page + 1; ?>&filter_type=<?php echo urlencode($filter_type); ?>&start_date=<?php echo urlencode($filter_start_date); ?>&end_date=<?php echo urlencode($filter_end_date); ?>" 
                       class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-all duration-300">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in;
    }
</style>

<?php include __DIR__ . '/footer.php'; ?>