<?php
require_once __DIR__ . '/includes/db.php';
$page_title = "آستور - رزرو هتل";
include __DIR__ . '/header.php';

// هتل‌های پیشنهادی (فقط Recommended)
$suggested_hotels = $db->query("SELECT h.*, MIN(rt.room_price) as min_price 
    FROM Hotels h 
    LEFT JOIN Room_Types rt ON h.hotel_id = rt.hotel_id 
    WHERE h.hotel_proposed = 'Recommended'
    GROUP BY h.hotel_id 
    ORDER BY h.hotel_star DESC 
    LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

// همه هتل‌ها
$all_hotels = $db->query("SELECT h.*, MIN(rt.room_price) as min_price 
    FROM Hotels h 
    LEFT JOIN Room_Types rt ON h.hotel_id = rt.hotel_id 
    GROUP BY h.hotel_id 
    ORDER BY h.hotel_star DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<main class="container mx-auto p-6 pt-20">
    <!-- هدر اصلی با گرادیانت و انیمیشن -->
    <section class="relative bg-cover bg-center h-96 rounded-xl shadow-xl mb-12 overflow-hidden" style="background-image: url('https://images.unsplash.com/photo-1542314831-8f9916e6e2b1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
        <div class="absolute inset-0 bg-gradient-to-r from-gray-900 to-blue-900 opacity-80 rounded-xl"></div>
        <div class="relative z-10 flex flex-col items-center justify-center h-full text-white text-center px-4">
            <h1 class="text-5xl font-bold mb-4 animate-fade-in-down">به آستور خوش آمدید</h1>
            <p class="text-xl mb-6 animate-fade-in-up">رزرو هتل‌های لوکس با بهترین قیمت‌ها</p>
            <a href="#search" class="btn bg-blue-500 text-white px-8 py-3 rounded-full shadow-lg hover:bg-blue-600 transition-all duration-300"><i class="fas fa-search mr-2"></i> جستجوی هتل</a>
        </div>
    </section>

    <!-- فرم جستجو -->
    <section id="search" class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-8 rounded-xl shadow-lg max-w-5xl mx-auto mb-12 transform -mt-20 z-20 relative border border-gray-700">
        <h2 class="text-3xl font-semibold text-white mb-6 text-center">جستجوی هتل</h2>
        <form method="GET" action="/site/hotels.php" class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <div>
                <label class="block text-gray-300 font-medium mb-2">شهر</label>
                <input type="text" name="city" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="مثلاً: تهران" required>
            </div>
            <div>
                <label class="block text-gray-300 font-medium mb-2">تاریخ ورود</label>
                <input type="date" name="check_in" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-gray-300 font-medium mb-2">تاریخ خروج</label>
                <input type="date" name="check_out" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-gray-300 font-medium mb-2">تعداد مهمان</label>
                <input type="number" name="guests" value="1" min="1" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn bg-blue-600 text-white px-6 py-3 rounded-lg w-full hover:bg-blue-700 transition-all duration-300"><i class="fas fa-search mr-2"></i> جستجو</button>
            </div>
        </form>
    </section>

    <!-- پیشنهادات ویژه -->
    <section class="mb-12">
        <h2 class="text-3xl font-semibold text-white mb-8 text-center">پیشنهادات ویژه</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($suggested_hotels as $hotel): ?>
                <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-lg overflow-hidden transform hover:scale-105 transition-all duration-300 border border-gray-700">
                    <img src="<?php echo htmlspecialchars($hotel['hotel_image'] ?: '/site/uploads/default.jpg'); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>" class="w-full h-56 object-cover">
                    <div class="p-6">
                        <h3 class="text-2xl font-bold text-white mb-2"><?php echo htmlspecialchars($hotel['hotel_name']); ?></h3>
                        <p class="text-gray-300 mb-1"><i class="fas fa-map-marker-alt mr-2 text-blue-400"></i> <?php echo htmlspecialchars($hotel['hotel_city']); ?></p>
                        <div class="flex items-center mb-2">
                            <span class="text-gray-400 mr-2">ستاره:</span>
                            <?php for ($i = 0; $i < $hotel['hotel_star']; $i++): ?>
                                <i class="fas fa-star text-yellow-400"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-200 font-semibold mb-4">شروع از: <?php echo number_format($hotel['min_price'] ?? 0, 2); ?> تومان</p>
                        <a href="/site/hotels.php?hotel_id=<?php echo $hotel['hotel_id']; ?>&check_in=<?php echo urlencode(date('Y-m-d', strtotime('+1 day'))); ?>&check_out=<?php echo urlencode(date('Y-m-d', strtotime('+2 days'))); ?>&guests=1" class="btn bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300"><i class="fas fa-bookmark mr-2"></i> رزرو</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- لیست همه هتل‌ها -->
    <section class="mb-12">
        <h2 class="text-3xl font-semibold text-white mb-8 text-center">همه هتل‌ها</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($all_hotels as $hotel): ?>
                <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 border border-gray-700">
                    <div class="relative">
                        <img src="<?php echo htmlspecialchars($hotel['hotel_image'] ?: '/site/uploads/default.jpg'); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>" class="w-full h-48 object-cover">
                        <?php if ($hotel['hotel_proposed'] == 'Recommended'): ?>
                            <span class="absolute top-4 left-4 bg-green-500 text-white px-3 py-1 rounded-full text-sm">پیشنهاد ویژه</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($hotel['hotel_name']); ?></h3>
                        <p class="text-gray-300 mb-1"><i class="fas fa-map-marker-alt mr-2 text-blue-400"></i> <?php echo htmlspecialchars($hotel['hotel_city']); ?></p>
                        <div class="flex items-center mb-2">
                            <?php for ($i = 0; $i < $hotel['hotel_star']; $i++): ?>
                                <i class="fas fa-star text-yellow-400"></i>
                            <?php endfor; ?>
                            <span class="text-gray-400 text-sm ml-2">(<?php echo $hotel['hotel_star']; ?> ستاره)</span>
                        </div>
                        <p class="text-gray-200 font-semibold mb-4">شروع از: <?php echo number_format($hotel['min_price'] ?? 0, 2); ?> تومان</p>
                        <a href="/site/hotels.php?hotel_id=<?php echo $hotel['hotel_id']; ?>&check_in=<?php echo urlencode(date('Y-m-d', strtotime('+1 day'))); ?>&check_out=<?php echo urlencode(date('Y-m-d', strtotime('+2 days'))); ?>&guests=1" class="btn bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300"><i class="fas fa-eye mr-2"></i> مشاهده</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<style>
    @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-down { animation: fadeInDown 1s ease-in; }
    .animate-fade-in-up { animation: fadeInUp 1s ease-in; }
</style>
<?php include __DIR__ . '/footer.php'; ?>