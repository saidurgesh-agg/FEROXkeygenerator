<?php
include 'db.php';
error_reporting(0);
header('Content-Type: application/json');

// 1. Receive Data (Fix for Missing Parameters)
$key_input = isset($_REQUEST['key']) ? $_REQUEST['key'] : (isset($_REQUEST['user_key']) ? $_REQUEST['user_key'] : "");
$device_id = isset($_REQUEST['hwid']) ? $_REQUEST['hwid'] : (isset($_REQUEST['serial']) ? $_REQUEST['serial'] : "");

$user_key = trim($key_input);
$serial = trim($device_id);

// 2. Auto-Fix: Agar Device ID nahi aayi, to Fake ID bana lo
if (empty($serial)) {
    $serial = "UNKNOWN_DEVICE_" . md5($user_key);
}

// Check if Key is empty
if (empty($user_key)) {
    echo json_encode(["status" => false, "reason" => "Key Missing"]);
    exit;
}

// 3. Database Validation
$isValid = false;

// Check Premium Keys
$stmt = $conn->prepare("SELECT * FROM premium_keys WHERE key_code = ?");
$stmt->bind_param("s", $user_key);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $used_ips = $row['used_ips'] ? explode(',', $row['used_ips']) : [];

    if (strtotime($row['expiry_date']) > time()) {
        if (in_array($serial, $used_ips)) {
            $isValid = true;
        } elseif ($row['devices_used'] < $row['max_devices']) {
            $new_ips = $row['used_ips'] . ($row['used_ips'] ? ',' : '') . $serial;
            $new_count = $row['devices_used'] + 1;
            $update = $conn->prepare("UPDATE premium_keys SET used_ips = ?, devices_used = ? WHERE id = ?");
            $update->bind_param("sii", $new_ips, $new_count, $row['id']);
            $update->execute();
            $isValid = true;
        } else {
            echo json_encode(["status" => false, "reason" => "Device Limit Reached"]);
            exit;
        }
    } else {
        echo json_encode(["status" => false, "reason" => "Key Expired"]);
        exit;
    }
} else {
    // Check Free Keys
    $stmt2 = $conn->prepare("SELECT * FROM ip_logs WHERE generated_key = ?");
    $stmt2->bind_param("s", $user_key);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2->num_rows > 0) {
        $row = $result2->fetch_assoc();
        if ((time() - strtotime($row['created_at'])) < 86400) {
            $isValid = true;
        } else {
            echo json_encode(["status" => false, "reason" => "Free Key Expired"]);
            exit;
        }
    } else {
        echo json_encode(["status" => false, "reason" => "Invalid Key"]);
        exit;
    }
}

// 4. Success Response
if ($isValid) {
    $salt = "Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E"; 
    $raw_data = "PUBG" . "-" . $user_key . "-" . $serial . "-" . $salt;
    $token = md5($raw_data);
    
    echo json_encode([
        "status" => true,
        "data" => [
            "token" => $token,
            "rng"   => time(),
            "EXP"   => "Active"
        ]
    ]);
}
?>
