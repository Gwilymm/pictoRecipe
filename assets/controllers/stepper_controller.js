import { Controller } from '@hotwired/stimulus';

// Stepper controller pour gérer les 5 étapes du formulaire de recette
// avec navigation et affichage dynamique de la prévisualisation
export default class extends Controller {
	static targets = [ 'step', 'stepItem', 'header', 'currentStepName' ];

	connect() {
		this.index = 0;
		this.totalSteps = this.stepTargets.length;
		this.showCurrent();
	}

	next(event) {
		event?.preventDefault();
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

	scrollToTop() {
		// Scroll vers le haut pour voir l'étape
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}
}
