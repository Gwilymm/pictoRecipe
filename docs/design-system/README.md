# PictoRecette — mise en place du design system v2

Maquettes de référence : canvas « PictoRecette — Refonte design system » (Claude).

## 1. Analyse du projet

| Point | Constat | Conséquence |
|---|---|---|
| Stack front | Symfony 7.3, AssetMapper, TailwindBundle (Tailwind **v4.1.11**), DaisyUI **5.5.3** vendorisé, Stimulus + Turbo, UX Icons | Pas de build Node. Le design system passe par **un seul fichier CSS** |
| Usage DaisyUI | ~105 `btn`, 35 `card`, 19 `badge`, 19 `alert`… et des couleurs **sémantiques** (`bg-base-100` ×33, `text-primary` ×20, `border-base-300` ×35) | Bonne nouvelle : en redéfinissant les thèmes DaisyUI, **~80 % du site change d'apparence sans toucher aux templates** |
| Couleurs en dur | Très peu (`text-gray-500/600`, `text-yellow-400`, `text-blue-*`) | Nettoyage rapide |
| Thème | `theme_controller.js` pose `data-theme="light"/"dark"` (thèmes DaisyUI par défaut) | On remplace par `pictorecette` / `pictorecette-dark` |
| Icônes | Police **Material Icons** (navbar, 8×) + **~100 emoji** (dont 53 dans `home/index`) + 5 Heroicons via UX Icons | Tout passer par `ux_icon()`, supprimer la police et les emoji |
| Texte peu contrasté | `text-base-content/50`, `/60`, `/30` (~16 occurrences) | Passent sous 4,5:1 → remplacer par `text-muted` |
| Libellés anglais | `title`, `servings`, `prepTimeMinutes`, `cookTimeMinutes` sans `label` dans `src/Form/RecipeType.php` → « Title », « Servings »… | Ajouter les `label` en français |
| « 0.00 » sur la fiche | `{{ ingredient.amount }}` brut (`recipe/_preview.html.twig:129`), décimal à 2 chiffres, souvent 0 sur les recettes importées | Macro `ui.amount()` (masque 0, retire `.00`) |
| PDF | `recipe/pdf.html.twig` a son propre CSS (bleu `#2563eb`, `#eef2ff`…) | À aligner à part ; reste toujours en clair |
| `dark:` Tailwind | 2 usages dans `_preview` | Inutiles avec les thèmes DaisyUI, à retirer |

## 2. Fichiers fournis

| Fichier | Destination |
|---|---|
| `app.css` | remplace `assets/styles/app.css` |
| `theme_controller.js` | remplace `assets/controllers/theme_controller.js` |
| `_navbar.html.twig` | remplace `templates/partials/_navbar.html.twig` |
| `_ui.html.twig` | nouveau : `templates/_ds/_ui.html.twig` (macros) |

## 3. Plan de mise en place

Branche : `git switch -c feat/design-system-v2`

### Étape 1 — Fondations (½ journée, gros effet visible)
1. Copier `app.css` et `theme_controller.js`.
2. Dans `base.html.twig` :
   ```twig
   <html lang="fr" data-theme="pictorecette">
   <head>
     <script>try{const t=localStorage.getItem('theme');if(t)document.documentElement.dataset.theme=t;else if(matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.dataset.theme='pictorecette-dark'}catch(e){}</script>
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Lexend:wght@500;600;700&display=swap">
   ```
   - le script inline évite le flash clair → sombre au chargement ;
   - supprimer le `<link>` Material Icons ;
   - `<body class="min-h-screen">` : le fond vient du CSS (`base-200`) ;
   - `<main class="container mx-auto …">` → `<main class="mx-auto max-w-7xl px-4 py-8">`.
3. `php bin/console tailwind:build --watch` et parcourir le site : couleurs, typo, rayons et tailles de boutons (48 px) sont déjà appliqués.

> Le CSS n'a pas pu être compilé de mon côté (pas d'accès npm) : vérifier que `tailwind:build` passe sans erreur au premier lancement.

### Étape 2 — Coquille (½ journée)
1. Icônes : `php bin/console ux:icons:import lucide:plus lucide:moon lucide:sun lucide:image lucide:search lucide:pencil lucide:trash-2 lucide:download lucide:chevron-left lucide:users lucide:clock lucide:link` (elles arrivent dans `assets/icons/lucide/`).
2. Remplacer la navbar par `_navbar.html.twig`.
3. Footer : retirer l'emoji, liens en `link link-hover`.
4. Favicon : remplacer l'emoji 🖼️ par un SVG du logo (4 carrés vert/carotte).

### Étape 3 — Composants (1 jour)
1. Créer `templates/_ds/_ui.html.twig`.
2. Brancher dans les vues : `{% import '_ds/_ui.html.twig' as ui %}` puis `ui.picto_tile()`, `ui.section_title()`, `ui.ingredient_card()`, `ui.step_card()`, `ui.empty_state()`.
3. Boutons : une seule action pleine (`btn-primary`) par écran ; « Modifier » → `btn btn-outline` ; « Supprimer » → `btn btn-outline btn-error` (jamais plein), toujours via `_dialog_modal`.
4. Filtres (recherche en ligne, galerie) : `<button class="chip" aria-pressed="true|false">` à la place des checkbox.

### Étape 4 — Pages, par priorité (2–3 jours)
1. **Fiche recette** (`recipe/_preview` + `preview`) : sections Ingrédients / Ustensiles / Préparation avec `section_title`, grille d'ingrédients, étapes numérotées. Retirer `dark:` et le bouton « Proposer autre » de la vue lecture (le garder en édition).
2. **Assistant** (`recipe/_form`, `stepper_controller`) : barre de progression en 5 segments, libellés FR dans `RecipeType.php`, tuiles ustensiles sélectionnables (`picto-tile is-selected`).
3. **Listes** : `recipe/index` (aujourd'hui cartes en mobile / tableau dès `md` : passer aux cartes avec photo 4/3 à toutes les tailles, une seule version à maintenir), `pictogram/index` (actions en icônes, plus de boutons jaunes/roses), `utensil/index` (bandeau doublons).
4. **Recherche & import** : `recipe/search`, `recipe/import`.
5. **Accueil** : réécrire `home/index` (53 emoji, jargon technique) d'après la maquette.
6. **PDF** : remplacer `#2563eb`/`#eef2ff` par basilic `#1F6F5C`/carotte `#FDEBDC`, garder DejaVu ou embarquer Atkinson Hyperlegible.

### Étape 5 — Nettoyage & contrôle (½ journée)
```bash
# restes à chasser
grep -rnE 'material-icons|text-base-content/(30|50|60)|text-(gray|slate|blue|yellow)-[0-9]|dark:' templates
grep -rnP '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]' templates
```
- Lighthouse / axe sur 4 pages (accueil, liste, fiche, assistant) en clair **et** sombre ;
- navigation au clavier : focus bleu visible partout, ordre logique ;
- test à 375 px de large ;
- génération d'un PDF.

## 4. Règles à retenir

- **Une couleur = un rôle** : `accent` (carotte) = ingrédients, `secondary` (myrtille) = ustensiles/liens, `primary` (basilic) = action/étapes, `error` = suppression.
- La carotte ne sert jamais de couleur de texte (contraste insuffisant).
- Pictogrammes toujours dans `.picto-tile` (fond blanc, y compris en sombre).
- Texte secondaire : `text-muted`, jamais d'opacité sous 70 %.
- Cibles cliquables ≥ 44 px, boutons 48 px.
- Pas d'emoji dans l'interface : icônes `ux_icon()` avec `aria-hidden`, et `aria-label` sur les boutons sans texte.
