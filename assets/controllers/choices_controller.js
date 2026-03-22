import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect () {
    this.index = parseInt(this.element.dataset.choicesIndexValue || '0', 10)
  }

  addChoice () {
    const prototypeEl = this.element.querySelector('[data-choices-prototype]')
    const container = this.element.querySelector('[data-choices-container]')
    const html = prototypeEl.dataset.choicesPrototype.replace(/__name__/g, this.index++)
    const row = document.createElement('div')
    row.className = 'choice-row card mb-2'
    row.innerHTML = `<div class="card-body py-2 px-3"><div class="d-flex gap-2 align-items-center"><div class="flex-grow-1">${html}</div><button type="button" class="btn btn-sm btn-outline-danger" data-action="click->choices#removeChoice"><i class="bi bi-trash"></i></button></div></div>`
    container.appendChild(row)
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
