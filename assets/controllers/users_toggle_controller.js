import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['cards', 'table', 'form', 'tableLabel'];

  showForm() {
    this.cardsTarget.classList.add('hidden');
    this.tableTarget.classList.add('hidden');
    this.formTarget.classList.remove('hidden');
    this.resetTableLabel();
  }

  showCards() {
    this.formTarget.classList.add('hidden');
    this.tableTarget.classList.add('hidden');
    this.cardsTarget.classList.remove('hidden');
    this.resetTableLabel();
  }

  toggleTable() {
    const isTableVisible = !this.tableTarget.classList.contains('hidden');
    if (isTableVisible) {
      this.tableTarget.classList.add('hidden');
      this.cardsTarget.classList.remove('hidden');
      this.resetTableLabel();
      return;
    }

    this.formTarget.classList.add('hidden');
    this.cardsTarget.classList.add('hidden');
    this.tableTarget.classList.remove('hidden');
    if (this.hasTableLabelTarget) {
      this.tableLabelTarget.textContent = 'Show Cards';
    }
  }

  resetTableLabel() {
    if (this.hasTableLabelTarget) {
      this.tableLabelTarget.textContent = 'Show Table';
    }
  }
}
