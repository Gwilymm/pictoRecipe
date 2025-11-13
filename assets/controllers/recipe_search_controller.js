import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = [
		'form',
		'query',
		'dish',
		'budget',
		'difficulty',
		'time',
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
		'errorText',
		'modal',
		'modalContent'
	];

	connect() {
		console.log('Recipe search controller connected');
		console.log('Form target:', this.formTarget);
	}

	async search(event) {
		console.log('Search method called', event);
		event.preventDefault();
		event.stopPropagation();

		console.log('Form submission prevented');

		// Récupérer les valeurs du formulaire
		const criteria = {
			query: this.queryTarget.value.trim(),
			dish: this.dishTarget.value,
			budget: this.budgetTarget.value,
			difficulty: this.difficultyTarget.value,
			time: parseInt(this.timeTarget.value) || 0,
			vegan: this.veganTarget.checked,
			vegetarian: this.vegetarianTarget.checked,
			withoutGluten: this.withoutGlutenTarget.checked,
			withoutDairyProducts: this.withoutDairyProductsTarget.checked,
			withoutOven: this.withoutOvenTarget.checked,
			raw: this.rawTarget.checked
		};

		console.log('Search criteria:', criteria);

		// Vérifier qu'au moins un critère est renseigné
		if (!criteria.query &&
			criteria.dish === 'all' &&
			criteria.budget === 'all' &&
			criteria.difficulty === 'all' &&
			criteria.time === 0 &&
			!criteria.vegan &&
			!criteria.vegetarian &&
			!criteria.withoutGluten &&
			!criteria.withoutDairyProducts &&
			!criteria.withoutOven &&
			!criteria.raw) {
			this.showInfoMessage('Veuillez renseigner au moins un critère de recherche.');
			return;
		}

		// Masquer les messages
		this.hideMessages();

		// Afficher le spinner
		this.showLoading();

		try {
			// Appel à l'API Marmiton via notre backend Symfony
			console.log('Sending request to /api/marmiton/search...');

			// Construire la requête de recherche en ajoutant les termes des checkboxes
			let searchTerms = [];

			// Ajouter la requête de base
			if (criteria.query) {
				searchTerms.push(criteria.query.trim());
			}

			// Ajouter les termes des checkboxes cochées
			if (criteria.withoutGluten) {
				searchTerms.push('sans gluten');
			}
			if (criteria.vegetarian) {
				searchTerms.push('végétarien');
			}
			if (criteria.vegan) {
				searchTerms.push('vegan');
			}
			if (criteria.withoutDairyProducts) {
				searchTerms.push('sans lactose');
			}
			if (criteria.withoutOven) {
				searchTerms.push('sans four');
			}
			if (criteria.raw) {
				searchTerms.push('cru');
			}

			// Joindre tous les termes avec des tirets (format Marmiton)
			const searchQuery = searchTerms.join(' ').replace(/\s+/g, '-');

			console.log('Constructed search query:', searchQuery);

			const requestBody = {
				q: searchQuery,
				limit: 20,
				filters: {
					difficulty: criteria.difficulty !== 'all' ? criteria.difficulty : null,
					price: criteria.budget !== 'all' ? criteria.budget : null,
					maxTime: criteria.time > 0 ? criteria.time : null
				}
			};

			const response = await fetch('/api/marmiton/search', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(requestBody)
			});

			console.log('Response status:', response.status);
			const data = await response.json();
			console.log('Response data:', data);

			if (data.success && data.results && data.results.length > 0) {
				this.displayResults(data.results);
			} else if (data.success && data.results && data.results.length === 0) {
				this.showInfoMessage('Aucune recette trouvée pour ces critères.');
				this.clearResults();
			} else {
				this.showError(data.error || 'Une erreur est survenue lors de la recherche.');
				this.clearResults();
			}
		} catch (error) {
			console.error('Search error:', error);
			this.showError('Impossible de contacter le serveur. Veuillez réessayer.');
			this.clearResults();
		} finally {
			this.hideLoading();
		}
	}

	displayResults(results) {
		this.resultsTarget.innerHTML = '';

		results.forEach(recipe => {
			const card = this.createRecipeCard(recipe);
			this.resultsTarget.appendChild(card);
		});
	}

	createRecipeCard(recipe) {
		const card = document.createElement('div');
		card.className = 'card bg-base-100 shadow-md p-4 flex flex-col items-center hover:shadow-lg transition-shadow duration-300';

		// Image
		const img = document.createElement('img');
		img.src = recipe.image || recipe.picture || 'https://via.placeholder.com/150';
		img.alt = recipe.title || recipe.name;
		img.className = 'w-32 h-32 object-cover rounded-lg mb-3';
		img.onerror = function () {
			this.src = 'https://via.placeholder.com/150?text=Image+non+disponible';
		};

		// Titre
		const title = document.createElement('h3');
		title.className = 'text-lg font-semibold text-center mb-2';
		title.textContent = recipe.title || recipe.name;

		// Informations
		const infosContainer = document.createElement('div');
		infosContainer.className = 'text-sm text-gray-500 text-center mb-3 space-y-1';

		if (recipe.duration && recipe.duration !== 'N/A') {
			const duration = document.createElement('p');
			duration.innerHTML = `⏱️ ${recipe.duration}`;
			infosContainer.appendChild(duration);
		}

		if (recipe.difficulty && recipe.difficulty !== 'N/A') {
			const difficulty = document.createElement('p');
			difficulty.innerHTML = `📊 ${recipe.difficulty}`;
			infosContainer.appendChild(difficulty);
		}

		if (recipe.budget && recipe.budget !== 'N/A') {
			const budget = document.createElement('p');
			budget.innerHTML = `💰 ${recipe.budget}`;
			infosContainer.appendChild(budget);
		}

		if (recipe.rate || recipe.rating) {
			const rate = document.createElement('p');
			rate.innerHTML = `⭐ ${recipe.rate || recipe.rating}/5`;
			infosContainer.appendChild(rate);
		}

		if (recipe.reviews) {
			const reviews = document.createElement('p');
			reviews.innerHTML = `💬 ${recipe.reviews}`;
			infosContainer.appendChild(reviews);
		}

		if (recipe.category) {
			const category = document.createElement('p');
			category.innerHTML = `🍽️ ${recipe.category}`;
			infosContainer.appendChild(category);
		}

		// Boutons
		const buttonContainer = document.createElement('div');
		buttonContainer.className = 'flex gap-2 mt-2';

		// Bouton pour ouvrir dans la modale
		const viewButton = document.createElement('button');
		viewButton.className = 'btn btn-primary btn-sm';
		viewButton.textContent = 'Voir la recette';
		viewButton.addEventListener('click', () => this.openRecipeModal(recipe.url || recipe.link));

		// Bouton pour ouvrir sur Marmiton
		const externalButton = document.createElement('a');
		externalButton.href = recipe.url || recipe.link || '#';
		externalButton.target = '_blank';
		externalButton.rel = 'noopener noreferrer';
		externalButton.className = 'btn btn-outline btn-sm';
		externalButton.textContent = 'Marmiton';

		buttonContainer.appendChild(viewButton);
		buttonContainer.appendChild(externalButton);

		// Assemblage
		card.appendChild(img);
		card.appendChild(title);
		card.appendChild(infosContainer);
		card.appendChild(buttonContainer);

		return card;
	}

	clearResults() {
		this.resultsTarget.innerHTML = '';
	}

	showLoading() {
		this.submitBtnTarget.disabled = true;
		this.submitTextTarget.classList.add('hidden');
		this.spinnerTarget.classList.remove('hidden');
	}

	hideLoading() {
		this.submitBtnTarget.disabled = false;
		this.submitTextTarget.classList.remove('hidden');
		this.spinnerTarget.classList.add('hidden');
	}

	showInfoMessage(message) {
		this.infoMessageTarget.classList.remove('hidden');
		this.infoMessageTarget.querySelector('span').textContent = message;
		this.errorMessageTarget.classList.add('hidden');
	}

	showError(message) {
		this.errorMessageTarget.classList.remove('hidden');
		this.errorTextTarget.textContent = message;
		this.infoMessageTarget.classList.add('hidden');
	}

	hideMessages() {
		this.infoMessageTarget.classList.add('hidden');
		this.errorMessageTarget.classList.add('hidden');
	}

	/**
	 * Open recipe in a modal (no iframe)
	 */
	async openRecipeModal(url) {
		if (!url) return;

		// Create modal if it doesn't exist
		let modal = document.getElementById('recipe-modal');
		if (!modal) {
			modal = document.createElement('dialog');
			modal.id = 'recipe-modal';
			modal.className = 'modal';
			modal.innerHTML = `
				<div class="modal-box w-11/12 max-w-5xl">
					<form method="dialog">
						<button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
					</form>
					<div id="recipe-modal-content" class="py-4">
						<div class="flex justify-center items-center py-8">
							<span class="loading loading-spinner loading-lg"></span>
						</div>
					</div>
				</div>
				<form method="dialog" class="modal-backdrop">
					<button>close</button>
				</form>
			`;
			document.body.appendChild(modal);
		}

		// Show modal using DaisyUI method
		modal.showModal();

		const contentDiv = document.getElementById('recipe-modal-content');
		contentDiv.innerHTML = `
			<div class="flex justify-center items-center py-8">
				<span class="loading loading-spinner loading-lg"></span>
			</div>
		`;

		try {
			const response = await fetch('/api/marmiton/recipe', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({ link: url })
			});

			if (!response.ok) {
				throw new Error(`HTTP ${response.status}: ${response.statusText}`);
			}

			const data = await response.json();

			if (data.ok && data.html) {
				// Parse and display the recipe HTML
				this.displayRecipeInModal(data.html, contentDiv);
			} else {
				throw new Error(data.error || 'Failed to load recipe');
			}
		} catch (error) {
			console.error('Error loading recipe:', error);
			contentDiv.innerHTML = `
				<div class="alert alert-error">
					<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
					<span>Erreur lors du chargement de la recette: ${error.message}</span>
				</div>
			`;
		}
	}

	/**
	 * Display recipe HTML in modal
	 */
	displayRecipeInModal(html, container) {
		try {
			const parser = new DOMParser();
			const doc = parser.parseFromString(html, 'text/html');

			// Remove all images from the parsed document
			doc.querySelectorAll('img').forEach(img => img.remove());

			let content = '<div class="space-y-6">';

			// Build each section separately
			content += this.buildTitleAndPrimarySection(doc);
			content += this.buildIngredientsSection(doc);
			content += this.buildUtensilsSection(doc);
			content += this.buildPreparationSection(doc);

			content += '</div>';

			// If we successfully extracted content, use it
			if (content.length > 100) {
				container.innerHTML = content;
			} else {
				// Fallback: display the raw HTML without images
				const bodyContent = doc.body.innerHTML;
				container.innerHTML = `
					<div class="alert alert-info shadow-lg mb-4">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
						<span>Affichage du contenu brut de la recette</span>
					</div>
					<div class="prose prose-lg max-w-none">${bodyContent}</div>
				`;
				container.querySelectorAll('img').forEach(img => img.remove());
			}

			// Initialize scaling functionality after content is loaded
			this.initRecipeScaling(container);
		} catch (error) {
			console.error('Error parsing recipe HTML:', error);
			container.innerHTML = `
				<div class="alert alert-warning shadow-lg">
					<svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
					<span>Impossible d'analyser la recette. Affichage du contenu brut.</span>
				</div>
				<div class="prose prose-lg max-w-none mt-4">${html}</div>
			`;
			container.querySelectorAll('img').forEach(img => img.remove());
		}
	}

	/**
	 * Build title and primary info section (temps, difficulté, prix)
	 */
	buildTitleAndPrimarySection(doc) {
		let content = '';

		// Extract title
		const titleSelectors = [ '.mrtn-recette_title', '.recipe-title', 'h1', 'h2' ];
		for (const selector of titleSelectors) {
			const el = doc.querySelector(selector);
			if (el) {
				content += `
					<div class="text-center pb-6 border-b-2 border-primary">
						<h2 class="text-4xl font-bold text-primary mb-2">${this.escapeHtml(el.textContent.trim())}</h2>
					</div>
				`;
				break;
			}
		}

		// Extract primary info (temps, difficulté, budget)
		const primarySelectors = [ '.recipe-primary', '.marmiton-extract' ];
		for (const selector of primarySelectors) {
			const el = doc.querySelector(selector);
			if (el) {
				const items = el.querySelectorAll('.recipe-primary__item');
				const infos = [];

				items.forEach(item => {
					const span = item.querySelector('span');
					if (span) {
						infos.push(span.textContent.trim());
					}
				});

				console.log('Found primary info items:', infos);

				if (infos.length > 0) {
					const infoHtml = infos.map((info, index) => {
						if (index === 0) {
							return `<span class="text-sm font-semibold px-3 py-1">${info}</span>`;
						} else {
							return `<span class="text-base-content/40 mx-2">●</span><span class="text-sm font-semibold px-3 py-1">${info}</span>`;
						}
					}).join('');

					content += `
						<div class="flex flex-wrap gap-0 justify-center items-center bg-linear-to-r from-base-200 to-base-300 p-4 rounded-lg shadow-lg">
							${infoHtml}
						</div>
					`;
				}
				break;
			}
		}

		return content;
	}

	/**
	 * Build ingredients section
	 */
	buildIngredientsSection(doc) {
		let content = '';
		const ingredientsSelectors = [ '.mrtn-recette_ingredients', '.recipe-ingredients' ];

		for (const selector of ingredientsSelectors) {
			const el = doc.querySelector(selector);
			if (el) {
				el.querySelectorAll('img').forEach(img => img.remove());

				// Get the items container
				const itemsContainer = el.querySelector('.mrtn-recette_ingredients-items');
				let ingredientsHtml = '';

				if (itemsContainer) {
					// Process all children to handle groups and ingredients
					const children = Array.from(itemsContainer.children);
					let hasGroup = false;

					children.forEach((child) => {
						// Check if it's a group title
						if (child.classList.contains('mrtn-recette_ingredients-items-group-title')) {
							// Close previous group if exists
							if (hasGroup) {
								ingredientsHtml += '</div></div>';
							}

							// Start new group
							const groupTitle = child.textContent.trim();
							ingredientsHtml += `
								<div class="mb-6">
									<h4 class="text-xl font-bold mb-3 text-primary">${groupTitle}</h4>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
							`;
							hasGroup = true;
						}
						// Check if it's an ingredient card
						else if (child.classList.contains('card-ingredient')) {
							const title = child.querySelector('.card-ingredient-title');
							if (title) {
								const quantity = title.querySelector('.card-ingredient-quantity .count');
								const unit = title.querySelector('.card-ingredient-quantity .unit');
								const name = title.querySelector('.ingredient-name');
								const complement = title.querySelector('.ingredient-complement');

								const qtyText = quantity ? quantity.textContent.trim() : '';
								const unitText = unit ? unit.textContent.trim() : '';
								const nameText = name ? name.textContent.trim() : '';
								const complementText = complement ? complement.textContent.trim() : '';

								// Build the full text based on what's available
								let fullText = '';
								if (qtyText && unitText) {
									fullText = `${qtyText} ${unitText}`;
								} else if (qtyText) {
									fullText = qtyText;
								}

								// Add preposition if needed
								const preposition = title.textContent.includes(' de ') ? 'de' :
									title.textContent.includes(" d'") ? "d'" : '';

								if (fullText && preposition) {
									fullText += ` ${preposition}`;
								}

								if (nameText) {
									fullText += fullText ? ` ${nameText}` : nameText;
								}

								if (complementText) {
									fullText += ` ${complementText}`;
								}

								if (fullText) {
									const cardHtml = `
										<div class="card bg-base-100 shadow-md hover:shadow-lg transition-shadow border-l-2 border-primary">
											<div class="card-body p-3">
												<div class="flex items-center gap-2">
													<div class="badge badge-primary badge-sm">✓</div>
													<span class="text-sm font-medium">${fullText}</span>
												</div>
											</div>
										</div>
									`;

									if (!hasGroup) {
										// No group yet, create default grid
										if (!ingredientsHtml.includes('grid grid-cols')) {
											ingredientsHtml = '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
											hasGroup = true; // Mark as having a group to close it later
										}
									}
									ingredientsHtml += cardHtml;
								}
							}
						}
					});

					// Close any open group or default grid
					if (hasGroup) {
						ingredientsHtml += '</div>';
						if (ingredientsHtml.includes('<h4')) {
							ingredientsHtml += '</div>'; // Close the group div if there was a title
						}
					}
				}

				if (ingredientsHtml) {
					content += `
						<div class="card bg-linear-to-br from-primary/10 via-primary/5 to-base-100 shadow-xl border-l-4 border-primary">
							<div class="card-body">
								<h3 class="card-title text-3xl mb-4">
									<span class="text-4xl">🥘</span>
									<span class="bg-linear-to-r from-primary to-secondary bg-clip-text text-transparent">Ingrédients</span>
								</h3>
								<div class="divider mt-0 mb-4"></div>
								${ingredientsHtml}
							</div>
						</div>
					`;
				}
				break;
			}
		}

		return content;
	}

	/**
	 * Build utensils section
	 */
	buildUtensilsSection(doc) {
		let content = '';
		const utensilsSelectors = [ '.mrtn-recette_utensils', '.recipe-utensils' ];

		for (const selector of utensilsSelectors) {
			const el = doc.querySelector(selector);
			if (el) {
				el.querySelectorAll('img').forEach(img => img.remove());

				// Extract utensil cards - only get the quantity div content
				const utensilCards = el.querySelectorAll('.card-utensil');
				let utensilsHtml = '';

				if (utensilCards.length > 0) {
					utensilsHtml = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">';
					utensilCards.forEach((card) => {
						const quantityDiv = card.querySelector('.card-utensil-quantity');

						if (quantityDiv) {
							const displayText = quantityDiv.textContent.trim().replace(/\s+/g, ' ');

							if (displayText) {
								utensilsHtml += `
									<div class="card bg-base-100 shadow-md hover:shadow-lg transition-all hover:scale-105 border-l-2 border-accent">
										<div class="card-body p-3 text-center">
											<div class="text-2xl mb-1">🔧</div>
											<span class="text-xs font-medium">${displayText}</span>
										</div>
									</div>
								`;
							}
						}
					});
					utensilsHtml += '</div>';
				}

				if (utensilsHtml) {
					content += `
						<div class="card bg-linear-to-br from-accent/10 via-accent/5 to-base-100 shadow-xl border-l-4 border-accent">
							<div class="card-body">
								<h3 class="card-title text-3xl mb-4">
									<span class="text-4xl">🔪</span>
									<span class="bg-linear-to-r from-accent to-info bg-clip-text text-transparent">Ustensiles</span>
								</h3>
								<div class="divider mt-0 mb-4"></div>
								${utensilsHtml}
							</div>
						</div>
					`;
				}
				break;
			}
		}

		return content;
	}

	/**
	 * Build preparation section (steps)
	 */
	buildPreparationSection(doc) {
		let content = '';
		const preparationSelectors = [ '.recipe-preparation', '.recipe-step-list', '.mrtn-recette_steps' ];

		for (const selector of preparationSelectors) {
			const el = doc.querySelector(selector);
			if (el) {
				console.log('Found preparation section with selector:', selector);

				// Look specifically for recipe-step-list__container elements
				const stepContainers = el.querySelectorAll('.recipe-step-list__container');
				console.log('Found step containers:', stepContainers.length);

				let stepsHtml = '<div class="space-y-3">';

				if (stepContainers.length > 0) {
					stepContainers.forEach((stepContainer, index) => {
						// Get the step title from the head
						const stepHead = stepContainer.querySelector('.recipe-step-list__head span');
						const stepTitle = stepHead ? stepHead.textContent.trim() : `Étape ${index + 1}`;

						// Get the step description from the <p> tag
						const stepParagraph = stepContainer.querySelector('p');
						const stepText = stepParagraph ? stepParagraph.textContent.trim() : '';

						if (stepText) {
							stepsHtml += `
								<div class="card bg-base-100 shadow-md hover:shadow-lg transition-all duration-200 border-l-4 border-success">
									<div class="card-body p-4">
										<div class="flex gap-3 items-start">
											<div class="badge badge-success badge-lg font-bold shrink-0 px-3 py-3">
												${index + 1}
											</div>
											<div class="flex-1">
												<h4 class="font-bold text-success mb-2">${stepTitle}</h4>
												<p class="text-sm leading-relaxed">${stepText}</p>
											</div>
										</div>
									</div>
								</div>
							`;
						}
					});
				} else {
					console.log('No step containers found, trying fallback');
					// Fallback: try to find steps differently
					const allSteps = el.querySelectorAll('p');
					console.log('Found paragraphs in fallback:', allSteps.length);
					if (allSteps.length > 0) {
						allSteps.forEach((p, index) => {
							const text = p.textContent.trim();
							if (text && text.length > 10) {
								stepsHtml += `
									<div class="card bg-base-100 shadow-md border-l-4 border-success">
										<div class="card-body p-4">
											<div class="flex gap-3 items-start">
												<div class="badge badge-success badge-lg font-bold shrink-0 px-3 py-3">
													${index + 1}
												</div>
												<div class="flex-1">
													<p class="text-sm leading-relaxed">${text}</p>
												</div>
											</div>
										</div>
									</div>
								`;
							}
						});
					}
				}

				stepsHtml += '</div>';

				if (stepsHtml.includes('card')) {
					content += `
						<div class="card bg-linear-to-br from-success/10 via-success/5 to-base-100 shadow-xl border-l-4 border-success">
							<div class="card-body">
								<h3 class="card-title text-3xl mb-4">
									<span class="text-4xl">👨‍🍳</span>
									<span class="bg-linear-to-r from-success to-warning bg-clip-text text-transparent">Préparation</span>
								</h3>
								<div class="divider mt-0 mb-4"></div>
								${stepsHtml}
							</div>
						</div>
					`;
				}
				break;
			}
		}

		return content;
	}

	/**
	 * Initialize recipe scaling (servings adjustment)
	 */
	initRecipeScaling(container) {
		try {
			const servingsInput = container.querySelector('.recipe-ingredients__qt-counter__value') ||
				container.querySelector('.mrtn-recette_ingredients-counter input') ||
				container.querySelector('input[aria-label="counter"]');

			if (!servingsInput) return;

			const origServings = parseFloat(servingsInput.value) || 1;
			const ingredients = [];

			// Parse ingredients with quantities
			container.querySelectorAll('.mrtn-recette_ingredients-items .card-ingredient').forEach(item => {
				const qtyEl = item.querySelector('[data-ingredientquantity], .card-ingredient-quantity, .count');
				if (!qtyEl) return;

				let origQty = parseFloat(qtyEl.getAttribute('data-ingredientquantity'));
				if (isNaN(origQty)) {
					origQty = this.parseFraction(qtyEl.textContent);
				}
				if (isNaN(origQty)) return;

				ingredients.push({ element: qtyEl, originalQty: origQty });
			});

			// Add event listeners for +/- buttons
			const minusBtn = container.querySelector('.recipe-ingredients__qt-counter__increment-minus');
			const plusBtn = container.querySelector('.recipe-ingredients__qt-counter__increment-plus');

			const rescale = (newServings) => {
				const factor = newServings / origServings;
				ingredients.forEach(ing => {
					const newQty = ing.originalQty * factor;
					ing.element.textContent = this.formatQuantity(newQty);
				});
			};

			if (minusBtn) {
				minusBtn.addEventListener('click', () => {
					const newVal = Math.max(1, parseInt(servingsInput.value) - 1);
					servingsInput.value = newVal;
					rescale(newVal);
				});
			}

			if (plusBtn) {
				plusBtn.addEventListener('click', () => {
					const newVal = parseInt(servingsInput.value) + 1;
					servingsInput.value = newVal;
					rescale(newVal);
				});
			}

			servingsInput.addEventListener('input', (e) => {
				const val = parseInt(e.target.value);
				if (val > 0) rescale(val);
			});
		} catch (error) {
			console.warn('Could not initialize recipe scaling:', error);
		}
	}

	/**
	 * Parse fractions and special characters
	 */
	parseFraction(str) {
		if (!str) return NaN;
		str = String(str).trim();

		const fractionMap = { '½': 0.5, '¼': 0.25, '¾': 0.75, '⅓': 1 / 3, '⅔': 2 / 3 };
		if (fractionMap[ str ]) return fractionMap[ str ];

		const match = str.match(/^(\d+)\s*\/\s*(\d+)$/);
		if (match) return Number(match[ 1 ]) / Number(match[ 2 ]);

		const num = Number(str.replace(',', '.'));
		return isNaN(num) ? NaN : num;
	}

	/**
	 * Format quantity for display
	 */
	formatQuantity(n) {
		if (!isFinite(n)) return String(n);
		if (Math.abs(n - Math.round(n)) < 1e-6) return String(Math.round(n));
		return String(Number(n.toFixed(2))).replace('.00', '');
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	escapeHtml(str) {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}
}
