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

#### Installation d'une version spécifique

```bash
# Tag/Release spécifique
composer require tassi/tassi-php:v1.0.0

# Branche spécifique
composer require tassi/tassi-php:dev-main

# Commit spécifique
composer require tassi/tassi-php:dev-main#abc1234
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

#### Mise à jour

```bash
composer update tassi/tassi-php
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

### Créer une expédition

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
    "package" => [
        "description" => "Colis test contenant accessoires électroniques",
        "weight" => 5,
        "dimensions" => "10x10x10",
        "declared_value" => "100",
        "currency" => "USD",
        "insurance" => false
    ],
    "route" => [
        "origin" => "Cotonou",
        "destination" => "Porto-Novo",
        "stops" => [
            [
                "city" => "Sèmè-Kpodji",
                "address" => "Avenue de l'Inter, Sèmè-Kpodji",
                "latitude" => 6.3512,
                "longitude" => 2.4987
            ]
        ]
    ]
];

try {
    $shipment = Shipment::create($shipmentData);
    echo "Expédition créée avec succès\n";
    echo "ID: " . $shipment->id . "\n";
    echo "Status: " . $shipment->status . "\n";
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

// Suivre un package
$trackingInfo = $package->track();
echo "Informations de suivi récupérées\n";

// Récupérer l'étiquette d'expédition
$label = $package->getShippingLabel(1);
echo "Étiquette: " . $label->shipping_label->filename . "\n";
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

## Structure de l'API

### Classes principales

- **Tassi** : Configuration globale (API key, environnement)
- **TassiObject** : Classe de base pour tous les objets
- **Resource** : Classe de base avec méthodes CRUD héritées
- **Requestor** : Gestionnaire des requêtes HTTP

### Ressources disponibles

#### 1. Package

**Méthodes de classe :**

- `Package::all($params = null, $headers = null)` - Liste tous les packages
- `Package::retrieve($id, $headers = null)` - Récupère un package par ID
- `Package::update($id, $params = null, $headers = null)` - Met à jour un package

**Méthodes d'instance :**

- `$package->track($headers = null)` - Suivi du package
- `$package->getShippingLabel($labelId, $headers = null)` - Récupère l'étiquette d'expédition

#### 2. Shipment

**Méthodes de classe :**

- `Shipment::create($params = null, $headers = null)` - Crée une nouvelle expédition

#### 3. Marketplace

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

## Dépendances

### Production

```
guzzlehttp/guzzle: ^7.0      # Client HTTP
doctrine/inflector: ^2.0     # Pluralisation des noms de ressources
```

### Développement

```
phpunit/phpunit: ^9.0        # Framework de tests
mockery/mockery: ^1.4        # Mocking
```

## Exemples complets

### Workflow complet : Créer et suivre une expédition

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Shipment;
use Tassi\Package;

// Configuration
Tassi::setApiKey("votre_cle_api");

// Créer une expédition
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
    "package" => [
        "description" => "Vêtements",
        "weight" => 1.2,
        "dimensions" => "30x20x15",
        "declared_value" => "50",
        "currency" => "XOF"
    ],
    "route" => [
        "origin" => "Cotonou",
        "destination" => "Abomey-Calavi"
    ]
];

try {
    // Créer l'expédition
    $shipment = Shipment::create($shipmentData);
    echo "Expédition créée: " . $shipment->id . "\n";

    // Récupérer les packages associés
    $packages = Package::all(["shipment_id" => $shipment->id]);

    if (isset($packages->packages) && count($packages->packages) > 0) {
        $package = $packages->packages[0];
        echo "Package ID: " . $package->id . "\n";
        echo "Tracking: " . $package->tracking_number . "\n";

        // Suivre le package
        $tracking = $package->track();
        echo "Statut: suivi récupéré\n";
    }
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

### Guidelines de contribution

- Écrire des tests pour toute nouvelle fonctionnalité
- Suivre les conventions de code existantes
- Documenter les changements dans le README
- S'assurer que tous les tests passent (`vendor/bin/phpunit`)

## Informations supplémentaires

- **Version** : 1.0.0
- **PHP** : >= 7.4
- **URL de l'API** : https://tassi-api.exanora.com
- **Environnements** : sandbox, live

---

**Tassi PHP SDK** - Simplifiez vos intégrations logistiques