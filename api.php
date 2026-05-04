<?php
include 'db.php';
error_reporting(0);
header('Content-Type: application/json');

$key_input = isset($_REQUEST['key']) ? $_REQUEST['key'] : (isset($_REQUEST['user_key']) ? $_REQUEST['user_key'] : "");
$device_id = isset($_REQUEST['hwid']) ? $_REQUEST['hwid'] : (isset($_REQUEST['serial']) ? $_REQUEST['serial'] : "");

$user_key = trim($key_input);
$serial = trim($device_id);

if (empty($serial)) { $serial = "UNKNOWN_" . md5($user_key); }
if (empty($user_key)) { echo json_encode(["status" => false, "reason" => "Key Missing"]); exit; }

$isValid = false;
$expiryTimestamp = 0; // ✅ NEW VARIABLE

// 1. VIP KEYS
$stmt = $conn->prepare("SELECT * FROM premium_keys WHERE key_code = ?");
$stmt->bind_param("s", $user_key);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $used_ips = $row['used_ips'] ? explode(',', $row['used_ips']) : [];
    
    // ✅ Calculate Expiry Timestamp
    $expiryTimestamp = strtotime($row['expiry_date']);

    if ($expiryTimestamp > time()) {
        if (in_array($serial, $used_ips)) {
            $isValid = true;
        } elseif ($row['devices_used'] < $row['max_devices']) {
            $new_ips = $row['used_ips'] . ($row['used_ips'] ? ',' : '') . $serial;
            $new_count = $row['devices_used'] + 1;
            $conn->query("UPDATE premium_keys SET used_ips = '$new_ips', devices_used = $new_count WHERE id = " . $row['id']);
            $isValid = true;
        } else {
            echo json_encode(["status" => false, "reason" => "Device Limit Reached"]); exit;
        }
    } else {
        echo json_encode(["status" => false, "reason" => "Key Expired"]); exit;
    }
} 
// 2. FREE KEYS (1 Device Lock)
else {
    $stmt2 = $conn->prepare("SELECT * FROM ip_logs WHERE generated_key = ?");
    $stmt2->bind_param("s", $user_key);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2->num_rows > 0) {
        $row = $result2->fetch_assoc();
        
        $created = strtotime($row['created_at']);
        // ✅ Calculate Expiry (Created + 6 Hours)
        $expiryTimestamp = $created + 21600;

        if ((time() - $created) > 21600) {
            echo json_encode(["status" => false, "reason" => "Key Expired"]); exit;
        }

        // DEVICE LOCK CHECK
        if ($row['device_id'] == NULL || $row['device_id'] == "") {
            $conn->query("UPDATE ip_logs SET device_id = '$serial' WHERE id = " . $row['id']);
            $isValid = true;
        } elseif ($row['device_id'] == $serial) {
            $isValid = true;
        } else {
            echo json_encode(["status" => false, "reason" => "Key Used on Another Device"]); exit;
        }
    } else {
        echo json_encode(["status" => false, "reason" => "Invalid Key"]); exit;
    }
}

if ($isValid) {
    // ✅ Sending 'EXP' back to C++
    echo json_encode([
        "status" => true, 
        "data" => [
            "token" => md5("SALT".$user_key), 
            "rng" => time(),
            "EXP" => $expiryTimestamp
        ]
    ]);
}
?>
