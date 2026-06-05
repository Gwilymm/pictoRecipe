import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = [ 'input', 'limit', 'results', 'status', 'spinner' ];

	static values = {
		searchUrl: { type: String, default: '/api/pictograms/wikimedia/search' },
		importUrl: { type: String, default: '/api/pictograms/import' },
	};

	connect() {
		this.results = [];
	}

	async search(event) {
		event?.preventDefault();

		const query = this.inputTarget.value.trim();
		const limit = this.hasLimitTarget ? this.limitTarget.value : '12';

		if (query.length < 2) {
			this.setStatus('Saisissez au moins 2 caractères.', 'warning');
			this.clearResults();
			return;
		}

		this.showSpinner();
		this.setStatus('', null);
		this.clearResults();

		try {
			const url = `${this.searchUrlValue}?q=${encodeURIComponent(query)}&limit=${encodeURIComponent(limit)}`;
			const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.error || `Erreur HTTP ${response.status}`);
			}

			this.results = data.results || [];
			this.renderResults(this.results);
		} catch (error) {
			this.setStatus(error.message || 'Recherche Wikimedia indisponible.', 'error');
		} finally {
			this.hideSpinner();
		}
	}

	renderResults(results) {
		this.clearResults();

		if (results.length === 0) {
			this.setStatus('Aucune image Wikimedia trouvée.', 'warning');
			return;
		}

		const grid = document.createElement('div');
		grid.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3';

		results.forEach((item) => {
			grid.appendChild(this.createResultCard(item));
		});

		this.resultsTarget.appendChild(grid);
	}

	createResultCard(item) {
		const card = document.createElement('article');
		card.className = 'card bg-base-100 border border-base-300 rounded-lg overflow-hidden';

		const figure = document.createElement('figure');
		figure.className = 'bg-white h-40 p-3';

		const img = document.createElement('img');
		img.src = item.thumbnail_url || item.file_url || '';
		img.alt = this.cleanTitle(item.title) || 'Image Wikimedia';
		img.className = 'w-full h-full object-contain';
		img.loading = 'lazy';
		img.referrerPolicy = 'no-referrer';

		figure.appendChild(img);
		card.appendChild(figure);

		const body = document.createElement('div');
		body.className = 'card-body p-3 gap-2';

		const title = document.createElement('h3');
		title.className = 'font-semibold text-sm leading-snug line-clamp-2';
		title.textContent = this.cleanTitle(item.title) || 'Image Wikimedia';
		body.appendChild(title);

		const license = document.createElement('p');
		license.className = 'text-xs text-base-content/70';
		license.textContent = item.license || 'Licence non renseignee';
		body.appendChild(license);

		const attribution = document.createElement('p');
		attribution.className = 'text-xs text-base-content/60 line-clamp-3';
		attribution.textContent = item.attribution || 'Attribution Wikimedia Commons';
		body.appendChild(attribution);

		const actions = document.createElement('div');
		actions.className = 'card-actions justify-end mt-1';

		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'btn btn-primary btn-sm';
		button.textContent = 'Utiliser cette image';
		button.addEventListener('click', () => this.importItem(item, button));
		actions.appendChild(button);

		body.appendChild(actions);
		card.appendChild(body);

		return card;
	}

	async importItem(item, button) {
		button.disabled = true;
		button.classList.add('loading');

		try {
			const payload = {
				label: this.cleanTitle(item.title),
				title: item.title,
				source: item.source,
				source_id: item.source_id,
				image_url: item.file_url,
				thumbnail_url: item.thumbnail_url,
				license: item.license,
				license_url: item.license_url,
				author: item.author,
				credit: item.credit,
				attribution: item.attribution,
				mime: item.mime,
			};

			const response = await fetch(this.importUrlValue, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify(payload),
			});
			const data = await response.json();

			if (!response.ok) {
				throw new Error(data.error || `Erreur HTTP ${response.status}`);
			}

			button.classList.remove('btn-primary');
			button.classList.add('btn-success');
			button.textContent = 'Image importee';
			this.setStatus(data.message || 'Image importee dans la bibliotheque.', 'success');
		} catch (error) {
			button.disabled = false;
			this.setStatus(error.message || "Impossible d'importer cette image.", 'error');
		} finally {
			button.classList.remove('loading');
		}
	}

	cleanTitle(title) {
		return String(title || '')
			.replace(/^File:/i, '')
			.replace(/\.[a-z0-9]{2,5}$/i, '')
			.replaceAll('_', ' ')
			.trim();
	}

	setStatus(message, type) {
		if (!this.hasStatusTarget) return;

		this.statusTarget.textContent = message;
		this.statusTarget.className = 'text-sm min-h-5';

		if (type === 'success') this.statusTarget.classList.add('text-success');
		if (type === 'warning') this.statusTarget.classList.add('text-warning');
		if (type === 'error') this.statusTarget.classList.add('text-error');
	}

	clearResults() {
		if (this.hasResultsTarget) {
			this.resultsTarget.innerHTML = '';
		}
	}

	showSpinner() {
		if (this.hasSpinnerTarget) {
			this.spinnerTarget.classList.remove('hidden');
		}
	}

	hideSpinner() {
		if (this.hasSpinnerTarget) {
			this.spinnerTarget.classList.add('hidden');
		}
	}
}
