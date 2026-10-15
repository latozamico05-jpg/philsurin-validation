document.addEventListener('DOMContentLoaded', function () {
  var table = document.querySelector('.report');
  if (!table) return;

  // The first four stages have five fields. HARVEST has only Observations.
  var firstHeader = table.querySelector('thead tr:first-child');
  var stageHeaders = table.querySelectorAll('thead tr:first-child .stage');
  var harvestHeader = stageHeaders[stageHeaders.length - 1];
  if (harvestHeader) harvestHeader.colSpan = 1;

  var secondHeader = table.querySelectorAll('thead tr:nth-child(2) th');
  if (secondHeader.length) {
    var harvestDescription = secondHeader[secondHeader.length - 1];
    harvestDescription.colSpan = 1;
    harvestDescription.textContent = 'Observations';
  }

  var thirdHeader = table.querySelector('thead tr:nth-child(3)');
  if (thirdHeader) {
    var headerCells = Array.from(thirdHeader.children);
    headerCells.slice(21).forEach(function (cell) { cell.remove(); });
    if (headerCells[20]) headerCells[20].textContent = 'Observations';
  }

  table.querySelectorAll('tbody tr').forEach(function (row) {
    var cells = Array.from(row.children);
    var harvestStart = cells.length >= 27 ? 22 : 21;
    if (cells.length < harvestStart + 1) return;
    cells[harvestStart].colSpan = 1;
    cells.slice(harvestStart + 1).forEach(function (cell) { cell.remove(); });
  });

  fetch('report_data.php' + window.location.search)
    .then(function (response) { return response.json(); })
    .then(function (data) {
      Array.from(table.querySelectorAll('thead tr:first-child .stage')).forEach(function (heading) {
        var key = heading.textContent.trim().split(/\s+SIZE/)[0];
        var value = data[key];
        if (value && value.size) {
          heading.innerHTML = key + '<br><span class="small">SIZE: ' + value.size + ' / DATE: ' + value.date + '</span>';
        }
      });
    })
    .catch(function () {});
});
