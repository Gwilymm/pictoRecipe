import { Controller } from '@hotwired/stimulus';

// Bascule clair / sombre — remplace assets/controllers/theme_controller.js
// Par défaut : suit le réglage de l'appareil. Le choix manuel est mémorisé.
const LIGHT = 'pictorecette';
const DARK = 'pictorecette-dark';

export default class extends Controller {
	static targets = ['button', 'label'];

	connect() {
		const stored = this._get('theme');
		const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
		this.apply(stored === DARK || stored === LIGHT ? stored : (prefersDark ? DARK : LIGHT));
	}

	toggle() {
		const next = document.documentElement.dataset.theme === DARK ? LIGHT : DARK;
		this.apply(next);
		this._set('theme', next);
	}

	apply(theme) {
		document.documentElement.dataset.theme = theme;
		const dark = theme === DARK;
		if (this.hasButtonTarget) this.buttonTarget.setAttribute('aria-pressed', String(dark));
		if (this.hasLabelTarget) this.labelTarget.textContent = dark ? 'Thème clair' : 'Thème sombre';
	}

	_get(k) { try { return localStorage.getItem(k); } catch { return null; } }
	_set(k, v) { try { localStorage.setItem(k, v); } catch { /* ignore */ } }
}
