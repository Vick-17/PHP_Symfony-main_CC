# CC_Symphony – Gestion d’Hôtels et de Clients

Ce projet Symfony permet de gérer des hôtels, des chambres, des clients et leurs réservations, avec une base de données MongoDB.  
Il propose une interface d’administration, la gestion des rôles, la validation des données et une organisation en couches (contrôleur, entités, repository).

---

## Fonctionnalités principales

- CRUD Hôtels, Chambres, Clients, Réservations
- Authentification et gestion des rôles
- Validation des données (formulaires Symfony)
- Recherche de clients
- Gestion des doublons (emails, téléphones, numéros de chambre)
- Gestion des erreurs et exceptions
- Responsive design (Bootstrap)

---

## Prérequis

- [Docker](https://www.docker.com/products/docker-desktop)
- [Docker Compose](https://docs.docker.com/compose/)

---

## Installation et lancement avec Docker

1. **Clonez le dépôt**
   ```sh
   git clone <url_du_repo>
   cd CC_Symphony
   ```

2. **Créez un fichier `.env` à la racine (si besoin)**
   ```env
   APP_ENV=dev
   APP_DEBUG=1
   APP_SECRET=your_secret
   MONGODB_URL="mongodb://mongodb:27017"
   MONGODB_DB=hotel
   ```

3. **Créez un fichier `docker-compose.yml` à la racine :**
   ```yaml
   version: '3.8'

   services:
     app:
       build: .
       container_name: symfony_app
       ports:
         - "8000:8000"
       volumes:
         - .:/app
       depends_on:
         - mongodb
       environment:
         - APP_ENV=dev
         - MONGODB_URL=mongodb://mongodb:27017
         - MONGODB_DB=hotel
       command: bash -c "composer install && php -S 0.0.0.0:8000 -t public"

     mongodb:
       image: mongo:4.4
       container_name: mongodb
       ports:
         - "27017:27017"
       volumes:
         - mongo_data:/data/db

   volumes:
     mongo_data:
   ```

4. **(Optionnel) Ajoutez un `Dockerfile` pour Symfony :**
   ```dockerfile
   FROM php:8.2-cli

   RUN apt-get update && apt-get install -y \
       git unzip libicu-dev libzip-dev libpng-dev libonig-dev \
       && docker-php-ext-install intl pdo pdo_mysql zip

   COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

   WORKDIR /app
   ```

5. **Lancez les conteneurs**
   ```sh
   docker-compose up --build
   ```

6. **Accédez à l’application**
   - Ouvrez [http://localhost:8000](http://localhost:8000) dans votre navigateur.

---

## Utilisation de MongoDB

- Pour accéder à MongoDB via le terminal :
  ```sh
  docker exec -it mongodb mongo
  ```
- Pour voir les bases et collections :
  ```js
  show dbs
  use hotel
  show collections
  db.client.find()
  ```

---

## Structure du projet

- Le code source est dans le dossier `src/`
- Les templates Twig sont dans `templates/`
- Les contrôleurs sont dans `src/Controller/`
- Les entités (documents) sont dans `src/Document/`
- Les tests sont dans `tests/`

---

## Fonctionnement des routes

Le projet utilise les **attributs de routes Symfony** (`#[Route(...)]`) pour définir les URLs accessibles.  
Voici quelques exemples de routes principales :

- `/clients` : liste tous les clients (`ClientController::index`)
- `/clients/{id}` : affiche le détail d’un client (`ClientController::show`)
- `/client/{id}/edit` : modifie un client (`ClientController::edit`)
- `/clients/{id}/delete` : supprime un client (`ClientController::delete`)
- `/hotel` : liste des hôtels
- `/hotel/{id}` : détail d’un hôtel
- `/hotel/{id}/edit` : modification d’un hôtel
- `/chambre/{hotelId}/new` : ajout d’une chambre à un hôtel
- `/chambre/{id}/edit` : modification d’une chambre

Chaque route est associée à une méthode d’un contrôleur, qui gère la logique métier, la validation et l’affichage.

**Exemple :**
```php
#[Route('/clients', name: 'client_index', methods: ['GET'])]
public function index(Request $request, DocumentManager $dm): Response
```
Cette méthode affiche la liste des clients et accepte la méthode HTTP GET.

---

## Recherche et filtres

- La recherche de clients se fait via un paramètre `search` dans l’URL :  
  Exemple : `/clients?search=dupont`
- Le contrôleur filtre alors les clients par nom ou email contenant la chaîne recherchée.

---

## Validation et gestion des erreurs

- Les entités utilisent les contraintes Symfony (`@Assert\NotBlank`, `@Assert\Email`, etc.) pour valider les données.
- Les doublons (email, téléphone, numéro de chambre) sont gérés côté contrôleur et via les contraintes `UniqueEntity`.
- Les erreurs sont affichées à l’utilisateur grâce au système de flash messages Symfony.

---

## Tests

- Les tests unitaires et fonctionnels sont à placer dans `tests/`
- Pour lancer les tests :
  ```sh
  docker-compose exec app ./vendor/bin/phpunit
  ```

---

## Conseils et bonnes pratiques

- Pour que les documents MongoDB soient visibles dans l’application, ils doivent respecter la structure attendue par les entités (voir `src/Document/Client.php`).
- Les identifiants sont gérés automatiquement par Doctrine ODM.
- Pour la production, pensez à activer l’authentification MongoDB et à sécuriser vos variables d’environnement.
- Utilisez des rôles (`ROLE_ADMIN`, `ROLE_USER`, etc.) pour sécuriser l’accès aux pages sensibles.

---

## Auteur

Yannis Billon & Sacha Fougeras