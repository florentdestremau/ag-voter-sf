import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect () {
    this.index = parseInt(this.element.dataset.choicesIndexValue || '0', 10)
  }

  addChoice () {
    const prototypeEl = this.element.querySelector('[data-choices-prototype]')
    const container = this.element.querySelector('[data-choices-container]')
    const html = prototypeEl.dataset.choicesPrototype.replace(/__name__/g, this.index++)
    container.insertAdjacentHTML('beforeend', html)
  }

  removeChoice (event) {
    event.currentTarget.closest('.choice-row').remove()
  }

  clearAll () {
    const container = this.element.querySelector('[data-choices-container]')
    container.querySelectorAll('.choice-row').forEach(row => row.remove())
    this.index = 0
  }
}
