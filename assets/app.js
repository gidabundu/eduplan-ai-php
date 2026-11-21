document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide) {
    lucide.createIcons();
  }
  // Close the workspace dropdown when clicking outside it.
  document.addEventListener('click', function (e) {
    var menu = document.getElementById('workspaceMenu');
    if (!menu || menu.classList.contains('hidden')) return;
    if (!e.target.closest('#workspaceMenu') && !e.target.closest('[onclick*="toggleWorkspaceMenu"]')) {
      menu.classList.add('hidden');
    }
  });
  // If the page was reached via "Create account" (users.php?add=1), open the modal.
  var params = new URLSearchParams(window.location.search);
  if (params.get('add') === '1') {
    var modal = document.getElementById('addUserModal');
    if (modal) modal.classList.remove('hidden');
  }

});

window.EduPlan = (function () {
  function openReportEdit(id, title, meta, content) {
    document.getElementById('edit-report-id').value = id;
    document.getElementById('edit-report-title').value = title;
    document.getElementById('edit-report-meta').value = meta;
    document.getElementById('edit-report-content').value = content;
    document.getElementById('editReportModal').classList.remove('hidden');
  }

  function openResourceEdit(id, title, type, subject, resourceClass, text) {
    document.getElementById('edit-resource-id').value = id;
    document.getElementById('edit-resource-title').value = title;
    document.getElementById('edit-resource-type').value = type;
    document.getElementById('edit-resource-subject').value = subject;
    document.getElementById('edit-resource-class').value = resourceClass;
    document.getElementById('edit-resource-text').value = text;
    document.getElementById('editResourceModal').classList.remove('hidden');
  }

  function openUserEdit(id, name, email, role, department, classes) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-user-name').value = name;
    document.getElementById('edit-user-email').value = email;
    document.getElementById('edit-user-role').value = role;
    document.getElementById('edit-user-department').value = department;
    document.getElementById('edit-user-classes').value = classes;
    document.getElementById('editUserModal').classList.remove('hidden');
  }

  function openLoginModal(id, name, isReset) {
    document.getElementById('teacher-login-user-id').value = id;
    document.querySelector('#teacherLoginModal input[name="temporary_password"]').value = '';
    document.getElementById('teacher-login-title').textContent = isReset ? 'Reset teacher login' : 'Create teacher login';
    document.getElementById('teacher-login-description').textContent = (isReset ? 'Set a new temporary password for ' : 'Create a login for ') + name + '.';
    document.getElementById('teacherLoginModal').classList.remove('hidden');
  }

  function toggleTheme() {
    var html = document.documentElement;
    var isLight = html.getAttribute('data-theme') === 'light';
    if (isLight) {
      html.removeAttribute('data-theme');
      localStorage.setItem('eduplan-theme', 'dark');
    } else {
      html.setAttribute('data-theme', 'light');
      localStorage.setItem('eduplan-theme', 'light');
    }
  }

  function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('hidden');
  }

  function toggleWorkspaceMenu() {
    var menu = document.getElementById('workspaceMenu');
    if (menu) menu.classList.toggle('hidden');
  }

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function checked(id) {
    var el = document.getElementById(id);
    return !!(el && el.checked);
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function step(id, delta, min, max) {
    var hidden = document.getElementById(id);
    var display = document.getElementById(id + '-display');
    var value = Math.min(max, Math.max(min, parseInt(hidden.value, 10) + delta));
    hidden.value = value;
    if (display) display.textContent = value;
  }

  function updateDifficultyLabel(value) {
    var label = document.getElementById('as-difficulty-label');
    if (!label) return;
    var v = parseInt(value, 10);
    label.textContent = v < 34 ? 'Foundational' : v < 67 ? 'Standard' : 'Advanced';
  }

  function difficultyLabel() {
    var v = parseInt(val('as-difficulty') || '50', 10);
    return v < 34 ? 'Foundational' : v < 67 ? 'Standard' : 'Advanced';
  }

  function loadingHtml(message) {
    return '' +
      '<div class="flex h-72 flex-col items-center justify-center text-center">' +
      '<i data-lucide="loader-2" class="h-6 w-6 animate-spin text-orange-500"></i>' +
      '<p class="mt-3 text-sm text-zinc-400">' + escapeHtml(message) + '</p>' +
      '</div>';
  }

  function errorHtml(message, retryFn) {
    return '' +
      '<div class="flex h-72 flex-col items-center justify-center text-center">' +
      '<i data-lucide="alert-circle" class="h-6 w-6 text-orange-400"></i>' +
      '<p class="mt-3 max-w-xs text-sm text-zinc-400">' + escapeHtml(message) + '</p>' +
      '<button onclick="' + retryFn + '" class="mt-3 rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800">Try again</button>' +
      '</div>';
  }

  function setResult(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
    if (window.lucide) lucide.createIcons();
  }

  function actionBar(contentId, regenerateFn) {
    return '' +
      '<div class="mt-6 flex flex-wrap gap-2 border-t border-zinc-800 pt-4">' +
      '<button onclick="EduPlan.toggleEdit(\'' + contentId + '\', this)" class="flex items-center gap-1.5 rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800"><i data-lucide="edit-3" class="h-3.5 w-3.5"></i> Edit</button>' +
      '<button onclick="' + regenerateFn + '" class="flex items-center gap-1.5 rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800"><i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Regenerate</button>' +
      '<button onclick="EduPlan.downloadPdf(\'' + contentId + '\', this)" class="flex items-center gap-1.5 rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-800"><i data-lucide="file-text" class="h-3.5 w-3.5"></i> Save PDF</button>' +
      (contentId === 'fc-content' ? '<button onclick="EduPlan.saveForecastProgress(\'' + contentId + '\', this)" class="flex items-center gap-1.5 rounded-full border border-orange-600 px-3 py-1.5 text-xs font-medium text-orange-300 hover:bg-orange-950"><i data-lucide="clipboard-check" class="h-3.5 w-3.5"></i> Save to Progress Tracker</button>' : '') +
      '<button onclick="EduPlan.saveResult(\'' + contentId + '\', this)" class="ml-auto flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-black hover:bg-zinc-200"><i data-lucide="save" class="h-3.5 w-3.5"></i> Save</button>' +
      '</div>';
  }

  function downloadPdf(contentId, btn) {
    var el = document.getElementById(contentId);
    if (!el) return;
    var win = window.open('', '_blank', 'width=900,height=700');
    if (!win) {
      alert('Please allow pop-ups so the PDF can open.');
      return;
    }

    var title = (el.dataset.title || 'EduPlan AI export').replace(/\s+/g, ' ').trim();
    var clone = el.cloneNode(true);
    clone.removeAttribute('id');
    clone.style.cssText = 'font-family: Arial, sans-serif; color: #111827; background: white; padding: 32px; line-height: 1.7; max-width: 900px; margin: 0 auto;';
    clone.classList.remove('ring-1', 'ring-orange-600', 'rounded-lg', 'p-2');

    if (btn) {
      var original = btn.innerHTML;
      btn.innerHTML = 'Preparing PDF...';
      btn.disabled = true;
    }

    var html = '' +
      '<!DOCTYPE html>' +
      '<html><head><meta charset="UTF-8"><title>' + escapeHtml(title) + '</title>' +
      '<style>' +
      'body { margin: 0; background: #fff; color: #111827; }' +
      'h1, h2, h3, h4 { color: #111827; margin: 0 0 12px; }' +
      'p, li, td, th { color: #1f2937; font-size: 14px; }' +
      'table { width: 100%; border-collapse: collapse; margin-top: 12px; }' +
      'th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: left; vertical-align: top; }' +
      'ul, ol { padding-left: 20px; }' +
      '@media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }' +
      '</style></head><body>' + clone.outerHTML + '</body></html>';

    win.document.open();
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(function () {
      win.print();
      if (btn) {
        btn.innerHTML = original;
        btn.disabled = false;
      }
    }, 400);
  }

  function toggleEdit(contentId, btn) {
    var el = document.getElementById(contentId);
    if (!el) return;
    var editing = el.getAttribute('contenteditable') === 'true';
    el.setAttribute('contenteditable', editing ? 'false' : 'true');
    el.classList.toggle('rounded-lg', !editing);
    el.classList.toggle('ring-1', !editing);
    el.classList.toggle('ring-orange-600', !editing);
    el.classList.toggle('p-2', !editing);
    btn.innerHTML = editing
      ? '<i data-lucide="edit-3" class="h-3.5 w-3.5"></i> Edit'
      : '<i data-lucide="check" class="h-3.5 w-3.5"></i> Done editing';
    if (window.lucide) lucide.createIcons();
    if (!editing) el.focus();
  }

  async function saveResult(contentId, btn) {
    var el = document.getElementById(contentId);
    if (!el) return;
    var original = btn.innerHTML;
    btn.innerHTML = 'Saving...';
    btn.disabled = true;
    try {
      const res = await fetch('api/save_generated.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
          type: el.dataset.type || 'content',
          title: el.dataset.title || 'Untitled',
          meta: el.dataset.meta || '',
          content: el.innerText,
        }),
      });
      const data = await res.json();
      if (!res.ok || data.error) throw new Error(data.error || 'Could not save.');
      btn.innerHTML = '<i data-lucide="check" class="h-3.5 w-3.5"></i> Saved to Reports';
    } catch (e) {
      btn.innerHTML = 'Save failed';
    }
    if (window.lucide) lucide.createIcons();
    setTimeout(function () {
      btn.innerHTML = original;
      btn.disabled = false;
      if (window.lucide) lucide.createIcons();
    }, 2500);
  }

  async function saveForecastProgress(contentId, btn) {
    var el = document.getElementById(contentId);
    if (!el) return;
    var weeks;
    try {
      weeks = JSON.parse(el.dataset.weeks || '[]');
    } catch (e) {
      btn.title = 'Forecast data could not be read.';
      return;
    }

    var original = btn.innerHTML;
    btn.innerHTML = 'Saving...';
    btn.disabled = true;
    try {
      const res = await fetch('api/save_forecast.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
          year: el.dataset.year || '',
          term: el.dataset.term || '',
          class_name: el.dataset.className || '',
          subject: el.dataset.subject || '',
          weeks: weeks,
        }),
      });
      const data = await res.json();
      if (!res.ok || data.error) throw new Error(data.error || 'Could not save forecast progress.');
      btn.innerHTML = '<i data-lucide="check" class="h-3.5 w-3.5"></i> Saved to tracker';
    } catch (e) {
      btn.innerHTML = 'Save failed';
      btn.title = e.message;
    }
    if (window.lucide) lucide.createIcons();
    setTimeout(function () {
      btn.innerHTML = original;
      btn.disabled = false;
      if (window.lucide) lucide.createIcons();
    }, 3500);
  }

  function badge(text) {
    return '<span class="inline-flex items-center gap-1 rounded-full border border-orange-900 bg-orange-950 px-2.5 py-0.5 text-xs font-medium text-orange-300">' + escapeHtml(text) + '</span>';
  }

  let callApiInFlight = false;

  async function callApi(type, form, resourceId, webSearch) {
    if (callApiInFlight) {
      throw new Error('A generation is already in progress. Please wait for it to finish.');
    }

    callApiInFlight = true;
    const controller = new AbortController();
    const timeoutId = setTimeout(function () {
      controller.abort();
    }, 65000);

    let res;
    try {
      res = await fetch('api/generate.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ type: type, form: form, resourceId: resourceId, webSearch: webSearch }),
        signal: controller.signal,
      });
    } catch (error) {
      if (error && error.name === 'AbortError') {
        throw new Error('Generation took too long. Please shorten the request or try again.');
      }
      throw error;
    } finally {
      clearTimeout(timeoutId);
      callApiInFlight = false;
    }
    const responseText = await res.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (error) {
      console.error('AI generation endpoint returned a non-JSON response. HTTP status:', res.status);
      throw new Error('The server returned an invalid response (HTTP ' + res.status + '). Check the PHP error log and try again.');
    }
    if (!res.ok || data.error) {
      throw new Error(data.error || 'Something went wrong.');
    }
    return data.result;
  }

  // ---------- Lesson Plan ----------
  async function submitLessonPlan() {
    setResult('lp-result', loadingHtml('Drafting your lesson plan...'));
    const form = {
      classLevel: val('lp-classLevel'),
      subject: val('lp-subject'),
      term: val('lp-term'),
      topic: val('lp-topic'),
    };
    try {
      const r = await callApi('lesson-plan', form, parseInt(val('lp-resource') || '0', 10), checked('lp-websearch'));
      const meta = form.classLevel + ' • ' + form.subject + ' • ' + (r.duration || '');
      let html = '<div id="lp-content" data-type="lesson-plan" data-title="' + escapeHtml(form.topic) + '" data-meta="' + escapeHtml(meta) + '">';
      html += '<div class="mb-1 flex items-center gap-2">' + badge('AI generated') + '<span class="text-xs text-zinc-500">' + escapeHtml(meta) + '</span></div>';
      html += '<h2 class="mt-2 text-xl font-semibold text-white">' + escapeHtml(form.topic) + '</h2>';
      html += '<div class="mt-5"><h3 class="text-sm font-semibold text-orange-400">Introduction (' + escapeHtml(r.duration || '') + ')</h3><p class="mt-1 text-sm leading-relaxed text-zinc-300">' + escapeHtml(r.introduction) + '</p></div>';
      html += '<div class="mt-5"><h3 class="text-sm font-semibold text-orange-400">Teacher activities</h3><ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-zinc-300">' + (r.teacherActivities || []).map(function (t) { return '<li>' + escapeHtml(t) + '</li>'; }).join('') + '</ul></div>';
      html += '<div class="mt-5"><h3 class="text-sm font-semibold text-orange-400">Student activities</h3><ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-zinc-300">' + (r.studentActivities || []).map(function (t) { return '<li>' + escapeHtml(t) + '</li>'; }).join('') + '</ul></div>';
      html += '</div>' + actionBar('lp-content', 'EduPlan.submitLessonPlan()');
      setResult('lp-result', html);
    } catch (e) {
      setResult('lp-result', errorHtml(e.message, 'EduPlan.submitLessonPlan()'));
    }
  }

  // ---------- Assessment ----------
  async function submitAssessment() {
    setResult('as-result', loadingHtml('Writing questions...'));
    const typeEl = document.querySelector('input[name="as-type"]:checked');
    const form = {
      classLevel: val('as-classLevel'),
      subject: val('as-subject'),
      topic: val('as-topic'),
      assessType: typeEl ? typeEl.value : 'Test',
      difficulty: difficultyLabel(),
      mcqCount: parseInt(val('as-mcq') || '4', 10),
      essayCount: parseInt(val('as-essay') || '2', 10),
    };
    try {
      const r = await callApi('assessment', form, parseInt(val('as-resource') || '0', 10), checked('as-websearch'));
      const meta = form.subject + ' • ' + form.classLevel + ' • ' + form.difficulty + ' difficulty';
      let html = '<div id="as-content" data-type="assessment" data-title="' + escapeHtml(r.title || form.topic) + '" data-meta="' + escapeHtml(meta) + '">';
      html += '<div class="flex items-center justify-between">' + badge('Generated test') + '<span class="text-xs text-zinc-500">' + escapeHtml(r.estimatedTime || '') + '</span></div>';
      html += '<h2 class="mt-2 text-xl font-semibold text-white">' + escapeHtml(r.title || '') + '</h2>';
      html += '<p class="text-xs text-zinc-500">' + escapeHtml(form.subject) + ' • ' + escapeHtml(form.classLevel) + ' • ' + escapeHtml(form.difficulty) + ' difficulty</p>';
      if (r.mcqs && r.mcqs.length) {
        html += '<div class="mt-5"><h3 class="text-sm font-semibold text-zinc-300">Part I: Multiple choice</h3><div class="mt-2 space-y-4">';
        r.mcqs.forEach(function (q, i) {
          html += '<div><p class="text-sm font-medium text-zinc-100">' + (i + 1) + '. ' + escapeHtml(q.question) + '</p><div class="mt-1.5 grid grid-cols-1 gap-1.5 sm:grid-cols-2">';
          (q.options || []).forEach(function (o) {
            const correct = o.label === q.correct;
            html += '<div class="flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs ' + (correct ? 'border-orange-700 bg-orange-950 text-orange-300' : 'border-zinc-700 text-zinc-300') + '">' +
              '<span class="font-semibold">' + escapeHtml(o.label) + '</span> ' + escapeHtml(o.text) +
              (correct ? '<i data-lucide="check" class="ml-auto h-3 w-3 flex-shrink-0"></i>' : '') + '</div>';
          });
          html += '</div></div>';
        });
        html += '</div></div>';
      }
      if (r.essays && r.essays.length) {
        html += '<div class="mt-6"><h3 class="text-sm font-semibold text-zinc-300">Part II: Open ended</h3><ol class="mt-2 list-decimal space-y-2 pl-4 text-sm text-zinc-300">' +
          r.essays.map(function (q) { return '<li>' + escapeHtml(q) + '</li>'; }).join('') + '</ol></div>';
      }
      html += '</div>' + actionBar('as-content', 'EduPlan.submitAssessment()');
      setResult('as-result', html);
    } catch (e) {
      setResult('as-result', errorHtml(e.message, 'EduPlan.submitAssessment()'));
    }
  }

  // ---------- Forecast ----------
  async function submitForecast() {
    setResult('fc-result', loadingHtml('Mapping out the term...'));
    const form = {
      year: val('fc-year'),
      term: val('fc-term'),
      className: val('fc-className'),
      subject: val('fc-subject'),
      source: val('fc-source'),
      weeks: parseInt(val('fc-weeks') || '4', 10),
      lessonsPerWeek: parseInt(val('fc-lessonsPerWeek') || '3', 10),
      startDate: val('fc-startDate'),
    };
    try {
      const r = await callApi('forecast', form, parseInt(val('fc-resource') || '0', 10), checked('fc-websearch'));
      const meta = form.subject + ' • ' + form.className + ' • ' + form.term;
      let html = '<div id="fc-content" data-type="forecast" data-title="' + escapeHtml(form.subject) + ' term forecast" data-meta="' + escapeHtml(meta) + '" data-year="' + escapeHtml(form.year) + '" data-term="' + escapeHtml(form.term) + '" data-class-name="' + escapeHtml(form.className) + '" data-subject="' + escapeHtml(form.subject) + '" data-weeks="' + escapeHtml(JSON.stringify(r.weeks || [])) + '">';
      html += '<div class="mb-4 rounded-lg border border-orange-900 bg-orange-950 p-4">' +
        '<div class="flex items-center gap-1.5 text-xs font-semibold text-orange-300"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i> AI context insights</div>' +
        '<p class="mt-1.5 text-xs leading-relaxed text-orange-200">' + escapeHtml(r.insight || '') + '</p></div>';
      html += '<h2 class="text-sm font-semibold text-white">Generated teaching plan</h2>';
      html += '<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-zinc-800 text-xs text-zinc-500">' +
        '<th class="pb-2 pr-2 font-medium">Wk</th><th class="pb-2 pr-2 font-medium">Date</th><th class="pb-2 pr-2 font-medium">Topic</th><th class="pb-2 pr-2 font-medium">Sub-topic</th><th class="pb-2 font-medium">Learning objective</th>' +
        '</tr></thead><tbody class="divide-y divide-zinc-800">';
      (r.weeks || []).forEach(function (w) {
        html += '<tr><td class="py-2.5 pr-2 text-zinc-400">' + escapeHtml(w.week) + '</td><td class="py-2.5 pr-2 text-zinc-400">' + escapeHtml(w.date) + '</td>' +
          '<td class="py-2.5 pr-2 font-medium text-zinc-200">' + escapeHtml(w.topic) + '</td><td class="py-2.5 pr-2 text-zinc-300">' + escapeHtml(w.subtopic) + '</td>' +
          '<td class="py-2.5 text-zinc-400">' + escapeHtml(w.objective) + '</td></tr>';
      });
      html += '</tbody></table></div>';
      html += '</div>' + actionBar('fc-content', 'EduPlan.submitForecast()');
      setResult('fc-result', html);
    } catch (e) {
      setResult('fc-result', errorHtml(e.message, 'EduPlan.submitForecast()'));
    }
  }

  // ---------- Lesson Note ----------
  async function submitLessonNote() {
    setResult('ln-result', loadingHtml('Writing the lesson note...'));
    const form = {
      classLevel: val('ln-classLevel'),
      subject: val('ln-subject'),
      topic: val('ln-topic'),
      subtopic: val('ln-subtopic'),
      duration: val('ln-duration'),
      tone: val('ln-tone'),
    };
    try {
      const r = await callApi('lesson-note', form, parseInt(val('ln-resource') || '0', 10), checked('ln-websearch'));
      const meta = form.classLevel + ' • ' + form.subject + ' • ' + form.duration;
      let html = '<div id="ln-content" data-type="lesson-note" data-title="' + escapeHtml(form.topic) + '" data-meta="' + escapeHtml(meta) + '">';
      html += '<div class="flex items-center gap-2">' + badge('AI generated') + '<span class="text-xs text-zinc-500">Last updated: just now</span></div>';
      html += '<h2 class="mt-2 text-2xl font-semibold text-white">' + escapeHtml(form.topic) + '</h2>';
      html += '<p class="text-xs text-zinc-500">' + escapeHtml(form.classLevel) + ' • ' + escapeHtml(form.subject) + ' • ' + escapeHtml(form.duration) + '</p>';
      html += '<p class="mt-2 text-sm font-medium text-zinc-300">Sub-topic: ' + escapeHtml(form.subtopic) + '</p>';
      html += '<div class="mt-5 space-y-5">';
      (r.sections || []).forEach(function (s) {
        html += '<div><h3 class="text-sm font-semibold text-zinc-100">' + escapeHtml(s.heading) + '</h3><p class="mt-1 text-sm leading-relaxed text-zinc-300">' + escapeHtml(s.body) + '</p></div>';
      });
      html += '</div></div>' + actionBar('ln-content', 'EduPlan.submitLessonNote()');
      setResult('ln-result', html);
    } catch (e) {
      setResult('ln-result', errorHtml(e.message, 'EduPlan.submitLessonNote()'));
    }
  }

  // ---------- Custom request ----------
  async function submitCustom() {
    setResult('cu-result', loadingHtml('Thinking it through...'));
    const form = { request: val('cu-request') };
    try {
      const r = await callApi('custom', form, parseInt(val('cu-resource') || '0', 10), checked('cu-websearch'));
      const title = form.request.length > 60 ? form.request.slice(0, 60) + '…' : form.request;
      let html = '<div class="mb-3">' + badge('AI response') + '</div>';
      html += '<div id="cu-content" data-type="custom" data-title="' + escapeHtml(title) + '" data-meta="Custom request">' +
        '<div class="whitespace-pre-wrap text-sm leading-relaxed text-zinc-200">' + escapeHtml(r.text || '') + '</div></div>';
      html += actionBar('cu-content', 'EduPlan.submitCustom()');
      setResult('cu-result', html);
    } catch (e) {
      setResult('cu-result', errorHtml(e.message, 'EduPlan.submitCustom()'));
    }
  }

  return {
    openReportEdit: openReportEdit,
    openResourceEdit: openResourceEdit,
    openUserEdit: openUserEdit,
    openLoginModal: openLoginModal,
    toggleTheme: toggleTheme,
    toggleSidebar: toggleSidebar,
    toggleWorkspaceMenu: toggleWorkspaceMenu,
    toggleEdit: toggleEdit,
    downloadPdf: downloadPdf,
    saveResult: saveResult,
    saveForecastProgress: saveForecastProgress,
    step: step,
    updateDifficultyLabel: updateDifficultyLabel,
    submitLessonPlan: submitLessonPlan,
    submitAssessment: submitAssessment,
    submitForecast: submitForecast,
    submitLessonNote: submitLessonNote,
    submitCustom: submitCustom,
  };
})();
