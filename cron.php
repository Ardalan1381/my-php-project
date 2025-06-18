<?php
require_once __DIR__ . '/includes/db.php';

$today = date('Y-m-d');
$expired = $db->prepare("SELECT hotel_id, room_type_id, num_guests FROM Reservations WHERE check_out_date < :today");
$expired->execute(['today' => $today]);
while ($row = $expired->fetch(PDO::FETCH_ASSOC)) {
    $db->prepare("UPDATE Hotels SET hotel_capacity = hotel_capacity + :num_guests WHERE hotel_id = :hotel_id")
       ->execute(['num_guests' => $row['num_guests'], 'hotel_id' => $row['hotel_id']]);
    $db->prepare("UPDATE Room_Types SET available_count = available_count + 1 WHERE room_type_id = :room_type_id")
       ->execute(['room_type_id' => $row['room_type_id']]);
    $db->prepare("DELETE FROM Reservations WHERE check_out_date < :today")
       ->execute(['today' => $today]);
}
?>