<?php
session_start();
include 'db.php';

// --- 1. REAL IP DETECTION ---
function getUserIP() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) return $_SERVER["HTTP_CF_CONNECTING_IP"];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $addr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($addr[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

$user_ip = getUserIP();
$show_key = false;
$final_key = "";
$status_text = "Waiting for Verification...";

// --- 2. AUTO CLEANUP (Delete keys older than 1 MINUTE) ---
$conn->query("DELETE FROM ip_logs WHERE created_at < NOW() - INTERVAL 1 MINUTE");

// --- 3. CHECK EXISTING KEY (Only if less than 1 minute old) ---
$checkSql = "SELECT * FROM ip_logs WHERE user_ip = '$user_ip' AND created_at >= NOW() - INTERVAL 1 MINUTE LIMIT 1";
$result = $conn->query($checkSql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $show_key = true;
    $final_key = $row['generated_key'];
    
    // Calculate remaining time
    $created_time = strtotime($row['created_at']);
    $expiry_time = $created_time + 60; // 60 seconds = 1 minute
    $current_time = time();
    $remaining_seconds = $expiry_time - $current_time;
    
    if ($remaining_seconds > 0) {
        $status_text = "Active Key (Expires in {$remaining_seconds}s)";
    } else {
        // Key expired, delete it
        $conn->query("DELETE FROM ip_logs WHERE user_ip = '$user_ip'");
        $show_key = false;
        $status_text = "Key Expired - Verify Again";
    }
}

// --- 4. GENERATE KEY LOGIC (Only after Verification) ---
if (isset($_POST['generate_final'])) {
    if (isset($_SESSION['verification_complete']) && $_SESSION['verification_complete'] == true) {
        
        // Purana record delete karo
        $conn->query("DELETE FROM ip_logs WHERE user_ip = '$user_ip'");

        $final_key = "FEROX -" . strtoupper(bin2hex(random_bytes(3))) . "-XMOD";
        
        // Save to DB with current timestamp
        $stmt = $conn->prepare("INSERT INTO ip_logs (user_ip, generated_key, device_id, created_at) VALUES (?, ?, NULL, NOW())");
        $stmt->bind_param("ss", $user_ip, $final_key);
        $stmt->execute();
        
        $show_key = true;
        unset($_SESSION['verification_complete']);
        header("Refresh:0");
        exit;
    } 
    else {
        session_destroy();
        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FEROX  PRIME  | KEY GENERATION PORTAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, rgba(0, 0, 0, 0.95) 0%, rgba(10, 10, 20, 1) 90%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 0, 100, 0.03) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(0, 255, 255, 0.03) 0%, transparent 30%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 500px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: floatIn 0.8s ease-out;
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .neon-card {
            background: rgba(12, 12, 20, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 28px 24px;
            margin-bottom: 20px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(255, 255, 255, 0.02) inset;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .neon-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ff00c6, #00f2ff, transparent);
            opacity: 0.5;
        }

        .neon-card.active-card::before {
            opacity: 1;
            height: 3px;
            background: linear-gradient(90deg, transparent, #ff00c6, #00f2ff, #ff00c6, transparent);
            animation: scan 3s linear infinite;
        }

        @keyframes scan {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .status-dot {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #aaa;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #00ff88;
            box-shadow: 0 0 10px #00ff88;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo-glow {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 0, 198, 0.5);
            box-shadow: 
                0 0 30px rgba(255, 0, 198, 0.3),
                0 0 60px rgba(0, 242, 255, 0.2);
            animation: logoPulse 3s ease-in-out infinite;
            background: rgba(0, 0, 0, 0.3);
            padding: 5px;
        }

        @keyframes logoPulse {
            0%, 100% {
                border-color: rgba(255, 0, 198, 0.5);
                box-shadow: 0 0 30px rgba(255, 0, 198, 0.3), 0 0 60px rgba(0, 242, 255, 0.2);
            }
            50% {
                border-color: rgba(0, 242, 255, 0.5);
                box-shadow: 0 0 50px rgba(0, 242, 255, 0.4), 0 0 80px rgba(255, 0, 198, 0.3);
            }
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            background: linear-gradient(135deg, #fff 0%, #00f2ff 50%, #ff00c6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        h2 {
            font-size: 20px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        h2 i {
            color: #00f2ff;
            font-size: 20px;
        }

        .custom-input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: #fff;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .custom-input:focus {
            border-color: #00f2ff;
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.3);
            background: rgba(0, 0, 0, 0.6);
        }

        select.custom-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2300f2ff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #ff00c6 0%, #00f2ff 100%);
            border: none;
            padding: 16px 32px;
            border-radius: 16px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn-gradient:hover::before {
            left: 100%;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 10px 30px rgba(255, 0, 198, 0.4),
                0 0 30px rgba(0, 242, 255, 0.4);
        }

        .btn-secondary {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 0, 198, 0.5);
            color: #ff00c6 !important;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 0, 198, 0.1);
            border-color: #ff00c6;
            box-shadow: 0 0 20px rgba(255, 0, 198, 0.3);
        }

        .log-box {
            background: rgba(0, 0, 0, 0.6);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-family: 'Courier New', monospace;
        }

        .log-box div {
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .log-box div:last-child {
            border-bottom: none;
        }

        .key-display {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            letter-spacing: 2px;
            background: linear-gradient(135deg, rgba(0, 242, 255, 0.1), rgba(255, 0, 198, 0.1));
            padding: 18px;
            border-radius: 16px;
            text-align: center;
            margin: 15px 0;
            border: 1px dashed rgba(255, 255, 255, 0.2);
            color: #00f2ff;
            font-weight: bold;
            word-break: break-all;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .key-display:hover {
            background: linear-gradient(135deg, rgba(0, 242, 255, 0.2), rgba(255, 0, 198, 0.2));
            transform: scale(1.02);
        }

        .divider {
            margin: 20px 0;
            position: relative;
            text-align: center;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        }

        .divider span {
            background: rgba(12, 12, 20, 0.9);
            padding: 0 15px;
            color: #666;
            font-size: 12px;
            position: relative;
            z-index: 1;
        }

        .timer-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin: 10px 0;
            overflow: hidden;
        }

        .timer-progress {
            height: 100%;
            background: linear-gradient(90deg, #00ff88, #00f2ff);
            width: 100%;
            animation: shrink 60s linear forwards;
        }

        @keyframes shrink {
            from { width: 100%; }
            to { width: 0%; }
        }

        @keyframes glow {
            0%, 100% { text-shadow: 0 0 10px #00f2ff; }
            50% { text-shadow: 0 0 20px #ff00c6; }
        }

        .glow-text {
            animation: glow 2s infinite;
        }

        .floating-icon {
            position: fixed;
            font-size: 24px;
            color: rgba(255, 255, 255, 0.02);
            z-index: 0;
            pointer-events: none;
        }

        .expiry-text {
            color: #ffaa00;
            font-size: 12px;
            margin-top: 5px;
            animation: pulse 2s infinite;
        }

        @media (max-width: 500px) {
            .neon-card {
                padding: 20px 16px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .logo-glow {
                width: 80px;
                height: 80px;
            }
            
            .btn-gradient {
                padding: 14px 24px;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
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
            color: black;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 5px 20px rgba(0, 255, 136, 0.3);
            z-index: 9999;
            animation: slideIn 0.3s ease, fadeOut 2s ease 1.5s forwards;
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

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    </style>
    <script>
        // Disable Inspect Element
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.onkeydown = function(e) {
            if(e.keyCode == 123) return false;
            if(e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charAt(0)) return false;
            if(e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charAt(0)) return false;
            if(e.ctrlKey && e.keyCode == 'U'.charAt(0)) return false;
        }
    </script>
</head>
<body>

    <div class="floating-icon" style="top: 10%; left: 5%;"><i class="fas fa-shield-alt"></i></div>
    <div class="floating-icon" style="bottom: 15%; right: 8%;"><i class="fas fa-key"></i></div>
    <div class="floating-icon" style="top: 30%; right: 12%;"><i class="fas fa-lock"></i></div>

    <div class="container">
        
        <div class="neon-card active-card">
            <div class="status-dot">
                <i class="fas fa-shield-alt" style="color: #00ff88;"></i>
                System Status: Online 
                <span class="dot"></span>
            </div>
            <div class="logo-container">
                <img src="logo.png" alt="FEROX  PRIME " class="logo-glow" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'45\' fill=\'%23000\' stroke=\'%23ff00c6\' stroke-width=\'3\'/%3E%3Ctext x=\'50\' y=\'65\' font-size=\'40\' text-anchor=\'middle\' fill=\'%23fff\' font-family=\'Arial\'%3ES%3C/text%3E%3C/svg%3E'">
            </div>
            <h1>FEROX PRIME</h1>
            <p style="text-align: center; color: #888; font-size: 12px; letter-spacing: 2px; margin-top: 10px;">
                <i class="fas fa-bolt" style="color: #00f2ff;"></i> KEY GENERATION PORTAL <i class="fas fa-bolt" style="color: #ff00c6;"></i>
            </p>
        </div>

        <div class="neon-card">
            <?php if ($show_key): 
                // Calculate remaining time for JavaScript
                $created_time = strtotime($row['created_at']);
                $expiry_time = $created_time + 60;
                $current_time = time();
                $remaining = $expiry_time - $current_time;
            ?>
                <h2>
                    <i class="fas fa-check-circle" style="color: #00ff88;"></i>
                    Access Key Generated
                </h2>
                <p style="color: #00ff88; font-size: 12px; margin-bottom: 5px;">
                    <i class="fas fa-shield-alt"></i> Status: Verified & Secure
                </p>
                
                <!-- Timer Display -->
                <div style="text-align: center; margin-bottom: 10px;">
                    <span class="expiry-text" id="timerDisplay">
                        <i class="fas fa-hourglass-half"></i> Key expires in <span id="seconds"><?php echo $remaining; ?></span> seconds
                    </span>
                </div>
                
                <!-- Timer Progress Bar -->
                <div class="timer-bar">
                    <div class="timer-progress" id="timerProgress" style="animation: shrink <?php echo $remaining; ?>s linear forwards;"></div>
                </div>
                
                <!-- Key Display -->
                <div class="key-display" id="keyDisplay" onclick="copyKey()">
                    <?php echo $final_key; ?>
                    <i class="fas fa-copy" style="margin-left: 10px; font-size: 14px; opacity: 0.7;"></i>
                </div>
                <input type="text" value="<?php echo $final_key; ?>" id="keyInput" style="position: absolute; opacity: 0; pointer-events: none;">
                
                <button onclick="copyKey()" class="btn-gradient">
                    <i class="fas fa-copy"></i> Copy Key
                </button>
                
                <div class="divider">
                    <span>NEED NEW KEY?</span>
                </div>
                
                <div style="text-align: center;">
                    <a href="go.php?id=1" class="btn-secondary">
                        <i class="fas fa-sync-alt"></i> RESET & GENERATE NEW
                    </a>
                </div>

                <!-- Auto-refresh after expiry -->
                <script>
                    var secondsLeft = <?php echo $remaining; ?>;
                    var timer = setInterval(function() {
                        secondsLeft--;
                        document.getElementById('seconds').textContent = secondsLeft;
                        
                        if (secondsLeft <= 0) {
                            clearInterval(timer);
                            // Show countdown finished message
                            document.getElementById('timerDisplay').innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #ff0055;"></i> Key Expired - Refreshing...';
                            // Refresh page after 1 second
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    }, 1000);
                </script>

            <?php else: ?>
                <h2>
                    <i class="fas fa-shield-alt" style="color: #00f2ff;"></i>
                    Complete Verification
                </h2>
                
                <div style="margin-bottom: 20px;">
                    <label style="color: #aaa; font-size: 13px; margin-bottom: 8px; display: block;">
                        <i class="fas fa-server"></i> Select Server Region
                    </label>
                    <select id="serverSelect" class="custom-input" onchange="changeLink()">
                        <option value="1">🚀 Region Server 1 - ( Fast )</option>
                        <option value="2">⚡ Region Server 2 - ( Balanced )</option>
                        <option value="3">🛡 Region Server 3 - ( Secure )</option>
                    </select>
                </div>
                
                <a id="genBtn" href="go.php?id=1" class="btn-gradient">
                    <i class="fas fa-play"></i> START VERIFICATION
                </a>
                
            <?php endif; ?>
        </div>

        <div class="neon-card">
            <h2>
                <i class="fas fa-terminal" style="color: #00ff88;"></i>
                Security Log
            </h2>
            <div class="log-box">
                <div>
                    <span style="color: #00f2ff;">[<?php echo date("H:i:s"); ?>]</span> 
                    <i class="fas fa-network-wired" style="color: #666;"></i>
                    <span style="color: #888;">IP:</span> 
                    <span style="color: #fff;"><?php echo $user_ip; ?></span>
                </div>
                <div>
                    <span style="color: #00f2ff;">[<?php echo date("H:i:s"); ?>]</span> 
                    <i class="fas fa-shield-virus" style="color: #666;"></i>
                    <span style="color: #888;">Anti-Bypass:</span> 
                    <span style="color: #00ff88;">Active</span>
                </div>
                <div>
                    <span style="color: #00f2ff;">[<?php echo date("H:i:s"); ?>]</span> 
                    <i class="fas fa-key" style="color: #666;"></i>
                    <span style="color: #888;">Status:</span> 
                    <span style="color: <?php echo $show_key ? '#00ff88' : '#ff00c6'; ?>;"><?php echo $status_text; ?></span>
                </div>
                <div>
                    <span style="color: #00f2ff;">[<?php echo date("H:i:s"); ?>]</span> 
                    <i class="fas fa-clock" style="color: #666;"></i>
                    <span style="color: #888;">Session:</span> 
                    <span style="color: #00f2ff;">Secure</span>
                </div>
                <?php if ($show_key): ?>
                <div>
                    <span style="color: #00f2ff;">[<?php echo date("H:i:s"); ?>]</span> 
                    <i class="fas fa-hourglass-half" style="color: #666;"></i>
                    <span style="color: #888;">Expires in:</span> 
                    <span style="color: #ffaa00;" id="statusTimer"><?php echo $remaining; ?>s</span>
                </div>
                <script>
                    // Update status timer
                    var statusSeconds = <?php echo $remaining; ?>;
                    var statusTimer = setInterval(function() {
                        statusSeconds--;
                        var statusElement = document.getElementById('statusTimer');
                        if (statusElement) {
                            statusElement.textContent = statusSeconds + 's';
                        }
                        if (statusSeconds <= 0) {
                            clearInterval(statusTimer);
                        }
                    }, 1000);
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function changeLink() { 
            var id = document.getElementById("serverSelect").value;
            document.getElementById("genBtn").href = "go.php?id=" + id; 
            
            document.getElementById("genBtn").style.animation = "none";
            setTimeout(() => {
                document.getElementById("genBtn").style.animation = "glow 2s infinite";
            }, 10);
        }

        function copyKey() {
            // Modern clipboard API
            var keyText = document.getElementById("keyDisplay").innerText;
            
            // Remove the copy icon text if present
            keyText = keyText.replace('', '').trim();
            
            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(keyText).then(function() {
                    showNotification('✅ Key copied to clipboard!');
                }).catch(function(err) {
                    // Fallback to older method
                    fallbackCopy();
                });
            } else {
                // Fallback for older browsers
                fallbackCopy();
            }
        }

        function fallbackCopy() {
            var keyDisplay = document.getElementById("keyDisplay");
            var keyText = keyDisplay.innerText.replace('', '').trim();
            
            // Create temporary textarea
            var textarea = document.createElement('textarea');
            textarea.value = keyText;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    showNotification('✅ Key copied to clipboard!');
                } else {
                    showNotification('❌ Copy failed!', 'error');
                }
            } catch (err) {
                showNotification('❌ Copy failed!', 'error');
            }
            
            document.body.removeChild(textarea);
        }

        function showNotification(message, type = 'success') {
            // Remove existing notification
            var existingNotif = document.querySelector('.copy-notification');
            if (existingNotif) {
                existingNotif.remove();
            }
            
            // Create new notification
            var notif = document.createElement('div');
            notif.className = 'copy-notification';
            notif.innerHTML = message;
            document.body.appendChild(notif);
            
            // Auto remove after animation
            setTimeout(() => {
                if (notif && notif.parentNode) {
                    notif.remove();
                }
            }, 3000);
        }

        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.neon-card');
            cards.forEach((card, index) => {
                card.style.animation = `floatIn 0.8s ease-out ${index * 0.1}s both`;
            });
        });
    </script>
</body>
</html>