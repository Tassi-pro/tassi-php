# Tassi PHP SDK

SDK PHP officiel pour l'API Tassi - Solution complète de logistique et d'expédition.

> **Nouveau ?** Consultez le [Guide de démarrage rapide](QUICKSTART.md) pour une installation pas à pas.

## Table des matières

- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Ressources disponibles](#ressources-disponibles)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Tests](#tests)
- [Exemples](#exemples-complets)
- [Support](#support-et-contribution)

## Installation

> **Note:** Le package n'est pas encore disponible sur Packagist. Utilisez l'installation depuis GitHub.

### Installation depuis GitHub

#### Prérequis
- PHP 7.4 ou supérieur
- Composer installé
- Git installé

#### Installation directe

```bash
# Créer un dossier pour votre projet (recommandé)
mkdir mon-projet-tassi
cd mon-projet-tassi

# Installer Tassi depuis GitHub
composer require tassi/tassi-php:dev-main
# OU si le repo est privé
composer config repositories.tassi vcs https://github.com/Tassi-pro/tassi-php
composer require tassi/tassi-php:dev-main
```

#### Installation avec composer.json

Ajoutez dans votre `composer.json` :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Tassi-pro/tassi-php"
        }
    ],
    "require": {
        "tassi/tassi-php": "dev-main"
    }
}
```

Puis installez :

```bash
composer install
```

#### Vérification de l'installation

```php
<?php
require 'vendor/autoload.php';
use Tassi\Tassi;
echo 'Tassi SDK version: ' . Tassi::VERSION . "\n";
```

**Sortie attendue :**
```
Tassi SDK version: 1.0.0
```

## Configuration

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;

// Configuration de base
Tassi::setApiKey("votre_cle_api");
Tassi::setEnvironment("sandbox");  // ou "live"
```

## Utilisation

### Créer et confirmer une expédition

```php
<?php
use Tassi\Shipment;

$shipmentData = [
    "marketplace_id" => "1",
    "customer" => [
        "first_name" => "John",
        "last_name" => "Doe",
        "email" => "john.doe@example.com",
        "address" => "123 Main Street",
        "city" => "Cotonou",
        "country_code" => "BJ"
    ],
    "pickup_point_id" => 4,
    "package" => [
        "description" => "Colis test contenant accessoires électroniques",
        "weight" => 5,
        "dimensions" => "10x10x10",
        "declared_value" => "100",
        "currency" => "USD",
        "insurance" => false
    ]
];

try {
    // Étape 1: Créer l'expédition et obtenir les options
    $result = Shipment::create($shipmentData);

    echo "Options de livraison disponibles:\n";
    foreach ($result->delivery_options as $option) {
        echo "- {$option->option_type}: {$option->cost} ({$option->estimated_days}j)\n";
        echo "  Route ID: {$option->route->id}\n";
    }

    // Étape 2: Confirmer avec l'option choisie
    $routeId = $result->delivery_options[0]->route->id;
    $confirmation = Shipment::confirm($routeId);

    echo "Message: " . $confirmation->message . "\n";
    echo "Montant débité: " . $confirmation->movement->amount . "\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
```

### Gérer les packages

```php
<?php
use Tassi\Package;

// Lister tous les packages
$result = Package::all();
echo "Nombre de packages: " . count($result->packages) . "\n";

foreach ($result->packages as $pkg) {
    echo "- " . $pkg->tracking_number . ": " . $pkg->status . "\n";
}

// Récupérer un package spécifique
$package = Package::retrieve(4);
echo "Package: " . $package->tracking_number . "\n";
echo "Status: " . $package->status . "\n";
echo "Description: " . $package->description . "\n";

// Mettre à jour un package
$updatedPackage = Package::update(4, [
    "description" => "Nouvelle description",
    "weight" => "15.0"
]);
echo "Package mis à jour: " . $updatedPackage->description . "\n";
```

### Gérer les marketplaces

```php
<?php
use Tassi\Marketplace;

// Récupérer une marketplace
$marketplace = Marketplace::retrieve(1);
echo "Marketplace: " . $marketplace->name . "\n";
echo "Active: " . ($marketplace->is_active ? "Oui" : "Non") . "\n";
echo "Nombre de packages: " . $marketplace->packages_count . "\n";

// Mettre à jour une marketplace
$updatedMarketplace = Marketplace::update(1, [
    "website" => "nouveau-site.com"
]);
echo "Marketplace mise à jour\n";

// Récupérer l'historique du portefeuille
$history = $marketplace->getWalletHistory();
echo "Nombre de mouvements: " . count($history->wallet_movements) . "\n";

foreach ($history->wallet_movements as $movement) {
    echo $movement->action . ": " . $movement->amount . " (" . $movement->created_at . ")\n";
}
```

## Ressources disponibles

### Package

**Méthodes de classe :**

- `Package::all($params = null, $headers = null)` - Liste tous les packages
- `Package::retrieve($id, $headers = null)` - Récupère un package par ID
- `Package::update($id, $params = null, $headers = null)` - Met à jour un package

### Shipment

**Méthodes de classe :**

- `Shipment::create($params = null, $headers = null)` - Crée une expédition et retourne les options de livraison
- `Shipment::confirm($routeId, $headers = null)` - Confirme l'expédition avec la route choisie

### Marketplace

**Méthodes de classe :**

- `Marketplace::retrieve($id, $headers = null)` - Récupère une marketplace
- `Marketplace::update($id, $params = null, $headers = null)` - Met à jour une marketplace

**Méthodes d'instance :**

- `$marketplace->getWalletHistory($params = null, $headers = null)` - Historique du portefeuille

## Gestion des erreurs

```php
<?php
use Tassi\Error\TassiError;
use Tassi\Error\InvalidRequestError;
use Tassi\Error\ApiConnectionError;
use Tassi\Error\AuthenticationError;
use Tassi\Error\NotFoundError;
use Tassi\Error\ValidationError;

try {
    $package = Package::retrieve("invalid_id");
} catch (InvalidRequestError $e) {
    echo "Paramètres invalides: " . $e->getMessage() . "\n";
} catch (NotFoundError $e) {
    echo "Package non trouvé: " . $e->getMessage() . "\n";
} catch (ApiConnectionError $e) {
    echo "Erreur de connexion (HTTP " . $e->getHttpStatus() . "): " . $e->getMessage() . "\n";
} catch (AuthenticationError $e) {
    echo "Erreur d'authentification: " . $e->getMessage() . "\n";
} catch (TassiError $e) {
    echo "Erreur Tassi: " . $e->getMessage() . "\n";
}
```

### Hiérarchie des exceptions

```
TassiError (base)
├── InvalidRequestError       # Paramètres invalides
├── ApiConnectionError        # Erreur HTTP
├── AuthenticationError       # Authentification échouée
├── NotFoundError            # Ressource non trouvée (404)
└── ValidationError          # Validation des données échouée
```

## Tests

### Tests unitaires (avec mocks)

```bash
# Installation des dépendances de développement
composer install

# Lancer tous les tests
vendor/bin/phpunit

# Lancer avec couverture
vendor/bin/phpunit --coverage-html coverage
```

## Structure du projet

```
tassi-php/
├── src/
│   ├── Error/
│   │   ├── TassiError.php
│   │   ├── InvalidRequestError.php
│   │   ├── ApiConnectionError.php
│   │   ├── AuthenticationError.php
│   │   ├── NotFoundError.php
│   │   └── ValidationError.php
│   ├── Tassi.php             # Configuration principale
│   ├── TassiObject.php       # Classe de base
│   ├── Resource.php          # Ressource de base avec CRUD
│   ├── Requestor.php         # Gestionnaire HTTP
│   ├── Util.php              # Utilitaires
│   ├── Package.php           # Ressource Package
│   ├── Shipment.php          # Ressource Shipment
│   └── Marketplace.php       # Ressource Marketplace
├── tests/
│   ├── PackageTest.php       # Tests Package
│   ├── ShipmentTest.php      # Tests Shipment
│   └── MarketplaceTest.php   # Tests Marketplace
├── composer.json             # Configuration du package
└── README.md                 # Documentation
```

## Exemples complets

### Workflow complet : Créer et confirmer une expédition

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Shipment;
use Tassi\Package;

// Configuration
Tassi::setApiKey("votre_cle_api");

// Données de l'expédition
$shipmentData = [
    "marketplace_id" => "1",
    "customer" => [
        "first_name" => "Marie",
        "last_name" => "Dupont",
        "email" => "marie@example.com",
        "address" => "456 Rue de la Paix",
        "city" => "Cotonou",
        "country_code" => "BJ"
    ],
    "pickup_point_id" => 4,
    "package" => [
        "description" => "Vêtements",
        "weight" => 1.2,
        "dimensions" => "30x20x15",
        "declared_value" => "50",
        "currency" => "XOF"
    ]
];

try {
    // Étape 1: Créer l'expédition
    $result = Shipment::create($shipmentData);
    echo "Options disponibles:\n";

    foreach ($result->delivery_options as $option) {
        echo "- {$option->option_type}: {$option->cost} (route {$option->route->id})\n";
    }

    // Étape 2: Confirmer avec la première option
    $routeId = $result->delivery_options[0]->route->id;
    $confirmation = Shipment::confirm($routeId);

    echo "\nExpédition confirmée!\n";
    echo "Message: " . $confirmation->message . "\n";
    echo "Montant débité: " . $confirmation->movement->amount . "\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
```

### Gérer plusieurs marketplaces

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Marketplace;

Tassi::setApiKey("votre_cle_api");

// Récupérer une marketplace
$marketplace = Marketplace::retrieve(1);
echo "Marketplace: " . $marketplace->name . "\n";
echo "Active: " . ($marketplace->is_active ? "Oui" : "Non") . "\n";

// Mettre à jour si nécessaire
if (!$marketplace->is_active) {
    $updated = Marketplace::update(1, ["is_active" => true]);
    echo "Marketplace activée\n";
}

// Vérifier l'historique du portefeuille
$history = $marketplace->getWalletHistory();
$total = isset($history->wallet_movements) ? count($history->wallet_movements) : 0;
echo "Total des mouvements: " . $total . "\n";

// Afficher les derniers mouvements
if (isset($history->wallet_movements)) {
    foreach (array_slice($history->wallet_movements, 0, 5) as $movement) {
        echo "- " . $movement->action . ": " . $movement->amount . "\n";
    }
}
```

## Support et contribution

### Signaler un bug

Ouvrez une issue sur [GitHub Issues](https://github.com/Tassi-pro/tassi-php/issues)

### Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit vos changements (`git commit -am 'Ajouter nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## Informations supplémentaires

- **Version** : 1.0.0
- **PHP** : >= 7.4
- **URL de l'API** : https://api.tassi.pro - Live
- **URL de l'API** : https://sandbox-api.tassi.pro - Sandbox
- **Environnements** : sandbox, live

---

**Tassi PHP SDK** - Simplifiez vos intégrations logistiques
