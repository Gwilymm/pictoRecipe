import { Controller } from '@hotwired/stimulus';

// Stepper controller pour gérer les 5 étapes du formulaire de recette
// avec navigation et affichage dynamique de la prévisualisation
export default class extends Controller {
	static targets = [ 'step', 'stepItem', 'header', 'currentStepName' ];

	connect() {
		this.index = 0;
		this.totalSteps = this.stepTargets.length;
		this.cleanEmptyStepItemsBeforeSubmit = () => this.removeEmptyStepItems();
		this.element.addEventListener('submit', this.cleanEmptyStepItemsBeforeSubmit);
		this.showCurrent();
	}

	disconnect() {
		this.element.removeEventListener('submit', this.cleanEmptyStepItemsBeforeSubmit);
	}

	next(event) {
		event?.preventDefault();
		if (!this.canLeaveCurrentStep()) {
			return;
		}

		if (this.index < this.totalSteps - 1) {
			this.index++;
			this.showCurrent();
			this.scrollToTop();
		}
	}

	prev(event) {
		event?.preventDefault();
		if (this.index > 0) {
			this.index--;
			this.showCurrent();
			this.scrollToTop();
		}
	}

	showCurrent() {
		// Afficher seulement l'étape courante
		this.stepTargets.forEach((el, i) => {
			el.classList.toggle('hidden', i !== this.index);
		});

		// Mettre à jour l'état des indicateurs de step (DaisyUI)
		this.stepItemTargets.forEach((el, i) => {
			if (i < this.index) {
				// Étapes complétées
				el.classList.add('step-primary');
			} else if (i === this.index) {
				// Étape courante
				el.classList.add('step-primary');
			} else {
				// Étapes futures
				el.classList.remove('step-primary');
			}
		});

		// Mettre à jour le badge du nom de l'étape (mobile)
		if (this.hasCurrentStepNameTarget && this.stepItemTargets[ this.index ]) {
			const stepName = this.stepItemTargets[ this.index ].dataset.stepName;
			if (stepName) {
				this.currentStepNameTarget.textContent = stepName;
			}
		}

		// Si on arrive sur l'étape 5 (aperçu), mettre à jour l'aperçu avec les valeurs du formulaire
		if (this.index === 4) { // index 4 = étape 5
			this.updatePreview();
		}

		// Cacher les boutons de navigation dans l'en-tête si on est à la dernière étape (aperçu)
		if (this.hasHeaderTarget) {
			const navButtons = this.headerTarget.querySelectorAll('[data-action*="stepper#"]');
			navButtons.forEach(btn => {
				if (this.index === this.totalSteps - 1) {
					// Dernière étape : cacher les boutons du header (ils sont dans le step)
					btn.parentElement?.classList.add('hidden');
				} else {
					btn.parentElement?.classList.remove('hidden');
				}
			});
		}
	}

	canLeaveCurrentStep() {
		// Step 3: preparation steps must have text before moving forward.
		if (this.index === 2) {
			return this.validateStepsStep();
		}

		return true;
	}

	validateStepsStep() {
		const section = this.stepTargets[ this.index ];
		if (!section) {
			return true;
		}

		this.clearStepContentErrors(section);

		const stepItems = Array.from(section.querySelectorAll('.collection-item'))
			.filter(item => item.querySelector('[name$="[content]"], textarea[name*="[steps]"][name*="[content]"]'));

		if (stepItems.length === 0) {
			this.showSectionError(section, 'Ajoutez au moins une étape avant de continuer.');
			return false;
		}

		const emptyContentFields = stepItems
			.map(item => item.querySelector('[name$="[content]"], textarea[name*="[steps]"][name*="[content]"]'))
			.filter(field => field && !field.value.trim());

		if (emptyContentFields.length === 0) {
			return true;
		}

		emptyContentFields.forEach(field => {
			this.showFieldError(field, 'Renseignez la description de cette étape avant de continuer.');
		});

		emptyContentFields[ 0 ].focus();
		emptyContentFields[ 0 ].scrollIntoView({ behavior: 'smooth', block: 'center' });

		return false;
	}

	removeEmptyStepItems() {
		this.stepTargets.forEach(section => {
			section.querySelectorAll('.collection-item').forEach(item => {
				if (this.isEmptyStepItem(item)) {
					item.remove();
				}
			});
		});
	}

	isEmptyStepItem(item) {
		const contentInput = item.querySelector('[name$="[content]"], textarea[name*="[steps]"][name*="[content]"]');
		if (!contentInput) {
			return false;
		}

		const durationInput = item.querySelector('[name*="[durationMinutes]"]');
		const pictogramUrlInput = item.querySelector('[name*="[pictogramUrl]"]:not([name*="[pictogramUrls]"])');
		const pictogramUrlsInput = item.querySelector('[name*="[pictogramUrls]"]');

		return !contentInput.value.trim()
			&& !durationInput?.value.trim()
			&& !pictogramUrlInput?.value.trim()
			&& !this.hasPictogramUrls(pictogramUrlsInput?.value);
	}

	hasPictogramUrls(value) {
		if (!value?.trim()) {
			return false;
		}

		try {
			const urls = JSON.parse(value);
			return Array.isArray(urls) && urls.some(url => String(url).trim() !== '');
		} catch (error) {
			return true;
		}
	}

	clearStepContentErrors(section) {
		section.querySelectorAll('.step-content-error').forEach(error => error.remove());
		section.querySelectorAll('[name$="[content]"], textarea[name*="[steps]"][name*="[content]"]').forEach(field => {
			field.classList.remove('textarea-error', 'border-error');
			field.removeAttribute('aria-invalid');
			field.removeAttribute('aria-describedby');
		});
	}

	showSectionError(section, message) {
		const header = section.querySelector('.card-body > .flex.items-center');
		const error = document.createElement('div');
		error.className = 'step-content-error alert alert-error mt-4';
		error.setAttribute('role', 'alert');
		error.textContent = message;

		if (header) {
			header.insertAdjacentElement('afterend', error);
		} else {
			section.prepend(error);
		}
	}

	showFieldError(field, message) {
		const errorId = `${field.id || field.name.replace(/[^a-z0-9_-]/gi, '_')}_error`;
		const error = document.createElement('p');
		error.id = errorId;
		error.className = 'step-content-error text-error text-sm mt-2';
		error.setAttribute('role', 'alert');
		error.textContent = message;

		field.classList.add('textarea-error', 'border-error');
		field.setAttribute('aria-invalid', 'true');
		field.setAttribute('aria-describedby', errorId);

		const fieldWrapper = field.closest('.md\\:col-span-9, .flex-1') || field.parentElement;
		fieldWrapper?.appendChild(error);
	}

	updatePreview() {
		// Lire les valeurs du formulaire et mettre à jour l'aperçu
		const form = this.element;

		// Titre
		const titleEl = document.querySelector('.recipe-preview h2');
		const titleInput = form.querySelector('[name*="[title]"]');
		if (titleEl && titleInput) {
			titleEl.textContent = titleInput.value || 'Titre de la recette';
		}

		// Description
		const descEl = document.querySelector('.recipe-preview .text-base-content\\/80');
		const descInput = form.querySelector('[name*="[description]"]');
		if (descEl && descInput) {
			descEl.textContent = descInput.value || '';
			descEl.style.display = descInput.value ? 'block' : 'none';
		}

		// Portions, temps de préparation, temps de cuisson
		const servingsInput = form.querySelector('[name*="[servings]"]');
		const prepTimeInput = form.querySelector('[name*="[prepTimeMinutes]"]');
		const cookTimeInput = form.querySelector('[name*="[cookTimeMinutes]"]');

		// use a more permissive selector: gap value can change (gap-2/gap-3)
		const infos = document.querySelectorAll('.recipe-preview .shadow.rounded-lg .flex.items-center');
		if (infos.length >= 1 && servingsInput && servingsInput.value) {
			const spanEl = infos[ 0 ].querySelector('span:last-child');
			if (spanEl) {
				spanEl.textContent = `${servingsInput.value} part${servingsInput.value > 1 ? 's' : ''}`;
			}
		}
		if (infos.length >= 2 && prepTimeInput && prepTimeInput.value) {
			const spanEl = infos[ 1 ].querySelector('span:last-child');
			if (spanEl) {
				spanEl.textContent = `${prepTimeInput.value} min`;
			}
		}
		if (infos.length >= 3 && cookTimeInput && cookTimeInput.value) {
			const spanEl = infos[ 2 ].querySelector('span:last-child');
			if (spanEl) {
				spanEl.textContent = `${cookTimeInput.value} min`;
			}
		}

		// Ingrédients
		this.updateIngredientsPreview(form);

		// Étapes
		this.updateStepsPreview(form);

		// Ustensiles
		this.updateUtensilsPreview(form);
	}

	updateIngredientsPreview(form) {
		const ingredientsContainer = document.getElementById('preview-ingredients-container');
		if (!ingredientsContainer) return;

		// Collecter tous les éléments d'ingrédient (wrapper .collection-item)
		const ingredientItems = form.querySelectorAll('.collection-item');
		const ingredients = [];

		ingredientItems.forEach((item) => {
			const nameInput = item.querySelector('[name*="[name]"]');
			const amountInput = item.querySelector('[name*="[amount]"]');
			const unitInput = item.querySelector('[name*="[unit]"]');
			const pictogramInput = item.querySelector('[name*="[pictogramUrl]"]');

			if (nameInput && nameInput.value && nameInput.value.trim()) {
				ingredients.push({
					name: this.escapeHtml(nameInput.value),
					amount: this.escapeHtml(amountInput?.value || ''),
					unit: this.escapeHtml(unitInput?.value || ''),
					pictogramUrl: this.escapeHtml(pictogramInput?.value || '')
				});
			}
		});

		// Reconstruire le HTML des ingrédients
		if (ingredients.length === 0) {
			ingredientsContainer.className = 'alert alert-info mb-6';
			ingredientsContainer.innerHTML = '<span>Aucun ingrédient défini pour cette recette.</span>';
		} else {
			ingredientsContainer.className = 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mb-6';
			ingredientsContainer.innerHTML = ingredients.map(ing => `
				<div class="card bg-base-200/50 shadow-sm">
					<div class="card-body p-3 flex flex-row gap-3 items-center">
						${ing.pictogramUrl ? `<img src="${ing.pictogramUrl}" alt="${ing.name}" class="w-12 h-12 object-contain">` : '<div class="w-12 h-12 bg-base-300 rounded flex items-center justify-center text-2xl">🥕</div>'}
						<div class="flex-1">
							<div class="font-medium">${ing.name}</div>
							<div class="text-sm text-base-content/70">${ing.amount} ${ing.unit}</div>
						</div>
					</div>
				</div>
			`).join('');
		}
	}

	updateStepsPreview(form) {
		const stepsContainer = document.getElementById('preview-steps-container');
		if (!stepsContainer) return;

		// Collecter toutes les étapes du formulaire (wrapper .collection-item qui contiennent un champ content)
		const allCollectionItems = form.querySelectorAll('.collection-item');
		const stepItems = Array.from(allCollectionItems).filter(item => item.querySelector('[name$="[content]"]'));
		const steps = [];

		stepItems.forEach((item, index) => {
			const contentInput = item.querySelector('[name$="[content]"]') || item.querySelector('[name*="[content]"]');
			const durationInput = item.querySelector('[name*="[durationMinutes]"]');
			const pictogramsInput = item.querySelector('[name*="[pictogramUrls]"]');

			if (contentInput && contentInput.value.trim()) {
				let pictogramUrls = [];
				try {
					pictogramUrls = pictogramsInput?.value ? JSON.parse(pictogramsInput.value) : [];
				} catch (e) {
					console.error('Erreur parsing pictogramUrls:', e);
				}

				steps.push({
					position: index + 1,
					content: this.escapeHtml(contentInput.value),
					duration: durationInput?.value || '',
					pictogramUrls: pictogramUrls.map(url => this.escapeHtml(url))
				});
			}
		});

		// Reconstruire le HTML des étapes
		if (steps.length === 0) {
			stepsContainer.className = 'alert alert-info mb-6';
			stepsContainer.innerHTML = '<span>Aucune étape de préparation définie.</span>';
		} else {
			stepsContainer.className = 'space-y-4 mb-6';
			stepsContainer.innerHTML = steps.map(step => `
				<div class="card bg-base-200/30 shadow-sm">
					<div class="card-body p-3 md:p-4">
						<div class="flex flex-col sm:flex-row gap-3 md:gap-4 items-start">
							<div class="flex flex-row sm:flex-col items-center gap-2 md:gap-3 shrink-0 sm:w-20 md:w-28">
								<div class="badge badge-md md:badge-lg badge-primary font-bold">${step.position}</div>
								${step.pictogramUrls.length > 0 ? `
									<div class="grid grid-cols-2 gap-1 md:gap-2 mt-0 sm:mt-2">
										${step.pictogramUrls.map(url => `<img src="${url}" alt="Picto" class="w-12 h-12 md:w-16 md:h-16 object-contain rounded-lg bg-base-200 p-1">`).join('')}
									</div>
								` : ''}
							</div>
							<div class="flex-1">
								<div class="flex items-center gap-2 mb-2">
									${step.duration ? `<span class="badge badge-ghost badge-sm md:badge-md gap-1">⏱️ ${step.duration} minutes</span>` : ''}
								</div>
								<p class="text-sm md:text-base leading-relaxed">${step.content}</p>
							</div>
						</div>
					</div>
				</div>
			`).join('');
		}
	}

	updateUtensilsPreview(form) {
		const utensilsContainer = document.getElementById('preview-utensils-container');
		if (!utensilsContainer) return;

		// Collecter tous les ustensiles cochés
		// target checkboxes explicitly (input[type=checkbox]) to avoid matching other fields
		const checkedUtensils = form.querySelectorAll('input[type="checkbox"][name*="[utensils]"]:checked');
		const utensils = [];

		checkedUtensils.forEach(checkbox => {
			const label = checkbox.closest('label');
			if (label) {
				// The template uses a <span class="ml-2"> for the label text, not .font-medium
				const nameEl = label.querySelector('span.ml-2') || label.querySelector('span') || null;
				const imgEl = label.querySelector('img');
				const rawName = nameEl ? nameEl.textContent.trim() : label.textContent.trim();
				if (rawName) {
					utensils.push({
						name: this.escapeHtml(rawName),
						pictogramUrl: this.escapeHtml(imgEl?.src || '')
					});
				}
			}
		});

		// Reconstruire le HTML des ustensiles
		if (utensils.length === 0) {
			utensilsContainer.className = 'alert alert-info mb-6';
			utensilsContainer.innerHTML = '<span>Aucun ustensile spécifié.</span>';
		} else {
			utensilsContainer.className = 'grid grid-cols-2 md:grid-cols-4 gap-3 mb-6';
			utensilsContainer.innerHTML = utensils.map(utensil => `
				<div class="card bg-base-200/30 shadow-sm">
					<div class="card-body p-3 flex flex-col items-center gap-2">
						${utensil.pictogramUrl ? `<img src="${utensil.pictogramUrl}" alt="${utensil.name}" class="w-12 h-12 object-contain">` : '<div class="w-12 h-12 bg-base-300 rounded flex items-center justify-center text-2xl">🍴</div>'}
						<span class="text-sm text-center font-medium">${utensil.name}</span>
					</div>
				</div>
			`).join('');
		}
	}

	escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, m => map[ m ]);
	}

	scrollToTop() {
		// Scroll vers le haut pour voir l'étape
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}
}
