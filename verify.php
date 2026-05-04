<?php
session_start();

// 🔒 SECURITY CONFIG
$secret_code = "FEROX-SECURE-998877"; 
$min_seconds = 120; // Kam se kam itna time lagna chahiye shortlink solve karne me

// Function to Show Error Screen
function die_error($title, $msg) {
    echo "<body style='background:#050505; color:white; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; text-align:center;'>";
    echo "<div style='border:1px solid red; padding:20px; border-radius:10px; box-shadow:0 0 15px red;'>";
    echo "<h1 style='color:red;'>🚫 $title</h1>";
    echo "<p>$msg</p>";
    echo "<a href='index.php' style='color:#00ff41; text-decoration:none; border:1px solid #00ff41; padding:5px 10px; border-radius:5px;'>TRY AGAIN</a>";
    echo "</div></body>";
    exit;
}

// 1. URL TOKEN CHECK
if (!isset($_GET['token']) || $_GET['token'] !== $secret_code) {
    die_error("INVALID TOKEN", "Link token mismatch. Check your shortner settings.");
}

// 2. FLOW CHECK (Direct Access Block)
if (!isset($_SESSION['flow_started']) || $_SESSION['flow_started'] !== true) {
    die_error("DIRECT ACCESS BLOCKED", "Mc Shortner Bypass Mat Kar Barna Lund Milega 🖕");
}

// 3. FINGERPRINT CHECK (IP/Browser Change Block)
if ($_SESSION['secure_ip'] !== $_SERVER['REMOTE_ADDR']) {
    die_error("IP CHANGED", "Your IP Address changed during the process. VPN/Proxy detected.");
}
if ($_SESSION['secure_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    die_error("BROWSER MISMATCH", "Do not change browsers or use scripts.");
}

// 4. TIME TRAP (Bypasser Protection)
$time_taken = time() - $_SESSION['start_time'];
if ($time_taken < $min_seconds) {
    die_error("FAST BYPASS DETECTED", "Mc Shortner Bypass Mat Kar Lund Milega Barna 🖕");
}

// ✅ SUCCESS: Verification Complete
$_SESSION['verification_complete'] = true;

// Clear security sessions
unset($_SESSION['flow_started']);
unset($_SESSION['start_time']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifying...</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #050505; color: #fff; font-family: 'Rajdhani', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { text-align: center; width: 100%; max-width: 400px; }
        .card { background: #111; padding: 30px; border-radius: 15px; border: 1px solid #333; box-shadow: 0 0 20px rgba(0, 255, 65, 0.2); animation: pulseBorder 2s infinite; }
        @keyframes pulseBorder { 0% { border-color: #00ff41; } 50% { border-color: #008f11; } 100% { border-color: #00ff41; } }
        #finalBtn { background: linear-gradient(45deg, #009926, #00ff41); border: none; color: #000; padding: 12px 30px; font-weight: bold; font-size: 16px; border-radius: 5px; cursor: pointer; width: 100%; margin-top:15px; }
    </style>
    
    <script>
        var timeleft = 3; 
        var downloadTimer = setInterval(function(){
          if(timeleft <= 0){
            clearInterval(downloadTimer);
            document.getElementById("loading-area").style.display = "none";
            document.getElementById("success-area").style.display = "block";
          } else {
            document.getElementById("timer").innerHTML = timeleft;
          }
          timeleft -= 1;
        }, 1000);
    </script>
</head>
<body>
    <div class="container">
        <div class="card">
            <img src="logo.png" style="width:70px; border-radius:50%; border:2px solid #00ff41;">
            <h2>FINAL CHECK...</h2>
            
            <div id="loading-area">
                <p>Verifying Session Fingerprint...</p>
                <h1 id="timer" style="color:#00ff41;">3</h1>
            </div>

            <div id="success-area" style="display:none;">
                <h3 style="color:#00ff41;">VERIFIED!</h3>
                <p style="font-size:13px; color:#aaa;">Security checks passed.</p>
                <form action="index.php" method="POST">
                    <button type="submit" name="generate_final" id="finalBtn">GENERATE KEY</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
