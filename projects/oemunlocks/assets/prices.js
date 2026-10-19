'use strict';
(() => {
  const money = new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'});
  let busy = false;
  async function updatePrices() {
    if (busy) return;
    busy = true;
    try {
      const response = await fetch('prices.php', {
        headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(10000), cache: 'no-store'
      });
      if (!response.ok) throw new Error('Price service unavailable');
      const data = await response.json();
      if (!Number.isFinite(data.btc_usd) || data.btc_usd <= 0) throw new Error('Invalid rate');
      const age = Date.now() - Date.parse(data.as_of);
      if (!Number.isFinite(age) || age > 120000 || age < -10000) throw new Error('Stale rate');
      document.querySelectorAll('[data-service]').forEach(card => {
        const price = data.services[card.dataset.service];
        if (!price || !/^\d+\.\d{8}$/.test(price.btc)) throw new Error('Invalid service price');
        card.querySelector('[data-usd-price]').textContent = money.format(Number(price.usd));
        card.querySelector('[data-button-price]').textContent = money.format(Number(price.usd));
        card.querySelector('[data-btc-price]').textContent = '≈ ' + price.btc + ' BTC · live estimate';
      });
      document.querySelectorAll('[data-btc-total]').forEach(element => {
        const total = Number(element.dataset.btcTotal);
        if (!Number.isFinite(total) || total <= 0) return;
        element.querySelector('[data-usd-equivalent]').textContent =
          '≈ ' + money.format(total * data.btc_usd) + ' USD · updated ' +
          new Date(data.as_of).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
      });
    } catch {
      document.querySelectorAll('[data-btc-price]').forEach(element => {
        element.textContent = 'Live BTC estimate unavailable · quote at checkout';
      });
      document.querySelectorAll('[data-usd-equivalent]').forEach(element => {
        element.textContent = 'Live conversion unavailable. Use the saved quote shown below.';
      });
    } finally {
      busy = false;
    }
  }
  function quoteTime() {
    document.querySelectorAll('[data-quote-expires]').forEach(element => {
      if (Date.now() >= Number(element.dataset.quoteExpires) * 1000) {
        element.textContent = 'Quote time elapsed. If you already paid, submit your reference below. Otherwise refresh this page for a new quote before sending payment.';
      }
    });
  }
  updatePrices();
  quoteTime();
  setInterval(updatePrices, 60000);
  setInterval(quoteTime, 1000);
})();
