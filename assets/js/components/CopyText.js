export class CopyText {
    constructor(buttonSelector, textSelector, labelSelector) {
        this.button = document.querySelector(buttonSelector);
        this.textElement = document.querySelector(textSelector);
        this.labelElement = document.querySelector(labelSelector);

        if (this.button && this.textElement) {
            this.button.addEventListener('click', () => this.copy());
        }
    }

    copy() {
        const text = this.textElement.textContent.trim();

        navigator.clipboard.writeText(text).then(() => {
            this.labelElement.textContent = 'Copié !';
            this.button.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            this.button.classList.add('bg-green-600', 'hover:bg-green-700');

            setTimeout(() => {
                this.labelElement.textContent = 'Copier';
                this.button.classList.remove('bg-green-600', 'hover:bg-green-700');
                this.button.classList.add('bg-purple-600', 'hover:bg-purple-700');
            }, 2000);
        });
    }
}