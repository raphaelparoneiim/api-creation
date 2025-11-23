# Marketplace API

API Platform / Symfony 7.3 sécurisée par JWT pour gérer utilisateurs, catégories, médias et produits. Toutes les routes `/api` sont protégées par `ROLE_USER`.

## Installation rapide

```bash
git clone <repo_url> marketplace-api
cd marketplace-api
cp .env .env.local          # adapte DATABASE_URL, JWT_PASSPHRASE, etc.
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair --overwrite   # si besoin
```

Crée un premier utilisateur via CLI (hash avec `php bin/console security:hash-password`, puis `doctrine:query:sql`).

## Lancer l'API

```bash
symfony server:start --port=8001 -d
```

Swagger UI : http://127.0.0.1:8001/api

## Tests Postman (essentiel)

Base URL : http://127.0.0.1:8001  
Headers à mettre sur toutes les requêtes JSON :
- `Content-Type: application/json`
- `Authorization` = `Bearer {{token}}` (le test 1 enregistre `token` via `pm.collectionVariables.set("token", data.token);`. Si tu testes manuellement, copie le token retourné et remplace `{{token}}` par la chaîne réelle.)

| # | Requête | Corps / Notes | Tests Postman |
|---|---|---|---|
| 1 | `POST {{base_url}}/api/login` | `{ "email": "admin@marketplace.test", "password": "change-me" }` | `pm.response.to.have.status(200); const data = pm.response.json(); pm.collectionVariables.set("token", data.token);` |
| 2 | `POST {{base_url}}/api/users` | `{ "email": "buyer@marketplace.test", "firstname": "Buyer", "lastname": "Test", "plainPassword": "Password123!" }` | Vérifie `201` et stocke `pm.collectionVariables.set("user_iri", body["@id"]);` |
| 3 | `POST {{base_url}}/api/categories` | `{ "title": "Informatique" }` | Vérifie `201`, stocke `category_iri`. |
| 4 | `POST {{base_url}}/api/media` | `{ "filePath": "uploads/laptop.jpg", "contentUrl": "https://picsum.photos/seed/laptop/600/400" }` | Vérifie `201`, stocke `media_iri`. |
| 5 | `POST {{base_url}}/api/products` | `{ "title": "Laptop Pro 14”", "content": "16 Go RAM, 1 To SSD", "price": 1899.9, "isPublished": true, "category": "{{category_iri}}", "media": "{{media_iri}}" }` | Vérifie `201`, stocke `product_iri`. |
| 6 | `GET {{base_url}}/api/products?title=Laptop&isPublished=true&price[gt]=1000&media[exists]=1` | — | Vérifie `200` + `hydra:member` non vide. |
| 7 | `PATCH {{product_iri}}` (merge-patch) | `{ "price": 1799.9 }` | Vérifie `200` + nouveau prix. |
| 8 | `DELETE {{product_iri}}` | — | Vérifie `204`. |

Lancer la collection avec le Runner ⇒ 8/8 tests = API fonctionnelle (auth, CRUD, filtres).

## Commandes utiles

`composer install` · `php bin/console doctrine:migrations:diff` · `symfony server:stop`
