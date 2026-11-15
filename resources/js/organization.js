document.querySelectorAll('[data-profile-settings]').forEach(form => {
    const profile = form.querySelector('[name=profile_key]');
    const kind = form.querySelector('[name=organization_kind]');
    function syncKinds() {
        [...kind.options].forEach(option => {option.disabled = !option.dataset.kindProfiles.split(' ').includes(profile.value); option.hidden = option.disabled;});
        if (kind.selectedOptions[0]?.disabled) kind.value = [...kind.options].find(option => !option.disabled)?.value ?? '';
    }
    profile.addEventListener('change', syncKinds);
    syncKinds();
});
const wizard = document.querySelector('[data-organization-wizard]');
if (wizard) {
    const kinds = wizard.querySelector('[data-kind-choices]');
    const name = wizard.querySelector('[data-organization-name]');
    const status = wizard.querySelector('[data-draft-status]');
    const version = wizard.querySelector('[name=version]');
    const complete = wizard.querySelector('[data-complete-organization]');
    let queue = Promise.resolve(true), timer, submitting = false, changes = 0;
    function sync() {
        const profile = wizard.querySelector('[name=profile_key]:checked');
        kinds.querySelectorAll('[data-kind-profiles]').forEach(label => {
            const enabled = !!profile && label.dataset.kindProfiles.split(' ').includes(profile.value);
            label.hidden = !enabled;
            const input = label.querySelector('input');
            input.disabled = !enabled;
            if (!enabled) input.checked = false;
        });
        const kind = wizard.querySelector('[name=organization_kind]:checked');
        kinds.hidden = !profile;
        name.hidden = !profile || !kind;
        complete.hidden = !profile || !kind;
    }
    function persist() {
        queue = queue.then(async () => {
            const revision = changes;
            status.textContent = wizard.dataset.saving;
            status.classList.remove('is-error');
            try {
                const response = await fetch(wizard.dataset.draftUrl, {method:'POST', body:new FormData(wizard), headers:{'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
                const result = await response.json();
                if (!response.ok) {
                    status.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : wizard.dataset.failed;
                    status.classList.add('is-error');
                    return false;
                }
                version.value = result.version;
                status.textContent = revision === changes ? wizard.dataset.saved : wizard.dataset.saving;
                return true;
            } catch {
                status.textContent = wizard.dataset.failed;
                status.classList.add('is-error');
                return false;
            }
        });
        return queue;
    }
    wizard.querySelectorAll('input[type=radio]').forEach(input => input.addEventListener('change', () => {changes++; sync(); clearTimeout(timer); persist();}));
    wizard.querySelector('input[name=name]').addEventListener('input', () => {changes++; status.textContent=wizard.dataset.saving; clearTimeout(timer); timer=setTimeout(persist,700);});
    wizard.addEventListener('submit', async event => {
        if (submitting) return;
        event.preventDefault();
        clearTimeout(timer);
        const saveOnly = event.submitter?.hasAttribute('data-save-draft');
        complete.disabled = true;
        const saved = await persist();
        complete.disabled = false;
        if (!saved || saveOnly) return;
        submitting = true;
        wizard.requestSubmit(complete);
    });
    sync();
}
