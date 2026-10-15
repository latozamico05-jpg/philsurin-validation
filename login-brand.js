document.addEventListener('DOMContentLoaded', function () {
  const login = document.querySelector('main.login');
  if (!login) return;

  login.querySelector('h1')?.remove();
  login.querySelector('p')?.remove();

  // Transparent brand seal at a normal, moderate size.
  const mark = document.createElement('img');
  mark.className = 'login-seal';
  mark.src = 'assets/philsurin-seal.png';
  mark.alt = 'PHILSURIN seal';
  const brand = document.createElement('div');
  brand.className = 'login-brand-lockup';
  const wordmark = document.createElement('h1');
  wordmark.className = 'login-wordmark';
  wordmark.textContent = 'PHILSURIN';
  brand.append(mark, wordmark);
  login.insertBefore(brand, login.firstChild);
});
