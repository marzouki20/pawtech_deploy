import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['tbody', 'buttonAsc', 'buttonDesc'];

  connect() {
    this.direction = 'asc';
    this.updateButtons();
  }

  setAsc() {
    this.direction = 'asc';
    this.sort();
    this.updateButtons();
  }

  setDesc() {
    this.direction = 'desc';
    this.sort();
    this.updateButtons();
  }

  sort() {
    if (!this.hasTbodyTarget) return;

    const index = parseInt(this.tbodyTarget.dataset.entitySortColumn || '0', 10);
    const rows = Array.from(this.tbodyTarget.querySelectorAll('tr'))
      .filter((row) => row.querySelectorAll('td').length > 0);

    const getCellValue = (row) => {
      const cell = row.querySelectorAll('td')[index];
      return cell ? cell.textContent.trim() : '';
    };

    const isNumeric = (value) => value !== '' && !Number.isNaN(Number(value));

    rows.sort((a, b) => {
      const aVal = getCellValue(a);
      const bVal = getCellValue(b);
      const aNum = isNumeric(aVal) ? Number(aVal) : null;
      const bNum = isNumeric(bVal) ? Number(bVal) : null;

      let result = 0;
      if (aNum !== null && bNum !== null) {
        result = aNum - bNum;
      } else {
        result = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
      }

      return this.direction === 'asc' ? result : -result;
    });

    rows.forEach((row) => this.tbodyTarget.appendChild(row));
  }

  updateButtons() {
    if (this.hasButtonAscTarget) {
      this.buttonAscTarget.classList.toggle('bg-paw-orange', this.direction === 'asc');
      this.buttonAscTarget.classList.toggle('text-white', this.direction === 'asc');
      this.buttonAscTarget.classList.toggle('border-paw-orange', this.direction === 'asc');
    }
    if (this.hasButtonDescTarget) {
      this.buttonDescTarget.classList.toggle('bg-paw-orange', this.direction === 'desc');
      this.buttonDescTarget.classList.toggle('text-white', this.direction === 'desc');
      this.buttonDescTarget.classList.toggle('border-paw-orange', this.direction === 'desc');
    }
  }
}
