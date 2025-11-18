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

		console.log('form-collection: connected', {
			list: this.list,
			hasPrototype: !!this.prototype,
			prototypeLength: this.prototype.length
		});

		// Start index as number of children
		this.index = this.list.querySelectorAll('.collection-item').length || 0;

		// Delegate remove button clicks so server-rendered items work
		this._onRemoveClick = (e) => {
			const btn = e.target.closest('.js-remove');
			if (!btn) return;
			const item = btn.closest('.collection-item');
			if (item) {
				item.remove();
				// Recalculer les positions après suppression
				this.updatePositions();
			}
		};

		this.list.addEventListener('click', this._onRemoveClick);

		// Initialiser les positions au chargement
		this.updatePositions();
	}

	add(event) {
		event && event.preventDefault();

		console.log('form-collection: add called', {
			hasPrototype: !!this.prototype,
			prototypePreview: this.prototype.substring(0, 100),
			currentIndex: this.index
		});

		if (!this.prototype) {
			console.warn('form-collection: prototype not found');
			return;
		}

		// Replace the Symfony __name__ placeholder with the current index
		let newForm = this.prototype.replace(/__name__/g, this.index);

		console.log('form-collection: newForm preview', newForm.substring(0, 200));

		// Create wrapper element matching the existing items' structure
		const wrapper = document.createElement('div');
		wrapper.className = 'collection-item card bg-base-200 p-3 mb-2 transition-all duration-150 ease-out opacity-0 -translate-y-2';

		// Insert the form HTML
		wrapper.innerHTML = newForm;

		// Vérifier si un bouton de suppression existe déjà dans le prototype
		const existingRemoveBtn = wrapper.querySelector('.js-remove');

		// Si pas de bouton de suppression, en ajouter un
		if (!existingRemoveBtn) {
			console.log('form-collection: no remove button found, adding one');
			const removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'btn btn-ghost btn-sm btn-circle js-remove ml-2';
			removeBtn.setAttribute('title', 'Supprimer');
			removeBtn.innerHTML = `
				<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M3 6l3 1m0 0l-3 9a5 5 0 006 0L10 7m4 0l3 1m0 0l-3 9a5 5 0 01-6 0L7 7" />
				</svg>
			`;
			wrapper.appendChild(removeBtn);
		} else {
			console.log('form-collection: remove button already exists');
		}

		// Append to the list
		this.list.appendChild(wrapper);

		console.log('form-collection: item appended to list');

		// Trigger enter animation
		requestAnimationFrame(() => {
			wrapper.classList.remove('opacity-0', '-translate-y-2');
		});

		// increment index for next insertion
		this.index++;

		// Mettre à jour les positions de tous les éléments
		this.updatePositions();
	}

	/**
	 * Met à jour les champs position de tous les éléments de la collection
	 * pour maintenir l'ordre correct
	 */
	updatePositions() {
		const items = this.list.querySelectorAll('.collection-item');
		items.forEach((item, index) => {
			// Chercher le champ position dans l'item
			const positionField = item.querySelector('input.position-field, input[id$="_position"]');
			if (positionField) {
				positionField.value = index;
			}
		});
	}
}
