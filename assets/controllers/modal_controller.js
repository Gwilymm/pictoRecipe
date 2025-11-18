import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = [ 'toggle', 'dialog', 'form', 'title', 'token', 'content' ];

	connect() {
		console.debug('Modal controller connected');
	}

	// Open modal: set form action, token and title/content then open dialog or toggle
	open(event) {
		const el = event.currentTarget || event.target;
		const action = el.dataset.modalAction || el.getAttribute('data-action-url');
		const token = el.dataset.modalToken || el.getAttribute('data-token');
		const name = el.dataset.modalName || el.getAttribute('data-name');
		const html = el.dataset.modalHtml || el.getAttribute('data-html');

		if (this.hasFormTarget) this.formTarget.action = action || this.formTarget.action;
		if (this.hasTokenTarget) this.tokenTarget.value = token || '';
		if (this.hasTitleTarget) this.titleTarget.textContent = name || '';
		if (this.hasContentTarget && html) this.contentTarget.innerHTML = html;

		// prefer dialog.showModal() when available
		if (this.hasDialogTarget && typeof this.dialogTarget.showModal === 'function') {
			try {
				this.dialogTarget.showModal();
			} catch (e) {
				// some browsers may throw if dialog is already open — ignore
			}
			return;
		}

		// fallback to checkbox toggle if present
		if (this.hasToggleTarget) this.toggleTarget.checked = true;
	}

	// Close modal by closing dialog or unchecking toggle
	close() {
		if (this.hasDialogTarget && typeof this.dialogTarget.close === 'function') {
			try { this.dialogTarget.close(); } catch (e) { }
			return;
		}

		if (this.hasToggleTarget) this.toggleTarget.checked = false;
	}
}
