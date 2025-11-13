import { Controller } from '@hotwired/stimulus';

// Manage DaisyUI custom theme swap: pictorecette-light / pictorecette-dark
export default class extends Controller {
	static targets = [ 'toggle' ];

	connect() {
		this.html = document.documentElement;

		const stored = this._safeGet('theme');
		const prefersDark =
			window.matchMedia &&
			window.matchMedia('(prefers-color-scheme: pictorecette-dark)').matches;

		// Default theme logic
		const initial =
			stored ||
			(prefersDark ? 'pictorecette-dark' : 'pictorecette-light');

		this.applyTheme(initial);
	}

	// When toggle (checkbox) changes
	toggle(event) {
		const isDark = event.target.checked;

		const theme = isDark
			? 'pictorecette-dark'
			: 'pictorecette-light';

		this.applyTheme(theme);
		this._safeSet('theme', theme);
	}

	applyTheme(theme) {
		this.html.setAttribute('data-theme', theme);

		if (this.hasToggleTarget) {
			this.toggleTarget.checked = (theme === 'pictorecette-dark');
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
