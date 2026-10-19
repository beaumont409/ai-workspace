<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | O.E.M.
        :root { --bg: #0a0a0a; --card: #141414; --accent: #c6ff1a; --text: #ffffff; --text-dim: #888; --line: #222; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }   .checkout-card { background: var(--card)
        border: 1px solid var(--line); padding: 32px; border-radius: 16px; width: 100%; max-width: 400px; text-align: center; }
        .amount-display { font-size: 36px; font-weight: 800; color: var(--accent); margin: 16px 0; }
        .address-container { background: #000; padding: 16px; border-radius: 8px; border: 1px dashed var(--text-dim)
        margin-bottom: 8px; font-family: monospace; font-size: 14px; word-break: break-all; color: var(--text); cursor: pointer; }
        .address-container:hover { border-color: var(--accent); }     .status-msg { font-size: 14px; color: var(--text-dim); margin-bottom: 24px; }
        .spinner { width: 40px; height: 40px; border: 4px solid var(--line); border-top: 4px solid var(--accent)
        border-radius: 50%; animation: spin 1s linear infinite; display: none; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }       input { width: 100%; padding: 12px; background: #000; border: 1px solid var(--line); border-radius: 8px; color: #fff; margin-bottom: 16px; box-sizing: border-box; }
        input:focus { border-color: var(--accent); outline: none; }       .pay-btn { width: 100%; padding: 14px; background: var(--accent); color: #000;border: none; border-radius: 8px; font-weight: 700; font-size: 16px; cursor: pointer; }   </style>
</head>
<body>
    <div class="checkout-card">
        <div class="logo" style="color:var(--accent); font-weight: 800; margin-bottom: 8px;">O.E.M. UNLOCKS</div>        <div class="status-msg"
        <div class="status-msg">SECURE CRYPTO CHECKOUT</div>
        <div class="amount-display" id="display-amount">--- BTC</div>
        <div style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">SEND EXACTLY TOTHIS ADDRESS:</div>
        <div class="address-container" id="btc-address" onclick="copyAddress()"
        <div style="font-size: 11px; color: var(--accent); margin-top: 4px;">Click to copy address</div>

        <div style="margin-top: 32px; text-align: left;">            <label style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px; display: block;">DEVICE MODEL (e.g. Samsung S21)</label>
            <input type="text" name="model" placeholder="Enter device model"
            <label style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px; display: block;">IMEI / SERIAL NUMBER</label>
            <input type="text" name="imei" placeholder="Enter IMEI number" required>        <div id="loading-container" style="display:none;">        <div class="spinner"></div>           <div class="status-msg">Scanning blockchain for transaction...</div>    </div>

            <button type="submit" class="pay-btn" id="submit-btn"
        onclick="handleSubmit(event)">COMPLETE PAYMENT</button>
    </div>

    <script>
        const btcAddress = "1BvBMSEYstWetqTFn5Au4m4GFg7xWBwgWV"; // YOUR BTC ADDRESS HERE

        function copyAddress() {
            navigator.clipboard.writeText(btcAddress);       alert("Address copied to clipboard");
        }

        async function updateAmount() {            const params = new URLSearchParams(window.location.search);       const service = params.get('service');
            let usdAmount = 0;

            if (service === 'bootloader')
            if(service === 'bootloader') {
                usdAmount = 45.00;
            } else if (service === 'frp') {
                usdAmount = 30.00;
            }

            try {
                const response = await fetch('https://api.coindesk.com/v1/bpi/currentprice.json');
                const data = await response.json();   const btcPrice = data.bpi.USD.rate_float;       const btcAmount = (usdAmount / btcPrice).toFixed(6);
                document.getElementById('display-amount').textContent = btcAmount + " BTC"
                document.getElementById('display-amount').textContent = btcAmount + " BTC";           } catch (err) {     console.error("Price fetch failed", err);
            }
        }

        async function handleSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');       const loading = document.getElementById('loading-container');       const form = e.target.closest('form');         btn.style.display = 'none';
            loading.style.display = 'block';

            // Simulate blockchain confirmation delay (5 seconds)  setTimeout(() => {       alert("Transaction detected! Your unlock request is being processed. Check your email shortly.")
        }      updateAmount();  </script>
</body>
</html>









