const baseButtonClass = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50';

const createElement = (tag, className, text = null) => {
    const element = document.createElement(tag);
    if (className) {
        element.className = className;
    }
    if (text !== null) {
        element.textContent = text;
    }
    return element;
};

let closeActiveDialog = null;

const lockBodyScroll = () => {
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
        document.body.style.overflow = previousOverflow;
    };
};

const openDialog = ({
    type = 'confirm',
    title,
    message,
    confirmLabel,
    cancelLabel = 'Abbrechen',
    danger = false,
    defaultValue = '',
    placeholder = '',
    inputLabel = '',
    multiline = false,
    required = false,
} = {}) => {
    if (typeof document === 'undefined') {
        return Promise.resolve(type === 'prompt' ? null : false);
    }

    if (closeActiveDialog) {
        closeActiveDialog();
    }

    return new Promise((resolve) => {
        const unlockBodyScroll = lockBodyScroll();
        const overlay = createElement(
            'div',
            'fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/50 px-4 py-6 backdrop-blur-sm'
        );
        const panel = createElement(
            'form',
            'w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10'
        );
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');

        const content = createElement('div', 'p-6');
        const header = createElement('div', 'flex items-start gap-4');
        const iconWrap = createElement(
            'div',
            danger
                ? 'flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600'
                : 'flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0B5FFF]/10 text-[#0B5FFF]'
        );
        const icon = createElement('i', danger ? 'la la-exclamation-triangle text-xl' : 'la la-info-circle text-xl');
        icon.setAttribute('aria-hidden', 'true');
        iconWrap.appendChild(icon);

        const copy = createElement('div', 'min-w-0 flex-1');
        const heading = createElement('h2', 'text-lg font-semibold text-slate-950', title || (type === 'prompt' ? 'Eingabe erforderlich' : 'Bitte bestätigen'));
        copy.appendChild(heading);

        if (message) {
            const messageElement = createElement('p', 'mt-2 whitespace-pre-line text-sm leading-6 text-slate-600', message);
            messageElement.style.color = '#475569';
            copy.appendChild(messageElement);
        }

        let input = null;
        if (type === 'prompt') {
            const fieldWrap = createElement('div', 'mt-5');
            if (inputLabel) {
                const label = createElement('label', 'mb-2 block text-sm font-medium text-slate-700', inputLabel);
                label.setAttribute('for', 'airmius-dialog-input');
                fieldWrap.appendChild(label);
            }

            input = document.createElement(multiline ? 'textarea' : 'input');
            input.id = 'airmius-dialog-input';
            input.className = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#0B5FFF] focus:ring-4 focus:ring-[#0B5FFF]/10';
            input.placeholder = placeholder;
            input.value = defaultValue ?? '';
            if (!multiline) {
                input.type = 'text';
            } else {
                input.rows = 4;
            }
            fieldWrap.appendChild(input);
            copy.appendChild(fieldWrap);
        }

        header.appendChild(iconWrap);
        header.appendChild(copy);
        content.appendChild(header);

        const footer = createElement('div', 'flex flex-col-reverse gap-3 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end');
        const cancelButton = createElement(
            'button',
            `${baseButtonClass} border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 focus:ring-slate-300`,
            cancelLabel
        );
        cancelButton.type = 'button';

        const confirmButton = createElement(
            'button',
            danger
                ? `${baseButtonClass} bg-red-600 text-white hover:bg-red-700 focus:ring-red-500`
                : `${baseButtonClass} bg-[#0B5FFF] text-white hover:bg-[#094bd1] focus:ring-[#0B5FFF]`,
            confirmLabel || (type === 'prompt' ? 'Übernehmen' : 'Bestätigen')
        );
        confirmButton.type = 'submit';

        footer.appendChild(cancelButton);
        footer.appendChild(confirmButton);
        panel.appendChild(content);
        panel.appendChild(footer);
        overlay.appendChild(panel);

        const finish = (value) => {
            window.removeEventListener('keydown', onKeyDown);
            closeActiveDialog = null;
            overlay.remove();
            unlockBodyScroll();
            resolve(value);
        };

        const updateSubmitState = () => {
            if (!input || !required) {
                return;
            }
            confirmButton.disabled = input.value.trim().length === 0;
        };

        const submit = () => {
            if (type === 'prompt') {
                if (required && !input.value.trim()) {
                    input.focus();
                    return;
                }
                finish(input.value);
                return;
            }

            finish(true);
        };

        const cancel = () => finish(type === 'prompt' ? null : false);

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                cancel();
            }
        }

        panel.addEventListener('submit', (event) => {
            event.preventDefault();
            submit();
        });
        cancelButton.addEventListener('click', cancel);
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                cancel();
            }
        });
        input?.addEventListener('input', updateSubmitState);
        window.addEventListener('keydown', onKeyDown);

        closeActiveDialog = cancel;
        document.body.appendChild(overlay);
        updateSubmitState();

        window.setTimeout(() => {
            (input || confirmButton).focus();
            if (input) {
                input.select();
            }
        }, 0);
    });
};

export const confirmDialog = (options = {}) => openDialog({ ...options, type: 'confirm' });

export const promptDialog = (options = {}) => openDialog({ ...options, type: 'prompt' });

