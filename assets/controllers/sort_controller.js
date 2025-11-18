import { Controller } from '@hotwired/stimulus';

// Stimulus controller to sort recipe results client-side
// - attach to a node containing a select (data-sort-target="select") and the results container (data-sort-target="results")
// - listens for a `results:loaded` event containing detail { results: [...], query: '...'}
// - on change, or upon `results:loaded`, sorts the results (without reloading) and dispatches `sort:apply` with detail { sorted: [...] }
export default class extends Controller {
	static targets = [ 'select', 'results' ];

	connect() {
		this.defaultSort = 'best';
		this.currentSort = this.defaultSort;
		this.resultsAll = [];
		this.query = '';

		// Bind handlers
		this.onSelectChange = this.onSelectChange.bind(this);
		this.onResultsLoaded = this.onResultsLoaded.bind(this);

		if (this.hasSelectTarget) {
			this.selectTarget.addEventListener('change', this.onSelectChange);
		}

		// Listen for the `results:loaded` event dispatched by the search controller
		// It should include the results array and the query used
		const container = this.hasResultsTarget ? this.resultsTarget : this.element;
		container.addEventListener('results:loaded', this.onResultsLoaded);

		// Default sorting on connect
		this.applySort(this.defaultSort);
	}

	disconnect() {
		if (this.hasSelectTarget) {
			this.selectTarget.removeEventListener('change', this.onSelectChange);
		}
		const container = this.hasResultsTarget ? this.resultsTarget : this.element;
		container.removeEventListener('results:loaded', this.onResultsLoaded);
	}

	// Event when select value changes
	onSelectChange(e) {
		const sortKey = e?.target?.value || this.defaultSort;
		this.currentSort = sortKey;
		this.applySort(sortKey);
	}

	// When new results are loaded, keep the raw array and apply the current sort
	onResultsLoaded(e) {
		if (!e?.detail) return;
		this.resultsAll = Array.isArray(e.detail.results) ? e.detail.results : [];
		this.query = e.detail.query || '';
		// Keep the default (or selected) sort
		this.applySort(this.currentSort || this.defaultSort);
	}

	// The main sorting entry point — sorts the `resultsAll` and asks the parent to render
	applySort(sortKey = 'best') {
		if (!Array.isArray(this.resultsAll) || this.resultsAll.length === 0) {
			// If there are no structured results, attempt to read DOM nodes and convert them
			this.resultsAll = this._buildResultsFromDom();
		}

		let sorted = [ ...this.resultsAll ];
		switch (sortKey) {
			case 'rating':
				sorted = this.sortByRating(sorted);
				break;
			// 'reviews' sort option removed
			case 'name':
				sorted = this.sortByName(sorted);
				break;
			case 'source':
				sorted = this.sortBySource(sorted);
				break;
			case 'best':
			default:
				sorted = this.sortByBest(sorted, this.query);
				break;
		}

		// Let the search controller apply the sorted array and handle pagination/rendering
		try {
			const evt = new CustomEvent('sort:apply', { detail: { sorted }, bubbles: true });
			this.element.dispatchEvent(evt);
		} catch (err) {
			// Ignore if dispatch fails
			console.debug('Sort controller: failed to dispatch sort:apply', err);
		}
	}

	/* ============================================================
	  Parsers & Scores
	============================================================ */
	parseRating(raw) {
		// If an entire item object is passed, extract rating-like fields
		if (raw && typeof raw === 'object') {
			raw = raw.rating || raw.score || raw.itemRating || raw.rating_value || raw.value || '';
		}
		if (raw == null) return 0;
		if (typeof raw === 'number') {
			const n = Number(raw);
			return Number.isFinite(n) ? n : NaN;
		}
		const s = String(raw).trim();
		if (s === '') return 0;
		// Normalize different formats: 4,7/5 -> 4.7 ; 4.7/5 -> 4.7 ; '5' -> 5
		const cleaned = s.replace(/\s/g, '').replace(',', '.').replace(/\/\s*5$/i, '').replace(/\/5$/i, '').trim();
		const match = cleaned.match(/(\d+(?:\.\d+)?)/);
		if (!match) return NaN;
		const n = parseFloat(match[ 1 ]);
		return Number.isFinite(n) ? n : 0;
	}

	// parseReviews removed: reviews are no longer used in sorting

	// Score used by `best` sort
	score(item, query = '') {
		const title = (item.title || item.name || '').toLowerCase();
		const q = (query || '').toLowerCase().trim();
		let score = 0;

		if (q && title.startsWith(q)) score += 30;
		if (q && title.includes(q) && !title.startsWith(q)) score += 10;

		const rating = this.parseRating(item.rating);
		score += (rating * 10);

		const src = (item.source || item.itemSource || '').toLowerCase();
		if (src.includes('marmiton')) score += 20;

		// Keep 1 decimal for consistency with UI display
		return Math.round(score * 10) / 10;
	}

	/* ============================================================
	  Sort helpers
	============================================================ */
	sortByBest(items, query = '') {
		return items.sort((a, b) => {
			const sa = this.score(a, query);
			const sb = this.score(b, query);
			if (sb !== sa) return sb - sa;
			// fallback tie-breakers
			const ra = this.parseRating(a);
			const rb = this.parseRating(b);
			if (rb !== ra) return rb - ra;
			return String(a.title || a.name || '').localeCompare(String(b.title || b.name || ''));
		});
	}

	sortByRating(items) {
		return items.sort((a, b) => {
			const ra = this.parseRating(a);
			const rb = this.parseRating(b);
			const va = Number.isFinite(ra) ? ra : -Infinity; // No rating -> bottom
			const vb = Number.isFinite(rb) ? rb : -Infinity;
			return vb - va; // descending numeric
		});
	}

	// sortByReviews removed — reviews are no longer part of sorting

	sortByName(items) {
		return items.sort((a, b) => {
			const A = String(a.title || a.name || '').toLowerCase();
			const B = String(b.title || b.name || '').toLowerCase();
			return A.localeCompare(B);
		});
	}

	sortBySource(items) {
		const order = [ 'marmiton', 'cuisineaz' ];
		return items.sort((a, b) => {
			const sa = (a.source || a.itemSource || '').toLowerCase();
			const sb = (b.source || b.itemSource || '').toLowerCase();
			const ia = order.indexOf(sa) >= 0 ? order.indexOf(sa) : order.length;
			const ib = order.indexOf(sb) >= 0 ? order.indexOf(sb) : order.length;
			if (ia !== ib) return ia - ib; // small index first
			return (a.title || '').localeCompare(b.title || '');
		});
	}

	/* ============================================================
	  Helper - converts DOM cards into a results array (fallback)
	============================================================ */
	_buildResultsFromDom() {
		const container = this.hasResultsTarget ? this.resultsTarget : this.element.querySelector('[data-sort-target="results"]') || this.element;
		if (!container) return [];
		const cards = Array.from(container.children || []);
		return cards.map(card => ({
			title: card.dataset.itemTitle || card.dataset.title || card.dataset.name || '',
			rating: card.dataset.itemRating || card.dataset.rating || '',
			// reviews intentionally omitted
			// score intentionally omitted from DOM fallback
			source: card.dataset.itemSource || card.dataset.source || '',
			url: card.dataset.itemUrl || card.dataset.url || ''
		}));
	}
}
