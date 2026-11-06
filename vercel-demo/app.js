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

  /* ===== Toast notifications (global, demo) ===== */
  var lurniCharts = {};
  function showToast(message) {
    var container = document.querySelector('.lurni-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'lurni-toast-container';
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }
    var toast = document.createElement('div');
    toast.className = 'lurni-toast';
    var text = document.createElement('span');
    text.textContent = message;
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'lurni-toast-close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    close.addEventListener('click', function () { toast.remove(); });
    toast.appendChild(text);
    toast.appendChild(close);
    container.appendChild(toast);
    setTimeout(function () { if (toast.parentNode) toast.remove(); }, 3400);
  }

  /* ===== Enroll buttons (landing course cards) ===== */
  document.querySelectorAll('[data-enroll-btn]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (btn.getAttribute('data-enrolled') === 'true') return;
      btn.setAttribute('data-enrolled', 'true');
      var card = btn.closest('.course-card');
      var titleEl = card ? card.querySelector('h5') : null;
      var title = titleEl ? titleEl.textContent.trim() : 'this course';
      btn.textContent = 'Enrolled ✓';
      showToast('Enrolled in “' + title + '” — welcome aboard! (demo)');
    });
  });

  /* ===== ADMIN: date range analytics + live table filters ===== */
  var ADMIN_RANGES = {
    '30': { label: 'Last 30 days', labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'], enroll: [96, 118, 132, 154], revenue: [2600, 2980, 3240, 3660], students: '1,248', studentsNote: '↑ 12.4% · last 30 days', instructors: '86', instructorsNote: '↑ 6 · last 30 days', courses: '142', coursesNote: '18 in draft', revenueText: '$12,480', revenueNote: '↑ 18.2% · last 30 days' },
    '90': { label: 'Last 90 days', labels: ['Sep', 'Oct', 'Nov'], enroll: [342, 398, 424], revenue: [9800, 11200, 12480], students: '3,415', studentsNote: 'Active in last 90 days', instructors: '94', instructorsNote: '↑ 14 · last 90 days', courses: '156', coursesNote: '21 in draft', revenueText: '$33,480', revenueNote: 'Total · last 90 days' },
    '365': { label: 'Last 12 months', labels: ['Q1', 'Q2', 'Q3', 'Q4'], enroll: [980, 1120, 1340, 1510], revenue: [28400, 32600, 38900, 42100], students: '11,940', studentsNote: 'Active in last 12 months', instructors: '128', instructorsNote: '↑ 52 · last 12 months', courses: '189', coursesNote: '26 in draft', revenueText: '$142,000', revenueNote: 'Total · last 12 months' }
  };
  function setText(id, value) { var el = document.getElementById(id); if (el) el.textContent = value; }
  function updateAdminRange(key) {
    var r = ADMIN_RANGES[key] || ADMIN_RANGES['30'];
    if (lurniCharts.enrollmentChart) {
      lurniCharts.enrollmentChart.data.labels = r.labels;
      lurniCharts.enrollmentChart.data.datasets[0].data = r.enroll;
      lurniCharts.enrollmentChart.update();
    }
    if (lurniCharts.revenueChart) {
      lurniCharts.revenueChart.data.labels = r.labels;
      lurniCharts.revenueChart.data.datasets[0].data = r.revenue;
      lurniCharts.revenueChart.update();
    }
    setText('adminStatStudents', r.students);
    setText('adminStatStudentsNote', r.studentsNote);
    setText('adminStatInstructors', r.instructors);
    setText('adminStatInstructorsNote', r.instructorsNote);
    setText('adminStatCourses', r.courses);
    setText('adminStatCoursesNote', r.coursesNote);
    setText('adminStatRevenue', r.revenueText);
    setText('adminStatRevenueNote', r.revenueNote);
    setText('adminEnrollRangeLabel', r.label);
    setText('adminRevenueRangeLabel', r.label + ' · $');
  }
  var adminRangeBtns = document.querySelectorAll('[data-admin-range]');
  adminRangeBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      adminRangeBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      updateAdminRange(btn.getAttribute('data-admin-range'));
    });
  });

  function filterRows(rows, q, cat, status) {
    var visible = 0;
    rows.forEach(function (row) {
      var hay = ((row.getAttribute('data-search') || '') + ' ' + row.textContent).toLowerCase();
      var okQ = !q || hay.indexOf(q) !== -1;
      var okC = cat === 'all' || row.getAttribute('data-category') === cat;
      var okS = status === 'all' || row.getAttribute('data-status') === status;
      var show = okQ && okC && okS;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    return visible;
  }
  var adminSearch = document.getElementById('adminSearch');
  if (adminSearch) {
    var adminCat = document.getElementById('adminCategoryFilter');
    var adminStatus = document.getElementById('adminStatusFilter');
    var applyAdminFilters = function () {
      var q = adminSearch.value.trim().toLowerCase();
      var cat = adminCat ? adminCat.value : 'all';
      var status = adminStatus ? adminStatus.value : 'all';
      var courseRows = Array.prototype.slice.call(document.querySelectorAll('#adminCoursesTable tbody tr'));
      var studentRows = Array.prototype.slice.call(document.querySelectorAll('#adminStudentsTable tbody tr'));
      var enrollRows = Array.prototype.slice.call(document.querySelectorAll('#adminEnrollmentsTable tbody tr'));
      var cEmpty = document.getElementById('adminCoursesEmpty');
      var sEmpty = document.getElementById('adminStudentsEmpty');
      if (cEmpty) cEmpty.hidden = filterRows(courseRows, q, cat, status) !== 0;
      if (sEmpty) sEmpty.hidden = filterRows(studentRows, q, cat, status) !== 0;
      filterRows(enrollRows, q, cat, status);
    };
    adminSearch.addEventListener('input', applyAdminFilters);
    if (adminCat) adminCat.addEventListener('change', applyAdminFilters);
    if (adminStatus) adminStatus.addEventListener('change', applyAdminFilters);
  }

  /* ===== STUDENT: lesson completion + bookmarks ===== */
  var lessonItems = document.querySelectorAll('[data-lesson-item]');
  function updateLessonProgress() {
    if (!lessonItems.length) return;
    var done = 0;
    lessonItems.forEach(function (item) { if (item.getAttribute('data-completed') === 'true') done++; });
    var pct = lessonItems.length ? Math.round((done / lessonItems.length) * 100) : 0;
    var bar = document.getElementById('lessonProgressBar');
    if (bar) bar.style.width = pct + '%';
    setText('lessonProgressPct', pct + '%');
    setText('lessonCompletedCount', done + ' of ' + lessonItems.length + ' completed');
  }
  lessonItems.forEach(function (item) {
    var completeBtn = item.querySelector('[data-complete-btn]');
    if (completeBtn) {
      completeBtn.addEventListener('click', function () {
        var nowDone = item.getAttribute('data-completed') !== 'true';
        item.setAttribute('data-completed', nowDone ? 'true' : 'false');
        completeBtn.classList.toggle('is-complete', nowDone);
        completeBtn.setAttribute('aria-pressed', nowDone ? 'true' : 'false');
        completeBtn.textContent = nowDone ? 'Completed ✓' : 'Mark complete';
        updateLessonProgress();
        showToast(nowDone ? 'Lesson marked complete — progress updated. 🎉' : 'Lesson marked as not complete.');
      });
    }
    var bookmarkBtn = item.querySelector('[data-bookmark-btn]');
    if (bookmarkBtn) {
      bookmarkBtn.addEventListener('click', function () {
        var on = bookmarkBtn.getAttribute('aria-pressed') !== 'true';
        bookmarkBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        bookmarkBtn.classList.toggle('is-bookmarked', on);
        bookmarkBtn.innerHTML = on ? '<i class="bi bi-bookmark-fill"></i>' : '<i class="bi bi-bookmark"></i>';
        showToast(on ? 'Lesson bookmarked for later. 🔖' : 'Bookmark removed.');
      });
    }
  });
  updateLessonProgress();

  /* ===== STUDENT: timed mock quiz with instant grading ===== */
  var quizForm = document.getElementById('quizForm');
  if (quizForm) {
    var QUIZ_ANSWERS = { quizQ1: 'relu', quizQ2: 'gradients', quizQ3: 'classification', quizQ4: 'overfit' };
    var QUIZ_LABELS = { quizQ1: 'ReLU activation', quizQ2: 'Gradients of the loss for each weight', quizQ3: 'Predicting “spam” or “not spam” from labelled emails', quizQ4: 'Overfitting' };
    var quizSeconds = 300;
    var quizTimerId = null;
    var quizGraded = false;
    var timerEl = document.getElementById('quizTimer');
    function renderQuizTimer() {
      if (!timerEl) return;
      var m = Math.floor(quizSeconds / 60);
      var s = quizSeconds % 60;
      timerEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function startQuizTimer() {
      stopQuizTimer();
      renderQuizTimer();
      quizTimerId = setInterval(function () {
        quizSeconds--;
        renderQuizTimer();
        if (quizSeconds <= 0) {
          stopQuizTimer();
          if (!quizGraded) gradeQuiz(true);
        }
      }, 1000);
    }
    function stopQuizTimer() { if (quizTimerId) { clearInterval(quizTimerId); quizTimerId = null; } }
    function gradeQuiz(auto) {
      if (quizGraded) return;
      quizGraded = true;
      stopQuizTimer();
      var score = 0;
      var total = Object.keys(QUIZ_ANSWERS).length;
      Object.keys(QUIZ_ANSWERS).forEach(function (name) {
        var fieldset = quizForm.querySelector('input[name="' + name + '"]').closest('[data-quiz-question]');
        var picked = quizForm.querySelector('input[name="' + name + '"]:checked');
        var feedback = fieldset ? fieldset.querySelector('.quiz-feedback') : null;
        var correct = picked && picked.value === QUIZ_ANSWERS[name];
        if (correct) score++;
        if (feedback) {
          feedback.textContent = correct ? '✓ Correct' : '✗ Correct answer: ' + QUIZ_LABELS[name];
          feedback.style.color = correct ? '#059669' : '#DC2626';
        }
        quizForm.querySelectorAll('input[name="' + name + '"]').forEach(function (r) { r.disabled = true; });
      });
      var passed = (score / total) >= 0.75;
      var result = document.getElementById('quizResult');
      if (result) result.hidden = false;
      setText('quizScoreText', 'Score: ' + score + ' / ' + total + (auto ? ' (time expired — auto-submitted)' : ''));
      var pill = document.getElementById('quizPassFail');
      if (pill) {
        pill.textContent = passed ? 'Passed ✓' : 'Not passed — try again';
        pill.className = passed ? 'pill pill-green' : 'pill pill-amber';
      }
      setText('quizResultNote', passed ? 'Great work — this quiz now counts toward your Module 7 progress.' : 'Review lessons 7.1–7.4 and retake the quiz to pass.');
      var submitBtn = document.getElementById('quizSubmitBtn');
      if (submitBtn) submitBtn.disabled = true;
      showToast('Quiz submitted — you scored ' + score + ' / ' + total + (passed ? '. Passed! 🏆' : '.'));
    }
    quizForm.addEventListener('submit', function (e) { e.preventDefault(); gradeQuiz(false); });
    var retakeBtn = document.getElementById('quizRetakeBtn');
    if (retakeBtn) {
      retakeBtn.addEventListener('click', function () {
        quizGraded = false;
        quizSeconds = 300;
        quizForm.reset();
        quizForm.querySelectorAll('input[type="radio"]').forEach(function (r) { r.disabled = false; });
        quizForm.querySelectorAll('.quiz-feedback').forEach(function (f) { f.textContent = ''; });
        var result = document.getElementById('quizResult');
        if (result) result.hidden = true;
        var submitBtn = document.getElementById('quizSubmitBtn');
        if (submitBtn) submitBtn.disabled = false;
        startQuizTimer();
        showToast('Quiz reset — good luck on the retake!');
      });
    }
    startQuizTimer();
  }

  /* ===== INSTRUCTOR: course search/filter + create course ===== */
  var instructorSearch = document.getElementById('instructorSearch');
  if (instructorSearch) {
    var instructorStatus = document.getElementById('instructorStatusFilter');
    var applyInstructorFilters = function () {
      var q = instructorSearch.value.trim().toLowerCase();
      var status = instructorStatus ? instructorStatus.value : 'all';
      var rows = Array.prototype.slice.call(document.querySelectorAll('#instructorCoursesBody tr'));
      var visible = 0;
      rows.forEach(function (row) {
        var hay = ((row.getAttribute('data-search') || '') + ' ' + row.textContent).toLowerCase();
        var show = (!q || hay.indexOf(q) !== -1) && (status === 'all' || row.getAttribute('data-status') === status);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      var empty = document.getElementById('instructorCoursesEmpty');
      if (empty) empty.hidden = visible !== 0;
    };
    instructorSearch.addEventListener('input', applyInstructorFilters);
    if (instructorStatus) instructorStatus.addEventListener('change', applyInstructorFilters);
  }
  var createCourseForm = document.getElementById('createCourseForm');
  if (createCourseForm) {
    createCourseForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var titleInput = document.getElementById('newCourseTitle');
      var catInput = document.getElementById('newCourseCategory');
      var statusInput = document.getElementById('newCourseStatus');
      var tbody = document.getElementById('instructorCoursesBody');
      var title = titleInput ? titleInput.value.trim() : '';
      if (!title || !tbody) return;
      var status = statusInput ? statusInput.value : 'draft';
      var tr = document.createElement('tr');
      tr.setAttribute('data-status', status);
      tr.setAttribute('data-search', title.toLowerCase());
      var tdTitle = document.createElement('td');
      var bold = document.createElement('b');
      bold.textContent = '📘 ' + title;
      tdTitle.appendChild(bold);
      var tdStudents = document.createElement('td');
      tdStudents.textContent = '0';
      var tdCompletion = document.createElement('td');
      var note = document.createElement('small');
      note.className = 'text-muted2';
      note.textContent = status === 'published' ? 'Just launched' : 'Not launched';
      tdCompletion.appendChild(note);
      var tdRating = document.createElement('td');
      tdRating.textContent = '—';
      var tdStatus = document.createElement('td');
      var pill = document.createElement('span');
      pill.className = status === 'published' ? 'pill pill-green' : 'pill pill-amber';
      pill.textContent = status === 'published' ? 'Published' : 'Draft';
      tdStatus.appendChild(pill);
      tr.appendChild(tdTitle); tr.appendChild(tdStudents); tr.appendChild(tdCompletion); tr.appendChild(tdRating); tr.appendChild(tdStatus);
      tbody.appendChild(tr);
      if (status === 'published') {
        var countEl = document.getElementById('instructorCourseCount');
        if (countEl) countEl.textContent = String((parseInt(countEl.textContent, 10) || 0) + 1);
        if (lurniCharts.enrollmentsPerCourseChart) {
          lurniCharts.enrollmentsPerCourseChart.data.labels.push(title);
          lurniCharts.enrollmentsPerCourseChart.data.datasets[0].data.push(0);
          lurniCharts.enrollmentsPerCourseChart.update();
        }
      }
      if (instructorSearch) { instructorSearch.value = ''; var sf = document.getElementById('instructorStatusFilter'); if (sf) sf.value = 'all'; instructorSearch.dispatchEvent(new Event('input')); }
      var createdCategory = catInput ? catInput.value : 'course';
      createCourseForm.reset();
      var modalEl = document.getElementById('createCourseModal');
      try {
        if (window.bootstrap && bootstrap.Modal && modalEl) {
          var instance = bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : bootstrap.Modal.getInstance(modalEl);
          if (instance) instance.hide();
        }
      } catch (err) { /* demo fallback: leave modal state to Bootstrap dismiss */ }
      showToast('Course “' + title + '” created (' + createdCategory + ') — added to My Courses. (demo)');
    });
  }


  /* Charts — guarded per page: only init when canvas exists */
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "'Inter',sans-serif";
  Chart.defaults.color = '#64748B';
  var grad = function (ctx, c1, c2) { var g = ctx.createLinearGradient(0, 0, 0, 260); g.addColorStop(0, c1); g.addColorStop(1, c2); return g; };
  function make(id, cfg) { var el = document.getElementById(id); if (el) lurniCharts[id] = new Chart(el, cfg); }

  make('enrollmentChart', { type: 'bar', data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'], datasets: [{ label: 'Enrollments', data: [182,240,215,310,286,352,398,424], backgroundColor: '#2563EB', hoverBackgroundColor: '#7C3AED', borderRadius: 8, barPercentage: .62 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('revenueChart', { type: 'line', data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'], datasets: [{ label: 'Revenue ($)', data: [6400,7200,6900,8400,9100,10200,11400,12480], borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,.12)', fill: true, tension: .45, pointBackgroundColor: '#7C3AED', pointRadius: 4 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('performanceChart', { type: 'bar', data: { labels: ['Python Bootcamp','UI/UX Design','Data Science','Web Dev','Marketing'], datasets: [{ label: 'Avg. score %', data: [88,84,91,79,86], backgroundColor: ['#2563EB','#7C3AED','#0EA5E9','#F59E0B','#EC4899'], borderRadius: 8, barPercentage: .6 }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100, grid: { color: '#EEF2FF' } }, y: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('earningsChart', { type: 'line', data: { labels: ['Sep','Oct','Nov','Dec','Jan','Feb'], datasets: [{ label: 'Earnings ($)', data: [1240,1480,1390,1720,1980,2240], borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .45, pointBackgroundColor: '#2563EB' }] }, options: { plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('activityChart', { type: 'bar', data: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets: [{ label: 'Study minutes', data: [45,70,55,90,65,110,80], backgroundColor: function (c) { return grad(c.chart.ctx, '#2563EB', '#7C3AED'); }, borderRadius: 8, barPercentage: .58 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('enrollmentsPerCourseChart', { type: 'bar', data: { labels: ['Python for AI & ML','Data Science with Pandas','Deep Learning Basics','NLP Foundations'], datasets: [{ label: 'Enrollments', data: [3240,2260,340,0], backgroundColor: '#2563EB', hoverBackgroundColor: '#7C3AED', borderRadius: 8, barPercentage: .62 }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EEF2FF' } }, x: { grid: { display: false } } }, responsive: true, maintainAspectRatio: false } });
  make('progressChart', { type: 'doughnut', data: { labels: ['Completed','In progress','Not started'], datasets: [{ data: [62,28,10], backgroundColor: ['#2563EB','#7C3AED','#E2E8F0'], borderWidth: 0 }] }, options: { cutout: '68%', plugins: { legend: { position: 'bottom' } }, responsive: true, maintainAspectRatio: false } });
  if (document.getElementById('adminRangeToggle')) updateAdminRange('30');
})();
