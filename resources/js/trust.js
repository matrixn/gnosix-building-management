const invitation = document.querySelector('[data-trust-invitation]');
if (invitation && /^#[a-f0-9]{64}$/.test(window.location.hash)) {
    invitation.elements.invitation_token.value = window.location.hash.slice(1);
    history.replaceState(null, '', window.location.pathname + window.location.search);
}

const link = document.querySelector('[data-trust-link]');
const copy = document.querySelector('[data-trust-copy]');
if (link && copy && navigator.clipboard) {
    copy.hidden = false;
    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(link.value);
            document.querySelector('[data-trust-copy-status]').textContent = copy.dataset.copied;
        } catch {
            link.focus();
            link.select();
        }
    });
}
