import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'backdrop'];

    connect() {
        this.element.addEventListener('keydown', this.handleEscape.bind(this));
    }

    disconnect() {
        this.element.removeEventListener('keydown', this.handleEscape.bind(this));
    }

    open() {
        if (this.hasBackdropTarget) this.backdropTarget.classList.remove('hidden');
        if (this.hasDialogTarget) this.dialogTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        if (this.hasBackdropTarget) this.backdropTarget.classList.add('hidden');
        if (this.hasDialogTarget) this.dialogTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    handleEscape(e) {
        if (e.key === 'Escape') this.close();
    }
}
