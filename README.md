# Marketplace API

API REST construite avec Symfony 7.3 et API Platform 4 pour gérer un catalogue de produits, leurs catégories et leurs médias. L'authentification se fait via JWT (LexikJWTAuthenticationBundle) et toutes les routes `/api` sont sécurisées par `ROLE_USER`.

- **Stack** : PHP 8.2, Symfony 7.3, API Platform, Doctrine ORM 3, SQLite (par défaut) ou PostgreSQL, JWT, Nelmio CORS.
- **Ressources** : `User`, `Category`, `Media`, `Product` (+ filtres de recherche, booléens, numériques, plages et existence sur les produits).
- **Documentation interactive** : http://localhost:8000/api (Swagger UI générée par API Platform).

---

## 1. Prérequis

| Outil | Version conseillée | Notes |
| --- | --- | --- |
| PHP | ≥ 8.2 avec extensions `ctype`, `iconv`, `pdo_sqlite` (ou `pdo_pgsql`) | vérifier `php -v` |
| Composer | ≥ 2.5 | gestion des dépendances |
| Symfony CLI | ≥ 5 | simplifie les commandes locales |
| Docker & Docker Compose | optionnel | requis seulement si vous préférez PostgreSQL |
| OpenSSL | 1.1+ | génération des clés JWT |
| Postman | dernière version | exécution des tests décrits plus bas |

---

## 2. Installation

```bash
git clone <repo_url> marketplace-api
cd marketplace-api
composer install
```

### 2.1 Variables d'environnement

1. Dupliquez `.env` en `.env.local`.
2. Modifiez les valeurs sensibles uniquement dans `.env.local` :
   ```env
   APP_ENV=dev
   DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
   JWT_PASSPHRASE=change-me
   CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
   ```
3. Si vous utilisez PostgreSQL via Docker, remplacez `DATABASE_URL` par :
   ```
   DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
   ```

### 2.2 Base de données

- **SQLite (par défaut)** : rien d'autre à faire, le fichier est créé dans `var/data.db`.
- **PostgreSQL via Docker** :
  ```bash
  docker compose up -d database
  ```
  (le port 5432 est exposé via `compose.override.yaml`).

Ensuite exécutez :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

### 2.3 Clés JWT

Les clés RSA attendues par LexikJWT sont dans `config/jwt`. Si vous devez les régénérer :

```bash
php bin/console lexik:jwt:generate-keypair --overwrite
```

### 2.4 Création d'un premier utilisateur

Les routes `/api/users` nécessitent déjà un token. Créez un compte initial côté CLI :

```bash
# 1. Génère un hash
php bin/console security:hash-password
# 2. Insérez l'utilisateur dans SQLite
php bin/console doctrine:query:sql \
  "INSERT INTO user (email, roles, password, firstname, lastname) VALUES (
    'admin@marketplace.test',
    '[\"ROLE_ADMIN\"]',
    '<hash_généré>',
    'Admin',
    'User'
  )"
```

Vous pourrez ensuite utiliser ce compte dans Postman pour récupérer un JWT et créer d'autres utilisateurs via l'API (champ `plainPassword`).

---

## 3. Lancer l'API

```bash
symfony console cache:clear
symfony server:start -d   # ou symfony serve -d
# la documentation est accessible sur http://127.0.0.1:8000/api
```

Pour arrêter : `symfony server:stop`.

---

## 4. Ressources exposées

| Ressource | Endpoint principal | Points clés |
| --- | --- | --- |
| Authentification | `POST /api/login` | renvoie `token` JWT (TTL 3600s) + `firstname` dans le payload via `JWTCreatedListener` |
| User | `/api/users` | email unique, `plainPassword` est hashé côté serveur avant persistance, toutes les opérations requièrent `ROLE_USER` |
| Category | `/api/categories` | CRUD complet + exposition des produits liés |
| Media | `/api/media` | stocke `filePath`, `contentUrl` et `file`; peut être rattaché à un produit |
| Product | `/api/products` | associe catégorie + média, champs `title`, `content`, `price`, `isPublished`, `createdDate` lecture seule |

### Filtres disponibles sur `/api/products`

- `?title=chaussure` et `?content=` pour la recherche partielle.
- `?isPublished=true` pour filtrer sur la publication.
- `?price[gt]=100`, `?price[lt]=500`, `?price[between]=100..500`.
- `?createdDate[before]=2025-01-01`.
- `?media[exists]=1` pour ne retourner que les produits ayant un média associé.

---

## 5. Tests Postman

Ces tests couvrent l'ensemble du flux (auth, CRUD, filtres) et garantissent que l'API fonctionne.

### 5.1 Préparer Postman

1. Créez un environnement `Marketplace API (local)` avec les variables :
   - `base_url` = `http://127.0.0.1:8000`
   - `token` = *vide (sera défini automatiquement)*.
2. Ajoutez une collection `Marketplace API`.

Pour chaque requête authentifiée, ajoutez l'entête :
```
Authorization: Bearer {{token}}
```

### 5.2 Scénario de tests

> Importez ces requêtes dans la collection, puis exécutez-les dans l'ordre via le *Collection Runner*. Les scripts `Tests` fournis réalisent les assertions et stockent les variables nécessaires (`token`, IRIs…).

#### Test 1 – Authentification JWT
- **Requête** : `POST {{base_url}}/api/login`
- **Body (raw JSON)** :
  ```json
  {
    "email": "admin@marketplace.test",
    "password": "change-me"
  }
  ```
- **Tests (onglet Tests)** :
  ```javascript
  pm.test("200 OK", () => pm.response.to.have.status(200));
  const data = pm.response.json();
  pm.test("Token présent", () => pm.expect(data).to.have.property("token"));
  pm.collectionVariables.set("token", data.token);
  ```

#### Test 2 – Création d'un utilisateur métier
- **Requête** : `POST {{base_url}}/api/users`
- **Body** :
  ```json
  {
    "email": "buyer@marketplace.test",
    "firstname": "Buyer",
    "lastname": "Test",
    "plainPassword": "Password123!"
  }
  ```
- **Tests** :
  ```javascript
  pm.test("201 Created", () => pm.response.to.have.status(201));
  const body = pm.response.json();
  pm.collectionVariables.set("user_iri", body["@id"]);
  ```

#### Test 3 – Création d'une catégorie
- **Requête** : `POST {{base_url}}/api/categories`
- **Body** :
  ```json
  { "title": "Informatique" }
  ```
- **Tests** :
  ```javascript
  pm.test("201 Created", () => pm.response.to.have.status(201));
  pm.collectionVariables.set("category_iri", pm.response.json()["@id"]);
  ```

#### Test 4 – Création d'un média
- **Requête** : `POST {{base_url}}/api/media`
- **Body** :
  ```json
  {
    "filePath": "uploads/laptop.jpg",
    "contentUrl": "https://picsum.photos/seed/laptop/600/400"
  }
  ```
- **Tests** :
  ```javascript
  pm.test("201 Created", () => pm.response.to.have.status(201));
  pm.collectionVariables.set("media_iri", pm.response.json()["@id"]);
  ```

#### Test 5 – Création d'un produit
- **Requête** : `POST {{base_url}}/api/products`
- **Body** :
  ```json
  {
    "title": "Laptop Pro 14”",
    "content": "Ultra portable, 16 Go RAM, 1 To SSD.",
    "price": 1899.9,
    "isPublished": true,
    "category": "{{category_iri}}",
    "media": "{{media_iri}}"
  }
  ```
- **Tests** :
  ```javascript
  pm.test("201 Created", () => pm.response.to.have.status(201));
  const product = pm.response.json();
  pm.collectionVariables.set("product_iri", product["@id"]);
  pm.collectionVariables.set("product_id", product.id);
  ```

#### Test 6 – Listing filtré
- **Requête** : `GET {{base_url}}/api/products?title=Laptop&isPublished=true&price[gt]=1000&media[exists]=1`
- **Tests** :
  ```javascript
  pm.test("200 OK", () => pm.response.to.have.status(200));
  const json = pm.response.json();
  pm.test("Au moins un produit retourné", () => pm.expect(json["hydra:member"].length).to.be.above(0));
  ```

#### Test 7 – Mise à jour partielle
- **Requête** : `PATCH {{product_iri}}`
- **Headers** : `Content-Type: application/merge-patch+json`
- **Body** :
  ```json
  { "price": 1799.9 }
  ```
- **Tests** :
  ```javascript
  pm.test("200 OK", () => pm.response.to.have.status(200));
  pm.test("Prix mis à jour", () => pm.expect(pm.response.json().price).to.eql(1799.9));
  ```

#### Test 8 – Suppression
- **Requête** : `DELETE {{product_iri}}`
- **Tests** :
  ```javascript
  pm.test("204 No Content", () => pm.response.to.have.status(204));
  ```

L'exécution de la collection doit afficher 8/8 tests réussis, garantissant que l'ensemble du parcours utilisateur fonctionne (auth, création d'entités, filtrage, mise à jour, suppression).

---

## 6. Commandes utiles

| Commande | Rôle |
| --- | --- |
| `composer install` | installe les dépendances |
| `php bin/console doctrine:migrations:migrate` | exécute les migrations |
| `php bin/console doctrine:migrations:diff` | génère une migration à partir des entités |
| `php bin/console lexik:jwt:generate-keypair --overwrite` | régénère les clés JWT |
| `symfony server:start -d` / `symfony server:stop` | démarre / arrête le serveur de dev |

---

## 7. Aller plus loin

- Ajouter des fixtures (`doctrine:fixtures`) pour préparer un jeu de données complet.
- Brancher le `UserPasswordHasherProcessor` d'API Platform pour hasher automatiquement `plainPassword`.
- Raccorder `CreateMediaObjectAction` à une opération `POST` multipart si vous souhaitez uploader des fichiers binaires.
- Étendre les tests (PHPUnit, Behat) pour compléter la couverture offerte par les tests Postman.

Bon développement ! 🚀
