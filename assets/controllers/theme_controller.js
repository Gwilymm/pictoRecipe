import { Controller } from '@hotwired/stimulus';

// Stimulus controller to manage DaisyUI themes
// Usage: place data-controller="theme" on a <select> or container
// - If used on a <select>, bind change->theme#select
// - Otherwise, add data-theme-target="select" to a <select> inside
export default class extends Controller {
	static targets = [ 'select' ];
	static values = {
		themes: Array,           // e.g. ["light","dark","cupcake"]
		defaultTheme: { type: String, default: '' }
	};

	connect() {
		this.html = document.documentElement;

		// Allowed themes
		this.availableThemes = this.themesValue?.length ? this.themesValue : [ 'light', 'dark', 'cupcake' ];

		// Determine initial theme: localStorage > html[data-theme] > prefers-color-scheme > first available
		const stored = this._safeGet('theme');
		const currentAttr = this.html.getAttribute('data-theme');
		const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

		let initial = stored || currentAttr || (prefersDark ? 'dark' : (this.defaultThemeValue || 'light'));
		if (!this.availableThemes.includes(initial)) {
			initial = this.availableThemes[ 0 ];
		}

		this.applyTheme(initial);
		this._syncSelect(initial);
	}

	// When the select changes
	select(event) {
		const theme = event?.target?.value;
		if (!theme) return;
		this.applyTheme(theme);
		this._safeSet('theme', theme);
	}

	// Programmatic toggle between first two themes
	toggle() {
		const current = this.currentTheme();
		const idx = this.availableThemes.indexOf(current);
		const next = this.availableThemes[ (idx + 1) % this.availableThemes.length ];
		this.applyTheme(next);
		this._safeSet('theme', next);
	}

	applyTheme(theme) {
		this.html.setAttribute('data-theme', theme);
		this._syncSelect(theme);
	}

	currentTheme() {
		return this.html.getAttribute('data-theme') || this.availableThemes[ 0 ];
	}

	_syncSelect(theme) {
		const selectEl = this.hasSelectTarget ? this.selectTarget : (this.element.tagName === 'SELECT' ? this.element : null);
		if (selectEl) {
			selectEl.value = theme;
		}
	}

	_safeGet(key) {
		try { return window.localStorage.getItem(key); } catch { return null; }
	}
	_safeSet(key, val) {
		try { window.localStorage.setItem(key, val); } catch { /* ignore */ }
	}
}
