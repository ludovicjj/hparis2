export class ResetToken {
    constructor(buttonSelector, urlElementSelector, labelSelector, qrCodeImgSelector = null) {
        this.button = document.querySelector(buttonSelector);
        this.urlElement = document.querySelector(urlElementSelector);
        this.labelElement = document.querySelector(labelSelector);
        this.qrCodeImg = qrCodeImgSelector ? document.querySelector(qrCodeImgSelector) : null;

        if (this.button) {
            this.url = this.button.dataset.url;
            this.button.addEventListener('click', () => this.reset());
        }
    }

    async reset() {
        if (!confirm("Voulez-vous régénérer le token ? L'ancien lien ne fonctionnera plus.")) {
            return;
        }

        this.labelElement.textContent = '...';
        this.button.disabled = true;

        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.urlElement.textContent = data.url;

                if (this.qrCodeImg && data.qrCode) {
                    this.qrCodeImg.src = data.qrCode;
                }

                this.labelElement.textContent = 'OK !';
                this.button.classList.remove('bg-slate-700', 'hover:bg-slate-600');
                this.button.classList.add('bg-green-600', 'hover:bg-green-700');

                setTimeout(() => {
                    this.labelElement.textContent = 'Régénérer';
                    this.button.classList.remove('bg-green-600', 'hover:bg-green-700');
                    this.button.classList.add('bg-slate-700', 'hover:bg-slate-600');
                    this.button.disabled = false;
                }, 2000);
            } else {
                throw new Error('Erreur serveur');
            }
        } catch (error) {
            this.labelElement.textContent = 'Erreur';
            this.button.classList.remove('bg-slate-700', 'hover:bg-slate-600');
            this.button.classList.add('bg-red-600', 'hover:bg-red-700');

            setTimeout(() => {
                this.labelElement.textContent = 'Régénérer';
                this.button.classList.remove('bg-red-600', 'hover:bg-red-700');
                this.button.classList.add('bg-slate-700', 'hover:bg-slate-600');
                this.button.disabled = false;
            }, 2000);
        }
    }
}
