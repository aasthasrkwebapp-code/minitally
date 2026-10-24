// Mobile menu, confirm dialogs, dynamic entry rows and PWA install.
document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-toggle]');
    if (toggle) {
        document.getElementById(toggle.dataset.toggle)?.classList.toggle('hidden');
    }

    const confirmEl = e.target.closest('[data-confirm]');
    if (confirmEl && !confirm(confirmEl.dataset.confirm)) {
        e.preventDefault();
        e.stopImmediatePropagation();
    }

    const addRow = e.target.closest('[data-add-row]');
    if (addRow) {
        const tbody = document.getElementById(addRow.dataset.addRow);
        const template = document.getElementById(addRow.dataset.template);
        const index = tbody.querySelectorAll('[data-row]').length;
        tbody.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__i__', index).replaceAll('__n__', index + 1));
        tbody.lastElementChild.querySelector('select, input')?.focus();
    }

    const removeRow = e.target.closest('[data-remove-row]');
    if (removeRow) {
        removeRow.closest('[data-row]').remove();
    }

    // Sending a WhatsApp link marks the row as sent so the list shows progress
    const wa = e.target.closest('[data-wa-sent]');
    if (wa) {
        setTimeout(() => {
            wa.closest('[data-wa-row]')?.classList.add('opacity-60');
            const s = wa.closest('[data-wa-row]')?.querySelector('[data-wa-status]');
            if (s) s.textContent = 'Opened';
        }, 300);
    }
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
        e.preventDefault();
        return;
    }
    const btn = form.querySelector('[data-busy]');
    if (btn) {
        btn.disabled = true;
        btn.dataset.label = btn.textContent;
        btn.textContent = btn.dataset.busy;
    }
});

document.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
    if (e.target.matches('[data-check-all]')) {
        document.querySelectorAll(e.target.dataset.checkAll).forEach((c) => (c.checked = e.target.checked));
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    document.querySelectorAll('[data-install]').forEach((b) => b.classList.remove('hidden'));
});
document.addEventListener('click', async (e) => {
    if (!e.target.closest('[data-install]') || !deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    document.querySelectorAll('[data-install]').forEach((b) => b.classList.add('hidden'));
});
