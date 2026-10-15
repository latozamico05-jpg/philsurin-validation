(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[method="post"]');
    if (!form) return;

    const locationInput = form.elements.namedItem('location_id');
    const projectInput = form.elements.namedItem('project_location');
    const plantedInput = form.elements.namedItem('date_planted');
    const drInput = form.elements.namedItem('dr_dp');
    const controlInput = form.elements.namedItem('control_name');
    const varietySelect = form.elements.namedItem('variety_id');
    if (!locationInput || !projectInput || !plantedInput || !drInput || !controlInput) return;

    const hasLocation = new URLSearchParams(window.location.search).has('location');
    locationInput.readOnly = true;
    locationInput.setAttribute('aria-live', 'polite');
    if (!hasLocation) {
      projectInput.value = '';
      projectInput.placeholder = 'Enter Location of Project';
      controlInput.value = '';
      controlInput.placeholder = 'Enter Check / Control';
    }
    let updateNumber = 0;

    async function updateLocationId() {
      const project = projectInput.value.trim();
      if (!project) {
        locationInput.value = 'Enter Location of Project';
        return;
      }

      const currentUpdate = ++updateNumber;
      const source = [project, plantedInput.value, drInput.value, controlInput.value].join('|').trim();
      try {
        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(source));
        if (currentUpdate !== updateNumber) return;
        const hash = Array.from(new Uint8Array(digest), function (byte) {
          return byte.toString(16).padStart(2, '0');
        }).join('');
        locationInput.value = 'LOC-' + hash.slice(0, 12).toUpperCase();
      } catch (error) {
        locationInput.value = 'Location ID preview unavailable';
      }
    }

    [projectInput, plantedInput, drInput, controlInput].forEach(function (input) {
      input.addEventListener('input', updateLocationId);
      input.addEventListener('change', updateLocationId);
    });

    function updateReplicateVarieties() {
      if (!varietySelect) return;
      const selected = varietySelect.options[varietySelect.selectedIndex];
      const varietyName = selected && selected.value ? selected.textContent.trim() : 'Select Variety above';
      document.querySelectorAll('.replicates article').forEach(function (replicate) {
        const display = replicate.querySelector('input[disabled]');
        if (display) display.value = varietyName;
      });
    }

    if (varietySelect) {
      varietySelect.addEventListener('change', updateReplicateVarieties);
      updateReplicateVarieties();
    }

    if (!hasLocation) {
      updateLocationId();
    }
  });
})();
