import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['choiceBtn']

  connect () {
    const btns = this.element.querySelectorAll('[data-choice-id]')
    btns.forEach(btn => {
      btn.addEventListener('mouseenter', () => {
        const choiceId = btn.dataset.choiceId
        document.querySelectorAll('.free-text-area').forEach(el => el.style.display = 'none')
        const freeTextEl = document.getElementById(`free-text-${choiceId}`)
        if (freeTextEl) freeTextEl.style.display = 'block'
      })
    })
  }
}
