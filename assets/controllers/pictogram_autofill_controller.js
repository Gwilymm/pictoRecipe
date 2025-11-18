import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = [ 'assignBtn', 'status', 'validateAllBtn' ];
	static values = {
		recipeId: Number,
	}

	connect() {
		this.assignBtn = this.hasAssignBtnTarget ? this.assignBtnTarget : null;
		this.statusEl = this.hasStatusTarget ? this.statusTarget : null;
		this.validateAllBtn = this.hasValidateAllBtnTarget ? this.validateAllBtnTarget : null;
	}

	async assign(event) {
		event && event.preventDefault();
		if (!this.assignBtn) return;
		this.assignBtn.disabled = true;
		this.assignBtn.classList.add('loading');
		if (this.statusEl) this.statusEl.textContent = 'Attribution des pictogrammes...';

		try {
			// Process ingredients
			await this._processIngredients();
			// Process steps (no step assignment per user request)
			await this._processSteps();
			// Optional: Update UI for utensils (preview only)
			await this._processUtensils();

			if (this.statusEl) this.statusEl.textContent = 'Pictogrammes assignés (aperçu). Cliquer sur "Valider tout" pour enregistrer.';
		} catch (err) {
			console.error('Error assigning pictograms', err);
			if (this.statusEl) this.statusEl.textContent = 'Erreur lors de l\'attribution des pictogrammes.';
		} finally {
			this.assignBtn.disabled = false;
			this.assignBtn.classList.remove('loading');
		}
	}

	// assignPreparation removed — steps will not be auto-assigned per user request

	async _processIngredients() {
		const cards = document.querySelectorAll('#preview-ingredients-container [data-ingredient-index]');
		for (const card of cards) {
			const index = card.getAttribute('data-ingredient-index');
			const nameEl = card.querySelector('[data-ingredient-name]');
			const name = nameEl ? nameEl.textContent.trim() : '';
			if (!name) continue;
			const result = await this._searchBestPictogram(name);
			if (!result) continue;
			// update preview DOM image
			this._updateCardImage(card, result);
			// update hidden form input
			const inputName = `recipe[ingredients][${index}][pictogramUrl]`;
			const formInput = document.querySelector(`#preview-save-form input[name="${inputName}"]`);
			if (formInput) formInput.value = result.imageUrl;
			// show 'Proposer autre' button
			this._showButtonsForIngredient(card, true);
			this._toggleValidateAllButton();
		}
	}

	async _processSteps() {
		// No automatic pictogram assignment for steps.
		// This method intentionally left blank per user request.
		return;
	}

	async _processUtensils() {
		const cards = document.querySelectorAll('#preview-utensils-container [data-utensil-index]');
		for (const card of cards) {
			const nameEl = card.querySelector('[data-utensil-name]');
			const name = nameEl ? nameEl.textContent.trim() : '';
			if (!name) continue;
			const result = await this._searchBestPictogram(name);
			if (!result) continue;
			// DOM preview only; utensils pictogram can't be saved via recipe form (it requires updating Utensil entity)
			this._updateCardImage(card, result);
			// show 'Proposer autre' button for utensils (preview-only)
			this._showButtonsForIngredient(card, true);
			this._toggleValidateAllButton();
		}
	}

	_showButtonsForIngredient(card, show) {
		if (!card) return;
		const valBtn = card.querySelector('button.btn-success');
		const proposeBtn = card.querySelector('button.btn-ghost');
		if (valBtn) valBtn.classList.toggle('hidden', !show);
		if (proposeBtn) proposeBtn.classList.toggle('hidden', !show);
		// after toggling, ensure validateAll button state reflects overall assigned cards
		this._toggleValidateAllButton();
	}

	_toggleValidateAllButton() {
		if (!this.validateAllBtn) return;
		const assignedCards = document.querySelectorAll('[data-pictogram-assigned]');
		const hasAssigned = assignedCards && assignedCards.length > 0;
		this.validateAllBtn.disabled = !hasAssigned;
	}

	_normalizeKeyword(keyword) {
		if (!keyword) return '';
		let s = String(keyword).toLowerCase();
		// strip accents
		s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		// replace parentheses and punctuation with spaces
		s = s.replace(/[()\[\]{}\\/,:;!?\u2019'"\-]/g, ' ');
		// remove digits
		s = s.replace(/\d+/g, ' ');
		// collapse spaces
		s = s.replace(/\s+/g, ' ').trim();
		return s;
	}

	/**
	 * Returns { imageUrl, source, match } or null
	 */
	async _searchBestPictogram(keyword) {
		try {
			const normalized = this._normalizeKeyword(keyword);
			if (!normalized) return null;

			const tokens = normalized.split(' ').filter(Boolean);
			// try with full phrase first then tokens (longest first)
			const queries = [ normalized, ...tokens.sort((a, b) => b.length - a.length) ];
			// include singular forms: word without a trailing s
			for (const t of [ ...queries ]) {
				if (t.length > 3 && t.endsWith('s')) {
					const s = t.slice(0, -1);
					if (!queries.includes(s)) queries.push(s);
				}
			}

			for (const q of queries) {
				const url = `/api/pictograms/search?q=${encodeURIComponent(q)}`;
				const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				if (!res.ok) continue;
				const json = await res.json();
				if (!json || !json.results || json.results.length === 0) continue;
				// prefer local
				const local = json.results.find(r => r.source === 'local');
				if (local) return { imageUrl: local.imageUrl, source: 'local', match: q };
				const ar = json.results.find(r => r.source === 'arasaac');
				if (ar) return { imageUrl: ar.imageUrl, source: 'arasaac', match: q };
				// fallback to first
				return { imageUrl: json.results[ 0 ].imageUrl, source: json.results[ 0 ].source || 'unknown', match: q };
			}
			return null;
		} catch (err) {
			console.error('Pictogram search failed', err);
			return null;
		}
	}

	_updateCardImage(card, result) {
		const url = result && result.imageUrl ? result.imageUrl : null;
		if (!card || !url) return;
		const img = card.querySelector('img');
		if (img) {
			img.src = url;
			img.alt = img.alt || '';
			card.setAttribute('data-pictogram-assigned', 'true');
			return;
		}
		const placeholder = card.querySelector('div[class*="bg-base-300"]');
		if (placeholder) {
			const newImg = document.createElement('img');
			newImg.src = url;
			newImg.alt = '';
			newImg.className = 'w-12 h-12 md:w-16 md:h-16 object-contain rounded-lg';
			placeholder.parentNode.replaceChild(newImg, placeholder);
			card.setAttribute('data-pictogram-assigned', 'true');
		}
	}

	_showSavedBadge(card) {
		if (!card) return;
		const existing = card.querySelector('[data-pictogram-saved]');
		if (existing) return;
		const badge = document.createElement('div');
		badge.setAttribute('data-pictogram-saved', '');
		badge.className = 'badge badge-success mt-2';
		badge.textContent = 'Enregistré';
		const body = card.querySelector('.card-body');
		if (body) body.appendChild(badge); else card.appendChild(badge);
	}

	_getIngredientDataList() {
		const list = [];
		const cards = document.querySelectorAll('#preview-ingredients-container [data-ingredient-index]');
		for (const card of cards) {
			const index = card.getAttribute('data-ingredient-index');
			const nameEl = card.querySelector('[data-ingredient-name]');
			const name = nameEl ? nameEl.textContent.trim() : '';
			let pic = null;
			// attempt to find the assigned pictogram on the card
			const img = card.querySelector('img');
			if (img) pic = img.src;
			// fallback to hidden form input
			if (!pic) {
				const inputName = `recipe[ingredients][${index}][pictogramUrl]`;
				const formInput = document.querySelector(`#preview-save-form input[name="${inputName}"]`);
				if (formInput && formInput.value) pic = formInput.value;
			}
			list.push({ index, name, pictogramUrl: pic });
		}
		return list;
	}

	_getUtensilDataList() {
		const list = [];
		const cards = document.querySelectorAll('#preview-utensils-container [data-utensil-index]');
		for (const card of cards) {
			const index = card.getAttribute('data-utensil-index');
			const nameEl = card.querySelector('[data-utensil-name]');
			const name = nameEl ? nameEl.textContent.trim() : '';
			let pic = null;
			const img = card.querySelector('img');
			if (img) pic = img.src;
			// utensils may be EntityType on the form — try to find hidden/choice that contains data-pictogram
			if (!pic) {
				const choice = document.querySelector(`#preview-save-form input[type="checkbox"][value][name*="utensils"]`);
				// if found, try to look up a matching utensil by name
			}
			list.push({ index, name, pictogramUrl: pic });
		}
		return list;
	}

	// _filterStopwords & _buildTokenPictogramMap removed per user request

	async proposeIngredient(event) {
		event && event.preventDefault();
		const btn = event.target;
		const card = btn.closest('[data-ingredient-index]');
		if (!card) return;
		const index = card.getAttribute('data-ingredient-index');
		const nameEl = card.querySelector('[data-ingredient-name]');
		const name = nameEl ? nameEl.textContent.trim() : '';
		if (!name) return;

		// fetch proposals using normalized tokens (use the same _searchBestPictogram but return all results)
		const proposals = await this._fetchProposals(name);
		if (!proposals || proposals.length === 0) {
			alert('Aucune proposition trouvée pour « ' + name + ' »');
			return;
		}
		this._openProposalsModal(proposals, card, index);
	}

	async _fetchProposals(keyword) {
		try {
			const normalized = this._normalizeKeyword(keyword);
			if (!normalized) return [];
			// prepare multi-query with phrase + tokens
			const tokens = normalized.split(' ').filter(Boolean);
			const queries = [ normalized, ...tokens.filter(t => t.length > 2) ];
			const seen = new Set();
			const aggregated = [];
			for (const q of queries) {
				const url = `/api/pictograms/search?q=${encodeURIComponent(q)}`;
				const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				if (!res.ok) continue;
				const json = await res.json();
				const results = json.results || [];
				for (const r of results) {
					if (!r.imageUrl) continue;
					if (seen.has(r.imageUrl)) continue;
					seen.add(r.imageUrl);
					aggregated.push(r);
				}
				// stop if we collected a decent amount
				if (aggregated.length >= 20) break;
			}
			return aggregated;
		} catch (err) {
			console.error('Failed to fetch proposals', err);
			return [];
		}
	}

	_openProposalsModal(proposals, card, index) {
		const modal = document.getElementById('pictogram-proposals-modal');
		const list = document.getElementById('pictogram-proposals-list');
		if (!modal || !list) return;
		list.innerHTML = '';
		proposals.forEach((p) => {
			const item = document.createElement('div');
			item.className = 'p-2 border rounded flex flex-col items-center justify-center cursor-pointer hover:shadow';
			const img = document.createElement('img');
			img.src = p.imageUrl;
			img.className = 'w-20 h-20 object-contain';
			const label = document.createElement('div');
			label.className = 'text-xs text-base-content/60 mt-1';
			label.textContent = (p.source === 'local' ? 'Local' : 'ARASAAC');
			item.appendChild(img);
			item.appendChild(label);
			item.addEventListener('click', () => {
				// update preview and hidden input but don't save immediately — user should validate
				this._updateCardImage(card, { imageUrl: p.imageUrl, source: p.source, match: '' });
				const inputName = `recipe[ingredients][${index}][pictogramUrl]`;
				const formInput = document.querySelector(`#preview-save-form input[name="${inputName}"]`);
				if (formInput) formInput.value = p.imageUrl;
				// show validate button again
				this._showButtonsForIngredient(card, true);
				this.closeModal();
			});
			list.appendChild(item);
		});
		// attach context
		modal.setAttribute('data-current-index', String(index));
		modal.classList.remove('hidden');
		modal.classList.add('flex');
	}

	closeModal() {
		const modal = document.getElementById('pictogram-proposals-modal');
		if (!modal) return;
		modal.removeAttribute('data-current-index');
		modal.classList.add('hidden');
		modal.classList.remove('flex');
	}

	async validateAll(event) {
		event && event.preventDefault();
		const btn = event && event.currentTarget ? event.currentTarget : document.querySelector('button[data-action*="pictogram-autofill#validateAll"]');
		if (btn) {
			btn.disabled = true;
			btn.classList.add('loading');
		}

		try {
			// If there aren't any assigned pictograms, run the auto-assign first
			let assignedCards = document.querySelectorAll('[data-pictogram-assigned]');
			if (!assignedCards || assignedCards.length === 0) {
				await this.assign();
				assignedCards = document.querySelectorAll('[data-pictogram-assigned]');
			}

			const form = document.querySelector('#preview-save-form');
			if (form) console.debug('pictogram-autofill: form action', form.action);
			if (!form) {
				if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
				return;
			}

			const fd = new FormData(form);
			// Safety: ensure we don't send a top-level 'recipe' key containing non-scalar values
			const entries = Array.from(fd.entries());
			console.debug('pictogram-autofill: preview save form payload entries (first 50):', entries.slice(0, 50));
			const topRecipe = entries.find(([ k ]) => k === 'recipe');
			if (topRecipe) {
				console.warn('pictogram-autofill: Found unexpected top-level "recipe" form entry — removing to avoid server validation errors.', topRecipe);
				fd.delete('recipe');
			}
			// Normalize non-string values: stringify objects/arrays to ensure the server receives scalars
			for (const [ k, v ] of entries) {
				if (k === 'recipe') continue; // already handled
				if (typeof v !== 'string' && !(v instanceof File)) {
					try {
						fd.set(k, JSON.stringify(v));
					} catch (e) {
						// remove problematic entry
						fd.delete(k);
					}
				}
			}
			fd.set('recipe[id]', String(this.recipeIdValue));
			const response = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			if (response.redirected) {
				window.location.href = response.url;
				return;
			}
			if (response.ok) {
				// mark each assigned card as saved
				const cards = document.querySelectorAll('[data-pictogram-assigned]');
				for (const card of cards) {
					this._showSavedBadge(card);
					this._showButtonsForIngredient(card, false);
				}
			} else {
				// read and log response body for debugging
				let body = null;
				try {
					body = await response.json();
				} catch (e) {
					try {
						body = await response.text();
					} catch (err) {
						body = String(err);
					}
				}
				console.error('pictogram-autofill: validateAll error', response.status, body);
				// Fallback: attempt a full form submit so Symfony displays form errors in the UI and we can inspect them
				console.warn('pictogram-autofill: Falling back to full form submission for debugging');
				try { form.submit(); } catch (e) { console.error('pictogram-autofill: fallback submit failed', e); }
			}
		} catch (err) {
			console.error('Error during validateAll', err);
		} finally {
			if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
		}
	}
}
