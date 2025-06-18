<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

// فعال کردن لاگ‌های PHP برای دیباگ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log'); // تغییر مسیر لاگ به داخل پوشه site/logs

// ایجاد پوشه logs اگه وجود نداشته باشه
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, __DIR__ . '/logs/php_errors.log');
}

if (!isset($_SESSION['user_id'])) {
    debug_log("User not logged in");
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> لطفاً وارد شوید!</p>';
    header('Location: /site/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
debug_log("User ID: $user_id");

$stmt = $db->prepare("SELECT `F-Name`, `L-Name` FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    debug_log("User not found for user_id: $user_id");
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> کاربر یافت نشد!</p>';
    header('Location: /site/login.php');
    exit;
}
debug_log("User found: " . json_encode($user));

// رزروهای هتل
try {
    $stmt = $db->prepare("SELECT r.id, r.room_type_id, h.hotel_name AS name, r.check_in_date, r.check_out_date, r.total_price, 'Hotel' AS type, rt.room_type 
                          FROM Reservations r 
                          JOIN Hotels h ON r.hotel_id = h.hotel_id 
                          LEFT JOIN Room_Types rt ON r.room_type_id = rt.room_type_id 
                          WHERE r.user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $hotel_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log("Hotel reservations fetched: " . count($hotel_reservations));
} catch (Exception $e) {
    debug_log("Error fetching hotel reservations: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری رزروهای هتل: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// رزروهای پزشک
try {
    $stmt = $db->prepare("SELECT mr.medical_reservation_id AS id, NULL AS room_type_id, d.name, mr.appointment_date AS check_in_date, NULL AS check_out_date, mr.total_price, 'Doctor' AS type, d.specialty, mr.status 
                          FROM Medical_Reservations mr 
                          JOIN Doctors d ON mr.doctor_id = d.doctor_id 
                          WHERE mr.user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $doctor_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log("Doctor reservations fetched: " . count($doctor_reservations));
} catch (Exception $e) {
    debug_log("Error fetching doctor reservations: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری رزروهای پزشک: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// رزروهای بیمارستان
try {
    $stmt = $db->prepare("SELECT hr.hospital_reservation_id AS id, NULL AS room_type_id, h.name, hr.created_at AS check_in_date, NULL AS check_out_date, hr.total_price, 'Hospital' AS type, h.location 
                          FROM Hospital_Reservations hr 
                          JOIN Hospitals h ON hr.hospital_id = h.hospital_id 
                          WHERE hr.user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $hospital_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log("Hospital reservations fetched: " . count($hospital_reservations));
} catch (Exception $e) {
    debug_log("Error fetching hospital reservations: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری رزروهای بیمارستان: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// ترکیب رزروها
$reservations = array_merge($hotel_reservations, $doctor_reservations, $hospital_reservations);

// اصلاح فرمت تاریخ‌ها و مرتب‌سازی
foreach ($reservations as &$reservation) {
    if (isset($reservation['check_in_date'])) {
        $date = new DateTime($reservation['check_in_date']);
        $reservation['check_in_date_formatted'] = $date->format('Y-m-d');
    } else {
        $reservation['check_in_date_formatted'] = 'نامشخص';
    }
}
unset($reservation);

usort($reservations, function($a, $b) {
    return strtotime($b['check_in_date']) - strtotime($a['check_in_date']);
});
debug_log("Total reservations: " . count($reservations));

// هدر صفحه
$page_title = "رزروهای من - آستور";
include __DIR__ . '/header.php';
?>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-6"><i class="fas fa-bookmark mr-2 text-blue-400"></i> رزروهای شما</h2>

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

        <!-- فیلتر رزروها -->
        <div class="mb-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
            <select id="filter-type" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                <option value="all">همه رزروها</option>
                <option value="Hotel">هتل</option>
                <option value="Doctor">پزشک</option>
                <option value="Hospital">بیمارستان</option>
            </select>
            <input type="date" id="filter-date" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="فیلتر بر اساس تاریخ">
            <select id="filter-status" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                <option value="all">همه وضعیت‌ها</option>
                <option value="Pending">در انتظار</option>
                <option value="Confirmed">تأیید شده</option>
                <option value="Cancelled">لغو شده</option>
            </select>
        </div>

        <!-- لیست رزروها -->
        <div id="reservations-list" class="space-y-6">
            <?php if (empty($reservations)): ?>
                <div class="text-center p-6 bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md border border-gray-700">
                    <i class="fas fa-hotel text-gray-400 text-3xl sm:text-4xl mb-4"></i>
                    <p class="text-gray-300 text-base sm:text-lg">هنوز رزروی ندارید!</p>
                    <a href="/site/book.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base">رزرو کنید</a>
                </div>
            <?php else: ?>
                <?php foreach ($reservations as $reservation): ?>
                    <?php
                    // بررسی داده‌ها قبل از نمایش
                    if (
                        !isset($reservation['id']) || empty($reservation['id']) ||
                        !isset($reservation['type']) || empty($reservation['type']) ||
                        !isset($reservation['total_price']) || !is_numeric($reservation['total_price']) ||
                        !isset($reservation['check_in_date']) || empty($reservation['check_in_date']) ||
                        !isset($reservation['name']) || empty($reservation['name'])
                    ) {
                        debug_log("Invalid reservation data: " . json_encode($reservation));
                        continue; // از این رزرو صرف‌نظر کن و به سراغ رزرو بعدی برو
                    }
                    ?>
                    <div class="reservation-item bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-4 sm:p-6 rounded-xl shadow-md border border-gray-700 hover:shadow-lg transition-all duration-300" 
                         data-type="<?php echo $reservation['type']; ?>" 
                         data-date="<?php echo $reservation['check_in_date_formatted']; ?>" 
                         data-status="<?php echo $reservation['status'] ?? 'N/A'; ?>">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-4 sm:space-y-0">
                            <div class="flex items-center space-x-4">
                                <i class="fas <?php echo $reservation['type'] == 'Hotel' ? 'fa-hotel text-blue-400' : ($reservation['type'] == 'Doctor' ? 'fa-stethoscope text-green-400' : 'fa-hospital text-purple-400'); ?> text-xl sm:text-2xl"></i>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-semibold text-white"><?php echo htmlspecialchars($reservation['name']); ?></h3>
                                    <?php if ($reservation['type'] == 'Hotel'): ?>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">ورود:</strong> <?php echo htmlspecialchars($reservation['check_in_date_formatted']); ?></p>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">خروج:</strong> <?php echo htmlspecialchars($reservation['check_out_date'] ?? 'نامشخص'); ?></p>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">نوع اتاق:</strong> <?php echo htmlspecialchars($reservation['room_type'] ?? 'نامشخص'); ?></p>
                                    <?php elseif ($reservation['type'] == 'Doctor'): ?>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">تاریخ نوبت:</strong> <?php echo htmlspecialchars($reservation['check_in_date_formatted']); ?></p>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">تخصص:</strong> <?php echo htmlspecialchars($reservation['specialty'] ?? 'نامشخص'); ?></p>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">وضعیت:</strong> <?php echo htmlspecialchars($reservation['status'] ?? 'نامشخص'); ?></p>
                                    <?php else: ?>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">تاریخ رزرو:</strong> <?php echo htmlspecialchars($reservation['check_in_date_formatted']); ?></p>
                                        <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">مکان:</strong> <?php echo htmlspecialchars($reservation['location'] ?? 'نامشخص'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-left sm:text-right space-y-2">
                                <p class="text-green-400 font-semibold text-base sm:text-lg"><?php echo number_format($reservation['total_price']) . ' تومان'; ?></p>
                                <p class="text-xs sm:text-sm text-gray-500">شناسه: <?php echo htmlspecialchars($reservation['id']); ?></p>
                                <div class="flex space-x-2">
                                    <a href="/site/reservation_details.php?id=<?php echo $reservation['id']; ?>&type=<?php echo $reservation['type']; ?>" class="bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-info-circle mr-2"></i> جزئیات</a>
                                    <?php if (
                                        isset($reservation['id']) && !empty($reservation['id']) &&
                                        isset($reservation['type']) && !empty($reservation['type']) &&
                                        isset($reservation['total_price']) && is_numeric($reservation['total_price']) &&
                                        isset($reservation['check_in_date_formatted']) && !empty($reservation['check_in_date_formatted']) &&
                                        isset($reservation['name']) && !empty($reservation['name'])
                                    ): ?>
                                        <form method="POST" action="/site/confirm_cancellation.php">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                            <input type="hidden" name="reservation_type" value="<?php echo $reservation['type']; ?>">
                                            <input type="hidden" name="total_price" value="<?php echo $reservation['total_price']; ?>">
                                            <input type="hidden" name="check_in_date" value="<?php echo $reservation['check_in_date_formatted']; ?>">
                                            <input type="hidden" name="room_type_id" value="<?php echo $reservation['room_type_id'] ?? ''; ?>">
                                            <input type="hidden" name="reservation_name" value="<?php echo htmlspecialchars($reservation['name']); ?>">
                                            <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-times mr-2"></i> لغو</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-red-400 text-sm sm:text-base">داده‌های این رزرو ناقص است و امکان لغو وجود ندارد.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.getElementById('filter-type').addEventListener('change', filterReservations);
document.getElementById('filter-date').addEventListener('change', filterReservations);
document.getElementById('filter-status').addEventListener('change', filterReservations);

function filterReservations() {
    const typeFilter = document.getElementById('filter-type').value;
    const dateFilter = document.getElementById('filter-date').value;
    const statusFilter = document.getElementById('filter-status').value;
    const reservations = document.querySelectorAll('.reservation-item');

    reservations.forEach(reservation => {
        const type = reservation.getAttribute('data-type');
        const date = reservation.getAttribute('data-date');
        const status = reservation.getAttribute('data-status');

        const typeMatch = (typeFilter === 'all' || type === typeFilter);
        const dateMatch = (dateFilter === '' || date === dateFilter);
        const statusMatch = (statusFilter === 'all' || status === statusFilter);

        if (typeMatch && dateMatch && statusMatch) {
            reservation.style.display = 'block';
        } else {
            reservation.style.display = 'none';
        }
    });
}
</script>

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