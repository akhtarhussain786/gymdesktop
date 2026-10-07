/**
 * FITISIFY OS — MASTER INTERACTIVE ANIMATION & MOTION CONTROLLER
 * High-Precision Physics, Viewport Intersection Observer & Micro-Interactions
 */

let currentBillingCycle = 'monthly';

// ==========================================================================
// 1. Viewport Intersection Observer & Scroll Reveals
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
  const revealElements = document.querySelectorAll('.reveal-on-scroll, .reveal-fade-left, .reveal-fade-right, .reveal-scale');
  
  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        }
      });
    }, {
      root: null,
      threshold: 0.12,
      rootMargin: '0px 0px -40px 0px'
    });

    revealElements.forEach(el => revealObserver.observe(el));
  } else {
    // Fallback for older browsers
    revealElements.forEach(el => el.classList.add('is-revealed'));
  }

  // Animate mini bars when entering viewport
  const dashBars = document.querySelector('.dash-mini-bars');
  if (dashBars && 'IntersectionObserver' in window) {
    const barsObserver = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          document.querySelectorAll('.mini-bar-col').forEach(bar => {
            bar.classList.add('animated');
          });
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });
    barsObserver.observe(dashBars);
  }

  // Animate Number Counters on Viewport Entry
  initCounterAnimations();

  // Mouse Parallax on Hero Dashboard (Desktop Only)
  initHeroMouseParallax();

  // Scrollspy & Navbar Backdrop
  initScrollspyNavbar();

  // Progress Bars
  initProgressBars();
});

// ==========================================================================
// 2. Animated Number Counters
// ==========================================================================
function initCounterAnimations() {
  const counterElements = document.querySelectorAll('[data-counter]');
  if (!counterElements.length) return;

  if ('IntersectionObserver' in window) {
    const counterObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          animateSingleCounter(el);
          observer.unobserve(el);
        }
      });
    }, { threshold: 0.2 });

    counterElements.forEach(el => counterObserver.observe(el));
  } else {
    counterElements.forEach(el => {
      el.innerText = el.getAttribute('data-counter');
    });
  }
}

function animateSingleCounter(el) {
  const target = parseFloat(el.getAttribute('data-counter')) || 0;
  const prefix = el.getAttribute('data-counter-prefix') || '';
  const suffix = el.getAttribute('data-counter-suffix') || '';
  const isDecimal = el.getAttribute('data-counter-decimal') === 'true';
  const duration = 1400; // ms
  const startTime = performance.now();

  function updateCount(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    
    // Ease-out athletic curve
    const easeProgress = 1 - Math.pow(1 - progress, 3);
    const currentVal = target * easeProgress;

    if (isDecimal) {
      el.innerText = prefix + currentVal.toFixed(1) + suffix;
    } else if (target >= 1000) {
      el.innerText = prefix + Math.round(currentVal).toLocaleString() + suffix;
    } else {
      el.innerText = prefix + Math.round(currentVal) + suffix;
    }

    if (progress < 1) {
      requestAnimationFrame(updateCount);
    } else {
      if (isDecimal) {
        el.innerText = prefix + target.toFixed(1) + suffix;
      } else {
        el.innerText = prefix + (target >= 1000 ? target.toLocaleString() : target) + suffix;
      }
    }
  }

  requestAnimationFrame(updateCount);
}

// ==========================================================================
// 3. Hero Dashboard Mouse Parallax Physics
// ==========================================================================
function initHeroMouseParallax() {
  if (window.innerWidth < 992 || ('ontouchstart' in window)) return;

  const visualWrapper = document.querySelector('.hero-visual-wrapper');
  const frame = document.querySelector('.dashboard-device-frame');
  const widget1 = document.querySelector('.floating-widget-1');
  const widget2 = document.querySelector('.floating-widget-2');
  const widget3 = document.querySelector('.floating-widget-3');

  if (!visualWrapper || !frame) return;

  visualWrapper.addEventListener('mousemove', (e) => {
    const rect = visualWrapper.getBoundingClientRect();
    const x = e.clientX - rect.left - (rect.width / 2);
    const y = e.clientY - rect.top - (rect.height / 2);

    const normX = x / (rect.width / 2);
    const normY = y / (rect.height / 2);

    // Subtle 3D tilt
    frame.style.transform = `rotateY(${normX * 6 - 3}deg) rotateX(${-normY * 6 + 2}deg) translateY(-4px)`;

    if (widget1) widget1.style.transform = `translate(${normX * -8}px, ${normY * -8}px)`;
    if (widget2) widget2.style.transform = `translate(${normX * 10}px, ${normY * 10}px)`;
    if (widget3) widget3.style.transform = `translate(${normX * -6}px, ${normY * 6}px)`;
  });

  visualWrapper.addEventListener('mouseleave', () => {
    frame.style.transform = '';
    if (widget1) widget1.style.transform = '';
    if (widget2) widget2.style.transform = '';
    if (widget3) widget3.style.transform = '';
  });
}

// ==========================================================================
// 4. Scrollspy & Navbar Scroll Effects
// ==========================================================================
function initScrollspyNavbar() {
  const navbar = document.querySelector('.navbar-wrapper');
  const navLinks = document.querySelectorAll('.navbar-menu a');
  const sections = document.querySelectorAll('section[id]');

  window.addEventListener('scroll', () => {
    // 1. Add background blur when scrolled
    if (window.scrollY > 40) {
      if (navbar) navbar.classList.add('navbar-scrolled');
    } else {
      if (navbar) navbar.classList.remove('navbar-scrolled');
    }

    // 2. Dynamic Scroll Progress Bar Fill
    const winScroll = document.documentElement.scrollTop || document.body.scrollTop;
    const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    const scrollPercent = (docHeight > 0) ? (winScroll / docHeight) * 100 : 0;
    const progressFill = document.getElementById('navbar-progress-fill');
    if (progressFill) {
      progressFill.style.width = scrollPercent + '%';
    }

    // 3. Active Section Highlighting
    let currentId = '';
    const scrollPos = window.scrollY + 200;

    sections.forEach(sec => {
      const top = sec.offsetTop;
      const height = sec.offsetHeight;
      if (scrollPos >= top && scrollPos < top + height) {
        currentId = sec.getAttribute('id');
      }
    });

    if (currentId) {
      navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + currentId) {
          link.classList.add('active');
        }
      });
    }
  }, { passive: true });
}

// ==========================================================================
// 5. Progress Bars Fill Animation
// ==========================================================================
function initProgressBars() {
  const bars = document.querySelectorAll('.progress-fill-lime, .progress-fill-cyan');
  if (!bars.length) return;

  if ('IntersectionObserver' in window) {
    const progObserver = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const targetW = entry.target.getAttribute('data-width') || '80%';
          entry.target.style.width = targetW;
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });

    bars.forEach(b => progObserver.observe(b));
  } else {
    bars.forEach(b => {
      b.style.width = b.getAttribute('data-width') || '80%';
    });
  }
}

// ==========================================================================
// 6. Billing Cycle Switcher & Geo Currency
// ==========================================================================
function setBillingCycle(cycle) {
  currentBillingCycle = cycle;
  const btnMonthly = document.getElementById('btn-monthly');
  const btnYearly = document.getElementById('btn-yearly');

  if (btnMonthly) btnMonthly.classList.toggle('active', cycle === 'monthly');
  if (btnYearly) btnYearly.classList.toggle('active', cycle === 'yearly');

  const currCode = (typeof GeoCurrency !== 'undefined' && GeoCurrency.currentCurrency) ? GeoCurrency.currentCurrency : 'INR';
  const curr = (typeof GeoCurrency !== 'undefined' && GeoCurrency.rates[currCode]) ? GeoCurrency.rates[currCode] : { rate: 1, symbol: '₹' };

  updateAllPricesWithCurrency(curr);
}

function updateAllPricesWithCurrency(curr) {
  const priceEls = document.querySelectorAll('.plan-price-val, .tier-price-val');
  const cycleLabels = document.querySelectorAll('.billing-cycle-label, .tier-cycle-label');

  priceEls.forEach(el => {
    const mPriceINR = parseFloat(el.getAttribute('data-price-monthly')) || 0;
    const yPriceINR = parseFloat(el.getAttribute('data-price-yearly')) || (mPriceINR * 10);

    let priceInCurrency = (currentBillingCycle === 'yearly') ? (yPriceINR / 12) : mPriceINR;
    let converted = priceInCurrency * (curr.rate || 1);

    if (converted === 0) {
      el.innerText = '0';
    } else {
      el.innerText = Math.round(converted).toLocaleString();
    }
  });

  cycleLabels.forEach(label => {
    label.innerText = (currentBillingCycle === 'yearly') ? '/mo (billed yearly)' : '/month';
  });
}

// ==========================================================================
// 7. Interactive Solution Filter Tabs
// ==========================================================================
function filterSolutions(category, element) {
  document.querySelectorAll('.tab-pill-btn').forEach(btn => btn.classList.remove('active'));
  if (element) element.classList.add('active');

  const cards = document.querySelectorAll('.solution-card-item');
  cards.forEach(card => {
    const cardCat = card.getAttribute('data-category');
    if (category === 'all' || cardCat === category) {
      card.style.display = 'grid';
      card.style.animation = 'loadFadeUp 0.4s cubic-bezier(0.22, 1, 0.36, 1) both';
    } else {
      card.style.display = 'none';
    }
  });
}

// ==========================================================================
// 8. Multi-Branch Switcher Simulator
// ==========================================================================
const branchData = {
  mumbai: {
    name: 'Mumbai Flagship (Bandra)',
    members: '1,842',
    revenue: '₹4,82,500',
    checkins: '620',
    trainers: '18',
    status: 'OPTIMAL CAPACITY (84%)'
  },
  delhi: {
    name: 'Delhi South (Connaught)',
    members: '1,290',
    revenue: '₹3,45,000',
    checkins: '480',
    trainers: '14',
    status: 'ACTIVE LOGINS (72%)'
  },
  bangalore: {
    name: 'Bangalore Tech Park (Indiranagar)',
    members: '2,150',
    revenue: '₹5,90,000',
    checkins: '790',
    trainers: '22',
    status: 'PEAK TRAFFIC (92%)'
  },
  patna: {
    name: 'Patna Central (Boring Rd)',
    members: '890',
    revenue: '₹2,10,000',
    checkins: '310',
    trainers: '9',
    status: 'STABLE GROWTH (65%)'
  }
};

function switchBranch(branchKey, element) {
  document.querySelectorAll('.branch-btn').forEach(b => b.classList.remove('active'));
  if (element) element.classList.add('active');

  const data = branchData[branchKey];
  if (!data) return;

  const nameEl = document.getElementById('branch-display-name');
  const memEl = document.getElementById('branch-display-members');
  const revEl = document.getElementById('branch-display-revenue');
  const checkEl = document.getElementById('branch-display-checkins');
  const trainEl = document.getElementById('branch-display-trainers');
  const statusEl = document.getElementById('branch-display-status');

  if (nameEl) nameEl.innerText = data.name;
  if (memEl) memEl.innerText = data.members;
  if (revEl) revEl.innerText = data.revenue;
  if (checkEl) checkEl.innerText = data.checkins;
  if (trainEl) trainEl.innerText = data.trainers;
  if (statusEl) statusEl.innerText = data.status;
}

// ==========================================================================
// 9. Scanner Biometric Simulator
// ==========================================================================
function triggerScanDemo() {
  const btn = document.getElementById('btn-scan-trigger');
  const resultCard = document.getElementById('scanner-result-card');
  const countEl = document.getElementById('live-inside-count');

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Optical Laser Scanning...';
  }

  setTimeout(() => {
    if (resultCard) {
      resultCard.style.display = 'block';
      resultCard.style.animation = 'loadPopIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both';
    }
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Check-in Verified (0.12s)';
    }
    if (countEl) {
      countEl.innerText = '127';
    }
  }, 850);
}

// ==========================================================================
// 10. Mobile Menu Drawer Toggle
// ==========================================================================
function toggleMobileNav() {
  const drawer = document.getElementById('mobile-nav-drawer');
  if (drawer) {
    drawer.classList.toggle('open');
  }
}

// ==========================================================================
// 11. FAQ Accordion Controls
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.faq-header').forEach(header => {
    header.addEventListener('click', () => {
      const item = header.parentElement;
      const isActive = item.classList.contains('active');

      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
      if (!isActive) {
        item.classList.add('active');
      }
    });
  });
});
