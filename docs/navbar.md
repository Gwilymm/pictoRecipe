# 🧭 Navbar Composant

## Vue d'ensemble

Navbar réutilisable DaisyUI avec :
- ✅ Menu responsive (mobile hamburger + desktop horizontal)
- ✅ Liens vers tous les CRUD (Recettes, Ingrédients, Étapes)
- ✅ Sélecteur de thème DaisyUI intégré (9 thèmes disponibles)
- ✅ Icônes SVG pour chaque menu
- ✅ Sticky navbar avec backdrop blur
- ✅ Badge ARASAAC

## 🎨 Thèmes disponibles

Le sélecteur propose 9 thèmes DaisyUI :
1. 🌞 Light (par défaut)
2. 🌙 Dark
3. 🧁 Cupcake
4. 💚 Emerald
5. 🌆 Synthwave
6. 📻 Retro
7. 🤖 Cyberpunk
8. 💖 Valentine
9. 🌊 Aqua

Le thème est :
- Persisté dans `localStorage`
- Auto-détecté via `prefers-color-scheme`
- Géré par le contrôleur Stimulus `theme_controller.js`

## 📁 Fichiers

### Template
- `templates/_navbar.html.twig` - Composant navbar réutilisable

### Intégration base
- `templates/base.html.twig` - Inclut la navbar via `{{ include('_navbar.html.twig') }}`

### Configuration
- `assets/styles/app.css` - Active les thèmes DaisyUI
- `assets/controllers/theme_controller.js` - Contrôleur Stimulus pour gérer les thèmes

## 🔧 Utilisation

### Dans base.html.twig (déjà fait)
```twig
{% block navbar %}
    {{ include('_navbar.html.twig') }}
{% endblock %}
```

### Pour masquer la navbar sur une page spécifique
```twig
{% extends 'base.html.twig' %}

{% block navbar %}
    {# Navbar désactivée pour cette page #}
{% endblock %}

{% block body %}
    {# Votre contenu #}
{% endblock %}
```

### Pour personnaliser la navbar
Créez un override du block navbar :
```twig
{% extends 'base.html.twig' %}

{% block navbar %}
    {# Votre navbar custom #}
    <div class="navbar bg-primary">
        {# ... #}
    </div>
{% endblock %}
```

## 🎯 Structure du menu

### Mobile (< lg)
- Menu hamburger avec dropdown
- Sous-menus via `<details>` / `<summary>`

### Desktop (≥ lg)
- Menu horizontal centré
- Dropdowns natifs via `<details>`

### Menus disponibles
1. **Accueil** - Recherche de pictogrammes (`app_home`)
2. **Recettes** 
   - Liste (`app_recipe_index`)
   - Créer (`app_recipe_new`)
3. **Ingrédients**
   - Liste (`app_ingredient_index`)
   - Créer (`app_ingredient_new`)
4. **Étapes**
   - Liste (`app_step_index`)
   - Créer (`app_step_new`)

## 🎨 Classes DaisyUI utilisées

- `navbar` - Container principal
- `navbar-start` - Section gauche (logo + menu mobile)
- `navbar-center` - Section centre (menu desktop)
- `navbar-end` - Section droite (thème + badge)
- `dropdown` - Dropdown mobile
- `menu` - Liste de navigation
- `btn-ghost` - Boutons transparents
- `badge` - Badge ARASAAC
- `select` - Sélecteur de thème

## 🔗 Routes requises

Assurez-vous que ces routes existent dans vos contrôleurs :

```php
// HomeController
#[Route('/', name: 'app_home')]

// RecipeController
#[Route('/recipe', name: 'app_recipe_index')]
#[Route('/recipe/new', name: 'app_recipe_new')]

// IngredientController
#[Route('/ingredient', name: 'app_ingredient_index')]
#[Route('/ingredient/new', name: 'app_ingredient_new')]

// StepController
#[Route('/step', name: 'app_step_index')]
#[Route('/step/new', name: 'app_step_new')]
```

## 🚀 Personnalisation

### Ajouter un nouveau menu
Dans `templates/_navbar.html.twig`, ajoutez un item dans les deux sections (mobile et desktop) :

```twig
{# Mobile #}
<li>
    <a href="{{ path('app_votre_route') }}">
        <svg><!-- icône --></svg>
        Votre menu
    </a>
</li>

{# Desktop #}
<li>
    <a href="{{ path('app_votre_route') }}" class="gap-2">
        <svg><!-- icône --></svg>
        Votre menu
    </a>
</li>
```

### Ajouter un thème
1. Modifier `assets/styles/app.css` :
```css
@plugin "daisyui" {
    themes: light, dark, cupcake, emerald, votre_theme;
}
```

2. Ajouter l'option dans la navbar :
```twig
<option value="votre_theme">🎨 Votre Thème</option>
```

3. Mettre à jour le data-attribute :
```twig
data-theme-themes-value='["light","dark","cupcake","votre_theme"]'
```

## ⚡ Performance

- Navbar sticky avec `position: sticky`
- Backdrop blur pour effet glassmorphism
- Lazy loading des sous-menus (dropdowns)
- Z-index optimisé pour éviter les conflits

## 📱 Responsive

- **Mobile** (< lg) : Menu hamburger
- **Desktop** (≥ lg) : Menu horizontal
- Badge ARASAAC caché sur mobile (< sm)
- Logo texte caché sur petit écran (< sm)

## 🎨 Icônes

Toutes les icônes proviennent de **Heroicons** (outline) et sont intégrées en SVG inline pour :
- Meilleure performance (pas de requête HTTP)
- Personnalisation facile des couleurs
- Pas de dépendance externe
