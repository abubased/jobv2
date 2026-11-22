/**
 * Applies embedded {locale: {key: text}} translations to any element
 * carrying data-apay-trans within the given root, and tracks the active
 * locale on the root's data-apay-lang attribute.
 */
export class Translator {
    #root;
    #translations;
    #locale;

    constructor(root, translations) {
        this.#root = root;
        this.#translations = translations || {};
        this.#locale = root.dataset.apayLang || 'en';
    }

    get locale() {
        return this.#locale;
    }

    setLocale(locale) {
        if (!this.#translations[locale]) {
            return;
        }

        this.#locale = locale;
        this.#root.dataset.apayLang = locale;
        this.applyToDom();
    }

    translate(key) {
        const dictionary = this.#translations[this.#locale] || {};
        return dictionary[key] ?? null;
    }

    applyToDom() {
        this.#root.querySelectorAll('[data-apay-trans]').forEach((element) => {
            const text = this.translate(element.dataset.apayTrans);
            if (text) {
                // Translation strings are authored in this module's own lang
                // files (not user input), so markup like the T&C link is
                // intentional and safe to render as HTML.
                element.innerHTML = text;
            }
        });
    }
}
