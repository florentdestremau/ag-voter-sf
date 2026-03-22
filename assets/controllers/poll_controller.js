import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = { interval: { type: Number, default: 3000 } }

  connect () {
    this.timer = setInterval(() => {
      if (!this.element.querySelector('input:focus, textarea:focus, input[type="radio"]:checked')) {
        this.element.reload()
      }
    }, this.intervalValue)
  }

  disconnect () {
    clearInterval(this.timer)
  }
}
