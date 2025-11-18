import { Controller } from '@hotwired/stimulus';

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
			const endpoint = url.includes('cuisineaz') ? '/api/cuisineaz/recipe' : '/api/marmiton/recipe';
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
		// Minimal rendering similar to recipe_search_controller
		const html = `
      <div class="card bg-base-100 shadow-md p-3 md:p-6">
        <div class="flex items-start gap-4">
					<div class="skeleton w-24 h-24 md:w-32 md:h-32 rounded-lg mr-2 hidden sm:block" data-image-src="${(() => {
				try {
					const url = new URL(recipe.image || recipe.picture || '');
					const host = url.host;
					const allowedSuffixes = [ 'afcdn.com', 'marmiton.org' ];
					if (allowedSuffixes.some(s => host.endsWith(s))) return `/api/image-proxy?url=${encodeURIComponent(url.toString())}`;
				} catch (e) {
					// ignore URL parse errors
				}
				return recipe.image || recipe.picture || '';
			})()}"></div>
          <div class="flex-1">
            <h2 class="text-xl font-bold mb-2">${recipe.title || 'Titre'}</h2>
            <div class="text-sm text-muted mb-2">${recipe.author ? `Par ${recipe.author}` : ''} ${recipe.published ? `— ${recipe.published}` : ''}</div>
            ${recipe.description ? `<p class="mb-2">${recipe.description}</p>` : ''}
            ${recipe.primary && recipe.primary.length ? `<div class="mb-2">${recipe.primary.map(p => `<span class="badge badge-sm badge-outline mr-1">${p}</span>`).join('')}</div>` : ''}
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
          <div>
            <h3 class="font-bold">Ingrédients</h3>
            <ul class="list-disc pl-4 text-sm">${(recipe.ingredients || []).map(i => `<li>${(i.quantity || '')} ${(i.unit || '')} ${i.name || ''}</li>`).join('')}</ul>
          </div>
          <div>
            <h3 class="font-bold">Préparation</h3>
            <ol class="list-decimal pl-4 text-sm">${(recipe.steps || []).map(s => `<li>${s.text || s.content || ''}</li>`).join('')}</ol>
          </div>
        </div>
        <div class="mt-3 text-right">
          ${recipe.url ? `<a class="btn btn-xs btn-outline" href="${recipe.url}" target="_blank" rel="noopener">Voir l'original</a>` : ''}
        </div>
      </div>`;
		this.previewTarget.innerHTML = html;
		// After rendering preview, load skeleton images into real <img> tags
		this._replaceSkeletonsWithImages(this.previewTarget);
	}

	_createImageElement(src, alt, cssClass) {
		const img = document.createElement('img');
		img.src = src || '';
		img.alt = alt || '';
		img.className = cssClass || '';
		img.referrerPolicy = 'no-referrer';
		img.loading = 'lazy';
		return img;
	}

	_replaceSkeletonsWithImages(root) {
		if (!root) return;
		const skeletons = root.querySelectorAll('[data-image-src]');
		skeletons.forEach(skel => {
			const src = skel.getAttribute('data-image-src');
			if (!src) return;
			const cssClass = 'w-24 h-24 md:w-32 md:h-32 object-cover rounded-lg mr-2 hidden sm:block';
			const alt = (skel.getAttribute('data-image-alt') || '');
			const img = this._createImageElement(src, alt, cssClass);
			img.onload = () => {
				try { skel.parentNode.replaceChild(img, skel); } catch (e) { console.warn('Replacing skeleton failed', e); }
			};
			img.onerror = () => {
				console.warn('Recipe image failed to load:', src);
				const fallback = document.createElement('div');
				fallback.className = 'w-24 h-24 md:w-32 md:h-32 bg-base-300 rounded-lg mr-2 hidden sm:block flex items-center justify-center text-2xl';
				fallback.textContent = '❌';
				try { skel.parentNode.replaceChild(fallback, skel); } catch (e) { console.warn('Replacing skeleton with fallback failed', e); }
			};
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
