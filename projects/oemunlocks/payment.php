<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security Recommendation: Use environment variables or a config file for sensitive keys
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$chatId = getenv('TELEGRAM_CHAT_ID') ?: '';
$yourWallet = "bc1qtema35w6qeeqmwnuxr3u9f3nx3gvqc79cl7dxv";

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $email = $_POST['email'] ?? 'Unknown';
    $tier = $_POST['tier'] ?? 'None';
    $txid = $_POST['txid'] ?? 'No TXID provided';
    
    $message = "💰 *NEW PAYMENT RECEIVED* 💰\n\n" . 
               "📧 *Email:* $email\n" . 
               "📦 *Tier:* $tier\n" . 
               "🔗 *TXID:* $txid";
               
    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message) . "&parse_mode=Markdown";
    
    // Note: file_get_contents requires allow_url_fopen to be enabled in php.ini
    // For production, cURL is generally more reliable for external API requests.
    @file_get_contents($url); 
    
    echo "Payment submitted! We are verifying your transaction.";
    exit; // Stop execution after form submission to prevent showing the form again
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Verification</title>
    <style>
:root {
    --bg:#060806;
    --bg-soft:#0c0f0c;
    --accent:#c6ff1a;
    --accent-dim:#3d4a12;
    --text:#eef4f6;
    --text-dim:#8a97a3;
    --line:#1c2128;
}

* {
    box-sizing:border-box;
    margin:0;
    padding:0;
}

html, body {
    background:var(--bg);
    color:var(--text);
    font-family:"Segoe UI",system-ui,-apple-system,sans-serif;
    -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
}

body {
    min-height:100vh;
    padding:0;
    max-width:none;
    position:relative;
}

body::before {
    content:"";
    position:fixed;
    top:-20%;
    right:-30%;
    width:80vw;
    height:80vw;
    max-width:700px;
    max-height:700px;
    background:radial-gradient(
        circle,
        rgba(198,255,26,.14) 0%,
        rgba(198,255,26,0) 70%
    );
    pointer-events:none;
}

body::after {
    content:"";
    position:fixed;
    inset:0;
    background:linear-gradient(
        180deg,
        rgba(255,255,255,.015),
        transparent 35%
    );
    pointer-events:none;
}

h1 {
    position:relative;
    font-size:clamp(32px,9vw,46px);
    line-height:1.08;
    font-weight:800;
    letter-spacing:-.02em;
    color:var(--text);
    margin:0 0 14px;
}

body > p {
    position:relative;
    max-width:680px;
    margin:0 auto 28px;
    padding:0 22px;
    color:var(--text-dim);
    font-size:16px;
    line-height:1.65;
    text-align:center;
}

body > p b {
    color:var(--accent);
}

.wallet-box {
    position:relative;
    width:calc(100% - 32px);
    max-width:680px;
    margin:0 auto 28px;
    padding:20px;
    background:var(--bg-soft)!important;
    border:1px solid var(--accent-dim)!important;
    border-radius:10px;
    text-align:center;
    box-shadow:0 12px 40px rgba(0,0,0,.25);
}

.wallet-box code {
    display:block;
    color:var(--accent)!important;
    font-family:ui-monospace,SFMono-Regular,Consolas,monospace;
    font-size:15px!important;
    line-height:1.6;
    word-break:break-all;
}

form {
    position:relative;
    width:calc(100% - 32px);
    max-width:680px;
    margin:0 auto;
    padding:26px 22px 28px;
    background:var(--bg-soft);
    border:1px solid var(--line);
    border-radius:12px;
    box-shadow:0 18px 50px rgba(0,0,0,.25);
}

label {
    display:block;
    margin:0 0 8px;
    color:var(--text);
    font-family:"Segoe UI",system-ui,sans-serif;
    font-size:14px;
    font-weight:600;
}

input {
    display:block;
    width:100%;
    min-height:52px;
    padding:14px 15px;
    margin:0 0 20px;
    background:#060806!important;
    border:1px solid var(--line)!important;
    border-radius:7px;
    color:var(--text)!important;
    font-family:"Segoe UI",system-ui,sans-serif;
    font-size:16px;
    outline:none;
}

input::placeholder {
    color:#66727c;
}

input:focus {
    border-color:var(--accent-dim)!important;
    box-shadow:0 0 0 3px rgba(198,255,26,.07);
}

input[readonly] {
    color:var(--text-dim)!important;
}

button {
    display:block;
    width:100%;
    min-height:54px;
    margin-top:4px;
    padding:14px 20px;
    background:var(--accent)!important;
    color:#060806!important;
    border:0;
    border-radius:7px;
    font-family:"Segoe UI",system-ui,sans-serif;
    font-size:15px;
    font-weight:800;
    letter-spacing:.04em;
    text-transform:uppercase;
    cursor:pointer;
}

button:hover {
    background:#d7ff55!important;
}

button:active {
    transform:scale(.985);
}

@media (max-width:600px) {
    h1 {
        font-size:34px;
        padding:0 16px;
    }

    body > p {
        font-size:16px;
        padding:0 20px;
    }

    .wallet-box {
        width:calc(100% - 24px);
        padding:18px 14px;
    }

    .wallet-box code {
        font-size:14px!important;
    }

    form {
        width:calc(100% - 24px);
        padding:24px 16px 22px;
        border-radius:10px;
    }

    input {
        min-height:54px;
        font-size:16px;
    }

    button {
        min-height:56px;
        font-size:15px;
    }
}
</style>
</head>
<body>
    <h1>PAYMENT REQUIRED</h1>
    <p>To receive access, send exactly <b>0.001 BTC</b> to the address below:</p>
    
    <div class="wallet-box">
        <code style="font-size:1.2rem; word-break:break-all; color: #fff;">
            <?php echo htmlspecialchars($yourWallet); ?>
        </code>
    </div>
    
    <form method="POST">      
        <label for="email">Email Address:</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
        
        <label for="tier">Payment Tier:</label>
        <input type="text" id="tier" name="tier" value="Standard Unlock" readonly>
        
        <label for="txid">Transaction ID (TXID):</label>
        <input type="text" id="txid" name="txid" placeholder="Paste TXID from blockchain" required>
        
        <button type="submit">Submit Payment</button>
    </form>
</body>
</html>
