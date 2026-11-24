'use strict';

(() => {
  const money = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
  const unavailable = 'Rate temporarily unavailable';
  const currencies = ['BTC', 'LTC', 'XMR'];
  let busy = false;

  async function updatePrices() {
    if (busy) return;
    busy = true;
    document.querySelectorAll('[data-crypto]').forEach(element => { element.textContent = unavailable; });
    try {
      const response = await fetch('../prices.php', {
        headers: { Accept: 'application/json' },
        signal: AbortSignal.timeout(10000),
        cache: 'no-store'
      });
      if (!response.ok) throw new Error('Price service unavailable');
      const data = await response.json();
      document.querySelectorAll('[data-service]').forEach(card => {
        const service = data.services?.[card.dataset.service];
        if (!service) throw new Error('Invalid service prices');
        card.querySelector('[data-usd-price]').textContent = money.format(Number(service.usd));
        card.querySelector('[data-button-price]').textContent = money.format(Number(service.usd));
        currencies.forEach(currency => {
          const display = card.querySelector('[data-crypto="' + currency + '"]');
          const quote = service.estimates?.[currency];
          if (!quote || typeof quote.amount !== 'string' || !/^\d+\.\d+$/.test(quote.amount)) return;
          const decimals = currency === 'XMR' ? 12 : 8;
          const age = Date.now() - Date.parse(quote.as_of);
          if (quote.amount.split('.')[1].length !== decimals || !Number.isFinite(age) || age > 120000 || age < -10000) return;
          display.textContent = '≈ ' + quote.amount + ' ' + currency;
        });
      });
    } catch {
      document.querySelectorAll('[data-crypto]').forEach(element => { element.textContent = unavailable; });
    } finally {
      busy = false;
    }
  }

  updatePrices();
  window.setInterval(updatePrices, 60000);
})();
