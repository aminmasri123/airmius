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
let dialogSequence = 0;

const dialogCopy = {
    de: {
        cancel: 'Abbrechen',
        confirm: 'Bestätigen',
        promptConfirm: 'Übernehmen',
        promptTitle: 'Eingabe erforderlich',
        confirmTitle: 'Bitte bestätigen',
        input: 'Eingabe',
    },
    en: {
        cancel: 'Cancel',
        confirm: 'Confirm',
        promptConfirm: 'Apply',
        promptTitle: 'Input required',
        confirmTitle: 'Please confirm',
        input: 'Input',
    },
    fr: {
        cancel: 'Annuler',
        confirm: 'Confirmer',
        promptConfirm: 'Appliquer',
        promptTitle: 'Saisie requise',
        confirmTitle: 'Veuillez confirmer',
        input: 'Saisie',
    },
    ar: {
        cancel: 'إلغاء',
        confirm: 'تأكيد',
        promptConfirm: 'تطبيق',
        promptTitle: 'الإدخال مطلوب',
        confirmTitle: 'يرجى التأكيد',
        input: 'إدخال',
    },
};

const localizedCopy = () => {
    const language = String(document.documentElement.lang || 'de').toLowerCase().split('-')[0];

    return dialogCopy[language] || dialogCopy.de;
};

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
    cancelLabel,
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
        const copyText = localizedCopy();
        const dialogId = `airmius-dialog-${++dialogSequence}`;
        const titleId = `${dialogId}-title`;
        const messageId = `${dialogId}-description`;
        const inputId = `${dialogId}-input`;
        const previouslyFocused = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        let finished = false;
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
        panel.setAttribute('aria-labelledby', titleId);
        panel.tabIndex = -1;

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
        const heading = createElement('h2', 'text-lg font-semibold text-slate-950', title || (type === 'prompt' ? copyText.promptTitle : copyText.confirmTitle));
        heading.id = titleId;
        copy.appendChild(heading);

        if (message) {
            const messageElement = createElement('p', 'mt-2 whitespace-pre-line text-sm leading-6 text-slate-600', message);
            messageElement.id = messageId;
            messageElement.style.color = '#475569';
            panel.setAttribute('aria-describedby', messageId);
            copy.appendChild(messageElement);
        }

        let input = null;
        if (type === 'prompt') {
            const fieldWrap = createElement('div', 'mt-5');
            if (inputLabel) {
                const label = createElement('label', 'mb-2 block text-sm font-medium text-slate-700', inputLabel);
                label.setAttribute('for', inputId);
                fieldWrap.appendChild(label);
            }

            input = document.createElement(multiline ? 'textarea' : 'input');
            input.id = inputId;
            input.className = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#0B5FFF] focus:ring-4 focus:ring-[#0B5FFF]/10';
            input.placeholder = placeholder;
            input.value = defaultValue ?? '';
            input.required = required;
            input.setAttribute('aria-required', String(required));
            if (!inputLabel) {
                input.setAttribute('aria-label', placeholder || copyText.input);
            }
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
            cancelLabel || copyText.cancel
        );
        cancelButton.type = 'button';

        const confirmButton = createElement(
            'button',
            danger
                ? `${baseButtonClass} bg-red-600 text-white hover:bg-red-700 focus:ring-red-500`
                : `${baseButtonClass} bg-[#0B5FFF] text-white hover:bg-[#094bd1] focus:ring-[#0B5FFF]`,
            confirmLabel || (type === 'prompt' ? copyText.promptConfirm : copyText.confirm)
        );
        confirmButton.type = 'submit';

        footer.appendChild(cancelButton);
        footer.appendChild(confirmButton);
        panel.appendChild(content);
        panel.appendChild(footer);
        overlay.appendChild(panel);

        const finish = (value) => {
            if (finished) {
                return;
            }

            finished = true;
            window.removeEventListener('keydown', onKeyDown);
            closeActiveDialog = null;
            overlay.remove();
            unlockBodyScroll();
            if (previouslyFocused instanceof HTMLElement && previouslyFocused.isConnected) {
                window.requestAnimationFrame(() => previouslyFocused.focus());
            }
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
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusableElements = Array.from(panel.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
            )).filter((element) => !element.hasAttribute('hidden'));

            if (!focusableElements.length) {
                event.preventDefault();
                panel.focus();
                return;
            }

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
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
            if (finished || !panel.isConnected) {
                return;
            }

            (input || confirmButton).focus();
            if (input) {
                input.select();
            }
        }, 0);
    });
};

export const confirmDialog = (options = {}) => openDialog({ ...options, type: 'confirm' });

export const promptDialog = (options = {}) => openDialog({ ...options, type: 'prompt' });
