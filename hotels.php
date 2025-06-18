<?php
require_once __DIR__ . '/includes/db.php';
$page_title = "هتل‌ها - آستور";
include __DIR__ . '/header.php';

$city = filter_input(INPUT_GET, 'city', FILTER_SANITIZE_STRING) ?? '';
$country = filter_input(INPUT_GET, 'country', FILTER_SANITIZE_STRING) ?? '';
$check_in = filter_input(INPUT_GET, 'check_in', FILTER_SANITIZE_STRING) ?? date('Y-m-d', strtotime('+1 day'));
$check_out = filter_input(INPUT_GET, 'check_out', FILTER_SANITIZE_STRING) ?? date('Y-m-d', strtotime('+2 days'));
$guests = filter_input(INPUT_GET, 'guests', FILTER_VALIDATE_INT) ?? 1;
$star_filter = filter_input(INPUT_GET, 'star', FILTER_VALIDATE_INT) ?? '';

// ساخت کوئری با فیلترها
$sql = "SELECT h.*, MIN(rt.room_price) as min_price 
        FROM Hotels h 
        LEFT JOIN Room_Types rt ON h.hotel_id = rt.hotel_id 
        WHERE 1=1";
$params = [];

if ($city) {
    $sql .= " AND h.hotel_city LIKE :city";
    $params[':city'] = "%$city%";
}
if ($country) {
    $sql .= " AND h.hotel_country LIKE :country";
    $params[':country'] = "%$country%";
}
if ($star_filter) {
    $sql .= " AND h.hotel_star = :star";
    $params[':star'] = $star_filter;
}
$sql .= " GROUP BY h.hotel_id ORDER BY h.hotel_star DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<main class="container mx-auto px-4 py-8 pt-20">
    <!-- سربرگ -->
    <h1 class="text-4xl font-bold text-white mb-8 text-center animate-fade-in">هتل‌های <?php echo htmlspecialchars($city ?: ($country ?: 'همه مناطق')); ?></h1>

    <!-- چیدمان اصلی -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- فیلترها -->
        <aside class="lg:w-1/4 w-full">
            <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 rounded-xl shadow-lg sticky top-20 border border-gray-700 transform hover:shadow-xl transition-all duration-300">
                <h3 class="text-xl font-semibold text-white mb-4 flex items-center"><i class="fas fa-filter mr-2 text-blue-400"></i> فیلترها</h3>
                <form method="GET" action="/site/hotels.php" class="space-y-6">
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">کشور</label>
                        <input type="text" name="country" value="<?php echo htmlspecialchars($country); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="مثلاً: ایران">
                    </div>
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">شهر</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($city); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="مثلاً: تهران">
                    </div>
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">تاریخ ورود</label>
                        <input type="date" name="check_in" value="<?php echo htmlspecialchars($check_in); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">تاریخ خروج</label>
                        <input type="date" name="check_out" value="<?php echo htmlspecialchars($check_out); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">تعداد مهمان</label>
                        <input type="number" name="guests" value="<?php echo htmlspecialchars($guests); ?>" min="1" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 font-medium mb-2">ستاره</label>
                        <select name="star" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">همه</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $star_filter == $i ? 'selected' : ''; ?>><?php echo $i; ?> ستاره</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn bg-blue-600 text-white px-4 py-3 rounded-lg w-full hover:bg-blue-700 transition-all duration-300"><i class="fas fa-search mr-2"></i> جستجو</button>
                </form>
            </div>
        </aside>

        <!-- لیست هتل‌ها -->
        <div class="lg:w-3/4 w-full">
            <?php if (empty($hotels)): ?>
                <div class="text-center p-6 bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md border border-gray-700">
                    <i class="fas fa-hotel text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-300 text-lg">هتلی با این مشخصات یافت نشد!</p>
                    <p class="text-sm text-gray-500 mt-2">فیلترها را تغییر دهید و دوباره امتحان کنید.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($hotels as $hotel): ?>
                        <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md overflow-hidden hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 border border-gray-700">
                            <div class="flex flex-col md:flex-row">
                                <img src="<?php echo htmlspecialchars($hotel['hotel_image'] ?: '/site/uploads/default.jpg'); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>" class="w-full md:w-1/3 h-64 object-cover rounded-t-xl md:rounded-r-xl md:rounded-t-none">
                                <div class="p-6 w-full md:w-2/3">
                                    <h3 class="text-2xl font-bold text-white mb-2"><?php echo htmlspecialchars($hotel['hotel_name']); ?></h3>
                                    <p class="text-gray-300 mb-1"><i class="fas fa-map-marker-alt mr-2 text-blue-400"></i> <?php echo htmlspecialchars($hotel['hotel_country'] . '، ' . $hotel['hotel_city']); ?></p>
                                    <div class="flex items-center mb-2">
                                        <?php for ($i = 0; $i < $hotel['hotel_star']; $i++): ?>
                                            <i class="fas fa-star text-yellow-400"></i>
                                        <?php endfor; ?>
                                        <span class="text-gray-400 text-sm ml-2">(<?php echo $hotel['hotel_star']; ?> ستاره)</span>
                                    </div>
                                    <p class="text-gray-200 font-semibold mb-4">شروع از: <?php echo number_format($hotel['min_price'] ?? 0, 2); ?> تومان/شب</p>
                                    <?php
                                    $rooms = $db->prepare("SELECT * FROM Room_Types WHERE hotel_id = :hotel_id");
                                    $rooms->execute(['hotel_id' => $hotel['hotel_id']]);
                                    $rooms = $rooms->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <div class="mt-4 space-y-4">
                                        <?php foreach ($rooms as $room): ?>
                                            <div class="flex flex-col sm:flex-row justify-between items-center py-3 bg-gray-700 bg-opacity-50 rounded-lg px-4">
                                                <div class="mb-2 sm:mb-0">
                                                    <p class="text-gray-200 font-semibold"><?php echo htmlspecialchars($room['room_type']); ?></p>
                                                    <p class="text-gray-400 text-sm">ظرفیت: <?php echo $room['room_capacity']; ?> نفر - <?php echo number_format($room['room_price'], 2); ?> تومان/شب</p>
                                                </div>
                                                <form method="GET" action="/site/book.php" class="flex items-center space-x-2">
                                                    <input type="hidden" name="hotel_id" value="<?php echo $hotel['hotel_id']; ?>">
                                                    <input type="hidden" name="room_type_id" value="<?php echo $room['room_type_id']; ?>">
                                                    <input type="hidden" name="check_in" value="<?php echo htmlspecialchars($check_in); ?>">
                                                    <input type="hidden" name="check_out" value="<?php echo htmlspecialchars($check_out); ?>">
                                                    <input type="hidden" name="guests" value="<?php echo htmlspecialchars($guests); ?>">
                                                    <button type="submit" class="btn bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300"><i class="fas fa-book mr-2"></i> رزرو</button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeIn 0.8s ease-in; }
    @media (max-width: 1024px) {
        .lg\:w-1\/4, .lg\:w-3\/4 { width: 100%; }
        aside { position: static; }
    }
    @media (max-width: 640px) {
        .sm\:flex-row { flex-direction: column; }
        .sm\:mb-0 { margin-bottom: 1rem; }
    }
</style>
<?php include __DIR__ . '/footer.php'; ?>