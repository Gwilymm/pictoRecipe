import { Controller } from '@hotwired/stimulus';
import { esc, CLOCK_ICON, IMAGE_ICON } from '../lib/ui.js';

const SOURCE_LABELS = { cuisineaz: 'Cuisine AZ', papillesetpupilles: 'Papilles & Pupilles', marmiton: 'Marmiton' };

export default class extends Controller {
	static targets = [
		'form',
		'query',
		'vegan',
		'vegetarian',
		'withoutGluten',
		'withoutDairyProducts',
		'withoutOven',
		'raw',
		'submitBtn',
		'submitText',
		'spinner',
		'results',
		'pagination',
		'count',
		'infoMessage',
		'errorMessage',
		'errorText'
	];

	connect() {
		console.log('Recipe controller connected');

		// Pagination state
		this.resultsAll = [];
		this.perPage = 8; // results per page
		this.currentPage = 1;
		this.totalPages = 0;

		// Listen for sort controller requests (it will provide a sorted array)
		this.element.addEventListener('sort:apply', (e) => {
			if (e && e.detail && Array.isArray(e.detail.sorted)) {
				// When applying results coming from the sort controller, avoid re-dispatching the event
				this._ignoreSortEvent = true;
				this.setResults(e.detail.sorted);
				setTimeout(() => { this._ignoreSortEvent = false; }, 0);
			}
		});

		this._ignoreSortEvent = false;
		this.lastQuery = '';
	}

	/* ============================================================
	   🔍 1. RECHERCHE MARMITON
	============================================================ */
	async search(event) {
		event.preventDefault();
		event.stopPropagation();

		this.hideMessages();
		this.showLoading();

		const criteria = {
			query: this.queryTarget.value.trim(),
			vegan: this.veganTarget.checked,
			vegetarian: this.vegetarianTarget.checked,
			withoutGluten: this.withoutGlutenTarget.checked,
			withoutDairyProducts: this.withoutDairyProductsTarget.checked,
			withoutOven: this.withoutOvenTarget.checked,
			raw: this.rawTarget.checked
		};

		if (!criteria.query &&
			!criteria.vegan &&
			!criteria.vegetarian &&
			!criteria.withoutGluten &&
			!criteria.withoutDairyProducts &&
			!criteria.withoutOven &&
			!criteria.raw
		) {
			this.showInfoMessage('Veuillez saisir au moins un critère.');
			this.hideLoading();
			return;
		}

		try {
			let searchTerms = [];

			if (criteria.query) searchTerms.push(criteria.query);
			if (criteria.withoutGluten) searchTerms.push("sans gluten");
			if (criteria.vegetarian) searchTerms.push("végétarien");
			if (criteria.vegan) searchTerms.push("vegan");
			if (criteria.withoutDairyProducts) searchTerms.push("sans lactose");
			if (criteria.withoutOven) searchTerms.push("sans four");
			if (criteria.raw) searchTerms.push("cru");

			const searchQuery = searchTerms.join(" ").replace(/\s+/g, "-");

			// Run both searches in parallel for better responsiveness
			const marmitonReq = fetch('/api/marmiton/search', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ q: searchQuery, limit: 40, filters: {} })
			});
			const cuisineReq = fetch('/api/cuisineaz/search', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				// Convert hyphenated searchQuery back to spaces for CuisineAZ
				body: JSON.stringify({ q: searchQuery.replace(/-/g, ' '), limit: 40 })
			});
			const papillesReq = fetch('/api/papillesetpupilles/search', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ q: searchQuery.replace(/-/g, ' '), limit: 40 })
			});

			const results = await Promise.allSettled([ marmitonReq, cuisineReq, papillesReq ]);
			let combined = [];

			// Process Marmiton result
			if (results[ 0 ]?.status === 'fulfilled') {
				try {
					const response = results[ 0 ].value;
					const data = await response.json();
					const marmitonResults = Array.isArray(data) ? data : (Array.isArray(data.results) ? data.results : []);
					if (response.ok && (data.success !== false) && marmitonResults.length > 0) {
						combined = combined.concat(marmitonResults.map(r => ({ ...r, source: r.source || 'marmiton' })));
					}
				} catch (e) {
					console.error('Marmiton response handling error', e);
				}
			} else {
				console.debug('Marmiton search failed', results[ 0 ]?.reason);
			}

			// Process CuisineAZ result
			if (results[ 1 ]?.status === 'fulfilled') {
				try {
					const response2 = results[ 1 ].value;
					const data2 = await response2.json();
					const cuisineResults = Array.isArray(data2) ? data2 : (Array.isArray(data2.results) ? data2.results : []);
					if (response2.ok && (data2.success !== false) && cuisineResults.length > 0) {
						combined = combined.concat(cuisineResults.map(r => ({ ...r, source: r.source || 'cuisineaz' })));
					}
				} catch (e) {
					console.error('CuisineAZ response handling error', e);
				}
			} else {
				console.debug('CuisineAZ search failed', results[ 1 ]?.reason);
			}

			if (results[ 2 ]?.status === 'fulfilled') {
				try {
					const response3 = results[ 2 ].value;
					const data3 = await response3.json();
					const papillesResults = Array.isArray(data3) ? data3 : (Array.isArray(data3.results) ? data3.results : []);
					if (response3.ok && (data3.success !== false) && papillesResults.length > 0) {
						combined = combined.concat(papillesResults.map(r => ({ ...r, source: r.source || 'papillesetpupilles' })));
					}
				} catch (e) {
					console.error('Papilles & Pupilles response handling error', e);
				}
			} else {
				console.debug('Papilles & Pupilles search failed', results[ 2 ]?.reason);
			}

			if (combined.length > 0) {
				// Deduplicate by URL (keep first occurrence)
				const seen = new Set();
				const unique = [];
				combined.forEach(r => {
					const key = r.url || r.link || r.title;
					if (!key) return;
					if (!seen.has(key)) { seen.add(key); unique.push(r); }
				});
				this.lastQuery = criteria.query || (this.queryTarget ? this.queryTarget.value : '');
				// compute scores now that we stored the latest query
				unique.forEach(r => r.score = this.computeScore(r, this.lastQuery));
				this.setResults(unique);
			} else {
				this.showInfoMessage('Aucune recette trouvée.');
				this.resultsTarget.innerHTML = '';
			}

		} catch (err) {
			this.showError("Impossible de contacter le serveur.");
		} finally {
			this.hideLoading();
		}
	}


	/* ============================================================
	   Score utilities
	============================================================ */
	parseRating(raw) {
		if (raw == null) return 0;
		if (typeof raw === 'number') return Number(raw) || 0;
		const s = String(raw).trim();
		const match = s.match(/(\d+(?:[\.\,]\d+)?)/);
		if (!match) return 0;
		return parseFloat(match[ 1 ].replace(',', '.')) || 0;
	}

	computeScore(item, query = '') {
		const title = (item.title || item.name || '').toLowerCase();
		const q = (query || '').toLowerCase().trim();
		let score = 0;

		if (q && title.startsWith(q)) score += 30;
		if (q && title.includes(q) && !title.startsWith(q)) score += 10;

		const rating = this.parseRating(item.rating);
		score += (rating * 10);

		const src = (item.source || item.url || '').toLowerCase();
		if (src.includes('marmiton')) score += 20;

		return Math.round(score * 10) / 10; // round to 1 decimal
	}

	/* ============================================================
	   🟦 2. AFFICHAGE DES CARTES + PAGINATION
	============================================================ */
	setResults(results) {
		// Ensure we have the latest query (use stored value or input value)
		this.lastQuery = this.queryTarget ? (this.queryTarget.value || this.lastQuery) : (this.lastQuery || '');
		// Compute score for each item if not present
		results.forEach(r => { r.score = r.score || this.computeScore(r, this.lastQuery); });
		this.resultsAll = results;
		this.currentPage = 1;
		this.totalPages = Math.ceil(this.resultsAll.length / this.perPage);
		this.renderPage();
		this.updateCount();

		// Dispatch an event to notify other controllers (sort controller, etc.) that new results are available
		try {
			// Avoid flooding the sort controller if we are the result of a sort application
			if (!this._ignoreSortEvent) {
				this.resultsTarget.dispatchEvent(new CustomEvent('results:loaded', {
					bubbles: true,
					detail: { results: this.resultsAll, query: this.queryTarget ? this.queryTarget.value : '' }
				}));
			}
		} catch (e) {
			// silent fail — not critical
		}
	}

	renderPage() {
		if (!this.resultsAll || this.resultsAll.length === 0) {
			this.resultsTarget.innerHTML = '';
			if (this.paginationTarget) this.paginationTarget.innerHTML = '';
			if (this.countTarget) this.countTarget.textContent = '';
			return;
		}

		const start = (this.currentPage - 1) * this.perPage;
		const slice = this.resultsAll.slice(start, start + this.perPage);
		this.resultsTarget.innerHTML = '';
		// Ensure each item has a score computed
		slice.forEach(r => {
			r.score = r.score || this.computeScore(r, this.lastQuery);
			this.resultsTarget.appendChild(this.createRecipeCard(r));
		});
		this.renderPager();
		this.updateCount();
	}

	renderPager() {
		if (!this.paginationTarget) return;
		this.paginationTarget.innerHTML = '';
		if (this.totalPages <= 1) return;

		const container = document.createElement('nav');
		container.className = 'join';
		container.setAttribute('aria-label', 'Pagination');

		const pageButton = (page, label, { ariaLabel = null, current = false, disabled = false } = {}) => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'btn join-item' + (current ? ' border-primary bg-step-soft' : '');
			btn.textContent = label;
			btn.disabled = disabled;
			btn.dataset.page = page;
			btn.dataset.action = 'click->recipe-search#gotoPage';
			if (ariaLabel) btn.setAttribute('aria-label', ariaLabel);
			if (current) btn.setAttribute('aria-current', 'page');
			return btn;
		};

		container.appendChild(pageButton(Math.max(1, this.currentPage - 1), '‹', { ariaLabel: 'Page précédente', disabled: this.currentPage <= 1 }));

		// Numéros de page (7 visibles au plus)
		const maxVisible = 7;
		let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
		let endPage = Math.min(this.totalPages, startPage + maxVisible - 1);
		if (endPage - startPage + 1 < maxVisible) {
			startPage = Math.max(1, endPage - maxVisible + 1);
		}
		for (let p = startPage; p <= endPage; p++) {
			container.appendChild(pageButton(p, String(p), { current: p === this.currentPage }));
		}

		container.appendChild(pageButton(Math.min(this.totalPages, this.currentPage + 1), '›', { ariaLabel: 'Page suivante', disabled: this.currentPage >= this.totalPages }));

		this.paginationTarget.appendChild(container);
	}

	gotoPage(e) {
		e.preventDefault();
		const page = Number(e.currentTarget.dataset.page || e.target.dataset.page);
		if (isNaN(page) || page < 1) return;
		this.currentPage = page;
		this.renderPage();
	}

	updateCount() {
		if (!this.countTarget) return;
		const total = this.resultsAll.length || 0;
		const start = Math.min(total, (this.currentPage - 1) * this.perPage + 1);
		const end = Math.min(total, this.currentPage * this.perPage);
		this.countTarget.textContent = `${total} résultat${total > 1 ? 's' : ''} — affichage ${start}-${end}`;
	}

	createRecipeCard(recipe) {
		const card = document.createElement("div");
		card.className = "flex flex-col overflow-hidden rounded-box border border-base-300 bg-base-100";

		// Provide dataset attributes so external controller (sorting/filtering) can read metadata
		card.dataset.itemTitle = (recipe.title || '').toString();
		card.dataset.itemRating = (recipe.rating || '').toString();
		card.dataset.itemSource = (recipe.source || '').toString();
		card.dataset.itemUrl = (recipe.url || '').toString();

		// Photo 4/3 : squelette, puis image (via le proxy pour les hôtes qui bloquent le hotlink)
		const media = document.createElement('div');
		media.className = 'relative aspect-[4/3] overflow-hidden bg-step-soft';
		const skeleton = document.createElement('div');
		skeleton.className = 'skeleton absolute inset-0 rounded-none';
		media.appendChild(skeleton);

		const imageSrc = (() => {
			try {
				const parsed = new URL(recipe.image || '');
				const suffixes = [ 'afcdn.com', 'marmiton.org', 'papillesetpupilles.fr' ];
				if (suffixes.some(s => parsed.host.endsWith(s))) {
					return `/api/image-proxy?url=${encodeURIComponent(parsed.toString())}`;
				}
			} catch (e) {
				// ignore parse errors
			}
			return recipe.image || '';
		})();

		const showPlaceholder = () => {
			skeleton.remove();
			media.insertAdjacentHTML('beforeend', `<div class="grid h-full place-items-center text-primary">${IMAGE_ICON}</div>`);
		};
		if (imageSrc) {
			const imgEl = document.createElement('img');
			imgEl.alt = '';
			imgEl.className = 'absolute inset-0 h-full w-full object-cover';
			imgEl.referrerPolicy = 'no-referrer';
			imgEl.loading = 'lazy';
			imgEl.onload = () => skeleton.remove();
			imgEl.onerror = () => {
				console.warn('Recipe card image failed to load:', imageSrc);
				imgEl.remove();
				showPlaceholder();
			};
			imgEl.src = imageSrc;
			media.appendChild(imgEl);
		} else {
			showPlaceholder();
		}

		const body = document.createElement('div');
		body.className = 'flex flex-1 flex-col gap-2 p-4';

		const title = document.createElement("h3");
		title.className = "text-base leading-snug";
		title.textContent = recipe.title || '';

		const src = recipe.source || (recipe.url && recipe.url.includes('cuisineaz') ? 'cuisineaz' : recipe.url && recipe.url.includes('papillesetpupilles') ? 'papillesetpupilles' : 'marmiton');
		const meta = document.createElement('p');
		meta.className = 'text-sm text-muted';
		const rating = recipe.rating ? String(recipe.rating) : '';
		meta.textContent = (SOURCE_LABELS[ src ] || SOURCE_LABELS.marmiton) + (rating ? ` · Note ${rating.includes('/') ? rating : rating + '/5'}` : '');

		const btn = document.createElement("button");
		btn.type = 'button';
		btn.className = "btn btn-sm btn-outline mt-auto";
		btn.textContent = "Voir la recette";
		btn.addEventListener("click", () => this.openRecipeModal(recipe.url, src));

		body.append(title, meta, btn);
		card.append(media, body);
		return card;
	}

	/* ============================================================
	   3. MODALE
	============================================================ */
	async openRecipeModal(url, source = 'marmiton') {
		let modal = document.getElementById("recipe-modal");
		if (!modal) {
			modal = document.createElement("dialog");
			modal.id = "recipe-modal";
			modal.className = "modal";
			modal.innerHTML = `
				<div class="modal-box w-11/12 max-w-5xl max-h-[90vh] overflow-y-auto p-5 sm:p-8">
					<form method="dialog">
						<button class="btn btn-ghost btn-square absolute right-3 top-3" aria-label="Fermer">✕</button>
					</form>
					<div id="recipe-modal-content"></div>
				</div>
				<form method="dialog" class="modal-backdrop"><button>Fermer</button></form>`;
			document.body.appendChild(modal);
		}

		modal.showModal();

		const container = document.getElementById("recipe-modal-content");
		container.innerHTML = `<div class="flex justify-center py-8"><span class="loading loading-spinner loading-lg" aria-label="Chargement"></span></div>`;

		const endpoint = source === 'cuisineaz' || (url && url.includes('cuisineaz'))
			? '/api/cuisineaz/recipe'
			: source === 'papillesetpupilles' || (url && url.includes('papillesetpupilles'))
				? '/api/papillesetpupilles/recipe'
				: '/api/marmiton/recipe';
		const res = await fetch(endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ link: url })
		});

		const data = await res.json();

		if (!data.ok) {
			container.innerHTML = `<p class="rounded-box bg-danger-soft p-4 font-bold">Erreur : ${esc(data.error)}</p>`;
			return;
		}

		this.displayRecipeJson(data.recipe, container);
	}

	/* ============================================================
	   4. JSON → HTML (toutes les valeurs viennent d'un site tiers : tout passe par esc())
	============================================================ */
	displayRecipeJson(recipe, container) {
		const byline = [ recipe.author ? `Par ${esc(recipe.author)}` : '', esc(recipe.published) ].filter(Boolean).join(' — ');
		container.innerHTML = `
			<article class="space-y-6">
				<header class="space-y-3 pr-12">
					${recipe.title ? `<h2 class="text-2xl">${esc(recipe.title)}</h2>` : ''}
					${this.sectionPrimary(recipe)}
					${recipe.description ? `<p>${esc(recipe.description)}</p>` : ''}
					${byline ? `<p class="text-sm text-muted">${byline}</p>` : ''}
					${this.sectionTimes(recipe.times)}
				</header>
				${this.sectionIngredients(recipe.ingredients)}
				${this.sectionUtensils(recipe.utensils)}
				${this.sectionSteps(recipe.steps)}
				<div class="flex justify-end">
					<button type="button" class="btn btn-primary" id="import-recipe-btn">Importer cette recette</button>
				</div>
			</article>
		`;

		const importBtn = document.getElementById("import-recipe-btn");
		if (importBtn) {
			importBtn.addEventListener("click", () => this.importRecipe(recipe));
		}
	}

	/* ============================================================
	   5. SECTIONS (mêmes repères que la fiche : carotte / myrtille / basilic)
	============================================================ */
	sectionHeading(label, markClass) {
		return `<div class="flex items-center gap-3"><span class="section-mark ${markClass}" aria-hidden="true"></span><h3 class="text-lg">${label}</h3></div>`;
	}

	sectionPrimary(r) {
		if (!r.primary?.length) return "";
		return `<ul class="flex flex-wrap gap-2">${r.primary.map(i => `<li class="rounded-full bg-base-200 px-3 py-1 text-sm font-bold">${esc(i)}</li>`).join("")}</ul>`;
	}

	sectionTimes(times) {
		if (!times || !times.total) return "";
		const details = (times.details || []).map(d => `<li><span class="text-muted">${esc(d.label)}</span> ${esc(d.value)}</li>`).join("");
		return `
			<ul class="flex flex-wrap gap-x-6 gap-y-1 font-bold">
				<li class="inline-flex items-center gap-1.5">${CLOCK_ICON}<span class="text-muted">Total</span> ${esc(times.total)}</li>
				${details}
			</ul>`;
	}

	sectionIngredients(ingredients) {
		if (!ingredients || ingredients.length === 0) return "";

		const groups = {};
		ingredients.forEach(item => {
			const group = item.group || "";
			(groups[ group ] ||= []).push(item);
		});

		const groupsHtml = Object.entries(groups).map(([ groupName, items ]) => `
			<div class="space-y-3">
				${groupName ? `<h4 class="text-base">${esc(groupName)}</h4>` : ''}
				<ul class="grid gap-2 sm:grid-cols-2">
					${items.map(i => {
						const text = [ i.quantity, i.unit, i.name, i.complement ].filter(Boolean).join(" ").replace(/\s+/g, " ").trim();
						return `<li class="rounded-box border border-base-300 border-l-4 border-l-accent bg-base-100 px-3 py-2">${esc(text)}</li>`;
					}).join("")}
				</ul>
			</div>`).join("");

		return `<section class="space-y-4">${this.sectionHeading('Ingrédients', 'bg-accent')}${groupsHtml}</section>`;
	}

	sectionUtensils(utensils) {
		if (!utensils || utensils.length === 0) return "";

		// Sans doublons
		const seen = new Set();
		const unique = utensils.filter(u => {
			const key = `${u.name}|${u.quantity}`.trim();
			if (seen.has(key)) return false;
			seen.add(key);
			return true;
		});

		return `
			<section class="space-y-4">
				${this.sectionHeading('Ustensiles', 'bg-secondary')}
				<ul class="flex flex-wrap gap-2">
					${unique.map(u => `<li class="rounded-full bg-utensil-soft px-4 py-2 font-bold">${esc((u.quantity || u.name || '').trim())}</li>`).join("")}
				</ul>
			</section>`;
	}

	sectionSteps(steps) {
		if (!steps?.length) return "";
		return `
			<section class="space-y-4">
				${this.sectionHeading('Préparation', 'bg-primary')}
				<ol class="space-y-3">
					${steps.map((s, i) => `
						<li class="flex items-start gap-3 rounded-box border border-base-300 bg-base-100 p-3">
							<span class="step-badge" aria-hidden="true">${i + 1}</span>
							<p class="pt-1 leading-relaxed"><span class="sr-only">Étape ${i + 1} : </span>${esc(s.text)}</p>
						</li>`).join("")}
				</ol>
			</section>`;
	}

	/* ============================================================
	   🟥 UI HELPERS
	============================================================ */
	showLoading() {
		this.submitBtnTarget.disabled = true;
		this.spinnerTarget.classList.remove("hidden");
		this.submitTextTarget.classList.add("hidden");
	}

	hideLoading() {
		this.submitBtnTarget.disabled = false;
		this.spinnerTarget.classList.add("hidden");
		this.submitTextTarget.classList.remove("hidden");
	}

	showInfoMessage(msg) {
		this.infoMessageTarget.classList.remove("hidden");
		this.infoMessageTarget.querySelector("span").textContent = msg;
	}

	showError(msg) {
		this.errorMessageTarget.classList.remove("hidden");
		this.errorTextTarget.textContent = msg;
	}

	hideMessages() {
		this.infoMessageTarget.classList.add("hidden");
		this.errorMessageTarget.classList.add("hidden");
	}

	/* ============================================================
	   🟩 Import — POST /api/recipes/import
	============================================================ */
	async importRecipe(recipeJson) {
		try {
			// Prefer sending the full Marmiton structure
			const body = { ok: true, recipe: recipeJson };
			const response = await fetch("/api/recipes/import", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify(body)
			});

			const data = await response.json();

			if (data.ok) {
				// Close modal if open
				const modal = document.getElementById("recipe-modal");
				if (modal && typeof modal.close === 'function') {
					modal.close();
				}

				this.showToast("Recette importée avec succès !", "success");
				window.location.href = `/recipe/${data.id}/show`;
			} else {
				this.showToast(data.error || "Erreur lors de l'import.", "error");
			}
		} catch (e) {
			this.showToast("Erreur import : " + (e?.message || e), "error");
		}
	}

	showToast(message, type = "info") {
		// DaisyUI toast container
		let container = document.querySelector(".toast.toast-top.toast-end");
		if (!container) {
			container = document.createElement("div");
			container.className = "toast toast-top toast-end z-50";
			document.body.appendChild(container);
		}

		const alert = document.createElement("div");
		alert.className = `alert ${type === 'success' ? 'alert-success' : type === 'error' ? 'alert-error' : 'alert-info'}`;
		const span = document.createElement('span');
		span.textContent = message;
		alert.appendChild(span);
		container.appendChild(alert);

		setTimeout(() => {
			alert.remove();
			// cleanup if empty
			if (container.childElementCount === 0) {
				container.remove();
			}
		}, 3500);
	}
}
