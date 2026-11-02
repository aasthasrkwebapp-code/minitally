// Mobile menu, confirm dialogs, dynamic entry rows, password reveal and PWA install.
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

    // Show/hide a password field, or copy it
    const reveal = e.target.closest('[data-reveal]');
    if (reveal) {
        const input = document.getElementById(reveal.dataset.reveal);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        reveal.textContent = show ? 'Hide' : 'Show';
    }
    const copy = e.target.closest('[data-copy]');
    if (copy) {
        const input = document.getElementById(copy.dataset.copy);
        navigator.clipboard?.writeText(input.value).then(() => {
            copy.textContent = 'Copied';
            setTimeout(() => (copy.textContent = 'Copy'), 1500);
        });
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

// Escape closes the slide-in menu on phones and tablets
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.getElementById('mobile-menu')?.classList.add('hidden');
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

// Daily entries: fill Amount from the exporter's accepted quotation (certificate + IR/NA + sets).
// A typed amount is never overwritten; only an empty or earlier auto-filled one.
const entryField = (box, f) => box.querySelector(`[name="${f}"], [name$="[${f}]"]`);
const fillQuotedRate = async (box) => {
    const amount = entryField(box, 'amount');
    const hint = box.querySelector('[data-quote-hint]');
    if (!amount || (amount.value !== '' && amount.dataset.auto !== '1')) return;
    const params = new URLSearchParams();
    ['party_name', 'mail_by', 'client_id', 'certificate', 'ir_na', 'sets'].forEach((f) => {
        const el = entryField(box, f);
        if (el) params.set(f, el.value);
    });
    params.set('issued_date', document.querySelector('[name="issued_date"]')?.value || '');
    if (!params.get('certificate')) return;
    try {
        const res = await fetch(`${box.dataset.rateUrl}?${params}`, { headers: { Accept: 'application/json' } });
        const quote = res.ok ? await res.json() : null;
        if (quote) {
            amount.value = quote.rate;
            amount.dataset.auto = '1';
            if (hint) {
                hint.textContent = `From quotation ${quote.number}`;
                hint.classList.remove('hidden');
            }
        } else if (amount.dataset.auto === '1') {
            amount.value = '';
            delete amount.dataset.auto;
            hint?.classList.add('hidden');
        }
    } catch {}
};
document.addEventListener('change', (e) => {
    const box = e.target.closest('[data-entry-fields]');
    if (!box) return;
    if (e.target === entryField(box, 'amount')) {
        delete e.target.dataset.auto;
        box.querySelector('[data-quote-hint]')?.classList.add('hidden');
        return;
    }
    fillQuotedRate(box);
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
