// Petits utilitaires partagés par les contrôleurs qui génèrent du HTML.

// Échappe une valeur avant de l'insérer dans du HTML (données de formulaire ou de sites tiers).
export function esc(value) {
	const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
	return String(value ?? '').replace(/[&<>"']/g, m => map[ m ]);
}

// Icônes Lucide (copies de assets/icons/lucide/*.svg) pour le HTML généré en JS.
export const CLOCK_ICON = '<svg viewBox="0 0 24 24" class="size-5" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></g></svg>';
export const IMAGE_ICON = '<svg viewBox="0 0 24 24" class="size-1/3 text-base-300" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15l-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></g></svg>';
