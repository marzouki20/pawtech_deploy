import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['form', 'input', 'field'];

  connect() {
    this.timeout = null;
  }

  queue() {
    if (this.timeout) {
      clearTimeout(this.timeout);
    }

    this.timeout = setTimeout(() => {
      this.submitNow();
    }, 300);
  }

  submitNow() {
    if (this.timeout) {
      clearTimeout(this.timeout);
    }

    if (this.hasFormTarget) {
      this.formTarget.requestSubmit();
    }
  }
}
