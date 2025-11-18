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
		'search',            // Champ de recherche (simplifié)
		'results',           // Conteneur des résultats
		'resultsList',       // Liste des résultats
		'selectedContainer', // Conteneur des pictogrammes sélectionnés (mode multiple)
		'emptyMessage',      // Message "Aucun pictogramme sélectionné"
		'searchZone',        // Zone de recherche collapsible
		'searchToggle',      // Toggle du collapse
		'searchInput',       // Champ de recherche (ancien nom, compatibilité)
		'searchButton',      // Bouton de recherche
		'resultsContainer',  // Conteneur des résultats (ancien nom)
		'spinner',           // Spinner de chargement
		'urlInput',          // Input caché pour l'URL (mode simple)
		'preview'            // Aperçu du picto sélectionné (mode simple)
	];

	static values = {
		apiUrl: { type: String, default: '/api/pictograms/search' },
		debounceDelay: { type: Number, default: 500 },
		mode: { type: String, default: 'single' }, // 'single' ou 'multiple'
		target: { type: String, default: '' }      // ID du champ hidden pour mode multiple
	};

	connect() {
		this.debounceTimer = null;

		// Déterminer le mode depuis l'attribut data
		if (this.element.dataset.pictogramMode) {
			this.modeValue = this.element.dataset.pictogramMode;
		}

		// Récupérer l'ID du target field pour le mode multiple
		if (this.element.dataset.pictogramTargetValue) {
			this.targetValue = this.element.dataset.pictogramTargetValue;
		}

		console.log('Pictogram controller connecté', {
			mode: this.modeValue,
			targetValue: this.targetValue,
			hasResultsTarget: this.hasResultsTarget,
			hasResultsContainerTarget: this.hasResultsContainerTarget,
			hasSearchTarget: this.hasSearchTarget
		});

		// Mode simple : afficher l'aperçu si déjà sélectionné
		if (this.modeValue === 'single') {
			this.selectedPictogramUrl = this.urlInputTarget?.value || null;
			if (this.selectedPictogramUrl && this.hasPreviewTarget) {
				this.showPreview(this.selectedPictogramUrl);
			}
		}

		// If a search input already contains a value (e.g. utensil name), automatically perform a search
		if (this.hasSearchInputTarget) {
			let initial = this.searchInputTarget.value?.trim() || '';
			initial = initial.toLowerCase();
			if (initial.length >= 2) {
				// use performSearch which handles fetching and rendering compact results
				this.performSearch(initial);
			}
		}

		// Mode multiple : charger les pictogrammes existants
		if (this.modeValue === 'multiple') {
			this.loadExistingPictograms();
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
		// Force lowercase while typing and use lowercase keyword for search
		event.target.value = event.target.value.toLowerCase();
		const keyword = event.target.value.trim();

		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer);
		}

		if (keyword.length < 2) {
			this.clearResults();
			this.hideResults();
			return;
		}

		this.debounceTimer = setTimeout(() => {
			if (this.modeValue === 'multiple') {
				this.performSearch(keyword);
			} else {
				this.search(keyword);
			}
		}, this.debounceDelayValue);
	}

	/**
	 * Déclenché lors du clic sur le bouton de recherche
	 */
	onSearchClick(event) {
		event.preventDefault();
		// ensure we search with lowercase text
		this.searchInputTarget.value = this.searchInputTarget.value.toLowerCase();
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
		// Créer un conteneur avec scroll et bouton de fermeture
		const container = document.createElement('div');
		container.className = 'card bg-base-100 shadow-lg max-h-96 overflow-y-auto mt-2 relative';

		const cardBody = document.createElement('div');
		cardBody.className = 'card-body p-2';

		// Bouton de fermeture
		const closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'btn btn-sm btn-circle btn-ghost absolute right-2 top-2 z-10';
		closeBtn.innerHTML = '✕';
		closeBtn.addEventListener('click', () => this.clearResults());

		const grid = document.createElement('div');
		grid.className = 'grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2';

		results.forEach(pictogram => {
			const card = this.createPictogramCard(pictogram);
			grid.appendChild(card);
		});

		cardBody.appendChild(grid);
		container.appendChild(closeBtn);
		container.appendChild(cardBody);

		this.resultsContainerTarget.innerHTML = '';
		this.resultsContainerTarget.appendChild(container);
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
		img.src = pictogram.imageUrl || '';
		img.alt = pictogram.name || 'Pictogramme';
		img.className = 'w-full h-full object-contain p-2';
		img.loading = 'lazy';
		img.referrerPolicy = 'no-referrer';

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
		console.log('selectPictogram called', {
			pictogram: pictogram.name,
			hasResultsContainerTarget: this.hasResultsContainerTarget
		});

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

		console.log('About to clear results...');

		// Masquer les résultats après sélection
		this.clearResults();

		console.log('Results cleared!');
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
		if (this.hasResultsContainerTarget) {
			// Vider complètement le conteneur
			this.resultsContainerTarget.innerHTML = '';
			// Masquer aussi le conteneur si besoin
			this.resultsContainerTarget.classList.add('hidden');
			// Réafficher immédiatement (au cas où on recherche à nouveau)
			setTimeout(() => {
				if (this.hasResultsContainerTarget) {
					this.resultsContainerTarget.classList.remove('hidden');
				}
			}, 100);
		}
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

	// ============== MODE MULTIPLE ==============

	/**
	 * Charge les pictogrammes existants depuis le champ hidden JSON
	 */
	loadExistingPictograms() {
		if (!this.targetValue) {
			console.log('Pas de targetValue, skip loadExistingPictograms');
			return;
		}

		const hiddenField = document.getElementById(this.targetValue);
		if (!hiddenField || !hiddenField.value) {
			console.log('Champ hidden non trouvé ou vide', { targetValue: this.targetValue });
			return;
		}

		try {
			const urls = JSON.parse(hiddenField.value);
			console.log('Pictogrammes existants chargés', { urls });
			if (Array.isArray(urls) && urls.length > 0) {
				urls.forEach(url => this.addSelectedPictogram(url));
			}
		} catch (e) {
			console.error('Erreur lors du chargement des pictogrammes:', e);
		}
	}

	/**
	 * Sélectionne un pictogramme en mode multiple
	 */
	selectPictogramMultiple(pictogram) {
		this.addSelectedPictogram(pictogram.imageUrl, pictogram.name);
		this.updateHiddenField();
		this.showSuccessToast(`${pictogram.name || 'Pictogramme'} ajouté`);
	}

	/**
	 * Ajoute visuellement un pictogramme sélectionné
	 */
	addSelectedPictogram(imageUrl, name = '') {
		if (!this.hasSelectedContainerTarget) return;

		// Masquer le message "Aucun pictogramme sélectionné"
		if (this.hasEmptyMessageTarget) {
			this.emptyMessageTarget.classList.add('hidden');
		}

		const badge = document.createElement('div');
		// Larger, non-badge card with white background for visibility
		badge.className = 'selected-picto flex items-center gap-3 p-2 md:p-3 bg-white border border-base-300 rounded-xl shadow-sm';
		badge.dataset.pictogramUrl = imageUrl;
		badge.innerHTML = `
			<div class="w-16 h-16 flex items-center justify-center">
				<img src="${imageUrl}" alt="${name}" class="max-w-16 max-h-16 object-contain" />
			</div>
			<span class="text-xs sm:text-sm max-w-[140px] truncate">${name}</span>
			<button type="button" class="btn btn-ghost btn-xs btn-circle" data-url="${imageUrl}">✕</button>
		`;

		// Événement pour retirer le pictogramme
		const removeBtn = badge.querySelector('button');
		removeBtn.addEventListener('click', () => {
			badge.remove();
			this.updateHiddenField();
			this.checkEmptyState();
		});

		this.selectedContainerTarget.appendChild(badge);
	}

	/**
	 * Met à jour le champ hidden avec les URLs en JSON
	 */
	updateHiddenField() {
		if (!this.targetValue) return;

		const hiddenField = document.getElementById(this.targetValue);
		if (!hiddenField) return;

		const urls = Array.from(this.selectedContainerTarget.querySelectorAll('[data-pictogram-url]'))
			.map(badge => badge.dataset.pictogramUrl);

		hiddenField.value = JSON.stringify(urls);
		hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
	}

	// Note: input handling is implemented in `onSearchInput` and `onSearchClick`.
	// Older templates may reference `pictogram#search`; prefer using
	// `pictogram#onSearchInput` (see templates/partials/_pictogram_multiple_widget.html.twig).

	/**
	 * Effectue la recherche (compatible mode simple et multiple)
	 */
	async performSearch(keyword) {
		console.log('performSearch appelée', {
			keyword,
			mode: this.modeValue,
			hasResultsTarget: this.hasResultsTarget
		});

		try {
			const url = `${this.apiUrlValue}?q=${encodeURIComponent(keyword)}`;
			const response = await fetch(url);

			if (!response.ok) {
				throw new Error(`Erreur HTTP: ${response.status}`);
			}

			const data = await response.json();

			console.log('Données reçues', {
				success: data.success,
				resultsCount: data.results?.length || 0
			});

			if (data.success && data.results.length > 0) {
				this.displayResultsCompact(data.results);
			} else {
				this.hideResults();
			}
		} catch (error) {
			console.error('Erreur lors de la recherche:', error);
			this.hideResults();
		}
	}

	/**
	 * Affiche les résultats de manière compacte
	 */
	displayResultsCompact(results) {
		const container = this.hasResultsTarget ? this.resultsTarget :
			this.hasResultsContainerTarget ? this.resultsContainerTarget : null;

		if (!container) {
			console.warn('Pas de conteneur de résultats trouvé', {
				hasResultsTarget: this.hasResultsTarget,
				hasResultsContainerTarget: this.hasResultsContainerTarget
			});
			return;
		}

		// create a scrollable card to contain a compact grid with close button
		const wrapper = document.createElement('div');
		wrapper.className = 'card bg-base-100 shadow-lg max-h-96 overflow-y-auto mt-2 relative';

		const body = document.createElement('div');
		body.className = 'card-body p-2';

		// Bouton de fermeture
		const closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'btn btn-sm btn-circle btn-ghost absolute right-2 top-2 z-10';
		closeBtn.innerHTML = '✕';
		closeBtn.addEventListener('click', () => {
			container.innerHTML = '';
			container.classList.add('hidden');
		});

		const grid = document.createElement('div');
		grid.className = 'grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 items-center justify-items-center';

		results.forEach(pictogram => {
			const card = document.createElement('div');
			card.className = 'cursor-pointer transition p-1 flex items-center justify-center rounded hover:bg-base-200 hover:scale-105';
			card.innerHTML = `<img src="${pictogram.imageUrl}" alt="${pictogram.name}" class="w-12 h-12 object-contain" title="${pictogram.name}" />`;

			card.addEventListener('click', () => {
				if (this.modeValue === 'multiple') {
					this.selectPictogramMultiple(pictogram);
				} else {
					this.selectPictogram(pictogram, card);
				}
			});

			grid.appendChild(card);
		});

		body.appendChild(grid);
		wrapper.appendChild(closeBtn);
		wrapper.appendChild(body);

		// Vider et afficher
		container.innerHTML = '';
		container.appendChild(wrapper);
		container.classList.remove('hidden');

		console.log('Résultats affichés dans le conteneur', {
			resultsCount: results.length,
			containerClass: container.className
		});
	}

	/**
	 * Masque les résultats
	 */
	hideResults() {
		if (this.hasResultsTarget) {
			this.resultsTarget.classList.add('hidden');
		}
	}

	/**
	 * Ferme la zone de recherche (mode multiple)
	 */
	closeSearch(event) {
		event?.preventDefault();

		// Vider le champ de recherche
		if (this.hasSearchTarget) {
			this.searchTarget.value = '';
		}

		// Masquer les résultats
		this.hideResults();

		// Fermer le collapse
		if (this.hasSearchToggleTarget) {
			this.searchToggleTarget.checked = false;
		}

		// Scroll smooth vers la zone pour voir qu'elle est fermée
		if (this.hasSearchZoneTarget) {
			this.searchZoneTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
	}

	/**
	 * Vérifie si des pictogrammes sont sélectionnés et affiche/masque le message
	 */
	checkEmptyState() {
		if (!this.hasSelectedContainerTarget || !this.hasEmptyMessageTarget) return;

		const hasPictograms = this.selectedContainerTarget.querySelectorAll('[data-pictogram-url]').length > 0;

		if (hasPictograms) {
			this.emptyMessageTarget.classList.add('hidden');
		} else {
			this.emptyMessageTarget.classList.remove('hidden');
		}
	}
}
