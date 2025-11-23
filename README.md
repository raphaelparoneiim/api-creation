# Marketplace API – Guide Débutant

API REST “marketplace” (Symfony 7.3 + API Platform) : les utilisateurs publient des produits rattachés à des catégories et médias. Toutes les routes `/api` sont sécurisées par JWT (`/api/login`). Ce guide t’accompagne étape par étape.

---

## 1. Installation

```bash
git clone <repo_url> marketplace-api
cd marketplace-api
cp .env .env.local          # personnalise APP_SECRET, DATABASE_URL, etc.
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair --overwrite   # regenère les clés si besoin
```

### Créer l’utilisateur administrateur

Les routes `/api/*` sont protégées ; il te faut un premier compte pour générer un JWT :

```bash
php bin/console security:hash-password "change-me" App\\Entity\\User
# copie le hash retourné puis :
php bin/console doctrine:query:sql "
INSERT INTO user (email, roles, password, firstname, lastname)
VALUES ('admin@marketplace.test', '[\"ROLE_ADMIN\"]', '<hash>', 'Admin', 'User');
"
```

---

## 2. Lancer l’API

```bash
symfony server:start --port=8001 -d
```

- API docs : http://127.0.0.1:8001/api  
- Pour arrêter : `symfony server:stop`

---

## 3. Entités exposées

| Ressource | Endpoint | Remarques |
| --- | --- | --- |
| Auth | `POST /api/login` | Renvoie `{ token, firstname, ... }` |
| User | `/api/users` | `plainPassword` est hashé automatiquement (processor) |
| Category | `/api/categories` | CRUD + relation vers produits |
| Media | `/api/media` | Sauvegarde `filePath`, `contentUrl`… |
| Product | `/api/products` | Filtres par `title`, `price`, `isPublished`, `createdDate`, `media`… |

---

## 4. Tests Postman détaillés

**Important :** toutes les requêtes Postman doivent respecter ces headers :

- `Authorization` = `Bearer <token>` (mets un espace après `Bearer`)
- `Content-Type` = `application/ld+json`

Pour le token : lance le test 1 (login), copie la valeur `token` du JSON de réponse **ou** laisse le script Postman le stocker dans `token` (voir plus bas). Si tu fais des essais hors Postman (curl, HTTPie), remplace `<token>` par la chaîne exacte.

### 4.1 Préparer Postman

1. Crée une collection “Marketplace API”.
2. Ajoute chaque requête ci-dessous en utilisant **http://127.0.0.1:8001** (pas de variable).
3. Dans l’onglet *Tests* de chaque requête, colle le script fourni pour enregistrer les IRIs/jetons.

> À propos des `{{category_iri}}`, `{{media_iri}}`, etc.  
> Ce sont des variables Postman. Quand une requête renvoie `"@id": "/api/categories/3"`, le script fait `pm.collectionVariables.set("category_iri", body["@id"]);`.  
> **Si tu exécutes la collection avec le Runner**, tu peux conserver `{{category_iri}}` dans les requêtes suivantes.  
> **Si tu testes manuellement (curl, interface Swagger)**, remplace-les **à la main** par l’IRI réel (ex. `/api/categories/3`). Ne les laisse jamais sous forme `{{...}}`.

### 4.2 Scénario complet

La colonne “Corps (sans variables Postman)” montre ce que tu dois envoyer si tu testes sans placeholders. En Collection Runner, tu peux réutiliser `{{category_iri}}`, etc.

| # | Requête | Corps (sans variables Postman) |
|---|---|---|
| 1 | `POST http://127.0.0.1:8001/api/login` | ```json { "email": "admin@marketplace.test", "password": "change-me" } ``` |
| 2 | `POST http://127.0.0.1:8001/api/users` | ```json { "email": "buyer@marketplace.test", "firstname": "Buyer", "lastname": "Test", "plainPassword": "Password123!" } ``` |
| 3 | `POST http://127.0.0.1:8001/api/categories` | ```json { "title": "Informatique" } ``` |
| 4 | `POST http://127.0.0.1:8001/api/media` | ```json { "filePath": "uploads/laptop.jpg", "contentUrl": "https://picsum.photos/seed/laptop/600/400" } ``` |
| 5 | `POST http://127.0.0.1:8001/api/products` | ```json { "title": "Laptop Pro 14”", "content": "16 Go RAM, 1 To SSD", "price": 1899.9, "isPublished": true, "category": "/api/categories/1", "media": "/api/media/1" } ``` |
| 6 | `GET http://127.0.0.1:8001/api/products?title=Laptop&isPublished=true&price[gt]=1000&media[exists]=1` | — |
| 7 | `PATCH {{product_iri}}` (géneré étape 5) | ```json { "price": 1799.9 } ``` (header `Content-Type: application/merge-patch+json`) |
| 8 | `DELETE {{product_iri}}` | — |

Quand tu lances le *Collection Runner*, tu dois voir **8/8 tests OK**. Si tu fais des tests à la main, pense à récupérer les valeurs `@id` dans la réponse JSON et à remplacer les `{{...}}` avant d’envoyer la requête suivante.

---

## 5. Commandes utiles

| Commande | Description |
| --- | --- |
| `composer install` | installe les dépendances |
| `php bin/console doctrine:migrations:migrate` | applique les migrations |
| `php bin/console doctrine:migrations:diff` | génère une nouvelle migration |
| `php bin/console lexik:jwt:generate-keypair --overwrite` | régénère les clés JWT |
| `symfony server:start --port=8001 -d` / `symfony server:stop` | démarrer/arrêter le serveur |

---

Bon testing ! Une fois ces étapes validées, tu peux personnaliser les entités, ajouter des fixtures ou brancher un autre SGBD (PostgreSQL via Docker est déjà prêt dans `compose.yaml`). Debugge avec `symfony server:log` si besoin. Liste les produits sur http://127.0.0.1:8001/api pour vérifier que tout est OK. Bonne exploration 🚀
