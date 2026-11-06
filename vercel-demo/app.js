/* Lurni Academy Cloud — Shared JS */
(function () {
  'use strict';

  /* Sidebar toggle (mobile) */
  var toggles = document.querySelectorAll('[data-sidebar-toggle]');
  var backdrop = document.querySelector('.sidebar-backdrop');
  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
  });
  if (backdrop) backdrop.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
  document.querySelectorAll('.side-link').forEach(function (link) {
    link.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
  });

  /* Animated counters */
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target, target = parseFloat(el.getAttribute('data-count')), suffix = el.getAttribute('data-suffix') || '';
        var start = null;
        function step(ts) {
          if (!start) start = ts;
          var p = Math.min((ts - start) / 1400, 1);
          el.textContent = Math.round(target * p).toLocaleString() + (p === 1 ? suffix : '');
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
        io.unobserve(el);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (c) { io.observe(c); });
  } else {
    counters.forEach(function (c) { c.textContent = Number(c.getAttribute('data-count')).toLocaleString() + (c.getAttribute('data-suffix') || ''); });
  }

  /* Pricing toggle */
  var toggleBtns = document.querySelectorAll('[data-billing]');
  toggleBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      toggleBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var mode = btn.getAttribute('data-billing'); // monthly | yearly
      document.querySelectorAll('[data-monthly]').forEach(function (el) {
        el.textContent = mode === 'yearly' ? el.getAttribute('data-yearly') : el.getAttribute('data-monthly');
      });
      document.querySelectorAll('.billing-note').forEach(function (el) {
        el.textContent = mode === 'yearly' ? 'per month, billed yearly — save 20%' : 'per month, billed monthly';
      });
    });
  });

  /* Course search (landing) — filters featured course cards by title */
  var searchInput = document.getElementById('courseSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('[data-course-title]').forEach(function (card) {
        card.closest('.col, [class*="col-"]').style.display = card.getAttribute('data-course-title').toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  /* Newsletter / generic demo forms */
  document.querySelectorAll('form[data-demo-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      if (btn) { var old = btn.textContent; btn.textContent = '✓ Done — check your inbox'; setTimeout(function () { btn.textContent = old; form.reset(); }, 2600); }
    });
  });

  /* Charts — guarded per page: only init when canvas exists */
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "'Inter',sans-serif";
  Chart.defaults.color = '#64748B';
  var grad = function (ctx, c1, c2) { var g = ctx.createLinearGradient(0, 0, 0, 260); g.addColorStop(0, c1); g.addColorStop(1, c2); return g; };
  function make(id, cfg) { var el = document.getElementById(id); if (el) new Chart(el, cfg); }

  make('enrollmentChart', { type: 'bar', data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'], datasets: [{ label: 'Enrollments', data: [182,240,215,310,286,352,398,424], backgroundColor: '#2563EB', hoverBackgroundColor: '#7C3AED', borderRadius: 8, barPercentage: .62 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('revenueChart', { type: 'line', data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'], datasets: [{ label: 'Revenue ($)', data: [6400,7200,6900,8400,9100,10200,11400,12480], borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,.12)', fill: true, tension: .45, pointBackgroundColor: '#7C3AED', pointRadius: 4 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('performanceChart', { type: 'bar', data: { labels: ['Python Bootcamp','UI/UX Design','Data Science','Web Dev','Marketing'], datasets: [{ label: 'Avg. score %', data: [88,84,91,79,86], backgroundColor: ['#2563EB','#7C3AED','#0EA5E9','#F59E0B','#EC4899'], borderRadius: 8, barPercentage: .6 }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100, grid: { color: '#EEF2FF' } }, y: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('earningsChart', { type: 'line', data: { labels: ['Sep','Oct','Nov','Dec','Jan','Feb'], datasets: [{ label: 'Earnings ($)', data: [1240,1480,1390,1720,1980,2240], borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .45, pointBackgroundColor: '#2563EB' }] }, options: { plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('activityChart', { type: 'bar', data: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets: [{ label: 'Study minutes', data: [45,70,55,90,65,110,80], backgroundColor: function (c) { return grad(c.chart.ctx, '#2563EB', '#7C3AED'); }, borderRadius: 8, barPercentage: .58 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('progressChart', { type: 'doughnut', data: { labels: ['Completed','In progress','Not started'], datasets: [{ data: [62,28,10], backgroundColor: ['#2563EB','#7C3AED','#E2E8F0'], borderWidth: 0 }] }, options: { cutout: '68%', plugins: { legend: { position: 'bottom' } }, responsive: true, maintainAspectRatio: false } });
})();
