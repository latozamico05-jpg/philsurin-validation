(function () {
  // config.php injects this file for index.php AND index.php adds its own
  // <script> tag (needed when a POST render skips the config asset echo).
  // Running twice made the second instance capture() freshly-rendered empty
  // inputs and restore() them over the first instance's drafts, so Replicate
  // 2-4 values vanished on every stage switch. Execute only once per page.
  if (window.__philsurinValidationFormLoaded) return;
  window.__philsurinValidationFormLoaded = true;
  const form = document.querySelector('form[method="post"]');
  const stageInput = document.getElementById('stage');
  const stageButtons = Array.from(document.querySelectorAll('.stage[data-stage]'));
  if (!form || !stageInput || stageButtons.length === 0) return;

  const status = document.createElement('div');
  status.className = 'save-status';
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  status.hidden = true;
  const style = document.createElement('style');
  style.textContent = '.save-status{margin:12px 0;padding:12px 14px;border-radius:6px;font-size:12px;font-weight:700}.save-status.waiting{background:#e8f3ee;color:#166747}.save-status.success{background:#e5f5eb;color:#176b3a}.save-status.failure{background:#fff0ef;color:#a3231d}.save-progress{height:5px;margin-top:8px;overflow:hidden;background:rgba(0,0,0,.1)}.save-progress:after{content:"";display:block;width:35%;height:100%;background:#159465;animation:save-progress 1s ease-in-out infinite alternate}@keyframes save-progress{from{transform:translateX(-20%)}to{transform:translateX(220%)}}';
  document.head.appendChild(style);
  form.parentElement.insertBefore(status, form);
  form.addEventListener('submit', function (event) {
    if (!event.submitter || event.submitter.name !== 'save_validation') return;
    status.className = 'save-status waiting';
    status.innerHTML = 'Please wait. Validation is being saved.<div class="save-progress" aria-hidden="true"></div>';
    status.hidden = false;
  });
  const params = new URLSearchParams(window.location.search);
  const formError = document.querySelector('.error');
  if (params.get('saved') === '1') {
    status.className = 'save-status success';
    status.textContent = 'Validation saved successfully. Saved values are loaded below.';
    status.hidden = false;
  } else if (formError) {
    status.className = 'save-status failure';
    status.textContent = formError.textContent;
    status.hidden = false;
  }

  const drafts = Object.create(null);
  const setupDrafts = Object.create(null);
  const sizeInput = form.elements.namedItem('location_size');
  const sizeLabel = sizeInput ? sizeInput.closest('label') : null;
  const dateInput = form.elements.namedItem('grading_date');
  const defaultDate = dateInput ? dateInput.value : '';
  let restoring = false;

  function showSizeRequired() {
    status.className = 'save-status failure';
    status.textContent = 'Size of Location is required for every grading, including Harvest. Enter it before saving.';
    status.hidden = false;
  }

  if (sizeInput) {
    sizeInput.required = true;
    sizeInput.addEventListener('invalid', function (event) {
      event.preventDefault();
      showSizeRequired();
    }, true);
    sizeInput.addEventListener('input', function () {
      if (sizeInput.value.trim() && status.textContent.includes('Size of Location is required')) {
        status.hidden = true;
      }
    });
  }

  function capture(stage) {
    setupDrafts[stage] = {
      location_size: sizeInput ? sizeInput.value : '',
      grading_date: dateInput ? dateInput.value : ''
    };
    const values = {};
    document.querySelectorAll('.stage-fields').forEach(function (box) {
      const replicate = box.dataset.rep;
      values[replicate] = {};
      box.querySelectorAll('[name]').forEach(function (input) {
        const key = input.name.match(/^field_([a-e])\[/)?.[1] || (input.name.startsWith('remarks[') ? 'remarks' : null);
        if (key) values[replicate][key] = input.value;
      });
    });
    drafts[stage] = values;
  }

  function restore(values) {
    Object.keys(values || {}).forEach(function (replicate) {
      const box = document.querySelector('.stage-fields[data-rep="' + replicate + '"]');
      if (!box) return;
      Object.keys(values[replicate] || {}).forEach(function (key) {
        const fieldName = key === 'remarks' ? 'remarks[' + replicate + ']' : 'field_' + key + '[' + replicate + ']';
        const input = box.querySelector('[name="' + fieldName + '"]');
        if (input) input.value = values[replicate][key] ?? '';
      });
    });
  }

  function restoreSetup(stage) {
    const setup = setupDrafts[stage] || {};
    if (sizeInput) {
      sizeInput.value = setup.location_size || '';
      sizeInput.required = true;
    }
    if (sizeLabel) sizeLabel.hidden = false;
    if (dateInput) dateInput.value = setup.grading_date || defaultDate;
  }

  // Field layout for every grading. Must mirror grading_functions.php:stage_fields()
  // so what the encoder types lands in the right database column.
  const STAGE_DEFINITIONS = {
    '01 GRADING': [['Germination', 'a', 'number'], ['E-S', 'b', 'number'], ['G-S', 'c', 'number'], ['Remarks', 'remarks', 'textarea']],
    '02 GRADING': [['Population', 'a', 'number'], ['Height', 'b', 'number'], ['Size', 'c', 'number'], ['Uniformity', 'd', 'number'], ['CS', 'e', 'text'], ['Remarks', 'remarks', 'textarea']],
    '03 GRADING': [['Population', 'a', 'number'], ['Height', 'b', 'number'], ['Size', 'c', 'number'], ['Uniformity', 'd', 'number'], ['CS', 'e', 'text'], ['Remarks', 'remarks', 'textarea']],
    '04 GRADING': [['Population', 'a', 'number'], ['Height', 'b', 'number'], ['Size', 'c', 'number'], ['Uniformity', 'd', 'number'], ['CS', 'e', 'text'], ['Remarks', 'remarks', 'textarea']],
    'HARVEST': [['Observations', 'remarks', 'textarea']]
  };

  function defaultDefinition(stage) {
    return String(stage).toUpperCase().includes('HARVEST')
      ? [['Observations', 'remarks', 'textarea']]
      : [['Population', 'a', 'number'], ['Height', 'b', 'number'], ['Size', 'c', 'number'], ['Uniformity', 'd', 'number'], ['CS', 'e', 'text'], ['Remarks', 'remarks', 'textarea']];
  }

  function definitionFor(stage) {
    const key = String(stage).trim().replace(/\s+/g, ' ').toUpperCase();
    if (STAGE_DEFINITIONS[key]) return STAGE_DEFINITIONS[key];
    return defaultDefinition(stage);
  }

  function renderCustomStage(stage) {
    stageInput.value = stage;
    const fields = definitionFor(stage);
    document.querySelectorAll('.stage-fields').forEach(function (box) {
      const replicate = box.dataset.rep;
      // Must produce byte-identical markup to the PHP loop in index.php, which now
      // renders these same inputs server-side (so the form works without JS).
      box.innerHTML = fields.map(function (field) {
        const name = field[1] === 'remarks' ? 'remarks' : 'field_' + field[1];
        const tag = field[2] === 'textarea' ? 'textarea' : 'input';
        const type = tag === 'input' ? ' type="' + field[2] + '"' : '';
        const step = field[2] === 'number' ? ' step="any"' : '';
        return '<label>' + field[0] + '<' + tag + ' name="' + name + '[' + replicate + ']"' + type + step + '></' + tag + '></label>';
      }).join('');
      const caption = box.closest('article').querySelector('small');
      if (caption) caption.textContent = 'REP ' + replicate + ' - ' + stage;
    });
  }

  function addCustomStage(stage) {
    const normalized = stage.trim().replace(/\s+/g, ' ').toUpperCase();
    if (!normalized || stageButtons.some(function (button) { return button.dataset.stage.toUpperCase() === normalized; })) return;
    const container = document.querySelector('.stages');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'stage';
    button.dataset.stage = normalized;
    button.textContent = normalized;
    button.addEventListener('click', function () { activate(button); });
    container.insertBefore(button, container.querySelector('#customStageInput') || null);
    stageButtons.push(button);
  }

  fetch('validation_data.php?stages=1', { credentials: 'same-origin' })
    .then(function (response) { return response.ok ? response.json() : []; })
    .then(function (stages) {
      stages.forEach(function (stage) { addCustomStage(stage); });
    })
    .catch(function (error) { console.error(error); });

  // Render the active stage immediately. Without this the four replicate boxes
  // stay empty until a stage is clicked, which is why the form looked blank.
  function activate(stageButton) {
    const stage = stageButton.dataset.stage;
    const previousStage = stageInput.value;
    if (!restoring) capture(previousStage);
    document.querySelectorAll('.stage').forEach(function (item) { item.classList.remove('active'); });
    stageButton.classList.add('active');
    renderCustomStage(stage);
    // Restore the PREVIOUS stage's captured drafts so the replication inputs
    // keep the values the user typed before switching. Using `stage` here would
    // read an empty/unpopulated drafts bucket and write [object Object] into
    // every field, which is the root cause of the encoding corruption.
    window.setTimeout(function () {
      restore(drafts[previousStage]);
      restoreSetup(previousStage);
    }, 0);
  }

  stageButtons.forEach(function (button) {
    button.addEventListener('click', function () { activate(button); }, true);
  });

  // Paint the first stage on load so the form is usable straight away.
  const initialButton = stageButtons.find(function (button) {
    return button.dataset.stage.trim().toUpperCase() === (stageInput.value || '').trim().toUpperCase();
  }) || stageButtons[0];
  if (initialButton) {
    document.querySelectorAll('.stage').forEach(function (item) { item.classList.remove('active'); });
    initialButton.classList.add('active');
    renderCustomStage(initialButton.dataset.stage);
  }

  const location = params.get('location');
  const stage = params.get('stage');
  const varietyId = params.get('variety_id');
  if (!location || !stage || !varietyId) return;

  const endpoint = new URL('validation_data.php', window.location.href);
  endpoint.search = new URLSearchParams({ location: location, stage: stage, variety_id: varietyId });
  restoring = true;
  fetch(endpoint, { credentials: 'same-origin' })
    .then(function (response) {
      if (!response.ok) throw new Error('Unable to load saved validation.');
      return response.json();
    })
    .then(function (data) {
      const meta = data.meta;
      if (!meta) return;
      const assign = function (name, value) {
        const input = form.elements.namedItem(name);
        if (input && value != null) {
          input.value = value;
          if (name === 'variety_id') input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      };
      assign('location_id', meta.location_id);
      assign('project_location', meta.project_location);
      assign('date_planted', meta.date_planted);
      assign('dr_dp', meta.dr_dp);
      assign('control_name', meta.control_name);
      assign('location_size', meta.location_size);
      assign('grading_date', meta.grading_date);
      assign('variety_id', varietyId);
      setupDrafts[stage] = { location_size: meta.location_size || '', grading_date: meta.grading_date || '' };
      addCustomStage(stage);
      const stageButton = stageButtons.find(function (button) {
        return button.dataset.stage.trim().toUpperCase() === stage.trim().toUpperCase();
      });
      if (stageButton) stageButton.click();
      drafts[stage] = data.replicates || {};
      window.setTimeout(function () {
        restore(drafts[stage]);
        restoreSetup(stage);
        restoring = false;
      }, 0);
    })
    .catch(function (error) {
      console.error(error);
      restoring = false;
    });
})();
