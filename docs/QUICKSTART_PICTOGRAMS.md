# 🎨 Guide de démarrage rapide - Système de Pictogrammes

## ✅ Ce qui a été implémenté

### 🎯 Objectif atteint
Vous pouvez maintenant associer des pictogrammes ARASAAC à vos ingrédients et étapes de recettes !

### 🔧 Composants créés

1. **Backend Symfony**
   - ✅ `ArasaacApiService` : Service d'appel à l'API ARASAAC
   - ✅ `PictogramApiController` : Endpoint API `/api/pictograms/search`
   - ✅ Propriété `pictogramUrl` ajoutée aux entités `Ingredient` et `Step`
   - ✅ Migration de base de données exécutée

2. **Frontend Stimulus + DaisyUI**
   - ✅ `pictogram_controller.js` : Contrôleur Stimulus pour la recherche
   - ✅ Widget réutilisable `_pictogram_widget.html.twig`
   - ✅ Intégration dans le formulaire de recette
   - ✅ Affichage des pictogrammes dans la vue de recette

## 🚀 Comment utiliser

### Éditer une recette

1. Ouvrez une recette existante ou créez-en une nouvelle
2. Ajoutez un ingrédient ou une étape
3. Sous chaque champ, vous verrez un widget de recherche de pictogramme

### Rechercher un pictogramme

1. **Tapez au moins 2 caractères** dans le champ de recherche
   - La recherche se lance automatiquement après 500ms
   - Ou cliquez sur "🔍 Rechercher"

2. **Parcourez les résultats** affichés en grille
   - Les pictogrammes sont chargés depuis l'API ARASAAC
   - Survolez pour voir le nom complet

3. **Cliquez sur un pictogramme** pour le sélectionner
   - Il s'affichera avec une bordure bleue
   - Un aperçu apparaît en haut du widget
   - Un toast de confirmation s'affiche brièvement

4. **Enregistrez la recette**
   - L'URL du pictogramme est automatiquement sauvegardée
   - Elle sera visible dans la vue détaillée de la recette

### Visualiser les pictogrammes

Quand vous consultez une recette, les pictogrammes s'affichent :
- 📸 À côté de chaque ingrédient (petite vignette 48x48px)
- 📸 À côté de chaque étape (vignette 64x64px)

## 🎨 Aperçu de l'interface

### Widget de recherche
```
┌─────────────────────────────────────────────┐
│ [Rechercher un pictogramme...] [🔍 Recher..]│
│ 💡 Tapez au moins 2 caractères              │
│                                              │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐        │
│ │ 🥖  │ │ 🧈  │ │ 🥛  │ │ 🥚  │        │
│ └──────┘ └──────┘ └──────┘ └──────┘        │
│ ┌──────┐ ┌──────┐                           │
│ │ 🧂  │ │ 🍯  │                           │
│ └──────┘ └──────┘                           │
└─────────────────────────────────────────────┘
```

### Pictogramme sélectionné
```
┌─────────────────────────────────────────────┐
│ ┌───────────────────────────────────────┐  │
│ │ [🥖] Pain                          [✕]│  │
│ └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

## 🐛 Dépannage rapide

### Le widget ne s'affiche pas ?
```bash
# Recompiler les assets
pnpm run build
```

### La recherche ne fonctionne pas ?
1. Vérifiez votre connexion internet (l'API ARASAAC est externe)
2. Testez directement : https://api.arasaac.org/api/pictograms/fr/search/pain
3. Consultez les logs : `tail -f var/log/dev.log`

### Les images ne se chargent pas ?
- Les URLs proviennent de `https://static.arasaac.org/`
- Vérifiez dans la console navigateur (F12) les erreurs de chargement
- Assurez-vous qu'il n'y a pas de bloqueur de contenu

## 📚 Documentation complète

Pour plus de détails, consultez :
- **Documentation technique** : `docs/pictograms.md`
- **API ARASAAC** : https://api.arasaac.org/api.html
- **Code source** :
  - Service : `src/Service/ArasaacApiService.php`
  - Contrôleur : `src/Controller/PictogramApiController.php`
  - Stimulus : `assets/controllers/pictogram_controller.js`
  - Widget : `templates/partials/_pictogram_widget.html.twig`

## 🎯 Prochaines étapes

Maintenant que le système est en place, vous pouvez :

1. **Tester** : Créez une recette et ajoutez des pictogrammes
2. **Personnaliser** : Modifiez les styles DaisyUI selon vos goûts
3. **Étendre** : Ajoutez des fonctionnalités (favoris, cache, etc.)

## ✨ Fonctionnalités

- ✅ Recherche en temps réel avec debounce
- ✅ Affichage en grille responsive (3/4/6 colonnes)
- ✅ Sélection visuelle avec bordure DaisyUI
- ✅ Aperçu du pictogramme sélectionné
- ✅ Notifications toast
- ✅ Spinner de chargement
- ✅ Gestion des erreurs
- ✅ Messages informatifs
- ✅ Lazy loading des images
- ✅ Sauvegarde automatique dans la base

## 🙏 Crédits

Les pictogrammes proviennent de **ARASAAC** (Aragon Portal of Augmentative and Alternative Communication), sous licence Creative Commons BY-NC-SA.

---

**Bon appétit avec vos recettes illustrées ! 🍽️✨**
