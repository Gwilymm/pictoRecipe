import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
	static targets = [
		'input', 'image', 'placeholder', 'positionX', 'positionY',
		'zoom', 'zoomRange', 'zoomValue', 'viewport', 'url'
	];

	connect() {
		this.positionX = this.clamp(Number(this.positionXTarget.value || 50), 0, 100);
		this.positionY = this.clamp(Number(this.positionYTarget.value || 50), 0, 100);
		this.zoomLevel = this.clamp(Number(this.zoomTarget.value || 1), 1, 3);
		this.applyCrop();
	}

	disconnect() {
		if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
	}

	select(event) {
		const file = event.target.files?.[ 0 ];
		if (!file) return;

		// Un fichier choisi remplace une éventuelle adresse saisie (le serveur donne la priorité au fichier)
		if (this.hasUrlTarget) this.urlTarget.value = '';
		if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
		this.objectUrl = URL.createObjectURL(file);
		this.showImage(this.objectUrl);
	}

	// Adresse d'image saisie : aperçu direct ; le téléchargement réel se fait côté serveur à l'enregistrement
	selectUrl(event) {
		const url = event.target.value.trim();
		if (!/^https?:\/\//i.test(url)) return;

		if (this.hasInputTarget) this.inputTarget.value = '';
		this.showImage(url);
	}

	showImage(src) {
		this.imageTarget.onerror = () => {
			this.imageTarget.classList.add('hidden');
			this.placeholderTarget.textContent = 'Aperçu indisponible : l’image sera tout de même téléchargée à l’enregistrement.';
			this.placeholderTarget.classList.remove('hidden');
		};
		this.imageTarget.src = src;
		this.imageTarget.classList.remove('hidden');
		this.placeholderTarget.classList.add('hidden');
		this.positionX = 50;
		this.positionY = 50;
		this.zoomLevel = 1;
		this.applyCrop();
	}

	startPan(event) {
		if (!this.imageTarget.src) return;
		event.preventDefault();
		this.dragging = true;
		this.startPointerX = event.clientX;
		this.startPointerY = event.clientY;
		this.startPositionX = this.positionX;
		this.startPositionY = this.positionY;
		this.viewportTarget.setPointerCapture(event.pointerId);
		this.viewportTarget.classList.add('cursor-grabbing');
	}

	pan(event) {
		if (!this.dragging) return;
		event.preventDefault();
		const bounds = this.viewportTarget.getBoundingClientRect();
		this.positionX = this.clamp(this.startPositionX - ((event.clientX - this.startPointerX) / bounds.width) * 100, 0, 100);
		this.positionY = this.clamp(this.startPositionY - ((event.clientY - this.startPointerY) / bounds.height) * 100, 0, 100);
		this.applyCrop();
	}

	endPan(event) {
		if (!this.dragging) return;
		this.dragging = false;
		if (this.viewportTarget.hasPointerCapture(event.pointerId)) {
			this.viewportTarget.releasePointerCapture(event.pointerId);
		}
		this.viewportTarget.classList.remove('cursor-grabbing');
	}

	changeZoom(event) {
		this.zoomLevel = this.clamp(Number(event.target.value), 1, 3);
		this.applyCrop();
	}

	reset(event) {
		event?.preventDefault();
		this.positionX = 50;
		this.positionY = 50;
		this.zoomLevel = 1;
		this.applyCrop();
	}

	applyCrop() {
		this.positionXTarget.value = String(Math.round(this.positionX));
		this.positionYTarget.value = String(Math.round(this.positionY));
		this.zoomTarget.value = this.zoomLevel.toFixed(2);
		this.zoomRangeTarget.value = String(this.zoomLevel);
		this.zoomValueTarget.textContent = `${Math.round(this.zoomLevel * 100)} %`;

		this.applyCropToImage(this.imageTarget);

		const form = this.element.closest('form');
		form?.querySelectorAll('[data-recipe-main-image]').forEach(image => {
			if (this.imageTarget.src) image.src = this.imageTarget.src;
			this.applyCropToImage(image);
			image.closest('[data-recipe-main-image-frame]')?.classList.toggle('hidden', !this.imageTarget.src);
		});
	}

	applyCropToImage(image) {
		image.style.objectPosition = `${this.positionX}% ${this.positionY}%`;
		image.style.transformOrigin = `${this.positionX}% ${this.positionY}%`;
		image.style.transform = `scale(${this.zoomLevel})`;
	}

	clamp(value, min, max) {
		return Math.min(max, Math.max(min, Number.isFinite(value) ? value : min));
	}
}
