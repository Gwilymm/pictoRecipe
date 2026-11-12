import { Controller } from '@hotwired/stimulus';
import { esc, IMAGE_ICON } from '../lib/ui.js';

export default class extends Controller {
	static targets = [ 'url', 'fetchBtn', 'message', 'preview', 'importBtn' ];

	connect() {
		this.recipeData = null;
	}

	clear(e) {
		e && e.preventDefault();
		if (this.hasUrlTarget) this.urlTarget.value = '';
		if (this.hasPreviewTarget) this.previewTarget.innerHTML = '';
		if (this.hasImportBtnTarget) this.importBtnTarget.classList.add('hidden');
		if (this.hasMessageTarget) this.messageTarget.textContent = '';
	}

	async fetchRecipe(e) {
		e && e.preventDefault();
		const url = this.urlTarget?.value?.trim();
		if (!url) {
			this.showMessage('Veuillez saisir une URL');
			return;
		}

		this.showMessage('Chargement...');
		if (this.hasFetchBtnTarget) this.fetchBtnTarget.disabled = true;

		try {
			const endpoint = url.includes('cuisineaz')
				? '/api/cuisineaz/recipe'
				: url.includes('papillesetpupilles.fr')
					? '/api/papillesetpupilles/recipe'
					: '/api/marmiton/recipe';
			const res = await fetch(endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ link: url })
			});
			const data = await res.json();
			if (!data.ok) {
				this.showMessage('Erreur: ' + (data.error || 'Impossible de récupérer la recette'));
				return;
			}
			this.recipeData = data.recipe;
			this.renderPreview(this.recipeData);
			if (this.hasImportBtnTarget) {
				this.importBtnTarget.classList.remove('hidden');
				this.importBtnTarget.disabled = false;
			}
			this.showMessage('Recette récupérée — vous pouvez l’importer');
		} catch (err) {
			console.error('fetchRecipe error', err);
			this.showMessage('Erreur: impossible de récupérer la recette');
		} finally {
			if (this.hasFetchBtnTarget) this.fetchBtnTarget.disabled = false;
		}
	}

	showMessage(msg) {
		if (this.hasMessageTarget) this.messageTarget.textContent = msg;
	}

	renderPreview(recipe) {
		if (!this.hasPreviewTarget) return;
		// Toutes les valeurs viennent du site importé : tout passe par esc()
		const image = (() => {
			try {
				const url = new URL(recipe.image || recipe.picture || '');
				const allowedSuffixes = [ 'afcdn.com', 'marmiton.org', 'papillesetpupilles.fr' ];
				if (allowedSuffixes.some(s => url.host.endsWith(s))) return `/api/image-proxy?url=${encodeURIComponent(url.toString())}`;
				return url.protocol === 'https:' || url.protocol === 'http:' ? url.toString() : '';
			} catch (e) {
				return '';
			}
		})();
		const original = (() => {
			try {
				const url = new URL(recipe.url || '');
				return url.protocol === 'https:' || url.protocol === 'http:' ? url.toString() : '';
			} catch (e) {
				return '';
			}
		})();
		const byline = [ recipe.author ? `Par ${esc(recipe.author)}` : '', esc(recipe.published) ].filter(Boolean).join(' — ');

		this.previewTarget.innerHTML = `
			<article class="space-y-6">
				<header class="flex flex-col gap-5 sm:flex-row sm:items-start">
					<div class="relative aspect-[4/3] w-full shrink-0 overflow-hidden rounded-box bg-step-soft sm:w-44" data-image-frame>
						${image ? `<div class="skeleton absolute inset-0 rounded-none" data-image-src="${esc(image)}"></div>` : `<div class="grid h-full place-items-center text-primary">${IMAGE_ICON}</div>`}
					</div>
					<div class="min-w-0 flex-1 space-y-2">
						<h2 class="text-xl">${esc(recipe.title || 'Titre')}</h2>
						${byline ? `<p class="text-sm text-muted">${byline}</p>` : ''}
						${recipe.description ? `<p>${esc(recipe.description)}</p>` : ''}
						${recipe.primary?.length ? `<ul class="flex flex-wrap gap-2">${recipe.primary.map(p => `<li class="rounded-full bg-base-200 px-3 py-1 text-sm font-bold">${esc(p)}</li>`).join('')}</ul>` : ''}
						${original ? `<a class="link link-hover inline-flex items-center gap-1 text-secondary" href="${esc(original)}" target="_blank" rel="noopener">Voir la recette d’origine</a>` : ''}
					</div>
				</header>
				<div class="grid gap-6 md:grid-cols-2">
					<section class="space-y-3">
						<div class="flex items-center gap-3"><span class="section-mark bg-accent" aria-hidden="true"></span><h3 class="text-base">Ingrédients</h3></div>
						<ul class="space-y-1.5">${(recipe.ingredients || []).map(i => `<li class="border-l-4 border-accent pl-3">${esc([ i.quantity, i.unit, i.name ].filter(Boolean).join(' '))}</li>`).join('')}</ul>
					</section>
					<section class="space-y-3">
						<div class="flex items-center gap-3"><span class="section-mark bg-primary" aria-hidden="true"></span><h3 class="text-base">Préparation</h3></div>
						<ol class="list-decimal space-y-1.5 pl-6 marker:font-bold">${(recipe.steps || []).map(s => `<li>${esc(s.text || s.content || '')}</li>`).join('')}</ol>
					</section>
				</div>
			</article>`;
		this._replaceSkeletonsWithImages(this.previewTarget);
	}

	_replaceSkeletonsWithImages(root) {
		if (!root) return;
		root.querySelectorAll('[data-image-src]').forEach(skel => {
			const src = skel.getAttribute('data-image-src');
			if (!src) return;
			const img = document.createElement('img');
			img.alt = '';
			img.className = 'absolute inset-0 h-full w-full object-cover';
			img.referrerPolicy = 'no-referrer';
			img.onload = () => skel.remove();
			img.onerror = () => {
				console.warn('Recipe image failed to load:', src);
				img.remove();
				skel.outerHTML = `<div class="grid h-full place-items-center text-primary">${IMAGE_ICON}</div>`;
			};
			skel.insertAdjacentElement('afterend', img);
			img.src = src;
		});
	}

	async importRecipe(e) {
		e && e.preventDefault();
		if (!this.recipeData) return this.showMessage('Aucune recette à importer');
		this.showMessage('Import en cours...');
		try {
			if (this.hasImportBtnTarget) this.importBtnTarget.disabled = true;
			const body = { recipe: this.recipeData };
			const res = await fetch('/api/recipes/import', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body)
			});
			const data = await res.json();
			if (!data.ok) {
				this.showMessage('Erreur import: ' + (data.error || 'unknown'));
				if (this.hasImportBtnTarget) this.importBtnTarget.disabled = false;
				return;
			}
			this.showMessage('Importé avec succès ; redirection...');
			// Redirect to preview
			window.location.href = `/recipe/${data.id}/preview`;
		} catch (err) {
			console.error('importRecipe error', err);
			this.showMessage('Erreur: importation impossible');
		}
	}
}
