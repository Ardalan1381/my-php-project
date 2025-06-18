<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

define('ALLOWED_ROLES', ['admin', 'Moderator']);
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ALLOWED_ROLES)) {
    echo json_encode(['success' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'get_reservations') {
    $type = $_POST['type'] ?? 'all';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $search_username = $_POST['search_username'] ?? '';

    $conditions = [];
    $params = [];

    if ($start_date) {
        $conditions[] = "r.created_at >= :start_date";
        $params['start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $conditions[] = "r.created_at <= :end_date";
        $params['end_date'] = $end_date . ' 23:59:59';
    }
    if ($search_username) {
        $conditions[] = "u.username LIKE :search_username";
        $params['search_username'] = "%$search_username%";
    }

    try {
        $reservations = [];

        // رزروهای هتل
        $hotel_sql = "
            SELECT r.id AS id, u.username, h.hotel_name AS entity_name, r.total_price, r.created_at, 'هتل' AS type
            FROM Reservations r
            JOIN Users u ON r.user_id = u.user_id
            JOIN Hotels h ON r.hotel_id = h.hotel_id
        ";
        if (!empty($conditions)) $hotel_sql .= " WHERE " . implode(' AND ', $conditions);
        if ($type === 'all' || $type === 'hotel') {
            $stmt = $db->prepare($hotel_sql);
            $stmt->execute($params);
            $reservations = array_merge($reservations, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // رزروهای پزشکی
        $medical_sql = "
            SELECT mr.medical_reservation_id AS id, u.username, d.name AS entity_name, mr.total_price, mr.status, mr.created_at, 'پزشکی' AS type
            FROM Medical_Reservations mr
            JOIN Users u ON mr.user_id = u.user_id
            JOIN Doctors d ON mr.doctor_id = d.doctor_id
        ";
        if (!empty($conditions)) $medical_sql .= " WHERE " . implode(' AND ', $conditions);
        if ($type === 'all' || $type === 'medical') {
            $stmt = $db->prepare($medical_sql);
            $stmt->execute($params);
            $reservations = array_merge($reservations, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // رزروهای بیمارستانی
        $hospital_sql = "
            SELECT hr.hospital_reservation_id AS id, u.username, h.name AS entity_name, hr.total_price, hr.created_at, 'بیمارستانی' AS type
            FROM Hospital_Reservations hr
            JOIN Users u ON hr.user_id = u.user_id
            JOIN Hospitals h ON hr.hospital_id = h.hospital_id
        ";
        if (!empty($conditions)) $hospital_sql .= " WHERE " . implode(' AND ', $conditions);
        if ($type === 'all' || $type === 'hospital') {
            $stmt = $db->prepare($hospital_sql);
            $stmt->execute($params);
            $reservations = array_merge($reservations, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        usort($reservations, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        echo json_encode($reservations);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action === 'update_status') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    $type = $_POST['type'] ?? '';

    if ($type === 'پزشکی') {
        try {
            $stmt = $db->prepare("UPDATE Medical_Reservations SET status = :status WHERE medical_reservation_id = :id");
            $stmt->execute(['status' => $status, 'id' => $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'تغییر وضعیت فقط برای رزروهای پزشکی امکان‌پذیر است']);
    }
} elseif ($action === 'delete_reservation') {
    $id = $_POST['id'] ?? '';
    $type = $_POST['type'] ?? '';

    try {
        if ($type === 'هتل') {
            $stmt = $db->prepare("DELETE FROM Reservations WHERE id = :id");
        } elseif ($type === 'پزشکی') {
            $stmt = $db->prepare("DELETE FROM Medical_Reservations WHERE medical_reservation_id = :id");
        } elseif ($type === 'بیمارستانی') {
            $stmt = $db->prepare("DELETE FROM Hospital_Reservations WHERE hospital_reservation_id = :id");
        }
        $stmt->execute(['id' => $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'درخواست نامعتبر']);
}
exit;