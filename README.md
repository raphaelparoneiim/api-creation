# Marketplace API – Guide Débutant

Tu as ici une API Symfony 7.3/API Platform pour gérer des utilisateurs, catégories, médias et produits. Tout passe par JWT (`/api/login`). Ce README t’explique pas à pas comment installer, lancer et tester le projet **comme si tu n’avais jamais touché à API Platform**.

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

| # | Requête | Corps | Script Tests |
|---|---|---|---|
| 1 | `POST http://127.0.0.1:8001/api/login` | ```json{ "email": "admin@marketplace.test", "password": "change-me" }``` | ```javascript pm.test("200", () => pm.response.to.have.status(200)); const data = pm.response.json(); pm.collectionVariables.set("token", data.token);``` |
| 2 | `POST http://127.0.0.1:8001/api/users` | ```json{ "email": "buyer@marketplace.test", "firstname": "Buyer", "lastname": "Test", "plainPassword": "Password123!" }``` | ```javascript pm.test("201", () => pm.response.to.have.status(201)); const body = pm.response.json(); pm.collectionVariables.set("user_iri", body["@id"]);``` |
| 3 | `POST http://127.0.0.1:8001/api/categories` | ```json{ "title": "Informatique" }``` | ```javascript pm.test("201", () => pm.response.to.have.status(201)); const body = pm.response.json(); pm.collectionVariables.set("category_iri", body["@id"]);``` |
| 4 | `POST http://127.0.0.1:8001/api/media` | ```json{ "filePath": "uploads/laptop.jpg", "contentUrl": "https://picsum.photos/seed/laptop/600/400" }``` | ```javascript pm.test("201", () => pm.response.to.have.status(201)); pm.collectionVariables.set("media_iri", pm.response.json()["@id"]);``` |
| 5 | `POST http://127.0.0.1:8001/api/products` | ```json{ "title": "Laptop Pro 14”", "content": "16 Go RAM, 1 To SSD", "price": 1899.9, "isPublished": true, "category": "{{category_iri}}", "media": "{{media_iri}}" }``` | ```javascript pm.test("201", () => pm.response.to.have.status(201)); const product = pm.response.json(); pm.collectionVariables.set("product_iri", product["@id"]); pm.collectionVariables.set("product_id", product.id);``` |
| 6 | `GET http://127.0.0.1:8001/api/products?title=Laptop&isPublished=true&price[gt]=1000&media[exists]=1` | (aucun body) | ```javascript pm.test("200", () => pm.response.to.have.status(200)); const list = pm.response.json()["hydra:member"]; pm.test("au moins 1", () => pm.expect(list.length).to.be.above(0));``` |
| 7 | `PATCH {{product_iri}}` (URL de l’étape 5) | Header supplémentaire `Content-Type: application/merge-patch+json` ; body ```json{ "price": 1799.9 }``` | ```javascript pm.test("200", () => pm.response.to.have.status(200)); pm.test("prix 1799.9", () => pm.expect(pm.response.json().price).to.eql(1799.9));``` |
| 8 | `DELETE {{product_iri}}` | (aucun body) | ```javascript pm.test("204", () => pm.response.to.have.status(204));``` |

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

## 6. FAQ débutant

- **401 “JWT Token not found”** : l’entête `Authorization: Bearer <token>` manque ou est mal écrit (pas d’espace). Relance `POST /api/login` et copie le nouveau token.
- **415 “application/json non supporté”** : mets `Content-Type: application/ld+json`.
- **“Invalid IRI {{category_iri}}”** : tu as oublié de remplacer le placeholder par la vraie valeur (`/api/categories/1`).
- **Erreur SQLite NOT NULL** : assure-toi que la requête contient toutes les propriétés obligatoires (`title`, `content`, `price`, `isPublished`, etc.).

Bon testing ! Une fois ces étapes validées, tu peux personnaliser les entités, ajouter des fixtures ou brancher un autre SGBD (PostgreSQL via Docker est déjà prêt dans `compose.yaml`). Debugge avec `symfony server:log` si besoin. Liste les produits sur http://127.0.0.1:8001/api pour vérifier que tout est OK. Bonne exploration 🚀
