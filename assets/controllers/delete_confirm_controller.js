import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['modal', 'message'];

  connect() {
    this.currentForm = null;
  }

  confirm(event) {
    event.preventDefault();
    this.currentForm = event.currentTarget;
    const customMessage = this.currentForm.dataset.deleteMessage;
    if (this.hasMessageTarget) {
      this.messageTarget.textContent = customMessage || 'Are you sure you want to delete this item?';
    }
    this.open();
  }

  submit() {
    if (this.currentForm) {
      this.currentForm.submit();
    }
  }

  open() {
    this.modalTarget.classList.remove('hidden');
  }

  close() {
    this.modalTarget.classList.add('hidden');
  }
}
