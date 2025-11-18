import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
	static targets = [ "input", "results", "brandInput", "pagination" ];

	connect() {
		console.log("PictoSearchController connected");
		this.currentPage = 1;
		this.itemsPerPage = 12;
		this.allResults = [];

		this.inputTarget.addEventListener("input", () => {
			clearTimeout(this.timer);
			this.timer = setTimeout(() => this.search(), 350);
		});

		// Écouter aussi le champ marque si présent
		if (this.hasBrandInputTarget) {
			this.brandInputTarget.addEventListener("input", () => {
				clearTimeout(this.timer);
				this.timer = setTimeout(() => this.search(), 350);
			});
		}
	}

	async search() {
		const q = this.inputTarget.value.trim();
		if (q.length < 2) {
			this.resultsTarget.innerHTML = "";
			if (this.hasPaginationTarget) {
				this.paginationTarget.innerHTML = "";
			}
			return;
		}

		// Afficher un indicateur de chargement
		this.resultsTarget.innerHTML = `
			<div class="col-span-full flex justify-center items-center py-8">
				<span class="loading loading-spinner loading-lg text-primary"></span>
			</div>
		`;

		// Récupérer la marque si présente
		const brand = this.hasBrandInputTarget ? this.brandInputTarget.value.trim() : '';

		try {
			let url = `/api/picto/search?q=${encodeURIComponent(q)}&limit=100`;
			if (brand) {
				url += `&brand=${encodeURIComponent(brand)}`;
			}

			const res = await fetch(url);

			if (!res.ok) {
				throw new Error(`HTTP error! status: ${res.status}`);
			}

			const data = await res.json();

			if (!data.success) {
				this.resultsTarget.innerHTML = `
					<div class="col-span-full alert alert-warning text-sm">
						<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
						</svg>
						<span>Erreur de recherche</span>
					</div>
				`;
				return;
			}

			// Stocker tous les résultats et réinitialiser la pagination
			this.allResults = data.results.filter(item => item.image);
			this.currentPage = 1;
			this.renderCurrentPage();
		} catch (e) {
			console.error("Erreur OFF:", e);
			this.resultsTarget.innerHTML = `
				<div class="col-span-full alert alert-error text-sm">
					<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					<span>Impossible de contacter OpenFoodFacts. Vérifiez votre connexion.</span>
				</div>
			`;
		}
	}

	renderCurrentPage() {
		this.resultsTarget.innerHTML = "";

		if (this.allResults.length === 0) {
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
		const pageItems = this.allResults.slice(start, end);

		pageItems.forEach(item => {
			const wrapper = document.createElement("div");
			wrapper.className = "relative group";

			const cssClass = "w-full h-32 object-contain bg-white p-1 rounded-lg border cursor-pointer transition-all duration-300 hover:shadow-lg group-hover:scale-150 group-hover:z-50 group-hover:relative";
			const skeleton = document.createElement('div');
			skeleton.className = `skeleton ${cssClass}`;
			skeleton.setAttribute('data-image-src', item.image || '');

			const loadImageIntoSkeleton = (sk) => {
				const src = sk.getAttribute('data-image-src');
				if (!src) return;
				const imgEl = document.createElement('img');
				imgEl.alt = item.name || '';
				imgEl.className = cssClass;
				imgEl.referrerPolicy = 'no-referrer';
				imgEl.loading = 'lazy';
				imgEl.onload = () => { try { sk.parentNode.replaceChild(imgEl, sk); } catch (e) { console.warn('Replace skeleton with img failed', e); } };
				imgEl.onerror = () => { console.warn('Picto image failed to load:', src); const back = document.createElement('div'); back.className = cssClass.replace('object-contain', 'bg-base-300 flex items-center justify-center text-2xl'); back.textContent = '❌'; try { sk.parentNode.replaceChild(back, sk); } catch (e) { console.warn('Replace with fallback failed', e); } };
				imgEl.addEventListener('click', () => this.select(item, imgEl));
				imgEl.src = src;
			};

			wrapper.appendChild(skeleton);
			loadImageIntoSkeleton(skeleton);
			this.resultsTarget.appendChild(wrapper);
		});

		// Afficher la pagination
		this.renderPagination();

		// reset scroll au cas où
		this.resultsTarget.scrollTop = 0;
	}

	renderPagination() {
		if (!this.hasPaginationTarget) return;

		const totalPages = Math.ceil(this.allResults.length / this.itemsPerPage);

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
				<span class="opacity-60">(${this.allResults.length} résultats)</span>
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

		// 🔥 MAJ du champ hidden externeImageTemp
		const hidden = document.getElementById("externalImageTemp");
		if (hidden) hidden.value = item.image;

		// 🔥 Envoi de l’événement pour la preview
		this.element.dispatchEvent(
			new CustomEvent("picto:selected", { detail: item, bubbles: true })
		);
	}
}
