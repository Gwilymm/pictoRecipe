import { Controller } from '@hotwired/stimulus';

// Stimulus controller to manage DaisyUI theme swap (light/dark)
export default class extends Controller {
	static targets = [ 'toggle' ];

	connect() {
		this.html = document.documentElement;

		// Load saved theme or detect system preference
		const stored = this._safeGet('theme');
		const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		const initial = stored || (prefersDark ? 'dark' : 'light');

		this.applyTheme(initial);
	}

	// When the swap checkbox changes
	toggle(event) {
		const isDark = event.target.checked;
		const theme = isDark ? 'dark' : 'light';
		this.applyTheme(theme);
		this._safeSet('theme', theme);
	}

	applyTheme(theme) {
		this.html.setAttribute('data-theme', theme);
		if (this.hasToggleTarget) {
			this.toggleTarget.checked = (theme === 'dark');
		}
	}

	_safeGet(key) {
		try {
			return window.localStorage.getItem(key);
		} catch {
			return null;
		}
	}

	_safeSet(key, val) {
		try {
			window.localStorage.setItem(key, val);
		} catch {
			/* ignore */
		}
	}
}