const root = document.documentElement;

const preferredTheme = () => localStorage.getItem('cyber-theme')
    ?? (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');

const applyTheme = (theme) => {
    root.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', theme === 'dark' ? 'Passa al tema chiaro' : 'Passa al tema scuro');
    });
    document.querySelectorAll('[data-theme-label]').forEach((label) => {
        label.textContent = theme === 'dark' ? 'Attiva tema chiaro' : 'Attiva tema scuro';
    });
};

applyTheme(preferredTheme());

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('cyber-theme', theme);
        applyTheme(theme);
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.textContent = show ? 'Nascondi' : 'Mostra';
    });
});

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-character-count]').forEach((input) => {
    const output = document.querySelector(`[data-character-output="${input.id}"]`);
    if (!output) return;
    const update = () => { output.textContent = `${input.value.length} caratteri`; };
    input.addEventListener('input', update);
    update();
});

const revealItems = document.querySelectorAll('[data-reveal], .reveal');
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: .12 });
    revealItems.forEach((item) => observer.observe(item));
} else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
}

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const openMenu = document.querySelector('.navbar-collapse.show');
    if (openMenu && window.bootstrap) window.bootstrap.Collapse.getOrCreateInstance(openMenu).hide();
});
