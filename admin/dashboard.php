<?php
require_once "config.php";

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["admin", "Moderator"])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT username, role FROM Users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// آمار کلی
$total_users = $conn->query("SELECT COUNT(*) FROM Users")->fetch_row()[0];
$total_hotels = $conn->query("SELECT COUNT(*) FROM Hotels")->fetch_row()[0];
$total_reservations = $conn->query("SELECT COUNT(*) FROM Reservations")->fetch_row()[0];
$total_transactions = $conn->query("SELECT COUNT(*) FROM Wallet_Transactions")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>پنل مدیریت - ASTOUR</title>
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- استایل سایتت -->
</head>
<body>
    <div class="dashboard">
        <h2>خوش آمدید، <?php echo $user["username"]; ?>!</h2>
        <p>نقش: <?php echo $user["role"]; ?></p>
        <div class="stats">
            <p>تعداد کاربران: <?php echo $total_users; ?></p>
            <p>تعداد هتل‌ها: <?php echo $total_hotels; ?></p>
            <p>تعداد رزروها: <?php echo $total_reservations; ?></p>
            <p>تعداد تراکنش‌ها: <?php echo $total_transactions; ?></p>
        </div>
        <a href="logout.php">خروج</a>
    </div>
</body>
</html>