# 🚀 Marketplace API – Guide Débutant

**Marketplace API** est une API REST développée en **PHP (Symfony 7.3 + API Platform)**.  
Les utilisateurs peuvent publier des produits rattachés à des **catégories** et des **médias**.  
Toutes les routes `/api/*` sont sécurisées par **JWT** (`/api/login`). 😎

❗ Lien vers la vidéo : ❗

---

## 1️⃣ Installation

```bash
git clone <repo_url> marketplace-api  
cd marketplace-api  
cp .env .env.local          # personnalise APP_SECRET, DATABASE_URL, etc.  
composer install  
php bin/console doctrine:database:create --if-not-exists  
php bin/console doctrine:migrations:migrate  
php bin/console lexik:jwt:generate-keypair --overwrite   # régénère les clés si besoin
```

### 👤 Créer l’utilisateur administrateur

```bash
php bin/console security:hash-password "change-me" App\\Entity\\User  
# copie le hash retourné puis :  
php bin/console doctrine:query:sql "  
INSERT INTO user (email, roles, password, firstname, lastname)  
VALUES ('admin@marketplace.test', '[\"ROLE_ADMIN\"]', '<hash>', 'Admin', 'User');  
"
```

---

## 2️⃣ Lancer l’API

symfony server:start  

- API docs : [http://127.0.0.1:8000/api](http://127.0.0.1:8000/api) 📄  
- Pour arrêter le serveur : symfony server:stop 🛑

---

## 3️⃣ Tests Postman

### 🔑 Headers importants
Toutes les requêtes doivent respecter ces headers :  

Authorization: Bearer <token>  # mets un espace après Bearer  
Content-Type: application/ld+json  

- Pour obtenir `<token>` : lance la requête login (étape 1) et copie le token renvoyé. 📝

### 3.1 Scénario complet

| # | Requête | Corps (avec variables Postman) | Où récupérer la variable |
|---|---------|--------------------------------|--------------------------|
| 1 | POST `http://127.0.0.1:8000/api/login` | `{ "email": "admin@marketplace.test", "password": "change-me" }` | — |
| 2 | POST `http://127.0.0.1:8000/api/users` | `{ "email": "buyer@marketplace.test", "firstname": "Buyer", "lastname": "Test", "plainPassword": "Password123!" }` | — |
| 3 | POST `http://127.0.0.1:8000/api/categories` | `{ "title": "Informatique" }` | `category_iri` = `@id` de la réponse (ex: `/api/categories/4`) 🏷️ |
| 4 | POST `http://127.0.0.1:8000/api/media` | `{ "filePath": "uploads/laptop.jpg", "contentUrl": "https://picsum.photos/seed/laptop/600/400" }` | `media_iri` = `@id` de la réponse (ex: `/api/media/3`) 🖼️ |
| 5 | POST `http://127.0.0.1:8000/api/products` | `{ "title": "Laptop Pro 14”", "content": "16 Go RAM, 1 To SSD", "price": 1899.9, "isPublished": true, "category": "{{category_iri}}", "media": "{{media_iri}}" }` | `product_iri` = `@id` de la réponse (ex: `/api/products/3`) 💻 |
| 6 | GET `http://127.0.0.1:8000/api/products?title=Laptop&isPublished=true&price[gt]=1000&media[exists]=1` | — | — |
| 7 | PATCH `http://127.0.0.1:8000/{{product_iri}}` | `{ "price": 1799.9 }` (header Content-Type: application/merge-patch+json) | utiliser `product_iri` récupéré à l’étape 5 ✏️ |
| 8 | DELETE `http://127.0.0.1:8000/{{product_iri}}` | — | utiliser `product_iri` récupéré à l’étape 5 ❌ |

> **Explication des variables Postman** 🔍  
> - `{{category_iri}}` : IRI de la catégorie créée (champ `@id` dans la réponse de POST /api/categories)  
> - `{{media_iri}}` : IRI du média créé (champ `@id` dans la réponse de POST /api/media)  
> - `{{product_iri}}` : IRI du produit créé (champ `@id` dans la réponse de POST /api/products)  

> ⚠️ Si tu testes manuellement (Swagger ou curl), remplace les `{{...}}` par les valeurs exactes récupérées dans les réponses JSON. 🛠️

---

## 4️⃣ Commandes utiles

| Commande | Description |
|----------|------------|
| composer install | Installe les dépendances 📦 |
| php bin/console doctrine:migrations:migrate | Applique les migrations 🔄 |
| php bin/console doctrine:migrations:diff | Génère une nouvelle migration ✨ |
| php bin/console lexik:jwt:generate-keypair --overwrite | Régénère les clés JWT 🔑 |
| symfony server:start / symfony server:stop | Démarrer / arrêter le serveur ▶️ / 🛑 |

---

Bonne exploration 🚀  
Liste les produits sur [http://127.0.0.1:8000/api](http://127.0.0.1:8000/api) pour vérifier que tout fonctionne. 🎉
