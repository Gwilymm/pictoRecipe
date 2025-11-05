# 🎨 Système de Pictogrammes ARASAAC - PictoRecipe

## 📋 Vue d'ensemble

Le système de pictogrammes ARASAAC permet d'associer des images visuelles aux ingrédients et étapes de vos recettes. Il utilise l'API publique ARASAAC pour rechercher et sélectionner des pictogrammes en français.

## 🏗️ Architecture

### Backend (Symfony 7)

**Entités modifiées :**
- `src/Entity/Ingredient.php` : ajout de `pictogramUrl` (string, nullable)
- `src/Entity/Step.php` : ajout de `pictogramUrl` (string, nullable)

**Service API :**
- `src/Service/ArasaacApiService.php` : Service d'intégration avec l'API ARASAAC
  - Méthode `search(string $keyword)` : recherche de pictogrammes par mot-clé
  - Gestion automatique des erreurs et timeout
  - Retourne un tableau simplifié avec id, keywords, name, imageUrl

**Contrôleur API :**
- `src/Controller/PictogramApiController.php`
  - Route : `GET /api/pictograms/search?q={keyword}`
  - Retourne un JSON avec les résultats de recherche
  - Gestion des erreurs avec messages appropriés

**Formulaires :**
- `src/Form/IngredientType.php` : ajout du champ `pictogramUrl` (hidden)
- `src/Form/StepType.php` : ajout du champ `pictogramUrl` (hidden)

### Frontend (Stimulus + DaisyUI)

**Contrôleur Stimulus :**
- `assets/controllers/pictogram_controller.js`
  - Recherche automatique avec debounce (500ms)
  - Affichage des résultats en grille responsive
  - Sélection visuelle avec bordure DaisyUI
  - Aperçu du pictogramme sélectionné
  - Toast de confirmation
  - Gestion du spinner de chargement

**Templates Twig :**
- `templates/partials/_pictogram_widget.html.twig` : Widget réutilisable
- `templates/recipe/_form.html.twig` : Intégration dans le formulaire de recette

## 🚀 Utilisation

### Dans l'interface

1. **Éditer une recette** : Accédez au formulaire d'édition d'une recette
2. **Ajouter un ingrédient ou une étape** : Cliquez sur "Ajouter un ingrédient" ou "Ajouter une étape"
3. **Rechercher un pictogramme** :
   - Tapez au moins 2 caractères dans le champ de recherche
   - La recherche se lance automatiquement après 500ms
   - Ou cliquez sur le bouton "🔍 Rechercher"
4. **Sélectionner un pictogramme** :
   - Cliquez sur l'image souhaitée dans la grille
   - Le pictogramme sélectionné s'affiche avec une bordure bleue
   - Un aperçu apparaît en haut du widget
5. **Enregistrer** : Cliquez sur "Enregistrer la recette" pour sauvegarder

### Dans le code

#### Utiliser le widget dans un formulaire Twig

```twig
{{ include('partials/_pictogram_widget.html.twig', {
    'form_field': ingredient.name,
    'url_field': ingredient.pictogramUrl,
    'placeholder': 'Rechercher un pictogramme pour cet ingrédient...'
}) }}
```

#### Appeler l'API depuis le backend

```php
use App\Service\ArasaacApiService;

public function __construct(
    private readonly ArasaacApiService $arasaacService
) {}

public function searchPictograms(string $keyword): array
{
    return $this->arasaacService->search($keyword);
}
```

#### Récupérer les pictogrammes dans un template

```twig
{% if ingredient.pictogramUrl %}
    <img src="{{ ingredient.pictogramUrl }}" 
         alt="{{ ingredient.name }}" 
         class="w-16 h-16 object-contain" />
{% endif %}
```

## 🎨 Personnalisation

### Modifier le délai de recherche automatique

Dans le template qui utilise le widget :

```twig
<div data-controller="pictogram"
     data-pictogram-debounce-delay-value="1000">
    ...
</div>
```

### Personnaliser l'apparence

Le widget utilise les classes DaisyUI. Vous pouvez les modifier dans :
- `templates/partials/_pictogram_widget.html.twig` : Structure HTML
- `assets/controllers/pictogram_controller.js` : Classes dynamiques

Classes DaisyUI utilisées :
- `input input-bordered input-sm` : Champ de recherche
- `btn btn-outline btn-primary btn-sm` : Bouton de recherche
- `loading loading-spinner text-primary` : Spinner
- `card bg-base-200 hover:bg-base-300` : Cartes de pictogrammes
- `ring ring-primary ring-offset-2` : Sélection active
- `alert alert-warning` / `alert alert-error` : Messages
- `toast toast-top toast-end` : Notifications

## 📊 Base de données

### Migration

La migration `Version20251105155134` a ajouté :
```sql
ALTER TABLE ingredient ADD pictogram_url VARCHAR(500) DEFAULT NULL;
ALTER TABLE step ADD pictogram_url VARCHAR(500) DEFAULT NULL;
```

### Annuler la migration

```bash
php bin/console doctrine:migrations:migrate prev
```

## 🔧 Dépannage

### Le widget ne s'affiche pas

1. Vérifiez que les assets sont compilés : `pnpm run build`
2. Vérifiez que Stimulus est chargé dans la console navigateur
3. Vérifiez que le contrôleur est détecté : `pictogram_controller.js` dans `assets/controllers/`

### La recherche ne renvoie rien

1. Vérifiez votre connexion internet (l'API ARASAAC est externe)
2. Testez directement l'API : https://api.arasaac.org/api/pictograms/fr/search/pain
3. Vérifiez les logs : `var/log/dev.log`
4. Testez la route API : `/api/pictograms/search?q=pain`

### Les images ne s'affichent pas

1. Les URLs des pictogrammes pointent vers `https://static.arasaac.org/`
2. Vérifiez qu'il n'y a pas de blocage CORS
3. Vérifiez la console navigateur pour les erreurs de chargement

### Le pictogramme n'est pas sauvegardé

1. Vérifiez que le champ hidden `pictogramUrl` est bien présent dans le formulaire
2. Inspectez l'élément pour voir si la valeur est remplie
3. Vérifiez dans les données POST que le champ est envoyé
4. Vérifiez la méthode `setPictogramUrl()` de l'entité

## 🔗 Ressources

- **API ARASAAC** : https://api.arasaac.org/api.html
- **Documentation ARASAAC** : https://arasaac.org/developers/api
- **Stimulus** : https://stimulus.hotwired.dev/
- **DaisyUI** : https://daisyui.com/

## 📝 Notes techniques

- **Cache** : Les résultats de l'API peuvent être mis en cache (à implémenter si nécessaire)
- **Performance** : Le debounce évite les appels API excessifs
- **Accessibilité** : Les images ont des attributs `alt` appropriés
- **Responsive** : La grille s'adapte : 3 colonnes (mobile), 4 (tablet), 6 (desktop)
- **Lazy loading** : Les images sont chargées avec `loading="lazy"`

## 🚀 Évolutions possibles

- [ ] Ajouter un système de favoris pour les pictogrammes fréquemment utilisés
- [ ] Permettre l'upload de pictogrammes personnalisés
- [ ] Ajouter un cache serveur avec Redis pour les recherches
- [ ] Créer une preview des recettes avec pictogrammes
- [ ] Exporter les recettes en PDF avec les pictogrammes
- [ ] Ajouter des filtres de recherche (couleur, catégorie)
- [ ] Internationalisation (recherche en plusieurs langues)
- [ ] Mode hors-ligne avec pictogrammes pré-téléchargés
