import { Translator } from './core/translator.js';
import { BoxInputController } from './core/box-input.js';
import { PaymentFormController } from './core/payment-form.js';

function boot() {
    const root = document.querySelector('[data-apay-payment]');
    if (!root) {
        return;
    }

    const translationsNode = document.querySelector('[data-apay-translations]');
    const translations = translationsNode ? JSON.parse(translationsNode.textContent) : {};

    const translator = new Translator(root, translations);
    translator.applyToDom();

    root.querySelectorAll('[data-apay-box-input]').forEach((container) => {
        new BoxInputController(container);
    });

    new PaymentFormController(root, translator);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
