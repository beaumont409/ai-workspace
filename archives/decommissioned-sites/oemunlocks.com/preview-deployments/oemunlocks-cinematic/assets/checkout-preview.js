'use strict';

(() => {
  const query = new URLSearchParams(window.location.search);
  const service = query.get('service') === 'frp' ? 'frp' : 'bootloader';
  const products = {
    bootloader: { name: 'Bootloader Unlock', price: '$45.00 USD' },
    frp: { name: 'FRP Removal', price: '$30.00 USD' }
  };
  const currencyNames = { BTC: 'Bitcoin', LTC: 'Litecoin', XMR: 'Monero' };
  const product = products[service];
  const title = document.getElementById('preview-title');
  const price = document.getElementById('preview-price');
  const checkout = document.getElementById('live-checkout');
  const detailLink = document.querySelector('.details-link');
  const disclaimer = document.getElementById('quote-disclaimer');
  const options = [...document.querySelectorAll('[data-currency]')];

  title.textContent = product.name;
  price.textContent = product.price;

  function setCurrency(currency) {
    options.forEach(option => option.setAttribute('aria-pressed', String(option.dataset.currency === currency)));
    checkout.href = 'https://oemunlocks.com/payment.php?service=' + encodeURIComponent(service) + '&currency=' + encodeURIComponent(currency);
    detailLink.href = 'https://oemunlocks.com/payment.php?service=' + encodeURIComponent(service) + '&currency=' + encodeURIComponent(currency);
    disclaimer.textContent = currencyNames[currency] + ' quote, receiving address, and scannable QR code appear in the existing checkout.';
  }

  options.forEach(option => {
    option.addEventListener('click', () => setCurrency(option.dataset.currency));
  });

  setCurrency('BTC');
})();
