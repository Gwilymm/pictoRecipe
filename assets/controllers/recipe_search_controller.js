import { Controller } from '@hotwired/stimulus';

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
		'infoMessage',
		'errorMessage',
		'errorText'
	];

	connect() {
		console.log('Recipe controller connected');
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

			const response = await fetch("/api/marmiton/search", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({
					q: searchQuery,
					limit: 40,
					filters: {}
				})
			});

			const data = await response.json();

			if (data.success && data.results.length > 0) {
				this.displayResults(data.results);

			} else if (data.success) {
				this.showInfoMessage("Aucune recette trouvée.");
				this.resultsTarget.innerHTML = "";

			} else {
				this.showError(data.error || "Erreur lors de la recherche.");
			}

		} catch (err) {
			this.showError("Impossible de contacter le serveur.");
		} finally {
			this.hideLoading();
		}
	}

	/* ============================================================
	   🟦 2. AFFICHAGE DES CARTES
	============================================================ */
	displayResults(results) {
		this.resultsTarget.innerHTML = "";
		results.forEach(r => this.resultsTarget.appendChild(this.createRecipeCard(r)));
	}

	createRecipeCard(recipe) {
		const card = document.createElement("div");
		card.className = "card bg-base-100 shadow-md p-3 md:p-4 text-center";

		const img = document.createElement("img");
		img.src = recipe.image;
		img.className = "w-24 h-24 md:w-32 md:h-32 object-cover mx-auto rounded-lg mb-2 md:mb-3";

		const title = document.createElement("h3");
		title.className = "font-bold text-sm md:text-base mb-2";
		title.textContent = recipe.title;

		const badge = document.createElement("div");
		badge.className = "badge badge-sm badge-outline gap-1 mb-2";
		badge.innerHTML = "🥘 Marmiton";

		const btn = document.createElement("button");
		btn.className = "btn btn-primary btn-xs md:btn-sm w-full mt-2";
		btn.textContent = "Voir la recette";
		btn.addEventListener("click", () => this.openRecipeModal(recipe.url));

		card.append(img, title, badge, btn);
		return card;
	}

	/* ============================================================
	   🟧 3. MODALE
	============================================================ */
	async openRecipeModal(url) {
		let modal = document.getElementById("recipe-modal");
		if (!modal) {
			modal = document.createElement("dialog");
			modal.id = "recipe-modal";
			modal.className = "modal";
			modal.innerHTML = `
				<div class="modal-box w-11/12 max-w-5xl max-h-[90vh] overflow-y-auto">
					<form method="dialog">
						<button class="btn btn-xs md:btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
					</form>
					<div id="recipe-modal-content" class="py-2 md:py-4"></div>
				</div>
				<form method="dialog" class="modal-backdrop"><button>close</button></form>`;
			document.body.appendChild(modal);
		}

		modal.showModal();

		const container = document.getElementById("recipe-modal-content");
		container.innerHTML = `<div class="flex justify-center py-8"><span class="loading loading-spinner loading-lg"></span></div>`;

		const res = await fetch("/api/marmiton/recipe", {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ link: url })
		});

		const data = await res.json();

		if (!data.ok) {
			container.innerHTML = `<div class="alert alert-error">Erreur : ${data.error}</div>`;
			return;
		}

		this.displayRecipeJson(data.recipe, container);
	}

	/* ============================================================
	   🟩 4. JSON → HTML
	============================================================ */
	displayRecipeJson(recipe, container) {
		container.innerHTML = `
			<div class="space-y-3 md:space-y-6">
				${this.sectionTitle(recipe)}
				<div class="flex justify-end">
					<button class="btn btn-success btn-xs md:btn-sm" id="import-recipe-btn">📥 Importer cette recette</button>
				</div>
				${this.sectionPrimary(recipe)}
				${this.sectionTimes(recipe.times)}
				${this.sectionIngredients(recipe.ingredients)}
				${this.sectionUtensils(recipe.utensils)}
				${this.sectionSteps(recipe.steps)}
			</div>
		`;

		// Bind import button
		const importBtn = document.getElementById("import-recipe-btn");
		if (importBtn) {
			importBtn.addEventListener("click", () => this.importRecipe(recipe));
		}
	}

	/* ============================================================
	   🟦 5. SECTIONS
	============================================================ */
	sectionTitle(r) {
		if (!r.title) return "";
		return `
			<div class="bg-primary text-primary-content p-3 md:p-6 rounded-xl shadow-xl">
				<h2 class="text-xl md:text-2xl lg:text-3xl font-bold text-center">${r.title}</h2>
			</div>`;
	}

	sectionPrimary(r) {
		if (!r.primary?.length) return "";
		return `
			<div class="flex flex-wrap justify-center gap-1 md:gap-2">
				${r.primary.map(i => `<div class="badge badge-sm md:badge-lg badge-outline">${i}</div>`).join("")}
			</div>`;
	}

	sectionTimes(times) {
		if (!times || !times.total) return "";

		return `
    <div class="card bg-base-200 shadow-xl">
        <div class="card-body p-3 md:p-6">
            <h3 class="card-title text-lg md:text-2xl mb-2 md:mb-4">⏱️ Temps</h3>

            <!-- Temps total -->
            <div class="stat place-items-center bg-primary text-primary-content rounded-2xl shadow mb-3 md:mb-6 p-3">
                <div class="stat-title text-xs md:text-sm text-primary-content/80">Temps total</div>
                <div class="stat-value text-xl md:text-3xl">${times.total}</div>
            </div>

            <!-- Stats détaillées -->
            <div class="stats stats-vertical shadow w-full">
                ${times.details.map(d => `
                    <div class="stat p-3">
                        <div class="stat-figure text-secondary text-lg md:text-2xl">🕒</div>
                        <div class="stat-title text-xs md:text-sm">${d.label}</div>
                        <div class="stat-value text-sm md:text-lg">${d.value}</div>
                    </div>
                `).join("")}
            </div>
        </div>
    </div>
    `;
	}


	/* ============================================================
	   🥘 INGREDIENTS — FIXÉS ✔️
	============================================================ */
	sectionIngredients(ingredients) {
		if (!ingredients || ingredients.length === 0) return "";

		// Regrouper par groupe
		const groups = {};
		ingredients.forEach(item => {
			const group = item.group || "Autres";
			if (!groups[ group ]) groups[ group ] = [];
			groups[ group ].push(item);
		});

		let html = `
        <div class="card bg-base-200 shadow-xl">
            <div class="card-body p-3 md:p-6">
                <div class="flex items-center gap-2 md:gap-3 mb-2 md:mb-4">
                    <div class="text-2xl md:text-3xl">🍪</div>
                    <h3 class="card-title text-lg md:text-2xl lg:text-3xl font-bold">Ingrédients</h3>
                </div>
    `;

		Object.entries(groups).forEach(([ groupName, items ]) => {
			html += `
            <h4 class="text-base md:text-xl font-bold mt-3 md:mt-6 mb-2 text-primary">${groupName}</h4>
            <div class="grid grid-cols-2 gap-2 md:gap-3">
        `;

			items.forEach(i => {
				const text = [
					i.quantity || "",
					i.unit || "",
					i.name || "",
					i.complement || ""
				].join(" ").replace(/\s+/g, " ").trim();

				html += `
                <div class="p-2 md:p-4 rounded-xl bg-base-100 shadow flex items-center gap-2 md:gap-3 border border-base-300 hover:shadow-lg transition">
                    <span class="text-base md:text-xl">✓</span>
                    <span class="text-xs md:text-sm">${text}</span>
                </div>
            `;
			});

			html += `</div>`;
		});

		html += `
            </div>
        </div>
    `;

		return html;
	}


	/* ============================================================
	   🔧 USTENSILES — FIXÉS ✔️
	============================================================ */
	sectionUtensils(utensils) {

		if (!utensils || utensils.length === 0) return "";

		// Remove duplicates
		const unique = [];
		const set = new Set();

		utensils.forEach(u => {
			const key = (u.name + "|" + u.quantity).trim();
			if (!set.has(key)) {
				set.add(key);
				unique.push(u);
			}
		});

		let html = `
        <div class="card bg-base-200 shadow-xl">
            <div class="card-body p-3 md:p-6">
                <h3 class="card-title text-lg md:text-2xl">🔧 Ustensiles</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 md:gap-4 mt-2 md:mt-4">
    `;

		unique.forEach(u => {
			const text = `${u.quantity || ""} `.trim();

			html += `
            <div class="p-2 md:p-3 rounded-xl bg-base-100 shadow border border-base-300 flex items-center justify-center text-center hover:shadow-lg transition text-xs md:text-sm">
                ${text}
            </div>
        `;
		});

		html += `
                </div>
            </div>
        </div>
    `;

		return html;
	}


	/* ============================================================
	   👨‍🍳 STEPS
	============================================================ */
	sectionSteps(steps) {
		if (!steps?.length) return "";
		return `
			<div class="card bg-base-200 shadow-xl">
				<div class="card-body p-3 md:p-6">
					<h3 class="card-title text-lg md:text-2xl">👨‍🍳 Préparation</h3>

					<div class="mt-2 md:mt-4 space-y-2 md:space-y-4">
						${steps.map((s, i) => `
							<div class="card bg-base-100 p-2 md:p-4 shadow">
								<h4 class="font-bold mb-1 md:mb-2 text-sm md:text-base">
									<span class="badge badge-sm md:badge-md badge-primary">${i + 1}</span>
									${s.number || ""}
								</h4>
								<p class="text-xs md:text-sm">${s.text}</p>
							</div>`).join("")}
					</div>
				</div>
			</div>`;
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
		alert.innerHTML = `<span>${message}</span>`;
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
