<?php require_once __DIR__ . '/checkout.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | O.E.M.</title>
    <style>
        :root { --bg: #0a0a0a; --card: #141414; --accent: #c6ff1a; --text: #ffffff; --text-dim: #888; --line: #222; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .checkout-card { background: var(--card); border: 1px solid var(--line); padding: 32px; border-radius: 16px; width: 100%; max-width: 400px; text-align: center; }
        .amount-display { font-size: 36px; font-weight: 800; color: var(--accent); margin: 16px 0; }
        .address-container { background: #000; padding: 16px; border-radius: 8px; border: 1px dashed var(--text-dim); margin-bottom: 8px; font-family: monospace; font-size: 14px; word-break: break-all; color: var(--text); cursor: pointer; }
        .address-container:hover { border-color: var(--accent); }
        .status-msg { font-size: 14px; color: var(--text-dim); margin-bottom: 24px; }
        input { width: 100%; padding: 12px; background: #000; border: 1px solid var(--line); border-radius: 8px; color: #fff; margin-bottom: 16px; box-sizing: border-box; }
        input:focus { border-color: var(--accent); outline: none; }
        label { font-size: 12px; color: var(--text-dim); margin-bottom: 8px; display: block; }
        .pay-btn { width: 100%; padding: 14px; background: var(--accent); color: #000; border: none; border-radius: 8px; font-weight: 700; font-size: 16px; cursor: pointer; }
        .pay-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="checkout-card">
        <div class="logo" style="color:var(--accent); font-weight: 800; margin-bottom: 8px;">O.E.M. UNLOCKS</div>
        <div class="status-msg">SECURE CRYPTO CHECKOUT</div>
        <div class="amount-display" id="display-amount">--- BTC</div>
        <p id="quote-status" class="status-msg" role="status">Fetching quote. Do not send payment yet.</p>
        <div id="payment-destination" hidden>
            <div style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">SEND EXACTLY TO THIS ADDRESS:</div>
            <div class="address-container" id="btc-address" role="button" tabindex="0" onclick="copyAddress()" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); copyAddress(); }"><?= htmlspecialchars(BTC_ADDRESS, ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size: 11px; color: var(--accent); margin-top: 4px;">Click to copy address</div>
        </div>

        <form id="order-form" style="margin-top: 32px; text-align: left;">
            <label for="model">DEVICE MODEL (e.g. Samsung S21)</label>
            <input id="model" type="text" name="model" placeholder="Enter device model" maxlength="120" required>
            <label for="imei">IMEI / SERIAL NUMBER</label>
            <input id="imei" type="text" name="imei" placeholder="Enter IMEI or serial number" maxlength="64" required>
            <label for="txid">BITCOIN TRANSACTION ID</label>
            <input id="txid" type="text" name="txid" placeholder="64-character transaction ID" pattern="[a-fA-F0-9]{64}" maxlength="64" required>
            <p id="order-status" class="status-msg" role="status"></p>
            <button type="submit" class="pay-btn" id="submit-btn" disabled>VERIFY PAYMENT &amp; SUBMIT ORDER</button>
        </form>
    </div>

    <script>
        const btcAddress = <?= json_encode(BTC_ADDRESS) ?>;
        let quote = null;
        let quoteTimer;

        async function copyAddress() {
            try {
                await navigator.clipboard.writeText(btcAddress);
                alert('Address copied to clipboard');
            } catch (err) {
                alert('Could not copy the address. Please copy the displayed address manually.');
            }
        }

        async function updateAmount() {
            const service = new URLSearchParams(window.location.search).get('service');
            const button = document.getElementById('submit-btn');
            const status = document.getElementById('quote-status');
            button.disabled = true;
            document.getElementById('payment-destination').hidden = true;
            clearTimeout(quoteTimer);
            quote = null;
            if (service !== 'bootloader' && service !== 'frp') {
                status.textContent = 'Invalid service. Choose Bootloader Unlock or FRP Removal from the store.';
                document.getElementById('display-amount').textContent = 'Unavailable';
                return;
            }
            let usdAmount;
            if (service === 'bootloader') {
                usdAmount = 45.00;
            } else if (service === 'frp') {
                usdAmount = 30.00;
            }
            try {
                const response = await fetch('api.php?action=quote&service=' + encodeURIComponent(service), { cache: 'no-store' });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Quote request failed.');
                if (!Number.isSafeInteger(data.satoshis) || data.satoshis <= 0 || data.satoshis > 2100000000000000 ||
                    typeof data.quoteId !== 'string' || !/^[a-f0-9]{64}$/.test(data.quoteId) ||
                    !Number.isSafeInteger(data.expiresAt) || data.expiresAt * 1000 <= Date.now()) {
                    throw new Error('A valid Bitcoin quote is unavailable.');
                }
                quote = data;
                document.getElementById('display-amount').textContent = (quote.satoshis / 100000000).toFixed(8) + ' BTC';
                document.getElementById('payment-destination').hidden = false;
                status.textContent = '$' + usdAmount + ' quote. Payment must confirm by ' +
                    new Date(quote.expiresAt * 1000).toLocaleTimeString() + '. After sending, enter the transaction ID below.';
                button.disabled = false;
                clearTimeout(quoteTimer);
                quoteTimer = setTimeout(() => {
                    document.getElementById('payment-destination').hidden = true;
                    status.textContent = 'Quote expired. Do not send payment. If already sent, submit its transaction ID for verification.';
                }, quote.expiresAt * 1000 - Date.now());
            } catch (err) {
                document.getElementById('display-amount').textContent = 'Unavailable';
                status.textContent = err.message;
            }
        }

        async function handleSubmit(event) {
            event.preventDefault();
            const form = document.getElementById('order-form');
            if (!quote || !form.reportValidity()) return;
            const button = document.getElementById('submit-btn');
            const status = document.getElementById('order-status');
            button.disabled = true;
            status.textContent = '';
            try {
                const response = await fetch('api.php?action=order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ quoteId: quote.quoteId, model: form.elements.model.value,
                        imei: form.elements.imei.value, txid: form.elements.txid.value })
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'verified' || typeof data.orderId !== 'string') {
                    throw new Error(data.error || 'Payment could not be verified. Please retry.');
                }
                clearTimeout(quoteTimer);
                document.getElementById('payment-destination').hidden = true;
                document.getElementById('quote-status').textContent = 'Payment verified.';
                status.textContent = 'Payment verified. Order ' + data.orderId + ' has been recorded.';
                button.textContent = 'PAYMENT VERIFIED';
                quote = null;
            } catch (err) {
                status.textContent = err.message;
                button.disabled = false;
            }
        }
        document.getElementById('order-form').addEventListener('submit', handleSubmit);
        updateAmount();
    </script>
</body>
</html>
