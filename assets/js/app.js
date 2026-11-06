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

/* =====================================================================
   Lurni Academy Cloud — interactive layer (upgrade)
   Toasts, Fetch/AJAX helpers, live search + filters, modal CRUD,
   quiz countdown timer, lesson-player controls (complete & advance,
   bookmarks, notes, discussion), Chart.js analytics with date filters.
   ===================================================================== */
(function () {
  'use strict';
  var Lurni = (window.Lurni = window.Lurni || {});
  var BRAND = ['#2563EB', '#7C3AED', '#16a34a', '#f59e0b', '#0ea5e9', '#ef4444', '#64748b', '#9333ea'];

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  /* ---------- Toast notifications ---------- */
  function toastWrap() {
    var w = document.getElementById('lurniToasts');
    if (!w) {
      w = document.createElement('div');
      w.id = 'lurniToasts';
      w.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      w.style.zIndex = '1080';
      document.body.appendChild(w);
    }
    return w;
  }
  Lurni.toast = function (message, type) {
    type = type || 'success';
    var icons = { success: 'bi-check-circle-fill', error: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var colors = { success: 'text-bg-success', error: 'text-bg-danger', info: 'text-bg-primary' };
    var el = document.createElement('div');
    el.className = 'toast align-items-center border-0 ' + (colors[type] || colors.info);
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi ' + (icons[type] || icons.info) + ' me-2"></i></div></div>';
    el.querySelector('.toast-body').appendChild(document.createTextNode(message));
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'btn-close btn-close-white me-2 m-auto';
    btn.setAttribute('data-bs-dismiss', 'toast'); btn.setAttribute('aria-label', 'Close');
    el.firstChild.appendChild(btn);
    toastWrap().appendChild(el);
    if (window.bootstrap && bootstrap.Toast) {
      var t = new bootstrap.Toast(el, { delay: 4200 });
      t.show();
      el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    } else {
      setTimeout(function () { el.remove(); }, 4200);
    }
  };

  /* ---------- Fetch helpers (CSRF-aware) ---------- */
  Lurni.postForm = function (url, formData) {
    if (!(formData instanceof FormData)) {
      var fd = new FormData();
      Object.keys(formData || {}).forEach(function (k) { fd.append(k, formData[k]); });
      formData = fd;
    }
    if (!formData.has('_token') && csrfToken()) formData.append('_token', csrfToken());
    return fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); });
  };
  Lurni.getJson = function (url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  };

  /* ---------- AJAX forms (modal CRUD without page reload) ---------- */
  document.querySelectorAll('form.js-ajax-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      if (btn) btn.disabled = true;
      Lurni.postForm(form.getAttribute('action'), new FormData(form)).then(function (res) {
        if (btn) btn.disabled = false;
        if (res && res.ok !== false) {
          Lurni.toast(res.message || 'Saved successfully.');
          var modalEl = form.closest('.modal');
          if (modalEl && window.bootstrap) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
          }
          if (form.hasAttribute('data-ajax-reload')) setTimeout(function () { location.reload(); }, 600);
          form.dispatchEvent(new CustomEvent('lurni:saved', { bubbles: true, detail: res }));
        } else {
          Lurni.toast((res && res.message) || 'Something went wrong.', 'error');
        }
      }).catch(function () {
        if (btn) btn.disabled = false;
        Lurni.toast('Network error — please try again.', 'error');
      });
    });
  });

  /* ---------- Live search + advanced filters for tables ---------- */
  document.querySelectorAll('[data-live-search]').forEach(function (input) {
    var table = document.querySelector(input.getAttribute('data-live-search'));
    if (!table) return;
    var filters = document.querySelectorAll('[data-live-filter-for="' + input.getAttribute('data-live-search') + '"]');
    function apply() {
      var q = input.value.trim().toLowerCase();
      var active = [];
      filters.forEach(function (f) { if (f.value) active.push({ col: parseInt(f.getAttribute('data-col'), 10), val: f.value.toLowerCase() }); });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        if (row.querySelector('td[colspan]')) return;
        var text = row.textContent.toLowerCase();
        var show = !q || text.indexOf(q) !== -1;
        active.forEach(function (f) {
          if (!show) return;
          var cell = row.children[f.col];
          if (!cell || cell.textContent.toLowerCase().indexOf(f.val) === -1) show = false;
        });
        row.style.display = show ? '' : 'none';
      });
    }
    input.addEventListener('input', apply);
    filters.forEach(function (f) { f.addEventListener('change', apply); });
  });

  /* ---------- Quiz countdown timer (JS) with auto-submit ---------- */
  document.querySelectorAll('.js-quiz-timer').forEach(function (badge) {
    var minutes = parseInt(badge.getAttribute('data-minutes'), 10) || 0;
    var form = document.getElementById(badge.getAttribute('data-form'));
    if (!minutes || !form) return;
    var left = minutes * 60;
    function render() {
      var m = Math.floor(left / 60), s = left % 60;
      badge.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      if (left <= 60) badge.classList.replace('bg-dark', 'bg-danger');
    }
    render();
    var iv = setInterval(function () {
      left--;
      render();
      if (left === 60) Lurni.toast('One minute left — your answers will be submitted automatically.', 'info');
      if (left <= 0) {
        clearInterval(iv);
        Lurni.toast('Time is up — submitting your answers.', 'error');
        setTimeout(function () { form.submit(); }, 400);
      }
    }, 1000);
  });

  /* ---------- Lesson player: complete & advance ---------- */
  document.querySelectorAll('.js-complete-next').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var lessonId = btn.getAttribute('data-lesson-id');
      var nextUrl = btn.getAttribute('data-next-url');
      btn.disabled = true;
      Lurni.postForm('/api/progress.php', { lesson_id: lessonId, action: 'complete' }).then(function (res) {
        if (res && res.ok) {
          // Update the progress bar + curriculum state in place.
          var bar = document.querySelector('.js-course-progress-bar');
          var pct = document.querySelector('.js-course-progress-pct');
          var newPct = typeof res.progress_percent !== 'undefined' ? res.progress_percent : res.percent;
          if (bar && typeof newPct !== 'undefined') {
            bar.style.width = newPct + '%';
            bar.setAttribute('aria-valuenow', newPct);
          }
          if (pct && typeof newPct !== 'undefined') pct.textContent = Math.round(newPct) + '%';
          var item = document.querySelector('[data-curriculum-lesson="' + lessonId + '"]');
          if (item) {
            item.classList.add('lesson-done');
            var icon = item.querySelector('.lesson-state-icon');
            if (icon) icon.className = 'lesson-state-icon bi bi-check-circle-fill text-success';
          }
          Lurni.toast(res.message || 'Lesson completed.');
          if (nextUrl) setTimeout(function () { window.location.href = nextUrl; }, 700);
          else btn.innerHTML = '<i class="bi bi-check2-circle"></i> Completed';
        } else {
          btn.disabled = false;
          Lurni.toast((res && res.message) || 'Could not mark the lesson complete.', 'error');
        }
      }).catch(function () { btn.disabled = false; Lurni.toast('Network error — please try again.', 'error'); });
    });
  });

  /* ---------- Lesson player: bookmark toggle ---------- */
  document.querySelectorAll('.js-bookmark-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var lessonId = btn.getAttribute('data-lesson-id');
      Lurni.postForm('/api/bookmarks.php', { lesson_id: lessonId }).then(function (res) {
        if (res && res.ok) {
          btn.classList.toggle('active', !!res.bookmarked);
          var icon = btn.querySelector('i');
          if (icon) icon.className = 'bi ' + (res.bookmarked ? 'bi-bookmark-fill' : 'bi-bookmark');
          Lurni.toast(res.message || (res.bookmarked ? 'Bookmarked.' : 'Bookmark removed.'), res.bookmarked ? 'success' : 'info');
        } else {
          Lurni.toast((res && res.message) || 'Could not update the bookmark.', 'error');
        }
      }).catch(function () { Lurni.toast('Network error — please try again.', 'error'); });
    });
  });

  /* ---------- Lesson player: notes (load + save) ---------- */
  document.querySelectorAll('.js-notes-form').forEach(function (form) {
    var lessonId = form.getAttribute('data-lesson-id');
    var area = form.querySelector('textarea');
    Lurni.getJson('/api/notes.php?lesson_id=' + encodeURIComponent(lessonId)).then(function (res) {
      if (res && res.ok && area && !area.value) area.value = res.note || '';
    }).catch(function () {});
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      Lurni.postForm('/api/notes.php', new FormData(form)).then(function (res) {
        Lurni.toast((res && res.message) || 'Note saved.', res && res.ok === false ? 'error' : 'success');
      }).catch(function () { Lurni.toast('Network error — please try again.', 'error'); });
    });
  });

  /* ---------- Discussion: post without reload ---------- */
  document.querySelectorAll('.js-discussion-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var list = document.querySelector(form.getAttribute('data-target') || '');
      Lurni.postForm('/api/discussion.php', new FormData(form)).then(function (res) {
        if (res && res.ok) {
          var body = form.querySelector('[name="body"]');
          if (list && body) {
            var empty = list.querySelector('.js-discussion-empty');
            if (empty) empty.remove();
            var li = document.createElement('div');
            li.className = 'discussion-post border rounded p-3 mb-2';
            var head = document.createElement('div');
            head.className = 'small text-muted mb-1';
            head.textContent = 'You · just now';
            var text = document.createElement('div');
            text.textContent = body.value;
            li.appendChild(head); li.appendChild(text);
            list.appendChild(li);
          }
          if (body) body.value = '';
          Lurni.toast(res.message || 'Posted.');
        } else {
          Lurni.toast((res && res.message) || 'Could not post your message.', 'error');
        }
      }).catch(function () { Lurni.toast('Network error — please try again.', 'error'); });
    });
  });

  /* ---------- Chart.js analytics with real date filtering ---------- */
  var chartInstances = {};
  function buildChart(canvas, cfg) {
    if (!window.Chart) return;
    var key = canvas.id || Math.random().toString(36).slice(2);
    if (chartInstances[key]) chartInstances[key].destroy();
    chartInstances[key] = new Chart(canvas.getContext('2d'), cfg);
  }
  function loadAnalytics(scope) {
    var from = document.querySelector('[data-analytics-from]');
    var to = document.querySelector('[data-analytics-to]');
    var qs = [];
    if (from && from.value) qs.push('from=' + encodeURIComponent(from.value));
    if (to && to.value) qs.push('to=' + encodeURIComponent(to.value));
    Lurni.getJson('/api/analytics.php' + (qs.length ? '?' + qs.join('&') : '')).then(function (res) {
      if (!res || !res.ok) { Lurni.toast('Analytics could not be loaded.', 'error'); return; }
      // Stat chips: [data-stat="revenue_range"] etc.
      document.querySelectorAll('[data-stat]').forEach(function (el) {
        var k = el.getAttribute('data-stat');
        if (res.stats && typeof res.stats[k] !== 'undefined') {
          var v = res.stats[k];
          el.textContent = el.hasAttribute('data-stat-money') ? ('$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 })) : Number(v).toLocaleString();
        }
      });
      document.querySelectorAll('canvas[data-series]').forEach(function (canvas) {
        var name = canvas.getAttribute('data-series');
        var s = res.series && res.series[name];
        if (!s) return;
        var type = canvas.getAttribute('data-chart-type') || 'line';
        var label = canvas.getAttribute('data-chart-label') || name;
        var moneySeries = canvas.hasAttribute('data-chart-money');
        buildChart(canvas, {
          type: type,
          data: {
            labels: s.labels,
            datasets: [{
              label: label,
              data: s.data,
              borderColor: BRAND[0],
              backgroundColor: type === 'bar' || type === 'doughnut' || type === 'pie'
                ? s.labels.map(function (_, i) { return BRAND[i % BRAND.length]; })
                : 'rgba(37,99,235,.12)',
              fill: type === 'line',
              tension: 0.35
            }]
          },
          options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: type === 'doughnut' || type === 'pie' } },
            scales: type === 'doughnut' || type === 'pie' ? {} : {
              y: { beginAtZero: true, ticks: { callback: function (v) { return moneySeries ? '$' + v : v; } } }
            }
          }
        });
      });
    }).catch(function () { Lurni.toast('Analytics could not be loaded.', 'error'); });
  }
  if (document.querySelector('canvas[data-series]') || document.querySelector('[data-stat]')) {
    loadAnalytics();
    var refreshBtn = document.querySelector('[data-analytics-refresh]');
    if (refreshBtn) refreshBtn.addEventListener('click', function () { loadAnalytics(); });
  }
  Lurni.loadAnalytics = loadAnalytics;

  /* ---------- Instructor question-type toggle ---------- */
  document.querySelectorAll('.js-question-type').forEach(function (sel) {
    var form = sel.closest('form');
    if (!form) return;
    var optionsBlock = form.querySelector('.js-options-block');
    var tfBlock = form.querySelector('.js-tf-block');
    function sync() {
      var v = sel.value;
      if (optionsBlock) optionsBlock.classList.toggle('d-none', v === 'true_false');
      if (tfBlock) tfBlock.classList.toggle('d-none', v !== 'true_false');
      form.querySelectorAll('.js-correct-single').forEach(function (r) { r.classList.toggle('d-none', v === 'multiple'); r.disabled = v === 'multiple'; });
      form.querySelectorAll('.js-correct-multi').forEach(function (c) { c.classList.toggle('d-none', v !== 'multiple'); c.disabled = v !== 'multiple'; });
    }
    sel.addEventListener('change', sync);
    sync();
  });
})();
