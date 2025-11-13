import { Controller } from '@hotwired/stimulus';

// Stimulus controller that replaces CSRF placeholder tokens rendered in cached HTML.
// It expects to be attached to the hidden input itself (Symfony renders data-controller="csrf-protection" on the field).
export default class extends Controller {
	connect() {
		try {
			const input = this.element;

			// Only operate on inputs with name like 'formName[_token]'
			const name = input.getAttribute('name');
			if (!name) return;

			// Helper to read cookie by name
			const readCookie = (key) => {
				const m = document.cookie.match('(^|;)\\s*' + key + '\\s*=\\s*([^;]+)');
				return m ? decodeURIComponent(m[ 2 ]) : null;
			};

			// Try cookie first
			const cookieVal = readCookie('csrf-token');
			if (cookieVal && cookieVal !== 'csrf-token') {
				input.value = cookieVal;
				return;
			}

			// Fallback: fetch token from server endpoint using the form name (before the '[')
			const formName = name.split('[')[ 0 ];
			if (!formName) return;

			fetch('/_csrf/' + encodeURIComponent(formName), { credentials: 'same-origin' })
				.then(r => { if (!r.ok) throw new Error('no-token'); return r.json(); })
				.then(json => {
					if (json && json.token) {
						input.value = json.token;
					}
				})
				.catch(e => {
					// silent fallback
					console && console.debug && console.debug('csrf-protection fetch failed', e);
				});
		} catch (e) {
			console && console.debug && console.debug('csrf-protection error', e);
		}
	}
}
