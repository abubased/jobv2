/**
 * Drives a single [data-apay-box-input] group: single-digit auto-advance,
 * backspace-to-previous, digit-only filtering, and keeping the group's
 * hidden input in sync with the merged value the server actually reads.
 */
export class BoxInputController {
    #inputs;
    #hiddenInput;

    constructor(container) {
        this.#inputs = Array.from(container.querySelectorAll('input:not([type="hidden"])'));
        this.#hiddenInput = container.querySelector('[data-apay-box-input-value]');

        this.#sync();

        this.#inputs.forEach((input, index) => {
            input.addEventListener('keydown', (event) => this.#handleKeydown(event, index));
            input.addEventListener('input', (event) => this.#handleInput(event, index));
            input.addEventListener('blur', () => this.#sync());
        });

        const form = container.closest('form');
        if (form) {
            form.addEventListener('submit', () => this.#sync());
        }
    }

    #handleKeydown(event, index) {
        if (event.key === 'Enter') {
            // Mirrors the original widget: Enter must not submit the form
            // while the merged value is still being typed digit by digit.
            event.preventDefault();
            return;
        }

        if (event.key === 'Backspace' && !event.target.value && index > 0) {
            event.preventDefault();
            const previous = this.#inputs[index - 1];
            previous.value = '';
            previous.focus();
            this.#sync();
        }
    }

    #handleInput(event, index) {
        const input = event.target;
        input.value = input.value.replace(/\D/g, '').slice(-1);

        if (input.value && index < this.#inputs.length - 1) {
            this.#inputs[index + 1].focus();
        }

        this.#sync();
    }

    #sync() {
        if (this.#hiddenInput) {
            this.#hiddenInput.value = this.#inputs.map((input) => input.value).join('');
        }
    }
}
