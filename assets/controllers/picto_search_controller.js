import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
	static targets = [ "input", "results", "brandInput", "pagination", "status", "urlInput", "filters", "detailsTab", "wikimediaTab" ];

	connect() {
		console.log("PictoSearchController connected");
		this.currentPage = 1;
		this.itemsPerPage = 12;
		this.allResults = [];
		this.filteredResults = [];
		this.activeSources = new Set([ 'openfoodfacts', 'arasaac', 'wikimedia', 'local' ]);

		if (this.hasInputTarget) {
			this.inputTarget.addEventListener("input", () => {
				clearTimeout(this.timer);
				this.timer = setTimeout(() => this.search(), 350);
			});
		} else {
			console.warn('PictoSearchController: no input target found - skipping input listener');
		}

		// Écouter aussi le champ marque si présent
		if (this.hasBrandInputTarget) {
			this.brandInputTarget.addEventListener("input", () => {
				clearTimeout(this.timer);
				this.timer = setTimeout(() => this.search(), 350);
			});
		}

		this.showTab({ params: { tab: 'details' } });
	}

	showTab(event) {
		event?.preventDefault?.();
		const tab = event?.params?.tab || event?.currentTarget?.dataset?.tab;
		if (!tab) {
			return;
		}

		if (this.hasDetailsTabTarget) {
			this.detailsTabTarget.classList.toggle('hidden', tab !== 'details');
		}
		if (this.hasWikimediaTabTarget) {
			this.wikimediaTabTarget.classList.toggle('hidden', tab !== 'wikimedia');
		}

		const tabs = this.element.querySelectorAll('.tabs button');
		tabs.forEach((button) => {
			button.classList.toggle('tab-active', button.dataset.tab === tab);
		});
	}

	async search() {
		// Read query value from the input target; if missing, try event or bail out
		const q = (this.hasInputTarget ? this.inputTarget.value.trim() : '').trim();
		if (!q) {
			// nothing to search, clear results if present
			if (this.hasResultsTarget) this.resultsTarget.innerHTML = '';
			if (this.hasPaginationTarget) this.paginationTarget.innerHTML = '';
			this.setStatus('');
			return;
		}
		if (q.length < 2) {
			this.resultsTarget.innerHTML = "";
			if (this.hasPaginationTarget) {
				this.paginationTarget.innerHTML = "";
			}
			this.setStatus('');
			return;
		}

		// Afficher un indicateur de chargement
		if (this.hasResultsTarget) {
			this.resultsTarget.innerHTML = `
			<div class="col-span-full flex justify-center items-center py-8">
				<span class="loading loading-spinner loading-lg text-primary"></span>
			</div>
		`;
		} else {
			console.warn('PictoSearchController.search: missing results target; skipping UI update');
		}

		// Récupérer la marque si présente
		const brand = this.hasBrandInputTarget ? this.brandInputTarget.value.trim() : '';
		this.setStatus('');
		this.lastQuery = this.normalize(q);
		const normalizedQuery = this.normalize(q);

		const offUrl = this.buildOffUrl(normalizedQuery, brand);
		const wikiUrl = this.buildWikimediaUrl(normalizedQuery);
		const arasaacUrl = this.buildArasaacUrl(normalizedQuery);

		try {
			const [ offResult, wikiResult, arasaacResult ] = await Promise.allSettled([
				fetch(offUrl),
				fetch(wikiUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
				fetch(arasaacUrl),
			]);

			const offItems = await this.parseOffResults(offResult);
			const wikiItems = await this.parseWikimediaResults(wikiResult);
			const arasaacItems = await this.parseArasaacResults(arasaacResult);

			const limitedWiki = wikiItems.slice(0, 40);
			const combined = [
				...arasaacItems.map((item, index) => ({ ...item, _index: index })),
				...offItems.map((item, index) => ({ ...item, _index: index })),
				...limitedWiki.map((item, index) => ({ ...item, _index: index })),
			];

			this.allResults = this.rankResults(combined, q);
			this.currentPage = 1;
			this.applyFilters();
			this.renderCurrentPage();

			const warnings = [];
			if (arasaacItems.length === 0) warnings.push('ARASAAC indisponible ou aucun resultat.');
			if (offItems.length === 0) warnings.push('OpenFoodFacts indisponible ou aucun resultat.');
			if (limitedWiki.length === 0) warnings.push('Wikimedia indisponible ou aucun resultat.');
			this.setStatus(warnings.length ? warnings.join(' ') : '');
		} catch (e) {
			console.error('Erreur recherche combinee:', e);
			this.resultsTarget.innerHTML = `
				<div class="col-span-full alert alert-error text-sm">
					<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					<span>Impossible de lancer la recherche combinee.</span>
				</div>
			`;
			this.setStatus('');
		}
	}

	renderCurrentPage() {
		if (!this.hasResultsTarget) {
			console.warn('PictoSearchController.renderCurrentPage: no results target found');
			return;
		}
		this.resultsTarget.innerHTML = "";

		if (this.filteredResults.length === 0) {
			this.resultsTarget.innerHTML = `
				<div class="col-span-full text-center text-sm text-base-content/70 py-4">
					<svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
					</svg>
					<p>Aucune image trouvée. Essayez avec un autre terme ou une marque différente.</p>
				</div>
			`;
			if (this.hasPaginationTarget) {
				this.paginationTarget.innerHTML = "";
			}
			return;
		}

		// Calculer la plage d'items à afficher
		const start = (this.currentPage - 1) * this.itemsPerPage;
		const end = start + this.itemsPerPage;
		const pageItems = this.filteredResults.slice(start, end);

		pageItems.forEach(item => {
			const wrapper = document.createElement("div");
			wrapper.className = "relative group";

			const badge = document.createElement('span');
			badge.className = `badge badge-sm ${this.badgeClass(item.source)} absolute top-2 left-2 z-10 shadow`;
			badge.textContent = this.badgeLabel(item.source);
			wrapper.appendChild(badge);

			const img = document.createElement('img');
			img.src = item.image || '';
			img.alt = item.name || '';
			img.className = "w-full h-32 object-contain bg-white p-1 rounded-lg border cursor-pointer transition-all duration-300 hover:shadow-lg group-hover:scale-150 group-hover:z-50 group-hover:relative";
			img.referrerPolicy = 'no-referrer';
			img.loading = 'lazy';
			img.onerror = function () {
				console.warn('Picto image failed to load:', this.src);
				const back = document.createElement('div');
				back.className = "w-full h-32 bg-base-300 flex items-center justify-center text-2xl p-1 rounded-lg";
				back.textContent = '❌';
				try { this.parentNode.replaceChild(back, this); } catch (e) { console.warn('Replace with fallback failed', e); }
			};
			img.addEventListener('click', () => this.select(item, img));
			wrapper.appendChild(img);
			this.resultsTarget.appendChild(wrapper);
		});

		// Afficher la pagination
		this.renderPagination();

		// reset scroll au cas où
		this.resultsTarget.scrollTop = 0;
	}

	renderPagination() {
		if (!this.hasPaginationTarget) return;

		const totalPages = Math.ceil(this.filteredResults.length / this.itemsPerPage);

		if (totalPages <= 1) {
			this.paginationTarget.innerHTML = "";
			return;
		}

		const buttons = [];

		// Bouton Précédent
		if (this.currentPage > 1) {
			buttons.push(`
				<button class="btn btn-sm" data-action="click->picto-search#previousPage">
					<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
					</svg>
					Précédent
				</button>
			`);
		} else {
			buttons.push(`<button class="btn btn-sm btn-disabled">Précédent</button>`);
		}

		// Info page courante
		buttons.push(`
			<span class="text-sm px-4 py-2">
				Page ${this.currentPage} / ${totalPages} 
				<span class="opacity-60">(${this.filteredResults.length} résultats)</span>
			</span>
		`);

		// Bouton Suivant
		if (this.currentPage < totalPages) {
			buttons.push(`
				<button class="btn btn-sm" data-action="click->picto-search#nextPage">
					Suivant
					<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
					</svg>
				</button>
			`);
		} else {
			buttons.push(`<button class="btn btn-sm btn-disabled">Suivant</button>`);
		}

		this.paginationTarget.innerHTML = `
			<div class="flex justify-center items-center gap-2 mt-4">
				${buttons.join('')}
			</div>
		`;
	}

	nextPage() {
		const totalPages = Math.ceil(this.allResults.length / this.itemsPerPage);
		if (this.currentPage < totalPages) {
			this.currentPage++;
			this.renderCurrentPage();
		}
	}

	previousPage() {
		if (this.currentPage > 1) {
			this.currentPage--;
			this.renderCurrentPage();
		}
	}

	toggleSource(event) {
		const source = event.currentTarget.dataset.source;
		if (!source) return;

		if (this.activeSources.has(source)) {
			this.activeSources.delete(source);
			event.currentTarget.classList.remove('btn-primary');
			event.currentTarget.classList.add('btn-outline');
		} else {
			this.activeSources.add(source);
			event.currentTarget.classList.add('btn-primary');
			event.currentTarget.classList.remove('btn-outline');
		}

		this.currentPage = 1;
		this.applyFilters();
		this.renderCurrentPage();
	}

	applyFilters() {
		this.filteredResults = this.allResults.filter(item => this.activeSources.has(item.source));
	}

	buildOffUrl(query, brand) {
		const normalized = this.normalize(query);
		let url = `/api/picto/search?q=${encodeURIComponent(normalized)}&limit=100`;
		if (brand) url += `&brand=${encodeURIComponent(this.normalize(brand))}`;
		return url;
	}

	buildWikimediaUrl(query) {
		const normalized = this.normalize(query);
		return `/api/pictograms/wikimedia/search?q=${encodeURIComponent(normalized)}&limit=100`;
	}

	buildArasaacUrl(query) {
		const normalized = this.normalize(query);
		return `/api/pictograms/search?q=${encodeURIComponent(normalized)}`;
	}

	async parseOffResults(result) {
		if (result.status !== 'fulfilled') {
			console.warn('OpenFoodFacts request failed', result.reason);
			return [];
		}

		const res = result.value;
		if (!res.ok) {
			console.warn('OpenFoodFacts HTTP error', res.status);
			return [];
		}

		const data = await res.json();
		if (!data.success || !Array.isArray(data.results)) return [];

		return data.results
			.filter(item => item.image)
			.map(item => ({
				image: item.image,
				name: item.name || item.product_name || 'Image OpenFoodFacts',
				source: 'openfoodfacts',
			}));
	}

	async parseWikimediaResults(result) {
		if (result.status !== 'fulfilled') {
			console.warn('Wikimedia request failed', result.reason);
			return [];
		}

		const res = result.value;
		if (!res.ok) {
			console.warn('Wikimedia HTTP error', res.status);
			return [];
		}

		const data = await res.json();
		const results = Array.isArray(data.results) ? data.results : (Array.isArray(data) ? data : []);
		return results
			.map(item => ({
				image: item.file_url || item.thumbnail_url || '',
				name: this.cleanTitle(item.title) || this.cleanTitle(item.file_url || item.thumbnail_url || '') || 'Image Wikimedia',
				source: 'wikimedia',
			}))
			.filter(item => item.image);
	}

	async parseArasaacResults(result) {
		if (result.status !== 'fulfilled') {
			console.warn('ARASAAC request failed', result.reason);
			return [];
		}

		const res = result.value;
		if (!res.ok) {
			console.warn('ARASAAC HTTP error', res.status);
			return [];
		}

		const data = await res.json();
		const results = Array.isArray(data.results) ? data.results : [];
		return results
			.map(item => ({
				image: item.imageUrl || '',
				name: item.name || item.keywords || 'Pictogramme',
				source: item.source || 'arasaac',
			}))
			.filter(item => item.image);
	}

	rankResults(items, query) {
		const normalizedQuery = this.normalize(query);
		return items
			.map((item, index) => ({
				...item,
				score: this.score(item, normalizedQuery),
				_tie: index,
			}))
			.sort((a, b) => {
				if (b.score !== a.score) return b.score - a.score;
				return a._tie - b._tie;
			})
			.map(({ _tie, score, ...rest }) => rest);
	}

	score(item, normalizedQuery) {
		const name = this.normalize(item.name || '');
		if (!name || !normalizedQuery) return 0;

		const queryTokens = this.tokenize(normalizedQuery);
		const nameTokens = this.tokenize(name);
		const overlap = queryTokens.filter(t => nameTokens.includes(t));
		if (overlap.length === 0) return 0;

		let score = 0;
		if (name === normalizedQuery) score += 120;
		if (name.startsWith(normalizedQuery)) score += 80;
		if (name.includes(normalizedQuery)) score += 40;

		score += overlap.length * 20;
		score += Math.round((overlap.length / queryTokens.length) * 30);

		score += this.sourceWeight(item.source);
		return score;
	}

	matchesQuery(name, normalizedQuery) {
		if (!normalizedQuery) return true;
		const normalizedName = this.normalize(name || '');
		if (!normalizedName) return false;
		return normalizedName.includes(normalizedQuery);
	}

	normalize(value) {
		return String(value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/\p{Diacritic}/gu, '')
			.replace(/[^a-z0-9 ]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	tokenize(value) {
		return this.normalize(value)
			.split(' ')
			.filter(Boolean);
	}

	cleanTitle(title) {
		return String(title || '')
			.replace(/^File:/i, '')
			.replace(/\.[a-z0-9]{2,5}$/i, '')
			.replaceAll('_', ' ')
			.trim();
	}

	badgeLabel(source) {
		if (source === 'wikimedia') return 'Wikimedia';
		if (source === 'local') return 'Local';
		if (source === 'arasaac') return 'ARASAAC';
		return 'OpenFoodFacts';
	}

	badgeClass(source) {
		if (source === 'wikimedia') return 'badge-accent';
		if (source === 'arasaac') return 'badge-success';
		if (source === 'local') return 'badge-neutral';
		return 'badge-info';
	}

	sourceWeight(source) {
		if (source === 'openfoodfacts') return 60;
		if (source === 'arasaac') return 40;
		if (source === 'local') return 50;
		return 0;
	}

	setStatus(message, type = 'warning') {
		if (!this.hasStatusTarget) return;
		this.statusTarget.textContent = message || '';
		this.statusTarget.className = 'text-sm min-h-5';
		if (!message) return;
		if (type === 'success') {
			this.statusTarget.classList.add('text-success');
		} else if (type === 'error') {
			this.statusTarget.classList.add('text-error');
		} else {
			this.statusTarget.classList.add('text-warning');
		}
	}

	select(item, element) {
		// Effacer l'ancien highlight
		this.resultsTarget.querySelectorAll("div.group").forEach(wrapper => {
			const img = wrapper.querySelector("img");
			if (img) {
				img.classList.remove("ring", "ring-primary", "ring-2");
			}
		});

		// Ajouter le surlignage
		element.classList.add("ring", "ring-primary", "ring-2");

		// 🔥 MAJ du champ hidden
		if (this.hasUrlInputTarget) {
			this.urlInputTarget.value = item.image;
			this.urlInputTarget.dispatchEvent(new Event('change', { bubbles: true }));
		} else {
			const hidden = document.getElementById("externalImageTemp");
			if (hidden) hidden.value = item.image;
		}

		// Préremplir le nom si vide
		const nameInput = this.element.querySelector('input[name$="[name]"]');
		if (nameInput && !nameInput.value.trim()) {
			nameInput.value = item.name || '';
		}

		// 🔥 Envoi de l’événement pour la preview
		this.element.dispatchEvent(
			new CustomEvent("picto:selected", { detail: item, bubbles: true })
		);

		this.setStatus('Image sélectionnée. Revenez à l’onglet Détails pour vérifier et enregistrer.', 'success');
	}
}
