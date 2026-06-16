# Observabilite applicative

Les logs metier de production sont ecrits par Monolog au format JSON dans `var/log/app*.log`.
Chaque requete recoit un `request_id`, aussi renvoye au navigateur dans le header `X-Request-Id`.

## Suivre les logs applicatifs

```bash
docker exec pictorecette-app sh -lc 'tail -f var/log/app*.log'
```

En local hors Docker :

```bash
tail -f var/log/app*.log
```

## Filtres utiles

```bash
docker exec pictorecette-app sh -lc 'grep "\"recipe_id\":35" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "recipe.edit" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "recipe.new" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "recipe.step.before_save" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "recipe.pdf.failed" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "pictogram.search.failed" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "pictogram.upload.failed" var/log/app*.log'
docker exec pictorecette-app sh -lc 'grep "\"request_id\":\"REQUEST_ID_ICI\"" var/log/app*.log'
```

## Evenements principaux

- `recipe.new.opened`
- `recipe.new.submitted`
- `recipe.new.valid`
- `recipe.new.invalid`
- `recipe.new.saved`
- `recipe.new.redirect`
- `recipe.edit.opened`
- `recipe.edit.submitted`
- `recipe.edit.valid`
- `recipe.edit.invalid`
- `recipe.step.before_save`
- `recipe.edit.saved`
- `recipe.edit.redirect`
- `recipe.pdf.requested`
- `recipe.pdf.skipped`
- `recipe.pdf.cache_hit`
- `recipe.pdf.cache_miss`
- `recipe.pdf.rendered_html`
- `recipe.pdf.generated`
- `recipe.pdf.failed`
- `pictogram.search.requested`
- `pictogram.search.completed`
- `pictogram.search.no_result`
- `pictogram.search.failed`
- `pictogram.upload.requested`
- `pictogram.upload.saved`
- `pictogram.upload.failed`
- `pictogram_urls.transform.invalid_json`

## Verifier les logs HTTP du serveur

FrankenPHP/Caddy ecrit les logs conteneur sur stdout/stderr :

```bash
docker logs -f pictorecette-app
```

Avec Docker Compose :

```bash
docker compose logs -f pictorecette-app
```

## Verifier les pictogrammes d'une recette en base

PostgreSQL, depuis le conteneur base :

```bash
docker exec pictoRecipeDatabase sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT id, recipe_id, position, pictogram_url, pictogram_urls FROM step WHERE recipe_id = 35 ORDER BY position;"'
```

Si la base de dev utilise les noms par defaut du compose :

```bash
docker exec pictorecette-db-dev sh -lc 'psql -U app -d picto -c "SELECT id, recipe_id, position, pictogram_url, pictogram_urls FROM step WHERE recipe_id = 35 ORDER BY position;"'
```

## Lire un cas utilisateur

1. Demander l'heure approximative, la recette concernee et, si possible, le `X-Request-Id` visible dans l'onglet Network du navigateur.
2. Filtrer par `request_id` ou par `recipe_id`.
3. Pour une sauvegarde de recette, suivre dans l'ordre :
   - `recipe.edit.submitted`
   - `recipe.edit.valid` ou `recipe.edit.invalid`
   - `recipe.step.before_save`
   - `recipe.edit.saved`
   - `recipe.edit.redirect`
4. Pour un PDF, suivre :
   - `recipe.pdf.requested`
   - `recipe.pdf.cache_hit` ou `recipe.pdf.cache_miss`
   - `recipe.pdf.generated` ou `recipe.pdf.failed`

Les logs ne doivent pas contenir de payload POST complet, de token CSRF ni de contenu binaire.
