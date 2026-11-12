# Pré-attribution des pictogrammes — idées

Brainstorming du 12/11/2026, à reprendre plus tard. Rien n'est encore développé.

## État actuel

- **Une logique de correspondance existe, mais elle est inactive.**
  `assets/controllers/pictogram_autofill_controller.js` (`_searchBestPictogram`) sait trouver le meilleur
  pictogramme pour un nom d'ingrédient :
  - il normalise le nom ;
  - il essaie la phrase entière, puis chaque mot du plus long au plus court, puis le singulier ;
  - il préfère la bibliothèque locale à ARASAAC.

  Elle ne se déclenche jamais : `assign()` a besoin d'un `assignBtnTarget` qu'aucun template ne fournit.
- **L'import ne cherche aucun pictogramme pour les ingrédients.**
  `src/Controller/ApiRecipeImportController.php` crée les ingrédients sans image.
  Les ustensiles sont retrouvés par leur nom (sans tenir compte des majuscules) et récupèrent donc leur
  pictogramme s'ils existent déjà dans la bibliothèque.
- **La recherche côté serveur existe déjà.**
  `GET /api/pictograms/search?q=` renvoie les pictogrammes locaux validés
  (`PictogramRepository::findSearchableByKeyword`), puis les résultats ARASAAC.

## Idées

### 1. Attribution automatique côté serveur
- Un service `PictogramMatcher`, qui reprend la logique JS en PHP et devient la seule source de vérité.
- Il est appelé à trois moments :
  - à l'import d'une recette (Marmiton, Cuisine AZ, Papilles & Pupilles) ;
  - à la saisie d'un ingrédient dans l'assistant, quand on quitte le champ nom, via un endpoint ;
  - par un bouton « Compléter les pictogrammes » sur les recettes existantes.

### 2. Une correspondance plus fiable que la recherche brute
- **Nettoyer le nom avant de chercher** : « 200 g de farine T55 » devient « farine ».
  On retire les quantités, les unités, les articles (`de`, `d'`, `du`) et les mots parasites
  (frais, haché, bio, en poudre…).
- **Gérer les variantes** : accents, pluriels, et un petit dictionnaire de synonymes
  (« œuf » / « oeuf », « sucre en poudre » vers « sucre »).
- **Ordre de priorité des sources** :
  1. bibliothèque locale validée ;
  2. ARASAAC ;
  3. OpenFoodFacts en dernier, car ce sont des photos de produits de marque, moins lisibles.

### 3. Mémoire des choix (meilleur rapport effort/gain)
- Une table qui associe un nom d'ingrédient normalisé à un pictogramme.
  Elle se remplit chaque fois que l'utilisateur choisit ou valide un pictogramme.
- La fois suivante, le même ingrédient reçoit le même pictogramme, sans aucun appel externe.
- On peut l'amorcer avec les recettes existantes qui ont déjà des pictogrammes.

### 4. Suggéré ou validé
- Distinguer un pictogramme proposé automatiquement d'un pictogramme validé,
  avec un badge « à vérifier » dans l'assistant.
- Réactiver « Proposer un autre pictogramme » (déjà présent dans `ingredient_card`, mais inactif)
  pour corriger en un clic. La correction est mémorisée tout de suite (idée 3).

### 5. Les étapes (aujourd'hui le plus gros manque dans le PDF)
- Détecter les ingrédients cités dans le texte d'une étape :
  « Ajouter les œufs et le sucre » reprend les pictogrammes œuf et sucre déjà attribués.
- Un dictionnaire de verbes d'action associés à des pictogrammes ARASAAC :
  couper, mélanger, cuire, enfourner…

### 6. Les ustensiles
- Si le site importé ne liste aucun ustensile, les déduire du texte des étapes
  (four, poêle, fouet, moule…).

### 7. Fiabilité
- Copier dans la bibliothèque locale chaque image ARASAAC utilisée, plutôt que de pointer vers leurs serveurs.
  Le PDF (Browsershot) et la prod ne dépendent plus de leur disponibilité.
  La route `POST /api/pictograms/import` existe déjà pour ça.
- Mettre en cache les recherches ARASAAC, avec un délai d'attente court, pour que l'import d'une recette
  de 15 ingrédients reste rapide.

## Ordre proposé

1. `PictogramMatcher` côté serveur, avec le nettoyage des noms, branché sur l'import et sur l'assistant,
   plus le marquage « suggéré ».
2. La mémoire des choix, et l'amorçage à partir des recettes existantes.
3. Les étapes : ingrédients cités, puis verbes d'action.

## Décisions à prendre avant de commencer

- **Sources** : ARASAAC en complément de la bibliothèque locale, ou la bibliothèque seule ?
- **Validation** : en cas de doute, attribuer un pictogramme « à vérifier », ou laisser la case vide ?
- **Licence** : les pictogrammes ARASAAC (CC BY-NC-SA) doivent être crédités.
  Il faut ajouter le crédit dans le PDF s'ils y apparaissent.
