document.addEventListener('DOMContentLoaded', function () {
  var stageNames = ['01 GRADING', '02 GRADING', '03 GRADING', '04 GRADING', 'HARVEST'];
  var location = new URLSearchParams(window.location.search).get('location');
  var headers = document.querySelectorAll('.report thead tr:first-child .stage');
  if (!location || !headers.length) return;

  var normalize = function (value) {
    return String(value || '').replace(/_/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
  };

  fetch('report_data.php?location=' + encodeURIComponent(location))
    .then(function (response) { return response.json(); })
    .then(function (data) {
      stageNames.forEach(function (key, index) {
        if (!headers[index]) return;
        var value = data[key] || data[normalize(key)] || data[key.replace(/\s+/g, '_')];
        if (value && value.size) {
          headers[index].innerHTML = key + '<br><span class="small">SIZE OF THE MAP: ' + value.size + ' / DATE: ' + value.date + '</span>';
          headers[index].style.color = '#087f59';
          headers[index].style.background = '#f2fff8';
        } else {
          headers[index].innerHTML = key + '<br><span class="small">NO MAP SIZE ENCODED</span>';
          headers[index].style.color = '#c62828';
          headers[index].style.background = '#fff0f0';
        }
      });
    })
    .catch(function () {});
});
