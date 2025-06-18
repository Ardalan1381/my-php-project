<?php
require_once __DIR__ . '/includes/db.php';
$page_title = "رزرو - آستور";
include __DIR__ . '/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT `F-Name`, `L-Name`, Phone, email, balance FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$hotel_id = filter_input(INPUT_GET, 'hotel_id', FILTER_VALIDATE_INT);
$room_type_id = filter_input(INPUT_GET, 'room_type_id', FILTER_VALIDATE_INT);
$check_in = filter_input(INPUT_GET, 'check_in', FILTER_SANITIZE_STRING) ?? date('Y-m-d', strtotime('+1 day'));
$check_out = filter_input(INPUT_GET, 'check_out', FILTER_SANITIZE_STRING) ?? date('Y-m-d', strtotime('+2 days'));
$guests = filter_input(INPUT_GET, 'guests', FILTER_VALIDATE_INT) ?? 1;

if (!$hotel_id || !$room_type_id) {
    header("Location: /site/hotels.php");
    exit;
}

// اطلاعات هتل
$stmt = $db->prepare("SELECT hotel_id, hotel_name, hotel_city, hotel_country, hotel_star FROM Hotels WHERE hotel_id = :hotel_id");
$stmt->execute(['hotel_id' => $hotel_id]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

// اطلاعات اتاق
$stmt = $db->prepare("SELECT room_type_id, room_type, room_price, extra_guest_price, available_count, room_capacity FROM Room_Types WHERE room_type_id = :room_type_id AND hotel_id = :hotel_id");
$stmt->execute(['room_type_id' => $room_type_id, 'hotel_id' => $hotel_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

// تصاویر اتاق
$stmt = $db->prepare("SELECT image_path FROM Room_Images WHERE room_type_id = :room_type_id");
$stmt->execute(['room_type_id' => $room_type_id]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['/site/uploads/rooms/default.jpg'];

if (!$hotel || !$room) {
    echo '<p class="text-red-400 text-center p-6 bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md border border-gray-700">هتل یا اتاق یافت نشد!</p>';
    include __DIR__ . '/footer.php';
    exit;
}

// پردازش رزرو
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $check_in = filter_input(INPUT_POST, 'check_in', FILTER_SANITIZE_STRING);
    $check_out = filter_input(INPUT_POST, 'check_out', FILTER_SANITIZE_STRING);
    $guests = filter_input(INPUT_POST, 'guests', FILTER_VALIDATE_INT);
    $guest_usernames = $_POST['guest_usernames'] ?? [];

    $days = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
    $extra_guests = max(0, $guests - 1); // مهمان‌های اضافی (نفر اول شامل قیمت پایه است)
    $total_price = ($room['room_price'] + ($extra_guests * $room['extra_guest_price'])) * $days;

    if (strtotime($check_out) <= strtotime($check_in)) {
        $error = "تاریخ خروج باید بعد از تاریخ ورود باشد!";
    } elseif ($guests > $room['room_capacity']) {
        $error = "تعداد مهمان‌ها نمی‌تواند بیشتر از ظرفیت اتاق ({$room['room_capacity']} نفر) باشد!";
    } elseif ($guests > 1 && count($guest_usernames) != $guests - 1) {
        $error = "لطفاً یوزرنیم همه مهمان‌های اضافی را وارد کنید!";
    } elseif ($room['available_count'] <= 0) {
        $error = "اتاق موردنظر موجود نیست!";
    } else {
        $valid_usernames = true;
        $guest_list = []; // برای ذخیره اطلاعات مهمان‌ها

        // بررسی یوزرنیم‌ها و جمع‌آوری اطلاعات مهمان‌ها
        foreach ($guest_usernames as $username) {
            $stmt = $db->prepare("SELECT user_id, username, `F-Name`, `L-Name` FROM Users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $guest = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$guest) {
                $valid_usernames = false;
                break;
            }
            // اضافه کردن اطلاعات مهمان به لیست
            $guest_list[] = [
                'username' => $guest['username'],
                'first_name' => $guest['F-Name'],
                'last_name' => $guest['L-Name']
            ];
        }

        if (!$valid_usernames) {
            $error = "یکی یا چند یوزرنیم نامعتبر است!";
        } elseif ($user['balance'] < $total_price) {
            $error = "موجودی کافی نیست!";
        } else {
            $db->beginTransaction();
            try {
                $guest_name = $user['F-Name'] . ' ' . $user['L-Name'];
                // تبدیل guest_list به JSON
                $guest_list_json = $guests > 1 ? json_encode($guest_list, JSON_UNESCAPED_UNICODE) : null;

                $stmt = $db->prepare("INSERT INTO Reservations (guest_name, guest_phone, hotel_id, room_type_id, check_in_date, check_out_date, num_guests, total_price, user_id, guest_list) 
                                      VALUES (:guest_name, :phone, :hotel_id, :room_type_id, :check_in, :check_out, :guests, :total_price, :user_id, :guest_list)");
                $stmt->execute([
                    'guest_name' => $guest_name,
                    'phone' => '0' . $user['Phone'],
                    'hotel_id' => $hotel_id,
                    'room_type_id' => $room_type_id,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'guests' => $guests,
                    'total_price' => $total_price,
                    'user_id' => $user_id,
                    'guest_list' => $guest_list_json
                ]);
                $db->prepare("UPDATE Users SET balance = balance - :total_price WHERE user_id = :user_id")
                   ->execute(['total_price' => $total_price, 'user_id' => $user_id]);
                $db->prepare("UPDATE Room_Types SET available_count = available_count - 1 WHERE room_type_id = :room_type_id")
                   ->execute(['room_type_id' => $room_type_id]);
                $db->prepare("INSERT INTO Wallet_Transactions (user_id, amount, transaction_type, description) 
                              VALUES (:user_id, :amount, 'BOOKING', :description)")
                   ->execute(['user_id' => $user_id, 'amount' => -$total_price, 'description' => "رزرو {$hotel['hotel_name']}"]);
                $db->commit();
                $_SESSION['success_message'] = "رزرو با موفقیت انجام شد!";
                header("Location: /site/dashboard.php");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = "خطا: " . $e->getMessage();
            }
        }
    }
}
?>
<main class="container mx-auto px-4 py-8 pt-20">
    <!-- سربرگ -->
    <h1 class="text-4xl font-bold text-white mb-8 text-center animate-fade-in">رزرو هتل <?php echo htmlspecialchars($hotel['hotel_name']); ?></h1>

    <!-- اطلاعات هتل و فرم -->
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-8 rounded-xl shadow-lg max-w-4xl mx-auto border border-gray-700">
        <!-- گالری تصاویر اتاق -->
        <div class="relative mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">تصاویر <?php echo htmlspecialchars($room['room_type']); ?></h2>
            <div id="gallery" class="flex overflow-x-hidden">
                <?php foreach ($images as $index => $image): ?>
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="تصویر <?php echo $index + 1; ?> از <?php echo htmlspecialchars($room['room_type']); ?>" class="w-full h-64 object-cover rounded-lg flex-shrink-0 <?php echo $index > 0 ? 'hidden' : ''; ?>" data-index="<?php echo $index; ?>">
                <?php endforeach; ?>
            </div>
            <button id="prev" class="absolute top-1/2 left-4 transform -translate-y-1/2 bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 transition-all duration-300"><i class="fas fa-chevron-right"></i></button>
            <button id="next" class="absolute top-1/2 right-4 transform -translate-y-1/2 bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 transition-all duration-300"><i class="fas fa-chevron-left"></i></button>
            <div class="flex justify-center mt-4 space-x-2">
                <?php foreach ($images as $index => $image): ?>
                    <span class="w-3 h-3 rounded-full bg-gray-500 hover:bg-blue-400 cursor-pointer transition-all duration-300 <?php echo $index === 0 ? 'bg-blue-400' : ''; ?>" data-index="<?php echo $index; ?>"></span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- اطلاعات هتل -->
        <div class="flex flex-col sm:flex-row gap-6 mb-8">
            <div class="flex-1">
                <h2 class="text-2xl font-bold text-white mb-2"><?php echo htmlspecialchars($hotel['hotel_name']); ?></h2>
                <p class="text-gray-300 mb-1"><i class="fas fa-map-marker-alt mr-2 text-blue-400"></i> <?php echo htmlspecialchars($hotel['hotel_country'] . '، ' . $hotel['hotel_city']); ?></p>
                <div class="flex items-center mb-2">
                    <?php for ($i = 0; $i < $hotel['hotel_star']; $i++): ?>
                        <i class="fas fa-star text-yellow-400"></i>
                    <?php endfor; ?>
                    <span class="text-gray-400 text-sm ml-2">(<?php echo $hotel['hotel_star']; ?> ستاره)</span>
                </div>
                <p class="text-gray-300 mb-1"><i class="fas fa-bed mr-2 text-blue-400"></i> اتاق‌های موجود: <?php echo $room['available_count']; ?></p>
            </div>
        </div>

        <!-- فرم رزرو -->
        <?php if (isset($error)): ?>
            <p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-300 font-medium mb-2">تاریخ ورود</label>
                    <input type="date" name="check_in" value="<?php echo htmlspecialchars($check_in); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-gray-300 font-medium mb-2">تاریخ خروج</label>
                    <input type="date" name="check_out" value="<?php echo htmlspecialchars($check_out); ?>" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
            <div>
                <label class="block text-gray-300 font-medium mb-2">نوع اتاق</label>
                <input type="text" value="<?php echo htmlspecialchars($room['room_type'] . ' - ' . number_format($room['room_price']) . ' تومان/شب + ' . number_format($room['extra_guest_price']) . ' تومان/مهمان اضافی'); ?>" class="w-full p-3 bg-gray-600 text-gray-200 border border-gray-500 rounded-lg" readonly>
                <input type="hidden" name="room_type_id" value="<?php echo $room_type_id; ?>">
            </div>
            <div>
                <label class="block text-gray-300 font-medium mb-2">تعداد مهمان‌ها</label>
                <input type="number" name="guests" id="guests" value="<?php echo htmlspecialchars($guests); ?>" min="1" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div id="guest-usernames" class="hidden">
                <label class="block text-gray-300 font-medium mb-2">نام کاربری مهمان‌های اضافی</label>
                <div id="guest-inputs" class="space-y-4">
                    <!-- این بخش توسط جاوااسکریپت پر می‌شود -->
                </div>
            </div>
            <div>
                <label class="block text-gray-300 font-medium mb-2">هزینه کل (محاسبه‌شده)</label>
                <input type="text" id="total-price" class="w-full p-3 bg-gray-600 text-gray-200 border border-gray-500 rounded-lg" readonly>
            </div>
            <button type="submit" class="btn bg-blue-500 text-white px-6 py-3 rounded-lg w-full hover:bg-blue-600 transition-all duration-300"><i class="fas fa-bookmark mr-2"></i> ثبت رزرو</button>
        </form>
    </div>
</main>
<script>
    // گالری اسلایدر
    const gallery = document.getElementById('gallery');
    const images = gallery.querySelectorAll('img');
    const prevBtn = document.getElementById('prev');
    const nextBtn = document.getElementById('next');
    const dots = document.querySelectorAll('.flex.justify-center span');
    let currentIndex = 0;

    function showImage(index) {
        images.forEach((img, i) => {
            img.classList.toggle('hidden', i !== index);
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('bg-blue-400', i === index);
            dot.classList.toggle('bg-gray-500', i !== index);
        });
        currentIndex = index;
    }

    prevBtn.addEventListener('click', () => {
        const newIndex = (currentIndex - 1 + images.length) % images.length;
        showImage(newIndex);
    });

    nextBtn.addEventListener('click', () => {
        const newIndex = (currentIndex + 1) % images.length;
        showImage(newIndex);
    });

    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            const index = parseInt(dot.getAttribute('data-index'));
            showImage(index);
        });
    });

    // فرم و محاسبه قیمت
    const guestsInput = document.getElementById('guests');
    const guestUsernames = document.getElementById('guest-usernames');
    const guestInputs = document.getElementById('guest-inputs');
    const checkInInput = document.querySelector('input[name="check_in"]');
    const checkOutInput = document.querySelector('input[name="check_out"]');
    const totalPriceInput = document.getElementById('total-price');
    const roomPrice = <?php echo $room['room_price']; ?>;
    const extraGuestPrice = <?php echo $room['extra_guest_price']; ?>;

    function updateGuests() {
        const guests = parseInt(guestsInput.value) || 0;
        guestInputs.innerHTML = '';
        if (guests > 1) {
            guestUsernames.classList.remove('hidden');
            for (let i = 1; i < guests; i++) {
                guestInputs.innerHTML += `
                    <input type="text" name="guest_usernames[]" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="نام کاربری مهمان ${i}" required>
                `;
            }
        } else {
            guestUsernames.classList.add('hidden');
        }
        updateTotalPrice();
    }

    function updateTotalPrice() {
        const checkIn = new Date(checkInInput.value);
        const checkOut = new Date(checkOutInput.value);
        const guests = parseInt(guestsInput.value) || 0;
        const days = (checkOut - checkIn) / (1000 * 60 * 60 * 24);
        const extraGuests = Math.max(0, guests - 1); // نفر اول شامل قیمت پایه است
        const total = days > 0 ? (roomPrice + (extraGuests * extraGuestPrice)) * days : 0;
        totalPriceInput.value = total ? total.toLocaleString() + ' تومان' : '';
    }

    guestsInput.addEventListener('input', updateGuests);
    checkInInput.addEventListener('change', updateTotalPrice);
    checkOutInput.addEventListener('change', updateTotalPrice);
    updateGuests(); // مقدار اولیه
</script>
<style>
    @keyframes fadeIn { 
        from { opacity: 0; transform: translateY(-10px); } 
        to { opacity: 1; transform: translateY(0); } 
    }
    .animate-fade-in { 
        animation: fadeIn 0.8s ease-in; 
    }
    @media (max-width: 640px) {
        .sm\:flex-row { flex-direction: column; }
    }
</style>
<?php include __DIR__ . '/footer.php'; ?>