// ⚠️ OBSOLETE - Ce serveur Node.js n'est plus utilisé
// La fonctionnalité a été migrée vers Symfony
// Voir: docs/MARMITON_INTEGRATION.md
//
// Ce fichier est conservé pour référence uniquement
// Il peut être supprimé en toute sécurité

import express from 'express';
import { searchRecipes, MarmitonQueryBuilder, RECIPE_PRICE, RECIPE_DIFFICULTY, RECIPE_TYPE } from 'marmiton-api';

const app = express();
const PORT = 3001;

app.use(express.json());

// Enable CORS for local requests
app.use((req, res, next) => {
	res.header('Access-Control-Allow-Origin', '*');
	res.header('Access-Control-Allow-Headers', 'Content-Type');
	next();
});

app.post('/api/search', async (req, res) => {
	try {
		console.log('Received search request:', req.body);

		const { query, dish, budget, difficulty, time, vegan, vegetarian, withoutGluten, withoutDairyProducts, withoutOven, raw, withPhoto } = req.body;

		// Build query using MarmitonQueryBuilder
		const qb = new MarmitonQueryBuilder();

		// Title search
		if (query && query.trim()) {
			qb.withTitleContaining(query.trim());
		}

		// Recipe type
		if (dish && dish !== 'all') {
			qb.withType(dish);
		}

		// Budget
		if (budget && budget !== 'all') {
			qb.withPrice(parseInt(budget));
		}

		// Difficulty
		if (difficulty && difficulty !== 'all') {
			qb.withDifficulty(parseInt(difficulty));
		}

		// Time
		if (time && time > 0) {
			qb.takingLessThan(parseInt(time));
		}

		// Dietary options
		if (vegetarian) {
			qb.vegetarian();
		}

		if (vegan) {
			qb.vegan();
		}

		if (withoutGluten) {
			qb.withoutGluten();
		}

		if (withoutDairyProducts) {
			qb.withoutDairyProducts();
		}

		// Cooking options
		if (withoutOven) {
			qb.withoutOven();
		}

		if (raw) {
			qb.raw();
		}

		// Other options
		if (withPhoto) {
			qb.withPhoto();
		}

		const queryString = qb.build();
		console.log('Built query string:', queryString);

		// Search recipes (limit to 12 by default)
		const recipes = await searchRecipes(queryString, { limit: 12 });
		console.log('Found recipes:', recipes.length);

		// Format results for frontend
		const results = recipes.map(recipe => ({
			id: recipe.id || null,
			title: recipe.name || 'Sans titre',
			image: recipe.images && recipe.images[ 0 ] ? recipe.images[ 0 ] : 'https://via.placeholder.com/150',
			duration: recipe.totalTime ? `${recipe.totalTime} min` : 'N/A',
			difficulty: recipe.difficulty ? [ '', 'Très facile', 'Facile', 'Moyenne', 'Difficile' ][ recipe.difficulty ] : 'N/A',
			budget: recipe.budget ? [ '', 'Bon marché', 'Moyen', 'Assez cher' ][ recipe.budget ] : 'N/A',
			rate: recipe.rate || null,
			url: recipe.url || null,
		}));

		res.json({
			success: true,
			results: results,
			count: results.length
		});

	} catch (error) {
		console.error('Search error:', error);
		res.status(500).json({
			success: false,
			error: error.message,
			results: []
		});
	}
});

app.listen(PORT, () => {
	console.log(`Marmiton API server listening on port ${PORT}`);
});
