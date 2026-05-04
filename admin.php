<?php
session_start();
include 'db.php';

// 1. AUTOMATIC CLEANUP (Delete keys older than 24 hours)
$conn->query("DELETE FROM ip_logs WHERE created_at < NOW() - INTERVAL 1 DAY");

// 2. LOGIN LOGIC
if (isset($_POST['login'])) {
    if ($_POST['admin_pass'] === "FREEFIRE@123") { 
        $_SESSION['is_admin'] = true;
        header("Location: admin.php"); 
        exit;
    } else {
        $error = "Access Denied!";
    }
}

// 3. LOGOUT LOGIC
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// 4. GENERATE KEY LOGIC
if (isset($_POST['create_key']) && isset($_SESSION['is_admin'])) {
    $days = (int)$_POST['custom_days'];
    if ($days <= 0) $days = 1;

    $max_dev = (int)$_POST['max_devices'];
    $custom_input = trim($_POST['custom_key_input']);

    if (!empty($custom_input)) {
        $new_key = $custom_input;
    } else {
        $new_key = "VIP-" . strtoupper(bin2hex(random_bytes(3))) . "FEROX";
    }
    
    $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));
    
    $check = $conn->query("SELECT * FROM premium_keys WHERE key_code = '$new_key'");
    
    if ($check->num_rows > 0) {
        $_SESSION['msg'] = "Error: Key Already Exists!";
        $_SESSION['msg_type'] = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO premium_keys (key_code, max_devices, expiry_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $new_key, $max_dev, $expiry);
        
        if ($stmt->execute()) {
            $_SESSION['newly_created_key'] = $new_key;
            $_SESSION['msg'] = "Key Generated Successfully!";
            $_SESSION['msg_type'] = "success";
        }
    }
    header("Location: admin.php");
    exit;
}

// 5. DELETE SELECTED
if (isset($_POST['delete_selected']) && isset($_SESSION['is_admin'])) {
    if(!empty($_POST['check_list'])) {
        $ids_to_delete = $_POST['check_list'];
        $ids = implode(',', array_map('intval', $ids_to_delete));
        
        $conn->query("DELETE FROM ip_logs WHERE id IN ($ids)");
        
        $_SESSION['msg'] = "Selected Logs Deleted Successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "No keys selected!";
        $_SESSION['msg_type'] = "error";
    }
    header("Location: admin.php");
    exit;
}

// 6. DELETE ALL FREE KEYS
if (isset($_POST['del_all_free']) && isset($_SESSION['is_admin'])) {
    $conn->query("DELETE FROM ip_logs");
    $_SESSION['msg'] = "All Website Logs Deleted Successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: admin.php");
    exit;
}

// 7. INDIVIDUAL DELETE ACTIONS
if (isset($_GET['del_vip']) && isset($_SESSION['is_admin'])) {
    $id = (int)$_GET['del_vip'];
    $conn->query("DELETE FROM premium_keys WHERE id=$id");
    header("Location: admin.php");
    exit;
}
if (isset($_GET['del_free']) && isset($_SESSION['is_admin'])) {
    $id = (int)$_GET['del_free'];
    $conn->query("DELETE FROM ip_logs WHERE id=$id");
    header("Location: admin.php");
    exit;
}

// --- STATS LOGIC (NEW) ---
$total_keys = 0;
$today_keys = 0;
$active_vip = 0;
$expired_vip = 0;

if (isset($_SESSION['is_admin'])) {
    // Count Total
    $res_total = $conn->query("SELECT COUNT(*) as cnt FROM ip_logs");
    $total_keys = $res_total->fetch_assoc()['cnt'];

    // Count Today
    $res_today = $conn->query("SELECT COUNT(*) as cnt FROM ip_logs WHERE DATE(created_at) = CURDATE()");
    $today_keys = $res_today->fetch_assoc()['cnt'];
    
    // VIP Stats
    $res_vip = $conn->query("SELECT COUNT(*) as cnt FROM premium_keys WHERE expiry_date > NOW()");
    $active_vip = $res_vip->fetch_assoc()['cnt'];
    
    $res_expired = $conn->query("SELECT COUNT(*) as cnt FROM premium_keys WHERE expiry_date <= NOW()");
    $expired_vip = $res_expired->fetch_assoc()['cnt'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FEROX  PRIME  | ADMIN DASHBOARD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #03050a;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255, 0, 198, 0.05) 0%, transparent 30%),
                radial-gradient(circle at 90% 80%, rgba(0, 242, 255, 0.05) 0%, transparent 40%);
            min-height: 100vh;
            padding: 20px;
            color: #fff;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Premium Login Card */
        .login-container {
            max-width: 450px;
            margin: 100px auto;
        }

        .premium-card {
            background: rgba(12, 15, 25, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 30px 50px rgba(0, 0, 0, 0.8);
            position: relative;
            overflow: hidden;
        }

        .premium-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ff00c6, #00f2ff, #ff00c6);
            animation: scan 4s linear infinite;
        }

        .glow-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: block;
            border: 3px solid rgba(255, 0, 198, 0.5);
            box-shadow: 0 0 30px rgba(255, 0, 198, 0.3);
            animation: pulse 3s infinite;
        }

        @keyframes pulse {
            0%, 100% { border-color: rgba(255, 0, 198, 0.5); box-shadow: 0 0 30px rgba(255, 0, 198, 0.3); }
            50% { border-color: rgba(0, 242, 255, 0.5); box-shadow: 0 0 50px rgba(0, 242, 255, 0.4); }
        }

        @keyframes scan {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .premium-input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            color: #fff;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .premium-input:focus {
            border-color: #00f2ff;
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.3);
            background: rgba(0, 0, 0, 0.6);
        }

        .premium-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ff00c6, #00f2ff);
            border: none;
            border-radius: 15px;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .premium-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
        }

        .premium-btn:hover::before {
            left: 100%;
        }

        .premium-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 0, 198, 0.4), 0 0 30px rgba(0, 242, 255, 0.4);
        }

        /* Admin Dashboard */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-badge {
            background: linear-gradient(135deg, #ff00c6, #00f2ff);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            color: #000;
        }

        .logout-btn {
            background: rgba(255, 0, 85, 0.1);
            border: 1px solid #ff0055;
            color: #ff0055;
            padding: 10px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #ff0055;
            color: #fff;
            box-shadow: 0 0 20px #ff0055;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(12, 15, 25, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 50%, rgba(255, 255, 255, 0.02) 100%);
            pointer-events: none;
        }

        .stat-icon {
            font-size: 35px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #ff00c6, #00f2ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #888;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-today {
            color: #00ff88;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Main Cards */
        .main-card {
            background: rgba(12, 15, 25, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            background: linear-gradient(135deg, #ff00c6, #00f2ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .key-badge {
            background: linear-gradient(135deg, #ff00c6, #00f2ff);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 12px;
            color: #000;
            font-weight: 600;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #aaa;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #00f2ff;
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.2);
        }

        /* Success Message */
        .success-message {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid #00ff88;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
            animation: glow 2s infinite;
        }

        .key-display {
            background: #000;
            padding: 20px;
            border-radius: 15px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 20px 0;
            border: 2px dashed #00f2ff;
            color: #00f2ff;
            word-break: break-all;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(0, 255, 136, 0.2); }
            50% { box-shadow: 0 0 40px rgba(0, 255, 136, 0.4); }
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            color: #00f2ff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .key-cell {
            color: #00f2ff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .key-cell:hover {
            color: #ff00c6;
            text-shadow: 0 0 10px #ff00c6;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid #00ff88;
            color: #00ff88;
        }

        .status-expired {
            background: rgba(255, 0, 85, 0.1);
            border: 1px solid #ff0055;
            color: #ff0055;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .delete-btn {
            background: rgba(255, 0, 85, 0.1);
            border: 1px solid #ff0055;
            color: #ff0055;
        }

        .delete-btn:hover {
            background: #ff0055;
            color: #fff;
            box-shadow: 0 0 15px #ff0055;
        }

        .warning-btn {
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid #ffaa00;
            color: #ffaa00;
        }

        .warning-btn:hover {
            background: #ffaa00;
            color: #000;
            box-shadow: 0 0 15px #ffaa00;
        }

        .danger-btn {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid #ff0000;
            color: #ff0000;
        }

        .danger-btn:hover {
            background: #ff0000;
            color: #fff;
            box-shadow: 0 0 15px #ff0000;
        }

        .action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #00f2ff;
        }

        .time-ago {
            color: #fff;
            font-size: 13px;
        }

        .exact-time {
            color: #666;
            font-size: 11px;
            margin-top: 3px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .main-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 24px;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.3);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ff00c6, #00f2ff);
            border-radius: 10px;
        }

        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #00ff88, #00f2ff);
            color: #000;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!isset($_SESSION['is_admin'])): ?>
        <!-- Premium Login Page -->
        <div class="login-container">
            <div class="premium-card">
                <img src="logo.png" alt="FEROX  PRIME " class="glow-logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'45\' fill=\'%23000\' stroke=\'%23ff00c6\' stroke-width=\'3\'/%3E%3Ctext x=\'50\' y=\'65\' font-size=\'40\' text-anchor=\'middle\' fill=\'%23fff\' font-family=\'Arial\'%3ES%3C/text%3E%3C/svg%3E'">
                
                <h1 style="text-align: center; font-size: 28px; margin-bottom: 10px; background: linear-gradient(135deg, #fff, #00f2ff, #ff00c6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">FEROX  PRIME </h1>
                <p style="text-align: center; color: #666; margin-bottom: 30px; letter-spacing: 2px;">ADMIN ACCESS</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock"></i> SECURE PASSWORD</label>
                        <input type="password" name="admin_pass" class="premium-input" placeholder="Enter admin password">
                    </div>
                    
                    <button type="submit" name="login" class="premium-btn">
                        <i class="fas fa-unlock-alt"></i> UNLOCK PANEL
                    </button>
                    
                    <?php if(isset($error)): ?>
                        <p style="color: #ff0055; text-align: center; margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- Admin Dashboard -->
        
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-left">
                <img src="logo.png" alt="Logo" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #ff00c6; box-shadow: 0 0 20px #ff00c6;">
                <div>
                    <h1 style="font-size: 28px; margin: 0;">FEROX <span style="color: #00f2ff;">X MODS</span></h1>
                    <div class="admin-badge">
                        <i class="fas fa-crown"></i> ADMIN DASHBOARD
                    </div>
                </div>
            </div>
            <a href="?logout=true" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> LOGOUT
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-key"></i></div>
                <div class="stat-value"><?php echo $total_keys; ?></div>
                <div class="stat-label">TOTAL KEYS</div>
                <div class="stat-today"><i class="fas fa-arrow-up"></i> +<?php echo $today_keys; ?> today</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $today_keys; ?></div>
                <div class="stat-label">TODAY'S KEYS</div>
                <div class="stat-today"><i class="fas fa-calendar"></i> <?php echo date('d M'); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-diamond"></i></div>
                <div class="stat-value"><?php echo $active_vip; ?></div>
                <div class="stat-label">ACTIVE VIP</div>
                <div class="stat-today"><?php echo $expired_vip; ?> expired</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-shield"></i></div>
                <div class="stat-value">100%</div>
                <div class="stat-label">SECURITY</div>
                <div class="stat-today">ENCRYPTED</div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['newly_created_key'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle" style="color: #00ff88; font-size: 40px; margin-bottom: 10px;"></i>
                <h3 style="color: #00ff88;">KEY GENERATED SUCCESSFULLY!</h3>
                <div class="key-display" id="generatedKey"><?php echo $_SESSION['newly_created_key']; ?></div>
                <button onclick="copyGenKey()" class="premium-btn" style="width: auto; padding: 12px 30px;">
                    <i class="fas fa-copy"></i> COPY KEY
                </button>
            </div>
            <?php unset($_SESSION['newly_created_key']); ?>
        <?php endif; ?>

        <!-- Error/Success Messages -->
        <?php if(isset($_SESSION['msg']) && !isset($_SESSION['newly_created_key'])): ?>
            <div style="background: rgba(<?php echo ($_SESSION['msg_type'] == 'error') ? '255,0,85' : '0,255,136'; ?>, 0.1); border: 1px solid <?php echo ($_SESSION['msg_type'] == 'error') ? '#ff0055' : '#00ff88'; ?>; border-radius: 15px; padding: 15px; margin-bottom: 20px; color: <?php echo ($_SESSION['msg_type'] == 'error') ? '#ff0055' : '#00ff88'; ?>;">
                <i class="fas fa-<?php echo ($_SESSION['msg_type'] == 'error') ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Generate VIP Key Card -->
        <div class="main-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-crown"></i> GENERATE VIP KEY
                </div>
                <span class="key-badge"><i class="fas fa-bolt"></i> PREMIUM</span>
            </div>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tag"></i> Custom Key (Optional)</label>
                        <input type="text" name="custom_key_input" class="form-control" placeholder="e.g. FEROX-VIP-2026">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-devices"></i> Max Devices</label>
                        <input type="number" name="max_devices" class="form-control" value="1" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Validity (Days)</label>
                        <input type="number" name="custom_days" class="form-control" placeholder="e.g. 30" required>
                    </div>
                </div>
                
                <button type="submit" name="create_key" class="premium-btn">
                    <i class="fas fa-magic"></i> GENERATE VIP KEY
                </button>
            </form>
        </div>

        <!-- VIP Keys Table -->
        <div class="main-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-diamond"></i> VIP KEYS
                </div>
                <span class="key-badge"><?php echo $active_vip; ?> ACTIVE</span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>KEY CODE</th>
                            <th>DEVICES</th>
                            <th>EXPIRY</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM premium_keys ORDER BY id DESC";
                        $res = $conn->query($sql);
                        if($res->num_rows > 0) {
                            while($row = $res->fetch_assoc()) {
                                $days = floor((strtotime($row['expiry_date']) - time()) / 86400);
                                $status = ($days < 0) ? 'expired' : 'active';
                                $expiry_date = date('d M Y', strtotime($row['expiry_date']));
                                
                                echo "<tr>";
                                echo "<td class='key-cell' onclick='copyToClipboard(\"".$row['key_code']."\")' title='Click to copy'><i class='fas fa-key'></i> ".$row['key_code']."</td>";
                                echo "<td>".$row['devices_used']." / ".$row['max_devices']."</td>";
                                echo "<td>
                                        <span class='time-ago'>".$expiry_date."</span>
                                        <div class='exact-time'>".$days." days left</div>
                                      </td>";
                                echo "<td><span class='status-badge ".($status == 'active' ? 'status-active' : 'status-expired')."'>".ucfirst($status)."</span></td>";
                                echo "<td><a href='?del_vip=".$row['id']."' class='action-btn delete-btn' onclick='return confirm(\"Delete this VIP key?\")'><i class='fas fa-trash'></i> DELETE</a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; color: #666;'>No VIP keys found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Website Keys Card -->
        <div class="main-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-globe"></i> WEBSITE KEYS
                </div>
                <span class="key-badge">LAST 100</span>
            </div>
            
            <form method="POST">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="color: #aaa;"><i class="fas fa-chart-line"></i> Today: <span style="color: #00ff88;"><?php echo $today_keys; ?></span> | Total: <?php echo $total_keys; ?></span>
                    </div>
                    
                    <div class="action-group">
                        <button type="submit" name="delete_selected" class="action-btn warning-btn">
                            <i class="fas fa-check-double"></i> DELETE SELECTED
                        </button>
                        <button type="submit" name="del_all_free" class="action-btn danger-btn" onclick="return confirm('⚠️ WARNING: Delete all website keys?')">
                            <i class="fas fa-trash-alt"></i> DELETE ALL
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="checkAll" class="checkbox-custom"></th>
                                <th>#</th>
                                <th>IP ADDRESS</th>
                                <th>KEY CODE</th>
                                <th>CREATED</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $sql2 = "SELECT * FROM ip_logs ORDER BY id DESC LIMIT 100";
                        $res2 = $conn->query($sql2);
                        $s_no = 1;

                        if($res2->num_rows > 0) {
                            while($row = $res2->fetch_assoc()) {
                                $created_time = strtotime($row['created_at']);
                                $time_ago = round((time() - $created_time) / 60);
                                $time_txt = ($time_ago > 60) ? round($time_ago/60)."h ago" : $time_ago."m ago";
                                $exact_date = date("d M Y, h:i A", $created_time);

                                echo "<tr>";
                                echo "<td><input type='checkbox' name='check_list[]' value='".$row['id']."' class='checkbox-custom'></td>";
                                echo "<td style='color: #666;'>".$s_no++."</td>";
                                echo "<td><i class='fas fa-network-wired' style='color: #666;'></i> ".$row['user_ip']."</td>";
                                echo "<td class='key-cell' onclick='copyToClipboard(\"".$row['generated_key']."\")' title='Click to copy'><i class='fas fa-key'></i> ".$row['generated_key']."</td>";
                                echo "<td>
                                        <span class='time-ago'>".$time_txt."</span>
                                        <div class='exact-time'>".$exact_date."</div>
                                      </td>";
                                echo "<td><a href='?del_free=".$row['id']."' class='action-btn delete-btn' onclick='return confirm(\"Reset this key?\")'><i class='fas fa-undo-alt'></i> RESET</a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align: center; color: #666;'>No website keys found</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; color: #444; font-size: 12px;">
            <i class="fas fa-copyright"></i> 2026 FEROX  PRIME  | ADMIN PANEL v2.0
        </div>

    <?php endif; ?>
</div>

<script>
    function copyGenKey() {
        var keyText = document.getElementById("generatedKey").innerText;
        copyToClipboard(keyText);
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('✅ Key copied to clipboard!');
            });
        } else {
            // Fallback
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showNotification('✅ Key copied to clipboard!');
        }
    }

    function showNotification(message) {
        var notif = document.createElement('div');
        notif.className = 'copy-notification';
        notif.innerHTML = message;
        document.body.appendChild(notif);
        
        setTimeout(() => {
            notif.remove();
        }, 3000);
    }

    document.getElementById('checkAll')?.addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('input[name="check_list[]"]');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });

    // Add smooth animations
    document.querySelectorAll('.stat-card, .main-card').forEach((card, index) => {
        card.style.animation = `floatIn 0.5s ease-out ${index * 0.1}s both`;
    });

    const style = document.createElement('style');
    style.textContent = `
        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
</script>

</body>
</html>