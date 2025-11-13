# Intégration Marmiton - Solution Symfony

## Vue d'ensemble

Cette solution remplace l'utilisation du paquet `marmiton-api` Node.js par une implémentation native Symfony qui :
- Scrappe directement le site Marmiton.org
- Utilise les composants Symfony natifs (HttpClient, DOMCrawler, Cache)
- Expose des endpoints API REST pour le frontend Stimulus
- Affiche les recettes dans une modale sans iframe

## Architecture

### Backend (Symfony)

#### 1. Service `MarmitonScraperService`
**Fichier:** `src/Service/MarmitonScraperService.php`

**Responsabilités:**
- Effectuer les requêtes HTTP vers Marmiton.org
- Parser le HTML avec DOMCrawler
- Extraire les données pertinentes (recettes, ingrédients, étapes)
- Mettre en cache les résultats (TTL: 30 minutes)
- Sanitiser le HTML pour éviter les injections XSS

**Méthodes principales:**
- `searchRecipes(string $query, int $limit, array $filters): array` - Recherche de recettes
- `fetchRecipe(string $url): array` - Récupération d'une recette complète

#### 2. Contrôleur `MarmitonApiController`
**Fichier:** `src/Controller/MarmitonApiController.php`

**Endpoints exposés:**

##### POST `/api/marmiton/search`
Recherche de recettes sur Marmiton

**Request Body:**
```json
{
  "q": "chocolat-sans-gluten",
  "limit": 20,
  "filters": {
    "withPhoto": true,
    "vegetarian": false,
    "vegan": false,
    "withoutGluten": true,
    "withoutDairy": false,
    "withoutOven": false,
    "difficulty": null,
    "price": null,
    "maxTime": null
  }
}
```

**Response:**
```json
{
  "success": true,
  "results": [
    {
      "name": "Gâteau au chocolat sans gluten",
      "title": "Gâteau au chocolat sans gluten",
      "link": "https://www.marmiton.org/recettes/recette_...",
      "url": "https://www.marmiton.org/recettes/recette_...",
      "image": "https://...",
      "picture": "https://...",
      "category": "Dessert",
      "rating": "4.5",
      "reviews": "234 avis",
      "position": 1
    }
  ],
  "count": 20
}
```

##### POST `/api/marmiton/recipe`
Récupération d'une recette complète

**Request Body:**
```json
{
  "link": "https://www.marmiton.org/recettes/recette_xxx.aspx"
}
```

**Response:**
```json
{
  "ok": true,
  "html": "<div class='marmiton-extract'>...</div>",
  "fragments": {
    "title": "Gâteau au chocolat",
    "primary": "<div class='recipe-primary'>...</div>",
    "ingredients": "<div class='mrtn-recette_ingredients'>...</div>",
    "preparation": "<div class='recipe-preparation'>...</div>"
  }
}
```

##### GET `/api/marmiton/health`
Vérification de l'état du service

**Response:**
```json
{
  "ok": true,
  "service": "marmiton-scraper",
  "php_version": "8.3.0",
  "timestamp": 1699999999
}
```

### Frontend (Stimulus)

#### Contrôleur `recipe_search_controller.js`
**Fichier:** `assets/controllers/recipe_search_controller.js`

**Fonctionnalités:**
- Formulaire de recherche avec critères multiples
- Affichage des résultats sous forme de cartes
- Ouverture de recettes dans une modale (sans iframe)
- Parsing et extraction des sections pertinentes (titre, ingrédients, préparation)
- Gestion du scaling des portions (fonctionnalité avancée)

**Méthodes principales:**
- `search(event)` - Effectue la recherche
- `displayResults(results)` - Affiche les résultats
- `openRecipeModal(url)` - Ouvre une recette en modale
- `displayRecipeInModal(html, container)` - Parse et affiche le HTML de la recette
- `initRecipeScaling(container)` - Active le scaling des portions

## Sélecteurs CSS utilisés

### Pour la recherche
- `ul.search-list li.search-list__item` - Liste des résultats
- `a.card-content__title` - Titre et lien de la recette
- `img` - Image de la recette
- `.image-label` - Catégorie
- `.rating__rating` - Note
- `.rating__nbreviews` - Nombre d'avis

### Pour la recette
- `.mrtn-recette_title`, `.recipe-title`, `h1` - Titre (fallback)
- `.recipe-primary`, `.marmiton-extract` - Métadonnées principales
- `.mrtn-recette_ingredients`, `.recipe-ingredients` - Ingrédients
- `.mrtn-recette_utensils`, `.recipe-utensils` - Ustensiles
- `.recipe-preparation`, `.recipe-step-list` - Étapes de préparation

**Note:** Plusieurs sélecteurs fallback sont définis pour gérer les changements de structure HTML de Marmiton.

## Sécurité et bonnes pratiques

### 1. Sanitization HTML
Le service `MarmitonScraperService` effectue un nettoyage basique :
- Suppression des balises `<script>`
- Suppression des attributs d'événements (`onclick`, etc.)

**⚠️ Recommandation:** Pour la production, intégrer une bibliothèque comme **HTML Purifier** pour une sanitization robuste.

### 2. Cache
- TTL: 30 minutes par défaut (configurable)
- Cache Symfony standard (peut utiliser Redis, Filesystem, etc.)
- Réduit la charge sur Marmiton.org

### 3. Rate Limiting
**À implémenter:** Utiliser le composant `RateLimiter` de Symfony pour limiter le nombre de requêtes par utilisateur/IP.

### 4. Respect des CGU
⚠️ **Important:** Vérifier que le scraping est autorisé par Marmiton.org
- Vérifier `robots.txt`
- Respecter les conditions d'utilisation
- Considérer l'utilisation d'une API officielle si disponible

### 5. User Agent
Le service utilise un User-Agent identifiable : `Mozilla/5.0 (compatible; PictoRecipe/1.0)`

## Installation et configuration

### 1. Vérifier les dépendances Symfony
```bash
composer require symfony/http-client
composer require symfony/dom-crawler
composer require symfony/css-selector
composer require symfony/cache
```

### 2. Configuration du cache (optionnel)
**Fichier:** `config/packages/cache.yaml`

```yaml
framework:
    cache:
        app: cache.adapter.filesystem
        # ou pour Redis:
        # app: cache.adapter.redis
        # default_redis_provider: redis://localhost
```

### 3. Tester les endpoints

#### Santé du service
```bash
curl http://localhost:8000/api/marmiton/health
```

#### Recherche
```bash
curl -X POST http://localhost:8000/api/marmiton/search \
  -H "Content-Type: application/json" \
  -d '{"q":"chocolat","limit":5,"filters":{}}'
```

#### Recette
```bash
curl -X POST http://localhost:8000/api/marmiton/recipe \
  -H "Content-Type: application/json" \
  -d '{"link":"https://www.marmiton.org/recettes/recette_xxx.aspx"}'
```

## Tests

### Tests manuels
1. Ouvrir la page de recherche de recettes
2. Entrer un terme de recherche (ex: "chocolat")
3. Vérifier l'affichage des résultats
4. Cliquer sur "Voir la recette"
5. Vérifier que la modale s'ouvre sans iframe
6. Vérifier l'affichage du titre, ingrédients, et préparation

### Tests automatisés (à implémenter)
```php
// tests/Service/MarmitonScraperServiceTest.php
public function testSearchRecipes(): void
{
    $results = $this->scraperService->searchRecipes('chocolat', 5, []);
    
    $this->assertIsArray($results);
    $this->assertNotEmpty($results);
    $this->assertArrayHasKey('name', $results[0]);
    $this->assertArrayHasKey('link', $results[0]);
}
```

## Dépannage

### Erreur : "No route found for POST /api/marmiton/search"
**Solution:** Vider le cache Symfony
```bash
php bin/console cache:clear
```

### Erreur : "Failed to fetch recipes: timeout"
**Solution:** Augmenter le timeout dans `MarmitonScraperService::REQUEST_TIMEOUT`

### Les recettes ne s'affichent pas correctement
**Solution:** Vérifier les sélecteurs CSS dans `MarmitonScraperService::extractRecipeFragments()`
- Marmiton peut avoir changé sa structure HTML
- Ajouter des logs pour voir le HTML retourné
- Mettre à jour les sélecteurs fallback

### Cache persistant
**Solution:** Vider le cache manuellement
```bash
php bin/console cache:pool:clear cache.app
```

## Migration depuis marmiton-server.js

### Ancien système (Node.js)
- ✅ Paquet `marmiton-api` désinstallé
- ✅ Serveur Express désinstallé
- ⚠️ Fichier `marmiton-server.js` peut être supprimé (sauf si besoin de référence)

### Scripts package.json
Le script `"marmiton-server": "node marmiton-server.js"` peut être supprimé.

## Améliorations futures

1. **Rate Limiting côté Symfony**
   ```php
   use Symfony\Component\RateLimiter\RateLimiterFactory;
   
   $limiter = $rateLimiterFactory->create('marmiton_api');
   $limit = $limiter->consume(1);
   if (!$limit->isAccepted()) {
       throw new TooManyRequestsHttpException();
   }
   ```

2. **HTML Purifier pour sanitization robuste**
   ```bash
   composer require ezyang/htmlpurifier
   ```

3. **Logs détaillés avec Monolog**
   - Tracer les temps de réponse
   - Alerter sur les erreurs de scraping

4. **Monitoring**
   - Métriques : nombre de recherches, taux d'erreur
   - Alertes si Marmiton change sa structure

5. **Fallback sur API officielle**
   - Si disponible à l'avenir

## Contrat API - Résumé

| Endpoint               | Méthode | Description                |
| ---------------------- | ------- | -------------------------- |
| `/api/marmiton/search` | POST    | Recherche de recettes      |
| `/api/marmiton/recipe` | POST    | Récupération d'une recette |
| `/api/marmiton/health` | GET     | État du service            |

---

**Auteur:** PictoRecipe Team  
**Date:** Novembre 2025  
**Version:** 1.0
