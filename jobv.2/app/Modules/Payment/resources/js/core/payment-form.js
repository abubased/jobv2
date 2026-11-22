const AUTO_RETURN_DELAY_MS = 1500;

/**
 * Wires the interactive bits that sit outside a single form's own submit
 * cycle: language switching, breaking out of the iframe back to the host
 * page, OTP resend, and submit protection.
 */
export class PaymentFormController {

    #root;
    #translator;

    constructor(root, translator) {
        console.log('APay PaymentFormController loaded');
        this.#root = root;
        this.#translator = translator;

        this.#bindLangSwitcher();
        this.#bindReturnButtons();
        this.#bindAutoReturn();
        this.#bindResend();
        this.#bindSubmitProtection();
    }

    #bindLangSwitcher() {
        const buttons = this.#root.querySelectorAll('[data-apay-lang-btn]');

        buttons.forEach((button) => {
            button.addEventListener('click', () => {

                buttons.forEach((other) =>
                    other.classList.remove('is-active')
                );

                button.classList.add('is-active');

                this.#translator.setLocale(
                    button.dataset.apayLangBtn
                );
            });
        });
    }

    #bindReturnButtons() {
        this.#root
            .querySelectorAll('[data-apay-return]')
            .forEach((button) => {

                button.addEventListener('click', () =>
                    this.#returnToHost(button.dataset.apayReturn)
                );

            });
    }

    #bindAutoReturn() {
        const status = this.#root.dataset.apayPaymentStatus;

        if (!status) {
            return;
        }

        window.setTimeout(
            () => this.#returnToHost(status),
            AUTO_RETURN_DELAY_MS
        );
    }

    #returnToHost(status) {
        const returnUrl = this.#root.dataset.apayReturnUrl;

        if (!returnUrl) {
            return;
        }

        const separator = returnUrl.includes('?') ? '&' : '?';

        window.top.location.href =
            `${returnUrl}${separator}payment_status=${status}`;
    }

    #bindResend() {
        const form = this.#root.querySelector('[data-apay-resend-url]');
        const button = form?.querySelector('[data-apay-resend]');
        const messages = this.#root.querySelector('[data-apay-messages]');

        if (!form || !button) {
            return;
        }

        button.addEventListener('click', async () => {

            button.disabled = true;

            try {

                const response = await fetch(
                    form.dataset.apayResendUrl,
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN':
                                form.querySelector(
                                    'input[name="_token"]'
                                )?.value ?? '',

                            Accept: 'application/json',
                        },
                    }
                );

                const body = await response.json();

                this.#showMessage(
                    messages,
                    body.message,
                    response.ok ? 'info' : 'error'
                );

            } catch {

                this.#showMessage(
                    messages,
                    'Could not resend the code. Please try again.',
                    'error'
                );

            } finally {

                button.disabled = false;

            }
        });
    }

    /*
     * Prevent multiple clicks on Proceed / Continue buttons.
     *
     * Once the form starts submitting:
     *
     * 1. The button becomes disabled.
     * 2. The button text changes to Processing...
     * 3. A second submit event is blocked.
     */
    #bindSubmitProtection() {

        this.#root
            .querySelectorAll('[data-apay-form]')
            .forEach((form) => {

                const button = form.querySelector('[data-apay-submit]');

                console.log('APay form:', form);
                console.log('APay submit button:', button);

                if (!button) {
                    return;
                }

                form.addEventListener('submit', (event) => {

                    console.log('APay SUBMIT fired');

                    if (form.dataset.submitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.submitting = 'true';

                    button.disabled = true;
                    button.style.opacity = '0.6';
                    button.style.cursor = 'not-allowed';
                    button.style.pointerEvents = 'none';

                    button.textContent = 'Processing...';
                });
            });
    }

    #showMessage(container, text, type) {

        if (!container || !text) {
            return;
        }

        const span = document.createElement('span');

        span.className =
            `apay-payment__text-${type}`;

        span.textContent = text;

        container.replaceChildren(span);
    }
}
