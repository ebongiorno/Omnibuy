(() => {
  const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
  const primaryNav = document.getElementById('primary-nav');

  if (mobileToggle && primaryNav) {
    mobileToggle.addEventListener('click', () => {
      const isOpen = primaryNav.classList.toggle('is-open');
      mobileToggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  const accountMenu = document.querySelector('[data-account-menu]');
  if (!accountMenu) return;

  const trigger = accountMenu.querySelector('.account-menu__trigger');
  const panel = accountMenu.querySelector('.account-menu__panel');

  const closeMenu = () => {
    panel.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
  };

  trigger.addEventListener('click', () => {
    const willOpen = panel.hidden;
    panel.hidden = !willOpen;
    trigger.setAttribute('aria-expanded', String(willOpen));
  });

  document.addEventListener('click', (event) => {
    if (!accountMenu.contains(event.target)) closeMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
      trigger.focus();
    }
  });
})();
