import { Controller } from '@hotwired/stimulus';

// Handles adding/removing items for a Symfony collection type.
// Expects:
// - container element with data-controller="form-collection"
// - container must have data-prototype attribute containing the prototype HTML (escaped)
// - a child element list (we use the controller element itself as the list)
// - add buttons must call the controller's add() method via data-action

export default class extends Controller {
	static targets = [ 'list' ];

	connect() {
		// If a list target exists use it, else the element itself
		this.list = this.hasListTarget ? this.listTarget : this.element;
		// Read prototype from data-prototype on the element or the list target
		this.prototype = this.list.dataset.prototype || this.element.dataset.prototype || '';
		// Start index as number of children
		this.index = this.list.querySelectorAll('.collection-item').length || 0;

		// Delegate remove button clicks so server-rendered items work
		this._onRemoveClick = (e) => {
			const btn = e.target.closest('.js-remove');
			if (!btn) return;
			const item = btn.closest('.collection-item');
			if (item) item.remove();
		};

		this.list.addEventListener('click', this._onRemoveClick);
	}

	add(event) {
		event && event.preventDefault();
		if (!this.prototype) {
			console.warn('form-collection: prototype not found');
			return;
		}

		// Replace the Symfony __name__ placeholder with the current index
		let newForm = this.prototype.replace(/__name__/g, this.index);


		// Create wrapper element matching the existing items' structure
		const wrapper = document.createElement('div');
		wrapper.className = 'collection-item card bg-base-200 p-3 mb-2 transition-all duration-150 ease-out opacity-0 -translate-y-2';

		// Insert the form HTML
		wrapper.innerHTML = newForm;

		// Add a subtle remove button (relies on delegated handler)
		const removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'btn btn-ghost btn-sm btn-circle js-remove ml-2';
		removeBtn.setAttribute('title', 'Supprimer');
		removeBtn.innerHTML = '🗑';

		// Insert remove button at the end (if prototype already contains structure this will append)
		wrapper.appendChild(removeBtn);

		// Append to the list
		this.list.appendChild(wrapper);

		// Trigger enter animation
		requestAnimationFrame(() => {
			wrapper.classList.remove('opacity-0', '-translate-y-2');
		});

		// increment index for next insertion
		this.index++;
	}
}
