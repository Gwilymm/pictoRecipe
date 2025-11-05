Parfait 👌
Tu veux transformer ce **modèle de recette en pictogrammes** (comme l’image de recette de cookies) en **application web Symfony + Tailwind + DaisyUI** connectée à l’API ARASAAC.
Voici une **roadmap claire et progressive** pour concevoir cette app de création de recettes visuelles.

---

## 🧭 Roadmap – App “Recettes Pictogrammes”

### 🏗️ Phase 1 — Base du projet (structure MVC)

**Objectif :** Créer une base Symfony claire avec pages, formulaires, et intégration Tailwind/DaisyUI (déjà faits).
**Durée estimée :** 1 jour

1. **Créer les entités principales**

   * `Recipe` (titre, description, durée, nb_personnes, etc.)
   * `Ingredient` (nom, quantité, unité, lien avec la recette)
   * `Step` (ordre, texte descriptif, lien avec la recette)
2. **Relations**

   * `Recipe` ⟶ `Ingredient` : OneToMany
   * `Recipe` ⟶ `Step` : OneToMany
3. **CRUD basique**

   * Générer les CRUD Symfony pour `Recipe`, `Ingredient`, `Step`
   * Ajouter quelques formulaires simples pour créer une recette manuellement

---

### 🔍 Phase 2 — Recherche automatique de pictogrammes

**Objectif :** Connecter ton app à l’API **ARASAAC** pour retrouver automatiquement les pictogrammes correspondant à un mot.
**Durée estimée :** 2 jours

1. **Créer un service `ArasaacApiService`**

   * Méthode `search(keyword: string): array`
   * Appelle `GET https://api.arasaac.org/api/pictograms/fr/search/{keyword}`
   * Retourne la liste d’images avec leurs URLs (`image`, `keywords`, `synonyms`…)

2. **Ajouter un champ “pictogramme” dans `Ingredient` et `Step`**

   * `pictogramUrl` (string nullable)
   * Si vide, un bouton “Rechercher pictogramme” permet de l’obtenir automatiquement via l’API.

3. **Créer un composant UI (Stimulus ou Alpine.js)**

   * Quand on tape un mot dans un champ (ex: “farine”), une requête AJAX va chercher les pictogrammes correspondants et affiche une sélection visuelle.
   * L’utilisateur peut cliquer pour sélectionner le bon pictogramme.
   * L’URL du pictogramme est enregistrée dans la base.

---

### 🎨 Phase 3 — Interface de création visuelle

**Objectif :** Rendre la création fluide et visuelle comme dans ton image d’exemple.
**Durée estimée :** 3 jours

1. **Interface “Créer une recette”**

   * Stepper DaisyUI (étapes successives)

     1. Nom + photo + description
     2. Ajouter les ingrédients (nom + quantité + pictogramme)
     3. Ajouter les étapes de préparation (texte + pictogramme)
   * Boutons “+ Ajouter un ingrédient” / “+ Ajouter une étape”

2. **Aperçu visuel**

   * Une page `/recipe/{id}/preview` affiche la recette comme une **fiche pictogramme** :

     * Grille d’ingrédients avec images ARASAAC
     * Étapes numérotées avec pictos et texte
   * Style proche de ton modèle : blocs visuels, pictos centrés, texte clair.

---

### 📄 Phase 4 — Génération et partage

**Objectif :** Pouvoir exporter la recette ou la partager.
**Durée estimée :** 2 jours

1. **Export PDF**

   * Générer un PDF “recette pictogramme” avec `dompdf` ou `wkhtmltopdf`
   * Mise en page inspirée de ton image :

     * Titres, ingrédients, étapes avec pictos et texte
2. **Partage**

   * Générer un lien public `/r/{slug}`
   * Possibilité de copier un QR Code (utilise `endroid/qr-code-bundle`)

---

### 🧩 Phase 5 — Améliorations & UX

**Objectif :** Rendre l’app agréable et accessible.
**Durée estimée :** 2 jours

* Thème clair/foncé DaisyUI
* Sélecteur de langue (fr, en, es → API ARASAAC supporte plusieurs langues)
* Gestion du cache local (pictos enregistrés localement pour usage offline via PWA)
* Authentification simple pour sauvegarder ses recettes personnelles

---

## 🚀 Récapitulatif

| Phase | Objectif              | Délai estimé | Livrable principal            |
| ----- | --------------------- | ------------ | ----------------------------- |
| 1     | Base Symfony CRUD     | 1 jour       | Entities + Formulaires        |
| 2     | Connexion API ARASAAC | 2 jours      | Recherche pictos              |
| 3     | Interface visuelle    | 3 jours      | Création par étapes + preview |
| 4     | Export / partage      | 2 jours      | PDF + QR Code                 |
| 5     | UX / PWA              | 2 jours      | Thème, langue, cache          |

---

Souhaites-tu que je te fasse à partir de cette roadmap une **première maquette (UI en DaisyUI + structure des routes)** pour que tu puisses commencer à coder directement ?
