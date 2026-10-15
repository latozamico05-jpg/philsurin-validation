/* Encoding Progress pie chart: saved varieties by grading stage.
   Replaces the old horizontal stage bars with a donut pie so encoding
   progress is visible at a glance. No external chart library needed. */
(function () {
  function boot() {
    var main = document.querySelector('.dashboard-main');
    if (!main) return;
    main.querySelectorAll('.encoding-progress').forEach(function (el) { el.remove(); });

  var panel = document.createElement('section');
  panel.className = 'card encoding-progress';
  panel.innerHTML = '<div class="progress-heading"><div><h2>Encoding Progress</h2>'
    + '<p class="hint">Pie graph of saved varieties by grading stage</p></div>'
    + '<label>Trial<select aria-label="Filter progress by trial"><option value="">All trials</option></select></label></div>'
    + '<div class="pie-wrap" aria-live="polite">'
    + '<div class="pie-holder"><div class="pie" role="img" aria-label="Encoding progress pie chart"></div>'
    + '<div class="pie-center"><strong>0%</strong><span>encoded</span></div></div>'
    + '<ul class="pie-legend"></ul></div>'
    + '<p class="pie-empty" hidden>No saved validation data yet.</p>';
  var style = document.createElement('style');
  style.textContent = '.encoding-progress{margin-top:14px}'
    + '.progress-heading{display:flex;align-items:center;justify-content:space-between;gap:16px}'
    + '.progress-heading h2{margin-bottom:5px}.progress-heading label{width:min(100%,320px)}'
    + '.pie-wrap{display:flex;align-items:center;gap:26px;margin-top:16px;flex-wrap:wrap}'
    + '.pie-holder{position:relative;width:190px;height:190px;flex:0 0 auto}'
    + '.pie{width:190px;height:190px;border-radius:50%;background:#edf2f6}'
    + '.pie-center{position:absolute;inset:34px;background:#fff;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(16,74,50,.10)}'
    + '.pie-center strong{font-size:26px;color:#07865f;letter-spacing:-.5px}'
    + '.pie-center span{font-size:10px;color:#748196;text-transform:uppercase;letter-spacing:.6px}'
    + '.pie-legend{list-style:none;margin:0;padding:0;display:grid;gap:9px;flex:1 1 220px;min-width:200px}'
    + '.pie-legend li{display:flex;align-items:center;gap:9px;font-size:12px;color:#24364b;font-weight:600}'
    + '.pie-dot{flex:0 0 12px;width:12px;height:12px;border-radius:50%}';
  if (!document.getElementById('encoding-progress-css')) {
    style.id = 'encoding-progress-css';
    document.head.appendChild(style);
  }
  var grid = main.querySelector('.dashboard-grid');
  if (grid) { grid.appendChild(panel); }
  else {
    var before = main.querySelector('.metric-grid') || main.querySelector('.card');
    main.insertBefore(panel, before ? before.nextSibling : null);
  }

  var select = panel.querySelector('select');
  var pie = panel.querySelector('.pie');
  var centerNum = panel.querySelector('.pie-center strong');
  var legend = panel.querySelector('.pie-legend');
  var emptyMsg = panel.querySelector('.pie-empty');
  var colors = ['#07865f', '#4276a8', '#ba7a19', '#74579a', '#477d48'];
  function legendRow(s, i) {
    var li = document.createElement('li');
    var dot = document.createElement('span');
    dot.className = 'pie-dot';
    dot.style.backgroundColor = colors[i % colors.length];
    var name = document.createElement('span');
    name.textContent = s.name;
    var small = document.createElement('small');
    small.style.marginLeft = 'auto';
    small.style.color = '#748196';
    small.textContent = s.completed + ' / ' + s.total + ' - ' + s.percent + '%';
    li.append(dot, name, small);
    legend.appendChild(li);
  }

  function render(data) {
    var selected = select.value;
    select.replaceChildren(new Option('All trials', ''));
    Object.keys(data.locations || {}).forEach(function (locationId) {
      select.add(new Option(locationId + ' - ' + data.locations[locationId], locationId));
    });
    select.value = selected;
    if (select.value !== selected) select.value = '';
    var stages = data.stages || [];
    var totalSlots = 0, doneSlots = 0;
    stages.forEach(function (s) {
      totalSlots += (+s.total || 0);
      doneSlots += (+s.completed || 0);
    });
    var overall = totalSlots ? Math.round((doneSlots / totalSlots) * 100) : 0;
    centerNum.textContent = overall + '%';
    pie.setAttribute('aria-label', 'Encoding progress: ' + doneSlots + ' of ' + totalSlots + ' saved (' + overall + '%)');
    legend.replaceChildren();
    if (!stages.length || doneSlots === 0) {
      pie.style.background = '#edf2f6';
      emptyMsg.hidden = false;
      stages.forEach(legendRow);
      return;
    }
    emptyMsg.hidden = true;
    var acc = 0;
    var parts = stages.map(function (s, i) {
      var frac = (+s.completed || 0) / doneSlots;
      var from = (acc * 100).toFixed(2);
      acc += frac;
      return colors[i % colors.length] + ' ' + from + '% ' + (acc * 100).toFixed(2) + '%';
    });
    pie.style.background = 'conic-gradient(' + parts.join(', ') + ')';
    stages.forEach(legendRow);
  }

  function load() {
    var query = select.value ? '?location=' + encodeURIComponent(select.value) : '';
    fetch('progress_data.php' + query, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('unavailable');
        return r.json();
      })
      .then(render)
      .catch(function () {
        pie.style.background = '#edf2f6';
        centerNum.textContent = '--';
        emptyMsg.hidden = false;
        emptyMsg.textContent = 'Progress data is unavailable.';
      });
  }

  select.addEventListener('change', load);
  load();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('load', function () {
    if (!document.querySelector('.encoding-progress')) boot();
  });
})();

