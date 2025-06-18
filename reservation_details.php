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

// بررسی وجود کاربر و موجودی
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

// دریافت اطلاعات رزرو از پارامترهای GET
$reservation_id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) : null;
$reservation_type = isset($_GET['type']) ? strtolower(filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING)) : null;

if (!$reservation_id || !$reservation_type || !in_array($reservation_type, ['hotel', 'hospital', 'doctor'])) {
    $error_reason = [];
    if (!$reservation_id) $error_reason[] = "ID is missing or invalid";
    if (!$reservation_type) $error_reason[] = "Type is missing";
    if ($reservation_type && !in_array($reservation_type, ['hotel', 'hospital', 'doctor'])) $error_reason[] = "Type is invalid (value: $reservation_type)";
    debug_log("Invalid reservation parameters: id=$reservation_id, type=$reservation_type, reasons: " . implode(", ", $error_reason));
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> درخواست نامعتبر!</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// بارگذاری جزئیات رزرو
$reservation = null;
$is_cancelled = false;
try {
    if ($reservation_type === 'hotel') {
        $stmt = $db->prepare("SELECT r.id, r.hotel_id, h.hotel_name, h.hotel_address, r.check_in_date, r.check_out_date, r.total_price, r.num_guests, rt.room_type as room_type_name, r.created_at, r.guest_list 
                              FROM Reservations r 
                              JOIN Hotels h ON r.hotel_id = h.hotel_id 
                              LEFT JOIN Room_Types rt ON r.room_type_id = rt.room_type_id 
                              WHERE r.id = :id AND r.user_id = :user_id");
        $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reservation) {
            // بررسی تراکنش‌های لغو برای این رزرو
            $stmt = $db->prepare("SELECT description FROM Wallet_Transactions 
                                  WHERE user_id = :user_id AND transaction_type = 'CANCELLATION_REFUND' 
                                  AND description LIKE :pattern");
            $stmt->execute([
                'user_id' => $user_id,
                'pattern' => "%لغو رزرو hotel (شناسه: $reservation_id)%"
            ]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $is_cancelled = true;
            }
        }
    } elseif ($reservation_type === 'hospital') {
        $stmt = $db->prepare("SELECT hr.hospital_reservation_id as id, hr.hospital_id, h.name as hospital_name, h.location, hr.check_in_date, hr.total_price, hr.created_at 
                              FROM Hospital_Reservations hr 
                              JOIN Hospitals h ON hr.hospital_id = h.hospital_id 
                              WHERE hr.hospital_reservation_id = :id AND hr.user_id = :user_id");
        $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reservation) {
            $stmt = $db->prepare("SELECT description FROM Wallet_Transactions 
                                  WHERE user_id = :user_id AND transaction_type = 'CANCELLATION_REFUND' 
                                  AND description LIKE :pattern");
            $stmt->execute([
                'user_id' => $user_id,
                'pattern' => "%لغو رزرو hospital (شناسه: $reservation_id)%"
            ]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $is_cancelled = true;
            }
        }
    } elseif ($reservation_type === 'doctor') {
        $stmt = $db->prepare("SELECT mr.medical_reservation_id as id, mr.doctor_id, d.name as doctor_name, d.specialty, mr.appointment_date, mr.status, mr.total_price, mr.created_at 
                              FROM Medical_Reservations mr 
                              JOIN Doctors d ON mr.doctor_id = d.doctor_id 
                              WHERE mr.medical_reservation_id = :id AND mr.user_id = :user_id");
        $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reservation && $reservation['status'] === 'Cancelled') {
            $is_cancelled = true;
        }
    }

    if (!$reservation && !$is_cancelled) {
        debug_log("Reservation not found: id=$reservation_id, type=$reservation_type");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> رزرو یافت نشد!</p>';
        header('Location: /site/dashboard.php');
        exit;
    }
    debug_log("Reservation found: " . json_encode($reservation));
} catch (Exception $e) {
    debug_log("Error fetching reservation: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری رزرو: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// تابع برای تبدیل تاریخ میلادی به شمسی (روش ساده)
function toPersianDate($datetime) {
    $date = new DateTime($datetime);
    $timestamp = $date->getTimestamp();
    
    // تبدیل به تاریخ شمسی با استفاده از تابع jdate (فرض می‌کنیم داری)
    if (function_exists('jdate')) {
        return jdate('Y/m/d H:i', $timestamp);
    }
    
    // اگه jdf در دسترس نیست، تاریخ میلادی رو با فرمت بهتر برگردون
    return $date->format('Y-m-d H:i');
}

// تابع برای محاسبه جریمه لغو
function calculateCancellationPenalty($reservation_date, $total_price) {
    global $db;
    
    $current_date = new DateTime();
    $reservation_datetime = new DateTime($reservation_date);
    $interval = $current_date->diff($reservation_datetime);
    $days_left = $interval->days * ($reservation_datetime > $current_date ? 1 : -1);

    // اگه تاریخ رزرو گذشته باشه، لغو ممکن نیست
    if ($days_left < 0) {
        return ['can_cancel' => false, 'penalty' => 0, 'refund' => 0];
    }

    // بارگذاری سیاست‌های لغو
    $stmt = $db->prepare("SELECT min_days_before, penalty_percentage FROM Cancellation_Policies ORDER BY min_days_before DESC");
    $stmt->execute();
    $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $penalty_percentage = 0;
    foreach ($policies as $policy) {
        if ($days_left >= $policy['min_days_before']) {
            $penalty_percentage = $policy['penalty_percentage'];
            break;
        }
    }

    $penalty = ($total_price * $penalty_percentage) / 100;
    $refund = $total_price - $penalty;

    return [
        'can_cancel' => true,
        'penalty' => $penalty,
        'refund' => $refund
    ];
}

// پردازش لغو رزرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_reservation'])) {
    try {
        $db->beginTransaction();

        // پیدا کردن رزرو (دوباره برای اطمینان)
        $reservation_date = null;
        $total_price = null;
        $name = null;

        if ($reservation_type === 'hotel') {
            $stmt = $db->prepare("SELECT r.total_price, r.check_in_date, h.hotel_name 
                                  FROM Reservations r 
                                  JOIN Hotels h ON r.hotel_id = h.hotel_id 
                                  WHERE r.id = :id AND r.user_id = :user_id");
            $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            $reservation_date = $reservation['check_in_date'];
            $total_price = $reservation['total_price'];
            $name = $reservation['hotel_name'];
        } elseif ($reservation_type === 'hospital') {
            $stmt = $db->prepare("SELECT hr.total_price, hr.check_in_date, h.name 
                                  FROM Hospital_Reservations hr 
                                  JOIN Hospitals h ON hr.hospital_id = h.hospital_id 
                                  WHERE hr.hospital_reservation_id = :id AND hr.user_id = :user_id");
            $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            $reservation_date = $reservation['check_in_date'];
            $total_price = $reservation['total_price'];
            $name = $reservation['name'];
        } elseif ($reservation_type === 'doctor') {
            $stmt = $db->prepare("SELECT mr.total_price, mr.appointment_date, d.name 
                                  FROM Medical_Reservations mr 
                                  JOIN Doctors d ON mr.doctor_id = d.doctor_id 
                                  WHERE mr.medical_reservation_id = :id AND mr.user_id = :user_id");
            $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            $reservation_date = $reservation['appointment_date'];
            $total_price = $reservation['total_price'];
            $name = $reservation['name'];
        }

        if (!$reservation) {
            throw new Exception("رزرو یافت نشد!");
        }

        // محاسبه جریمه
        $cancellation = calculateCancellationPenalty($reservation_date, $total_price);
        if (!$cancellation['can_cancel']) {
            throw new Exception("لغو رزرو ممکن نیست، زیرا تاریخ رزرو گذشته است!");
        }

        $penalty = $cancellation['penalty'];
        $refund = $cancellation['refund'];

        // به‌روزرسانی موجودی کاربر
        $stmt = $db->prepare("UPDATE Users SET balance = balance + :refund WHERE user_id = :user_id");
        $stmt->execute(['refund' => $refund, 'user_id' => $user_id]);

        // ثبت تراکنش
        $description = "لغو رزرو $reservation_type (شناسه: $reservation_id) - جریمه: " . number_format($penalty) . " تومان";
        $stmt = $db->prepare("INSERT INTO Wallet_Transactions (user_id, amount, transaction_type, description) 
                              VALUES (:user_id, :amount, 'CANCELLATION_REFUND', :description)");
        $stmt->execute([
            'user_id' => $user_id,
            'amount' => $refund,
            'description' => $description
        ]);

        // حذف یا به‌روزرسانی رزرو
        if ($reservation_type === 'hotel') {
            $stmt = $db->prepare("DELETE FROM Reservations WHERE id = :id AND user_id = :user_id");
        } elseif ($reservation_type === 'hospital') {
            $stmt = $db->prepare("DELETE FROM Hospital_Reservations WHERE hospital_reservation_id = :id AND user_id = :user_id");
        } elseif ($reservation_type === 'doctor') {
            $stmt = $db->prepare("UPDATE Medical_Reservations SET status = 'Cancelled' WHERE medical_reservation_id = :id AND user_id = :user_id");
        }
        $stmt->execute(['id' => $reservation_id, 'user_id' => $user_id]);

        $db->commit();
        debug_log("Reservation cancelled: type=$reservation_type, id=$reservation_id, penalty=$penalty, refund=$refund");
        $_SESSION['success_message'] = '<p class="text-green-400 bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-green-700"><i class="fas fa-check-circle mr-2"></i> رزرو با موفقیت لغو شد! مبلغ عودت‌شده: ' . number_format($refund) . ' تومان (جریمه: ' . number_format($penalty) . ' تومان)</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        debug_log("Cancellation failed: " . $e->getMessage());
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در لغو رزرو: ' . htmlspecialchars($e->getMessage()) . '</p>';
        header('Location: /site/reservation_details.php?id=' . $reservation_id . '&type=' . $reservation_type);
        exit;
    }
}

// هدر برای جلوگیری از کش
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// هدر صفحه
$page_title = "جزئیات رزرو - آستور";
include __DIR__ . '/header.php';
?>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-6"><i class="fas fa-book mr-2 text-blue-400"></i> جزئیات رزرو</h2>

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

        <!-- نمایش جزئیات رزرو -->
        <?php if ($is_cancelled): ?>
            <div class="text-center p-6 bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md border border-gray-700">
                <i class="fas fa-times-circle text-red-400 text-3xl sm:text-4xl mb-4"></i>
                <p class="text-gray-300 text-base sm:text-lg">این رزرو قبلاً لغو شده است!</p>
                <a href="/site/dashboard_reservations.php" class="inline-block mt-4 bg-gray-500 text-white px-3 py-1 rounded-lg hover:bg-gray-600 transition-all duration-300 text-sm sm:text-base">
                    <i class="fas fa-arrow-right mr-2"></i> بازگشت به لیست رزروها
                </a>
            </div>
        <?php else: ?>
            <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-4 sm:p-6 rounded-xl shadow-md border border-gray-700">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-4 sm:space-y-0">
                    <div class="flex items-center space-x-4">
                        <?php if ($reservation_type === 'hotel'): ?>
                            <i class="fas fa-hotel text-blue-400 text-xl sm:text-2xl"></i>
                            <div>
                                <p class="text-white font-semibold text-base sm:text-lg"><?php echo htmlspecialchars($reservation['hotel_name']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">آدرس: <?php echo htmlspecialchars($reservation['hotel_address']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ ورود: <?php echo toPersianDate($reservation['check_in_date']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ خروج: <?php echo toPersianDate($reservation['check_out_date']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تعداد مهمان‌ها: <?php echo $reservation['num_guests']; ?></p>
                                <?php
                                // نمایش لیست مهمان‌ها
                                if (!empty($reservation['guest_list'])) {
                                    $guests = json_decode($reservation['guest_list'], true);
                                    if ($guests && is_array($guests)) {
                                        echo '<p class="text-gray-400 text-xs sm:text-sm">لیست مهمان‌ها:</p>';
                                        echo '<ul class="text-gray-400 text-xs sm:text-sm list-disc list-inside">';
                                        foreach ($guests as $guest) {
                                            echo '<li>' . htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']) . ' (' . htmlspecialchars($guest['username']) . ')</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        debug_log("Invalid guest_list JSON for reservation id=$reservation_id: " . $reservation['guest_list']);
                                    }
                                }
                                ?>
                                <p class="text-gray-400 text-xs sm:text-sm">نوع اتاق: <?php echo htmlspecialchars($reservation['room_type_name'] ?? 'نامشخص'); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ رزرو: <?php echo toPersianDate($reservation['created_at']); ?></p>
                            </div>
                        <?php elseif ($reservation_type === 'hospital'): ?>
                            <i class="fas fa-hospital text-blue-400 text-xl sm:text-2xl"></i>
                            <div>
                                <p class="text-white font-semibold text-base sm:text-lg"><?php echo htmlspecialchars($reservation['hospital_name']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">موقعیت: <?php echo htmlspecialchars($reservation['location']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ رزرو: <?php echo toPersianDate($reservation['check_in_date']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ ایجاد: <?php echo toPersianDate($reservation['created_at']); ?></p>
                            </div>
                        <?php elseif ($reservation_type === 'doctor'): ?>
                            <i class="fas fa-user-md text-blue-400 text-xl sm:text-2xl"></i>
                            <div>
                                <p class="text-white font-semibold text-base sm:text-lg"><?php echo htmlspecialchars($reservation['doctor_name']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تخصص: <?php echo htmlspecialchars($reservation['specialty']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ وقت: <?php echo toPersianDate($reservation['appointment_date']); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">وضعیت: <?php echo $reservation['status'] === 'Pending' ? 'در انتظار' : ($reservation['status'] === 'Confirmed' ? 'تأیید شده' : 'لغو شده'); ?></p>
                                <p class="text-gray-400 text-xs sm:text-sm">تاریخ رزرو: <?php echo toPersianDate($reservation['created_at']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-left sm:text-right space-y-2">
                        <p class="text-blue-400 font-semibold text-base sm:text-lg"><?php echo number_format($reservation['total_price']) . ' تومان'; ?></p>
                        <?php
                        $reservation_date = $reservation_type === 'hotel' ? $reservation['check_in_date'] : ($reservation_type === 'hospital' ? $reservation['check_in_date'] : $reservation['appointment_date']);
                        $cancellation = calculateCancellationPenalty($reservation_date, $reservation['total_price']);
                        if ($cancellation['can_cancel'] && ($reservation_type !== 'doctor' || $reservation['status'] === 'Pending')):
                        ?>
                            <p class="text-gray-400 text-xs sm:text-sm">جریمه لغو: <?php echo number_format($cancellation['penalty']); ?> تومان</p>
                            <form method="POST" action="/site/reservation_details.php?id=<?php echo $reservation_id; ?>&type=<?php echo $reservation_type; ?>" 
                                  onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این رزرو را لغو کنید؟ جریمه: <?php echo number_format($cancellation['penalty']); ?> تومان');">
                                <button type="submit" name="cancel_reservation" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition-all duration-300 text-sm sm:text-base">
                                    <i class="fas fa-times mr-2"></i> لغو رزرو
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="/site/dashboard_reservations.php" class="inline-block bg-gray-500 text-white px-3 py-1 rounded-lg hover:bg-gray-600 transition-all duration-300 text-sm sm:text-base">
                            <i class="fas fa-arrow-right mr-2"></i> بازگشت به لیست رزروها
                        </a>
                    </div>
                </div>
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