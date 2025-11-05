# 🖼️ PictoRecipe - Recherche de Pictogrammes ARASAAC

Application Symfony 7 pour rechercher et afficher des pictogrammes depuis l'API publique ARASAAC.

## 📋 Fonctionnalités

- ✅ Recherche de pictogrammes par mot-clé en français
- ✅ Affichage responsive avec TailwindCSS + DaisyUI
- ✅ Gestion des erreurs (réseau, API, aucun résultat)
- ✅ Interface claire et intuitive
- ✅ Liens vers les pictogrammes originaux sur ARASAAC
- ✅ Lazy loading des images

## 🚀 Installation

1. **Cloner le projet** (si nécessaire)

2. **Installer les dépendances PHP**
```bash
composer install
```

3. **Installer les dépendances JavaScript**
```bash
pnpm install
```

4. **Compiler les assets**
```bash
pnpm run dev
# ou pour le mode watch
pnpm run watch
```

5. **Démarrer le serveur Symfony**
```bash
symfony server:start
# ou
php -S localhost:8000 -t public/
```

6. **Accéder à l'application**
Ouvrir votre navigateur à l'adresse : `http://localhost:8000`

## 🏗️ Architecture

### Structure des fichiers créés

```
src/
├── Controller/
│   └── HomeController.php          # Contrôleur principal (route /)
├── Service/
│   └── ArasaacApiService.php       # Service d'appel à l'API ARASAAC

templates/
└── home/
    └── index.html.twig             # Template de la page d'accueil

config/
└── packages/
    └── http_client.yaml            # Configuration du client HTTP
```

### Composants principaux

#### 1. **ArasaacApiService** (`src/Service/ArasaacApiService.php`)
Service responsable de la communication avec l'API ARASAAC :
- Méthode `search(string $keyword): array`
- Gestion des erreurs réseau et API
- Transformation des données pour simplification
- Logging des erreurs

#### 2. **HomeController** (`src/Controller/HomeController.php`)
Contrôleur principal avec une seule route :
- Route : `GET /`
- Paramètre de query : `q` (mot-clé de recherche)
- Utilise l'autowiring pour injecter le service
- Gère les exceptions et transmet les données au template

#### 3. **Template Twig** (`templates/home/index.html.twig`)
Interface utilisateur avec :
- Formulaire de recherche DaisyUI
- Grille responsive (2 colonnes mobile, 4 tablette, 6 desktop)
- Cartes pour chaque pictogramme
- Messages d'erreur et d'information
- Page d'accueil explicative

## 🎨 Design

L'interface utilise **DaisyUI** avec le thème par défaut et inclut :
- Navbar avec logo
- Cartes stylisées pour les pictogrammes
- Alertes pour les erreurs et informations
- Footer avec attribution ARASAAC
- Animations de hover sur les cartes
- Images avec fallback en cas d'erreur de chargement

## 🔌 API ARASAAC

### Endpoint utilisé
```
GET https://api.arasaac.org/api/pictograms/fr/search/{keyword}
```

### Format de réponse
L'API retourne un tableau JSON avec les pictogrammes correspondants.

### URLs des images
Les images sont chargées depuis :
```
https://static.arasaac.org/pictograms/{id}/{id}_500.png
```

## 🧪 Utilisation

1. **Accéder à la page d'accueil** : `http://localhost:8000`
2. **Saisir un mot-clé** dans le champ de recherche (ex : "chat", "manger", "bonjour")
3. **Cliquer sur "Rechercher"**
4. **Parcourir les résultats** affichés en grille
5. **Cliquer sur "Voir détails"** pour accéder au pictogramme sur le site ARASAAC

## ⚙️ Configuration

### HttpClient
Le client HTTP est configuré dans `config/packages/http_client.yaml` avec :
- Base URI : `https://api.arasaac.org`
- Timeout : 10 secondes
- Headers : Accept JSON

### Personnalisation
Pour modifier l'apparence :
- **Thème DaisyUI** : Modifier `assets/styles/app.css`
- **Couleurs** : Ajuster les classes Tailwind dans le template
- **Grille responsive** : Modifier les classes `grid-cols-*` dans `index.html.twig`

## 📝 Bonnes pratiques implémentées

✅ **Typage strict PHP 8.3** : `declare(strict_types=1)`
✅ **Autowiring** : Injection automatique des dépendances
✅ **Gestion des erreurs** : Try/catch avec messages clairs
✅ **Logging** : Utilisation du LoggerInterface
✅ **Code commenté** : Documentation PHPDoc
✅ **Séparation des responsabilités** : MVC strict
✅ **Readonly properties** : Immutabilité des services
✅ **Code sécurisé** : URL encoding, validation des entrées
✅ **Responsive design** : Mobile-first
✅ **Accessibilité** : Alt text, attributs ARIA

## 🔮 Extensions possibles

- [ ] Pagination des résultats
- [ ] Filtres avancés (couleur, catégorie)
- [ ] Téléchargement des pictogrammes
- [ ] Favoris/collections personnalisées
- [ ] Recherche multilingue
- [ ] Cache des résultats
- [ ] Mode hors-ligne avec Service Worker

## 📄 Licence

Les pictogrammes ARASAAC sont sous licence [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/).

## 🔗 Liens utiles

- [Documentation API ARASAAC](https://beta.arasaac.org/developers/api)
- [Site ARASAAC](https://arasaac.org)
- [Documentation Symfony](https://symfony.com/doc/current/index.html)
- [Documentation DaisyUI](https://daisyui.com/)
- [Documentation TailwindCSS](https://tailwindcss.com/)
