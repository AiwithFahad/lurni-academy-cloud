/* Lurni Academy Cloud — UI behaviour */
(function () {
  'use strict';

  // Collapsible sidebar (mobile/tablet)
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('appSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('show');
  }
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('show');
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  // Pricing monthly/yearly toggle (site + register pages)
  var cycle = document.getElementById('cycleToggle');
  if (cycle) {
    cycle.addEventListener('change', function () {
      var yearly = cycle.checked;
      document.querySelectorAll('.price-amount').forEach(function (el) {
        el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly;
      });
      document.querySelectorAll('.price-cycle').forEach(function (el) {
        el.textContent = yearly ? '/year' : '/mo';
      });
      var cycleInput = document.getElementById('cycleInput');
      if (cycleInput) cycleInput.value = yearly ? 'yearly' : 'monthly';
    });
  }

  // Plan picker on the register page: cards act like radios
  document.querySelectorAll('[data-plan-card]').forEach(function (card) {
    card.addEventListener('click', function () {
      var radio = card.querySelector('input[type="radio"]');
      if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
      document.querySelectorAll('[data-plan-card]').forEach(function (c) { c.classList.remove('border-primary', 'shadow'); });
      card.classList.add('border-primary', 'shadow');
    });
  });

  // Animated counters on the public homepage
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target, target = parseFloat(el.dataset.count), start = null;
        function step(ts) {
          if (!start) start = ts;
          var p = Math.min((ts - start) / 1200, 1);
          el.textContent = Math.round(target * p).toLocaleString();
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
        io.unobserve(el);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { io.observe(el); });
  }

  // Topbar course search suggestions (app pages)
  var searchInput = document.getElementById('topbarSearch');
  if (searchInput) {
    var box = document.createElement('div');
    box.className = 'list-group position-absolute shadow';
    box.style.cssText = 'top:110%;left:0;right:0;z-index:60;display:none;border-radius:12px;overflow:hidden;';
    searchInput.parentElement.appendChild(box);
    var timer = null;
    searchInput.addEventListener('input', function () {
      clearTimeout(timer);
      var q = searchInput.value.trim();
      if (q.length < 2) { box.style.display = 'none'; return; }
      timer = setTimeout(function () {
        fetch('/api/search.php?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            box.innerHTML = '';
            (data.results || []).forEach(function (r) {
              var a = document.createElement('a');
              a.className = 'list-group-item list-group-item-action py-2';
              a.href = r.url;
              a.innerHTML = '<strong></strong> <span class="badge bg-primary-subtle text-primary float-end">★ ' + r.rating.toFixed(1) + '</span>';
              a.querySelector('strong').textContent = r.title;
              box.appendChild(a);
            });
            box.style.display = box.children.length ? 'block' : 'none';
          })
          .catch(function () { box.style.display = 'none'; });
      }, 250);
    });
    document.addEventListener('click', function (e) {
      if (!searchInput.parentElement.contains(e.target)) box.style.display = 'none';
    });
  }
})();
