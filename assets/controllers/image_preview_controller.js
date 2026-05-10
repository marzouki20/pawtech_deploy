import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input', 'preview', 'placeholder'];

  preview() {
    const file = this.inputTarget.files && this.inputTarget.files[0];
    if (!file) {
      if (this.hasPreviewTarget) {
        this.previewTarget.classList.add('hidden');
        this.previewTarget.removeAttribute('src');
      }
      if (this.hasPlaceholderTarget) {
        this.placeholderTarget.classList.remove('hidden');
      }
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      this.previewTarget.src = reader.result;
      this.previewTarget.classList.remove('hidden');
      if (this.hasPlaceholderTarget) {
        this.placeholderTarget.classList.add('hidden');
      }
    };
    reader.readAsDataURL(file);
  }
}
