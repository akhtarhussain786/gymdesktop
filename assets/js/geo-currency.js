/**
 * Centralized Geo-Currency & Live Price Converter
 * Auto-detects local currency via IP and updates price cards seamlessly
 */
const GeoCurrency = {
  rates: {
    'INR': { symbol: '₹', rate: 1, name: 'Indian Rupee' },
    'USD': { symbol: '$', rate: 0.012, name: 'US Dollar' },
    'EUR': { symbol: '€', rate: 0.011, name: 'Euro' },
    'GBP': { symbol: '£', rate: 0.0095, name: 'British Pound' },
    'AED': { symbol: 'AED ', rate: 0.044, name: 'UAE Dirham' },
    'CAD': { symbol: 'CA$', rate: 0.016, name: 'Canadian Dollar' },
    'AUD': { symbol: 'AU$', rate: 0.018, name: 'Australian Dollar' },
    'NPR': { symbol: 'Rs. ', rate: 1.60, name: 'Nepalese Rupee' }
  },

  currentCurrency: 'INR',

  init: function() {
    const select = document.getElementById('global-currency-select');
    if (select) {
      select.addEventListener('change', (e) => {
        this.setCurrency(e.target.value);
      });
    }

    this.detectAndApply();
  },

  detectAndApply: function(callback) {
    // Check localStorage first
    const saved = localStorage.getItem('fitisify_user_currency');
    if (saved && this.rates[saved]) {
      this.setCurrency(saved);
      if (callback) callback(saved, this.rates[saved]);
      return;
    }

    // Attempt IP geolocation (graceful fallback to INR)
    fetch('https://ipapi.co/json/', { timeout: 3000 })
      .then(res => res.json())
      .then(data => {
        const countryCode = data.country_code;
        let detected = 'INR';
        if (countryCode === 'US') detected = 'USD';
        else if (countryCode === 'GB') detected = 'GBP';
        else if (countryCode === 'AE') detected = 'AED';
        else if (countryCode === 'CA') detected = 'CAD';
        else if (countryCode === 'AU') detected = 'AUD';
        else if (countryCode === 'NP') detected = 'NPR';
        else if (['DE','FR','IT','ES','NL','BE'].includes(countryCode)) detected = 'EUR';

        this.setCurrency(detected);
        if (callback) callback(detected, this.rates[detected]);
      })
      .catch(() => {
        this.setCurrency('INR');
        if (callback) callback('INR', this.rates['INR']);
      });
  },

  setCurrency: function(code) {
    if (!this.rates[code]) code = 'INR';
    this.currentCurrency = code;
    localStorage.setItem('fitisify_user_currency', code);

    const curr = this.rates[code];
    const select = document.getElementById('global-currency-select');
    if (select && select.value !== code) select.value = code;

    // Update all symbol elements
    document.querySelectorAll('.currency-symbol, .tier-currency-symbol').forEach(el => {
      el.innerText = curr.symbol;
    });

    // Update all plan prices based on active cycle
    if (typeof updateAllPricesWithCurrency === 'function') {
      updateAllPricesWithCurrency(curr);
    }
  }
};

document.addEventListener('DOMContentLoaded', () => {
  GeoCurrency.init();
});
