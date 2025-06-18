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

// بررسی داده‌های ارسالی از فرم (درخواست اولیه برای لغو)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['confirm_cancellation'])) {
    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $reservation_type = filter_input(INPUT_POST, 'reservation_type', FILTER_SANITIZE_STRING);
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : null;
    $check_in_date = filter_input(INPUT_POST, 'check_in_date', FILTER_SANITIZE_STRING);
    $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
    $reservation_name = filter_input(INPUT_POST, 'reservation_name', FILTER_SANITIZE_STRING);

    // لاگ کردن داده‌های ارسالی برای دیباگ
    debug_log("Received data: reservation_id=$reservation_id, reservation_type=$reservation_type, total_price=$total_price, check_in_date=$check_in_date, room_type_id=$room_type_id, reservation_name=$reservation_name");
    debug_log("is_numeric(total_price): " . var_export(is_numeric($total_price), true));

    // اعتبارسنجی ورودی‌ها
    if (
        !$reservation_id || 
        !$reservation_type || 
        ($total_price === null || !is_numeric($total_price)) || 
        !$check_in_date || 
        !$reservation_name
    ) {
        debug_log("Validation failed: Invalid reservation data");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> اطلاعات رزرو نامعتبر است!</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    }

    // اعتبارسنجی فرمت تاریخ
    debug_log("Attempting to create DateTime with check_in_date: $check_in_date");
    try {
        $current_date = new DateTime();
        if (!DateTime::createFromFormat('Y-m-d', $check_in_date)) {
            throw new Exception("فرمت تاریخ نامعتبر است: $check_in_date");
        }
        $check_in = new DateTime($check_in_date);
        $interval = $current_date->diff($check_in);
        $days_left = $interval->days;
        if ($current_date > $check_in) {
            $days_left = 0;
        }
        debug_log("Days left: $days_left");
    } catch (Exception $e) {
        debug_log("Error calculating days left: " . $e->getMessage());
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در محاسبه تاریخ: ' . htmlspecialchars($e->getMessage()) . '</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    }

    // پیدا کردن سیاست لغو مناسب
    try {
        $stmt = $db->prepare("SELECT penalty_percentage FROM Cancellation_Policies WHERE min_days_before <= :days_left ORDER BY min_days_before DESC LIMIT 1");
        $stmt->execute(['days_left' => $days_left]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        $penalty_percentage = $policy ? $policy['penalty_percentage'] : 100;
        debug_log("Penalty percentage: $penalty_percentage");
    } catch (Exception $e) {
        debug_log("Error fetching cancellation policy: " . $e->getMessage());
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در دریافت سیاست لغو: ' . htmlspecialchars($e->getMessage()) . '</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    }

    // محاسبه جریمه و مبلغ قابل بازگشت
    $penalty_amount = ($penalty_percentage / 100) * $total_price;
    $refund_amount = $total_price - $penalty_amount;
    debug_log("Penalty amount: $penalty_amount, Refund amount: $refund_amount");

    // ذخیره داده‌ها در سشن
    $_SESSION['cancellation_data'] = [
        'reservation_id' => $reservation_id,
        'reservation_type' => $reservation_type,
        'total_price' => $total_price,
        'check_in_date' => $check_in_date,
        'room_type_id' => $room_type_id,
        'reservation_name' => $reservation_name,
        'penalty_amount' => $penalty_amount,
        'refund_amount' => $refund_amount,
    ];

    // ریدایرکت به صفحه تأیید با روش GET
    header('Location: /site/confirm_cancellation.php?action=confirm');
    exit;
}

// نمایش صفحه تأیید یا پردازش لغو
if (isset($_GET['action']) && $_GET['action'] === 'confirm' && isset($_SESSION['cancellation_data'])) {
    // بازیابی داده‌ها از سشن
    $data = $_SESSION['cancellation_data'];
    $reservation_id = $data['reservation_id'];
    $reservation_type = $data['reservation_type'];
    $total_price = $data['total_price'];
    $check_in_date = $data['check_in_date'];
    $room_type_id = $data['room_type_id'];
    $reservation_name = $data['reservation_name'];
    $penalty_amount = $data['penalty_amount'];
    $refund_amount = $data['refund_amount'];
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_cancellation'])) {
    // پردازش تأیید لغو
    if (!isset($_SESSION['cancellation_data'])) {
        debug_log("Cancellation data not found in session");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> اطلاعات رزرو نامعتبر است!</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    }

    $data = $_SESSION['cancellation_data'];
    $reservation_id = $data['reservation_id'];
    $reservation_type = $data['reservation_type'];
    $total_price = $data['total_price'];
    $check_in_date = $data['check_in_date'];
    $room_type_id = $data['room_type_id'];
    $penalty_amount = $data['penalty_amount'];
    $refund_amount = $data['refund_amount'];

    // اعتبارسنجی
    if (
        !$reservation_id || 
        !$reservation_type || 
        ($total_price === null || !is_numeric($total_price)) || 
        !$check_in_date || 
        ($penalty_amount === null || !is_numeric($penalty_amount)) || 
        ($refund_amount === null || !is_numeric($refund_amount))
    ) {
        debug_log("Validation failed: Invalid confirmation data");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> اطلاعات تأیید نامعتبر است!</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    }

    // شروع تراکنش
    try {
        $db->beginTransaction();
        debug_log("Transaction started");

        // خواندن مقدار فعلی balance با قفل
        $stmt = $db->prepare("SELECT balance FROM Users WHERE user_id = :user_id FOR UPDATE");
        $stmt->execute(['user_id' => $user_id]);
        $current_balance = $stmt->fetchColumn();
        debug_log("Current balance before update: $current_balance");

        // محاسبه مقدار جدید
        $new_balance = $current_balance + $refund_amount;
        debug_log("New balance: $new_balance");

        // به‌روزرسانی موجودی کاربر
        $stmt = $db->prepare("UPDATE Users SET balance = :new_balance WHERE user_id = :user_id");
        $stmt->execute(['new_balance' => $new_balance, 'user_id' => $user_id]);
        $updated_rows = $stmt->rowCount();
        debug_log("User balance update - Rows affected: $updated_rows, New balance: $new_balance, User ID: $user_id");

        // فقط اگه کاربر وجود نداشته باشه خطا بندازیم
        if ($updated_rows === 0 && $current_balance != $new_balance) {
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM Users WHERE user_id = :user_id");
            $stmt_check->execute(['user_id' => $user_id]);
            $user_exists = $stmt_check->fetchColumn();
            if ($user_exists == 0) {
                throw new Exception("کاربر با شناسه $user_id وجود ندارد!");
            }
            throw new Exception("به‌روزرسانی موجودی کاربر انجام نشد! ممکن است مشکلی در دیتابیس باشد.");
        }

        // ثبت تراکنش
        $description = "لغو رزرو $reservation_type (شناسه: $reservation_id) - جریمه: " . number_format($penalty_amount) . " تومان";
        $stmt = $db->prepare("INSERT INTO Wallet_Transactions (user_id, amount, transaction_type, description) 
                              VALUES (:user_id, :amount, 'CANCELLATION_REFUND', :description)");
        $stmt->execute(['user_id' => $user_id, 'amount' => $refund_amount, 'description' => $description]);
        $inserted_rows = $stmt->rowCount();
        debug_log("Transaction insert - Rows affected: $inserted_rows");
        if ($inserted_rows === 0) {
            throw new Exception("ثبت تراکنش انجام نشد!");
        }

        // حذف رزرو
        if ($reservation_type == 'Hotel') {
            $stmt = $db->prepare("DELETE FROM Reservations WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
            $deleted_rows = $stmt->rowCount();
            debug_log("Hotel reservation delete - Rows affected: $deleted_rows");
            if ($deleted_rows === 0) {
                throw new Exception("رزرو هتل یافت نشد یا متعلق به شما نیست!");
            }
            if ($room_type_id) {
                $stmt = $db->prepare("UPDATE Room_Types SET available_count = available_count + 1 WHERE room_type_id = :room_type_id");
                $stmt->execute(['room_type_id' => $room_type_id]);
                $updated_room_rows = $stmt->rowCount();
                debug_log("Room availability update - Rows affected: $updated_room_rows");
                if ($updated_room_rows === 0) {
                    throw new Exception("به‌روزرسانی تعداد اتاق‌ها انجام نشد!");
                }
            }
        } elseif ($reservation_type == 'Doctor') {
            $stmt = $db->prepare("DELETE FROM Medical_Reservations WHERE medical_reservation_id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
            $deleted_rows = $stmt->rowCount();
            debug_log("Doctor reservation delete - Rows affected: $deleted_rows");
            if ($deleted_rows === 0) {
                throw new Exception("رزرو پزشک یافت نشد یا متعلق به شما نیست!");
            }
        } elseif ($reservation_type == 'Hospital') {
            $stmt = $db->prepare("DELETE FROM Hospital_Reservations WHERE hospital_reservation_id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
            $deleted_rows = $stmt->rowCount();
            debug_log("Hospital reservation delete - Rows affected: $deleted_rows");
            if ($deleted_rows === 0) {
                throw new Exception("رزرو بیمارستان یافت نشد یا متعلق به شما نیست!");
            }
        } else {
            throw new Exception("نوع رزرو نامعتبر است!");
        }

        $db->commit();
        debug_log("Transaction committed successfully");
        // پاک کردن داده‌های سشن
        unset($_SESSION['cancellation_data']);
        $_SESSION['success_message'] = '<p class="text-green-400 bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-green-700"><i class="fas fa-check-circle mr-2"></i> رزرو با موفقیت لغو شد! مبلغ ' . number_format($refund_amount) . ' تومان به حساب شما بازگشت داده شد.</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        debug_log("Transaction failed: " . $e->getMessage());
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در لغو رزرو: ' . htmlspecialchars($e->getMessage()) . '</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    }
} else {
    debug_log("Invalid request: Method=" . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> اطلاعات رزرو نامعتبر است!</p>';
    header('Location: /site/dashboard_reservations.php');
    exit;
}

// هدر صفحه
$page_title = "تأیید لغو رزرو - آستور";
include __DIR__ . '/header.php';
?>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-4"><i class="fas fa-exclamation-triangle mr-2 text-yellow-400"></i> تأیید لغو رزرو</h2>
        <p class="text-gray-300 mb-4">شما در حال لغو رزرو زیر هستید. لطفاً اطلاعات را بررسی کنید:</p>
        <div class="bg-gray-700 bg-opacity-50 p-4 rounded-lg mb-6">
            <p class="text-gray-300"><strong>نام:</strong> <?php echo htmlspecialchars($reservation_name); ?></p>
            <?php if ($reservation_type == 'Hotel'): ?>
                <p class="text-gray-300"><strong>تاریخ ورود:</strong> <?php echo htmlspecialchars($check_in_date); ?></p>
            <?php elseif ($reservation_type == 'Doctor'): ?>
                <p class="text-gray-300"><strong>تاریخ نوبت:</strong> <?php echo htmlspecialchars($check_in_date); ?></p>
            <?php else: ?>
                <p class="text-gray-300"><strong>تاریخ رزرو:</strong> <?php echo htmlspecialchars($check_in_date); ?></p>
            <?php endif; ?>
            <p class="text-gray-300"><strong>مبلغ کل:</strong> <?php echo number_format($total_price) . ' تومان'; ?></p>
            <p class="text-yellow-400"><strong>جریمه لغو:</strong> <?php echo number_format($penalty_amount) . ' تومان'; ?></p>
            <p class="text-green-400"><strong>مبلغ بازگشتی:</strong> <?php echo number_format($refund_amount) . ' تومان'; ?></p>
        </div>
        <form method="POST" action="/site/confirm_cancellation.php">
            <input type="hidden" name="confirm_cancellation" value="1">
            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                <button type="submit" class="bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-check mr-2"></i> تأیید لغو</button>
                <a href="/site/dashboard_reservations.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-times mr-2"></i> انصراف</a>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>