import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String };

    connect() {
        this.eventSource = new EventSource(this.urlValue);
        this.eventSource.onmessage = () => {
            this.element.reload();
        };
    }

    disconnect() {
        this.eventSource?.close();
    }
}
