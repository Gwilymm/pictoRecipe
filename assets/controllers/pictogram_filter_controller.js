import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = [ 'searchInput', 'card', 'grid', 'counter', 'visibleCount', 'noResults', 'pagination', 'pageInfo' ];

	connect() {
		this.totalCount = this.cardTargets.length;
		this.itemsPerPage = 6;
		this.currentPage = 1;
		this.filteredCards = [ ...this.cardTargets ];
		this.updateDisplay();
	}

	filter() {
		const query = this.searchInputTarget.value.toLowerCase().trim();

		this.filteredCards = this.cardTargets.filter(card => {
			const name = card.dataset.name;
			const format = card.dataset.format;
			return name.includes(query) || format.includes(query);
		});

		this.currentPage = 1; // Reset to first page on new search
		this.updateDisplay();
	}

	clear() {
		this.searchInputTarget.value = '';
		this.filter();
		this.searchInputTarget.focus();
	}

	nextPage() {
		const totalPages = Math.ceil(this.filteredCards.length / this.itemsPerPage);
		if (this.currentPage < totalPages) {
			this.currentPage++;
			this.updateDisplay();
			this.scrollToTop();
		}
	}

	prevPage() {
		if (this.currentPage > 1) {
			this.currentPage--;
			this.updateDisplay();
			this.scrollToTop();
		}
	}

	goToPage(event) {
		const page = parseInt(event.currentTarget.dataset.page);
		this.currentPage = page;
		this.updateDisplay();
		this.scrollToTop();
	}

	updateDisplay() {
		const totalPages = Math.ceil(this.filteredCards.length / this.itemsPerPage);
		const start = (this.currentPage - 1) * this.itemsPerPage;
		const end = start + this.itemsPerPage;

		// Hide all cards first
		this.cardTargets.forEach(card => card.classList.add('hidden'));

		// Show only cards for current page
		this.filteredCards.slice(start, end).forEach(card => {
			card.classList.remove('hidden');
		});

		// Update counter
		this.visibleCountTarget.textContent = this.filteredCards.length;

		// Show/hide no results message
		if (this.filteredCards.length === 0) {
			this.noResultsTarget.classList.remove('hidden');
			this.paginationTarget.classList.add('hidden');
		} else {
			this.noResultsTarget.classList.add('hidden');
			this.paginationTarget.classList.remove('hidden');
		}

		// Update pagination
		this.updatePagination(totalPages);

		// Update page info
		if (this.hasPageInfoTarget && this.filteredCards.length > 0) {
			const displayStart = start + 1;
			const displayEnd = Math.min(end, this.filteredCards.length);
			this.pageInfoTarget.textContent = `${displayStart}-${displayEnd} sur ${this.filteredCards.length}`;
		}
	}

	updatePagination(totalPages) {
		const paginationContainer = this.paginationTarget.querySelector('.join');
		if (!paginationContainer) return;

		// Find prev and next buttons
		const prevBtn = paginationContainer.querySelector('[data-action*="prevPage"]');
		const nextBtn = paginationContainer.querySelector('[data-action*="nextPage"]');

		// Clear ALL existing page number buttons (keep only prev/next)
		const allButtons = Array.from(paginationContainer.querySelectorAll('button'));
		allButtons.forEach(btn => {
			if (btn !== prevBtn && btn !== nextBtn) {
				btn.remove();
			}
		});

		// Disable/enable prev/next buttons
		if (prevBtn) {
			prevBtn.disabled = this.currentPage === 1;
			prevBtn.classList.toggle('btn-disabled', this.currentPage === 1);
		}
		if (nextBtn) {
			nextBtn.disabled = this.currentPage === totalPages;
			nextBtn.classList.toggle('btn-disabled', this.currentPage === totalPages);
		}

		// Generate page numbers
		const maxButtons = 5;
		let startPage = Math.max(1, this.currentPage - Math.floor(maxButtons / 2));
		let endPage = Math.min(totalPages, startPage + maxButtons - 1);

		if (endPage - startPage < maxButtons - 1) {
			startPage = Math.max(1, endPage - maxButtons + 1);
		}

		// Insert page buttons before nextBtn
		for (let i = startPage; i <= endPage; i++) {
			const btn = document.createElement('button');
			btn.className = 'join-item btn btn-xs md:btn-sm';
			btn.textContent = i;
			btn.dataset.page = i;
			btn.dataset.action = 'click->pictogram-filter#goToPage';

			if (i === this.currentPage) {
				btn.classList.add('btn-active', 'btn-primary');
			}

			if (nextBtn) {
				paginationContainer.insertBefore(btn, nextBtn);
			} else {
				paginationContainer.appendChild(btn);
			}
		}
	}

	scrollToTop() {
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}
}
