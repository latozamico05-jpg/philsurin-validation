document.addEventListener('DOMContentLoaded', function () {
  var headers = document.querySelectorAll('.report thead tr:first-child .stage');
  if (!headers.length) return;
  var location = new URLSearchParams(window.location.search).get('location');
  var endpoint = 'report_data.php' + (location ? '?location=' + encodeURIComponent(location) : '');
  fetch(endpoint)
    .then(function (response) { return response.json(); })
    .then(function (data) {
      headers.forEach(function (header) {
        var key = header.dataset.stage || '';
        var value = data[key] || {};
        var details = document.createElement('span');
        details.className = 'small';
        details.textContent = value.size
          ? 'SIZE OF THE MAP: ' + value.size + ' / DATE: ' + (value.date || '')
          : 'NO MAP SIZE ENCODED';
        header.replaceChildren(document.createTextNode(key), document.createElement('br'), details);
        header.style.color = value.size ? '#087f59' : '#c62828';
        header.style.background = value.size ? '#f2fff8' : '#fff0f0';
      });
    })
    .catch(function () {});
});
