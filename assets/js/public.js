(function () {
  const toast = document.getElementById('login-toast');
  const lockedFeatures = document.querySelectorAll('.locked-feature');

  function showLoginMessage(featureName) {
    if (!toast) return;
    toast.innerHTML = `You need to login to access <strong>${featureName}</strong>. <a href="login.php">Login now</a>`;
    toast.classList.add('show');
    window.clearTimeout(showLoginMessage.timeoutId);
    showLoginMessage.timeoutId = window.setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  lockedFeatures.forEach((card) => {
    const featureName = card.getAttribute('data-feature') || 'this feature';

    card.addEventListener('click', () => showLoginMessage(featureName));
    card.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        showLoginMessage(featureName);
      }
    });
  });
})();
