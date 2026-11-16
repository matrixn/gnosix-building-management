if(document.querySelector('[data-asset-studio]')) import('./assets.js');
if(document.querySelector('[data-evidence-studio]')) import('./evidence.js');
import './building-library.js';
if (document.querySelector('[data-building-studio], [data-building-dashboard]')) import('./building-studio.js');
import './organization.js';
import './studio.js';
document.documentElement.classList.add('identity-js');

document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = document.getElementById(button.dataset.passwordToggle);
    button.hidden = false;
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.textContent = visible ? button.dataset.hide : button.dataset.show;
    });
});

document.querySelectorAll('[data-submit-form]').forEach(form => {
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        const button = form.querySelector('[data-loading-label]');
        if (!button) return;
        button.dataset.originalLabel = button.querySelector('span').textContent;
        button.querySelector('span').textContent = button.dataset.loadingLabel;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
    });
});

// Back/forward cache can restore the form after a completed navigation.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-original-label]').forEach(button => {
        button.disabled = false;
        button.querySelector('span').textContent = button.dataset.originalLabel;
        button.closest('form').removeAttribute('aria-busy');
    });
});

const onboarding = document.querySelector('[data-onboarding]');
if (onboarding) {
    const intro = onboarding.querySelector('[data-onboarding-intro]');
    const selection = onboarding.querySelector('[data-profile-selection]');
    const button = onboarding.querySelector('[data-intro-continue]');
    const reveal = () => {
        intro.hidden = true;
        selection.hidden = false;
        onboarding.querySelector('[data-welcome-step]').removeAttribute('aria-current');
        onboarding.querySelector('[data-profile-step]').setAttribute('aria-current', 'step');
        document.getElementById('profile-heading').focus();
    };
    button.hidden = false;
    selection.hidden = true;
    button.addEventListener('click', reveal);
    if (onboarding.hasAttribute('data-onboarding-errors')) reveal();
}

document.querySelector('[data-validation-summary]')?.focus({preventScroll:true});

const mobileNavigation = document.querySelector('[data-mobile-navigation]');
if (mobileNavigation) {
    const menu = document.getElementById(mobileNavigation.getAttribute('aria-controls'));
    mobileNavigation.addEventListener('click', () => {
        const expanded = mobileNavigation.getAttribute('aria-expanded') !== 'true';
        mobileNavigation.setAttribute('aria-expanded', String(expanded));
        menu.classList.toggle('is-open', expanded);
    });
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            mobileNavigation.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
            mobileNavigation.focus();
        }
    });
}

if(document.querySelector('[data-maintenance]')) import('./maintenance.js');
