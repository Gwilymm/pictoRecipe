import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur Stimulus pour la recherche et sélection de pictogrammes ARASAAC
 * 
 * Usage :
 * <div data-controller="pictogram"
 *      data-pictogram-field-name-value="ingredient_name"
 *      data-pictogram-url-field-value="ingredient_pictogramUrl">
 *   ...
 * </div>
 */
export default class extends Controller {
	static targets = [
		'searchInput',      // Champ de recherche
		'searchButton',     // Bouton de recherche
		'resultsContainer', // Conteneur des résultats
		'spinner',          // Spinner de chargement
		'urlInput',         // Input caché pour l'URL
		'preview'           // Aperçu du picto sélectionné
	];

	static values = {
		apiUrl: { type: String, default: '/api/pictograms/search' },
		debounceDelay: { type: Number, default: 500 }
	};

	connect() {
		this.debounceTimer = null;
		this.selectedPictogramUrl = this.urlInputTarget?.value || null;

		// Afficher l'aperçu si un pictogramme est déjà sélectionné
		if (this.selectedPictogramUrl && this.hasPreviewTarget) {
			this.showPreview(this.selectedPictogramUrl);
		}
	}

	disconnect() {
		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer);
		}
	}

	/**
	 * Déclenché lors de la saisie dans le champ de recherche
	 * Recherche automatique avec debounce
	 */
	onSearchInput(event) {
		const keyword = event.target.value.trim();

		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer);
		}

		if (keyword.length < 2) {
			this.clearResults();
			return;
		}

		this.debounceTimer = setTimeout(() => {
			this.search(keyword);
		}, this.debounceDelayValue);
	}

	/**
	 * Déclenché lors du clic sur le bouton de recherche
	 */
	onSearchClick(event) {
		event.preventDefault();
		const keyword = this.searchInputTarget.value.trim();

		if (keyword.length >= 2) {
			this.search(keyword);
		}
	}

	/**
	 * Effectue la recherche via l'API
	 */
	async search(keyword) {
		try {
			this.showSpinner();
			this.clearResults();

			const url = `${this.apiUrlValue}?q=${encodeURIComponent(keyword)}`;
			const response = await fetch(url);

			if (!response.ok) {
				throw new Error(`Erreur HTTP: ${response.status}`);
			}

			const data = await response.json();

			this.hideSpinner();

			if (data.success && data.results.length > 0) {
				this.displayResults(data.results);
			} else {
				this.displayNoResults(keyword);
			}
		} catch (error) {
			this.hideSpinner();
			this.displayError(error.message);
			console.error('Erreur lors de la recherche de pictogrammes:', error);
		}
	}

	/**
	 * Affiche les résultats dans une grille DaisyUI
	 */
	displayResults(results) {
		const grid = document.createElement('div');
		grid.className = 'grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2 mt-4';

		results.forEach(pictogram => {
			const card = this.createPictogramCard(pictogram);
			grid.appendChild(card);
		});

		this.resultsContainerTarget.innerHTML = '';
		this.resultsContainerTarget.appendChild(grid);
	}

	/**
	 * Crée une carte de pictogramme cliquable
	 */
	createPictogramCard(pictogram) {
		const card = document.createElement('div');
		card.className = 'card bg-base-200 hover:bg-base-300 cursor-pointer transition hover:scale-105 w-16 h-16 overflow-hidden flex items-center justify-center rounded-lg';
		card.dataset.pictogramUrl = pictogram.imageUrl;
		card.dataset.pictogramId = pictogram.id;

		// Si c'est le pictogramme actuellement sélectionné
		if (this.selectedPictogramUrl === pictogram.imageUrl) {
			card.classList.add('ring', 'ring-primary', 'ring-offset-2');
		}

		const img = document.createElement('img');
		img.src = pictogram.imageUrl;
		img.alt = pictogram.name || 'Pictogramme';
		img.className = 'w-full h-full object-contain p-2';
		img.loading = 'lazy';

		// Gestion des erreurs de chargement d'image
		img.onerror = () => {
			card.innerHTML = '<span class="text-xs text-error">⚠️</span>';
		};

		card.appendChild(img);

		// Tooltip avec le nom
		if (pictogram.name) {
			card.title = pictogram.name;
		}

		// Événement de sélection
		card.addEventListener('click', () => this.selectPictogram(pictogram, card));

		return card;
	}

	/**
	 * Sélectionne un pictogramme
	 */
	selectPictogram(pictogram, cardElement) {
		// Retirer la sélection précédente
		this.resultsContainerTarget.querySelectorAll('.ring').forEach(el => {
			el.classList.remove('ring', 'ring-primary', 'ring-offset-2');
		});

		// Ajouter la sélection à la nouvelle carte
		cardElement.classList.add('ring', 'ring-primary', 'ring-offset-2');

		// Mettre à jour le champ caché
		if (this.hasUrlInputTarget) {
			this.urlInputTarget.value = pictogram.imageUrl;
			this.selectedPictogramUrl = pictogram.imageUrl;

			// Déclencher un événement change pour la validation de formulaire
			this.urlInputTarget.dispatchEvent(new Event('change', { bubbles: true }));
		}

		// Afficher l'aperçu
		this.showPreview(pictogram.imageUrl, pictogram.name);

		// Notification visuelle
		this.showSuccessToast(pictogram.name || 'Pictogramme sélectionné');
	}

	/**
	 * Affiche l'aperçu du pictogramme sélectionné
	 */
	showPreview(imageUrl, name = '') {
		if (!this.hasPreviewTarget) return;

		this.previewTarget.innerHTML = `
            <div class="flex items-center gap-2 p-2 bg-success/10 rounded-lg border border-success/30">
                <img src="${imageUrl}" alt="${name}" class="w-12 h-12 object-contain rounded" />
                <span class="text-sm font-medium flex-1">${name || 'Pictogramme sélectionné'}</span>
                <button type="button" 
                        class="btn btn-ghost btn-xs btn-circle" 
                        data-action="click->pictogram#clearSelection">
                    ✕
                </button>
            </div>
        `;
	}

	/**
	 * Efface la sélection
	 */
	clearSelection(event) {
		event?.preventDefault();

		if (this.hasUrlInputTarget) {
			this.urlInputTarget.value = '';
			this.selectedPictogramUrl = null;
		}

		if (this.hasPreviewTarget) {
			this.previewTarget.innerHTML = '';
		}

		// Retirer la sélection visuelle
		this.resultsContainerTarget.querySelectorAll('.ring').forEach(el => {
			el.classList.remove('ring', 'ring-primary', 'ring-offset-2');
		});
	}

	/**
	 * Affiche un message "aucun résultat"
	 */
	displayNoResults(keyword) {
		this.resultsContainerTarget.innerHTML = `
            <div class="alert alert-warning mt-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Aucun pictogramme trouvé pour "${keyword}".</span>
            </div>
        `;
	}

	/**
	 * Affiche un message d'erreur
	 */
	displayError(message) {
		this.resultsContainerTarget.innerHTML = `
            <div class="alert alert-error mt-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Erreur : ${message}</span>
            </div>
        `;
	}

	/**
	 * Affiche le spinner de chargement
	 */
	showSpinner() {
		if (this.hasSpinnerTarget) {
			this.spinnerTarget.classList.remove('hidden');
		}
	}

	/**
	 * Cache le spinner de chargement
	 */
	hideSpinner() {
		if (this.hasSpinnerTarget) {
			this.spinnerTarget.classList.add('hidden');
		}
	}

	/**
	 * Efface les résultats
	 */
	clearResults() {
		this.resultsContainerTarget.innerHTML = '';
	}

	/**
	 * Affiche un toast de succès (optionnel, nécessite une bibliothèque de toast)
	 */
	showSuccessToast(message) {
		// Version simple sans bibliothèque externe
		const toast = document.createElement('div');
		toast.className = 'toast toast-top toast-end';
		toast.innerHTML = `
            <div class="alert alert-success">
                <span>✓ ${message}</span>
            </div>
        `;
		document.body.appendChild(toast);

		setTimeout(() => {
			toast.remove();
		}, 2000);
	}
}
