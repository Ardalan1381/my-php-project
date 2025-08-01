<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Moderator') {
    header("Location: /site/login.php");
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $availability = trim($_POST['availability'] ?? 'Sunday to Thursday, 9 AM - 5 PM');
    $fee = trim($_POST['fee'] ?? '0.00');

    // اعتبارسنجی
    if (empty($name)) $errors[] = "نام پزشک اجباری است.";
    if (empty($specialty)) $errors[] = "تخصص اجباری است.";
    if (!is_numeric($fee)) $errors[] = "هزینه باید عدد باشد.";

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO Doctors (name, specialty, availability, fee) 
                              VALUES (:name, :specialty, :availability, :fee)");
        $stmt->execute([
            'name' => $name,
            'specialty' => $specialty,
            'availability' => $availability,
            'fee' => $fee
        ]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>افزودن پزشک - آستور</title>
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
        .btn-action {
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-action:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.05);
        }
        input, select {
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
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <main class="flex-1 p-4 md:p-8 pt-20">
            <div class="card p-6 max-w-2xl mx-auto">
                <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-user-md mr-2 text-green-400"></i> افزودن پزشک</h2>
                
                <?php if ($success): ?>
                    <div class="success text-white">پزشک با موفقیت اضافه شد! <a href="/site/admin/doctors.php" class="underline">بازگشت به لیست</a></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="error text-white">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-300">نام پزشک</label>
                        <input type="text" name="name" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">تخصص</label>
                        <input type="text" name="specialty" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">ساعات کاری</label>
                        <input type="text" name="availability" value="Sunday to Thursday, 9 AM - 5 PM">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">هزینه (تومان)</label>
                        <input type="number" name="fee" value="0.00" step="0.01">
                    </div>
                    <button type="submit" class="btn-action bg-green-500 text-white p-2 rounded-lg w-full hover:bg-green-600">ثبت پزشک</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>