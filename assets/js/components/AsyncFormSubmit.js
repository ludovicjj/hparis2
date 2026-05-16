export class AsyncFormSubmit {
    constructor(form) {
        this.form = form;
        this.form.addEventListener('submit', this.onSubmit.bind(this));
    }

    async onSubmit(event) {
        event.preventDefault();
        this.clearErrors();

        const submitButton = this.form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(this.form.action, {
                method: 'POST',
                body: new FormData(this.form),
                headers: { 'Accept': 'application/json' },
            });

            if (response.status === 422) {
                const { errors } = await response.json();
                this.displayErrors(errors || {});
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.redirectUrl) {
                window.location.href = data.redirectUrl;
                return;
            }
        } catch (error) {
            console.error('AsyncFormSubmit error:', error);
            alert('Erreur réseau, veuillez réessayer.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    displayErrors(errors) {
        Object.entries(errors).forEach(([field, message]) => {
            const input = this.form.querySelector(`[name$="[${field}]"]`);
            if (!input) {
                console.warn(`AsyncFormSubmit: no input found for field "${field}"`);
                return;
            }

            const errorEl = document.createElement('p');
            errorEl.className = 'async-form-error text-red-400 text-xs mt-1';
            errorEl.textContent = message;
            input.parentNode.appendChild(errorEl);
        });
    }

    clearErrors() {
        this.form.querySelectorAll('.async-form-error').forEach((el) => el.remove());
    }
}
