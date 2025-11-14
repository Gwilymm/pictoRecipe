import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
	static targets = [ "input", "results" ];

	connect() {
		console.log("PictoSearchController connected");

		this.inputTarget.addEventListener("input", () => {
			clearTimeout(this.timer);
			this.timer = setTimeout(() => this.search(), 350);
		});
	}

	async search() {
		const q = this.inputTarget.value.trim();
		if (q.length < 2) {
			this.resultsTarget.innerHTML = "";
			return;
		}

		try {
			const res = await fetch(`/api/picto/search?q=${encodeURIComponent(q)}&limit=20`);
			const data = await res.json();

			if (!data.success) {
				this.resultsTarget.innerHTML = "<div class='text-sm'>Erreur de recherche</div>";
				return;
			}

			this.renderImages(data.results);
		} catch (e) {
			console.error("Erreur OFF:", e);
			this.resultsTarget.innerHTML = "<div class='text-sm text-error'>Impossible de contacter OpenFoodFacts</div>";
		}
	}

	renderImages(items) {
		this.resultsTarget.innerHTML = "";

		items.forEach(item => {
			if (!item.image) return; // ignorer produits sans image

			const img = document.createElement("img");
			img.src = item.image;
			img.alt = item.name || "";
			img.className =
				"w-full h-32 object-contain bg-white p-1 rounded-lg border cursor-pointer transition hover:shadow-lg";

			img.addEventListener("click", () => this.select(item, img));

			this.resultsTarget.appendChild(img);
		});

		// reset scroll au cas où
		this.resultsTarget.scrollTop = 0;
	}

	select(item, element) {
		// Effacer l’ancien highlight
		this.resultsTarget.querySelectorAll("img").forEach(img => {
			img.classList.remove("ring", "ring-primary", "ring-2");
		});

		// Ajouter le surlignage
		element.classList.add("ring", "ring-primary", "ring-2");

		// 🔥 MAJ du champ hidden externeImageTemp
		const hidden = document.getElementById("externalImageTemp");
		if (hidden) hidden.value = item.image;

		// 🔥 Envoi de l’événement pour la preview
		this.element.dispatchEvent(
			new CustomEvent("picto:selected", { detail: item, bubbles: true })
		);
	}
}
