'use strict';
(() => {
  const money = new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'});
  const unavailable = 'Rate temporarily unavailable';
  const currencies = ['BTC', 'LTC', 'XMR'];
  let busy = false;
  async function updatePrices() {
    if (busy) return;
    busy = true;
    document.querySelectorAll('[data-crypto]').forEach(element => { element.textContent = unavailable; });
    try {
      const response = await fetch('prices.php', {
        headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(10000), cache: 'no-store'
      });
      if (!response.ok) throw new Error('Price service unavailable');
      const data = await response.json();
      document.querySelectorAll('[data-service]').forEach(card => {
        const service = data.services?.[card.dataset.service];
        if (!service) throw new Error('Invalid service prices');
        card.querySelector('[data-usd-price]').textContent = money.format(Number(service.usd));
        card.querySelector('[data-button-price]').textContent = money.format(Number(service.usd));
        currencies.forEach(currency => {
          const display = card.querySelector(`[data-crypto="${currency}"]`);
          const quote = service.estimates?.[currency];
          if (!quote || typeof quote.amount !== 'string' || !/^\d+\.\d+$/.test(quote.amount)) return;
          const decimals = currency === 'XMR' ? 12 : 8;
          const age = Date.now() - Date.parse(quote.as_of);
          if (quote.amount.split('.')[1].length !== decimals || !Number.isFinite(age) || age > 120000 || age < -10000) return;
          display.textContent = `≈ ${quote.amount} ${currency}`;
        });
      });
      document.querySelectorAll('[data-btc-total]').forEach(element => {
        const total = Number(element.dataset.btcTotal);
        const age = Date.now() - Date.parse(data.as_of);
        if (!Number.isFinite(total) || total <= 0 || !Number.isFinite(data.btc_usd)
            || data.btc_usd <= 0 || !Number.isFinite(age) || age > 120000 || age < -10000) {
          element.querySelector('[data-usd-equivalent]').textContent =
            'Live conversion unavailable. Use the saved quote shown below.';
          return;
        }
        element.querySelector('[data-usd-equivalent]').textContent =
          '≈ ' + money.format(total * data.btc_usd) + ' USD · updated ' +
          new Date(data.as_of).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
      });
    } catch {
      document.querySelectorAll('[data-crypto]').forEach(element => { element.textContent = unavailable; });
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
