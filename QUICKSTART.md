# Guide de démarrage rapide - Tassi PHP SDK

Ce guide vous accompagne pas à pas pour installer et utiliser le SDK Tassi.

## Étape 1 : Créer votre projet

```bash
# Créer un nouveau dossier pour votre projet
mkdir mon-projet-tassi
cd mon-projet-tassi
```

## Étape 2 : Initialiser Composer

```bash
# Initialiser composer
composer init --no-interaction
```

Créez un fichier `composer.json` :

```json
{
    "name": "mon-entreprise/mon-projet",
    "require": {
        "php": ">=7.4"
    }
}
```

## Étape 3 : Installer Tassi

```bash
# Ajouter le repository GitHub
composer config repositories.tassi vcs https://github.com/Tassi-pro/tassi-php

# Installer depuis GitHub
composer require tassi/tassi-php:dev-main
```

**Sortie attendue :**
```
Installing tassi/tassi-php (dev-main abc1234)
  - Downloading tassi/tassi-php (dev-main abc1234)
  - Installing tassi/tassi-php (dev-main abc1234): Extracting archive
```

## Étape 4 : Vérifier l'installation

```bash
php -r "require 'vendor/autoload.php'; use Tassi\Tassi; echo 'Version: ' . Tassi::VERSION . PHP_EOL;"
```

**Sortie attendue :**
```
Version: 1.0.0
```

## Étape 5 : Créer votre premier script

Créez un fichier `test_tassi.php` :

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Package;

// Configuration
Tassi::setApiKey("votre_cle_api_ici");
Tassi::setEnvironment("sandbox");

// Test de connexion
try {
    $result = Package::all();

    echo "Connexion réussie à l'API Tassi!\n";
    echo "SDK Version: " . Tassi::VERSION . "\n";
    echo "Environnement: " . Tassi::getEnvironment() . "\n";

    if (isset($result->packages)) {
        echo "Nombre de packages: " . count($result->packages) . "\n";
    } else {
        echo "Aucun package trouvé\n";
    }
} catch (Exception $e) {
    echo "Erreur de connexion: " . $e->getMessage() . "\n";
}
```

## Étape 6 : Configurer vos credentials

### Option A : Directement dans le code (pour tester)

```php
<?php
Tassi::setApiKey("sk_test_votre_cle_ici");
```

### Option B : Variables d'environnement (recommandé)

**1. Installer vlucas/phpdotenv :**
```bash
composer require vlucas/phpdotenv
```

**2. Créer un fichier `.env` :**
```bash
TASSI_API_KEY=sk_test_votre_cle_ici
TASSI_ENVIRONMENT=sandbox
```

**3. Ajouter `.env` au `.gitignore` :**
```bash
echo ".env" >> .gitignore
```

**4. Utiliser dans votre code :**
```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

Tassi::setApiKey($_ENV['TASSI_API_KEY']);
Tassi::setEnvironment($_ENV['TASSI_ENVIRONMENT']);
```

## Étape 7 : Exécuter votre script

```bash
php test_tassi.php
```

**Sortie attendue :**
```
Connexion réussie à l'API Tassi!
SDK Version: 1.0.0
Environnement: sandbox
Nombre de packages: 5
```

## Exemples d'utilisation

### Exemple 1 : Lister les packages

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Package;

Tassi::setApiKey("votre_cle");

$result = Package::all();

foreach ($result->packages as $pkg) {
    echo "Package " . $pkg->id . ":\n";
    echo "  - Tracking: " . $pkg->tracking_number . "\n";
    echo "  - Status: " . $pkg->status . "\n";
    echo "  - Description: " . $pkg->description . "\n";
    echo "\n";
}
```

### Exemple 2 : Créer et confirmer une expédition

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Shipment;

Tassi::setApiKey("votre_cle");

$shipmentData = [
    "marketplace_id" => "1",
    "customer" => [
        "first_name" => "John",
        "last_name" => "Doe",
        "email" => "john@example.com",
        "address" => "123 Main St",
        "city" => "Cotonou",
        "country_code" => "BJ"
    ],
    "pickup_point_id" => 4,
    "package" => [
        "description" => "Test package",
        "weight" => 2.5,
        "dimensions" => "20x15x10",
        "declared_value" => "50",
        "currency" => "XOF"
    ]
];

try {
    // Étape 1: Créer l'expédition
    $result = Shipment::create($shipmentData);

    echo "Options de livraison disponibles:\n";
    foreach ($result->delivery_options as $option) {
        echo "  - {$option->option_type}: {$option->cost} (route {$option->route->id})\n";
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

### Exemple 3 : Gérer une marketplace

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Marketplace;

Tassi::setApiKey("votre_cle");

// Récupérer une marketplace
$marketplace = Marketplace::retrieve(1);
echo "Marketplace: " . $marketplace->name . "\n";
echo "Active: " . ($marketplace->is_active ? "Oui" : "Non") . "\n";

// Historique du wallet
$history = $marketplace->getWalletHistory();
echo "Mouvements: " . count($history->wallet_movements) . "\n";

foreach ($history->wallet_movements as $movement) {
    echo "  - " . $movement->action . ": " . $movement->amount . "\n";
}
```

## Structure recommandée du projet

```
mon-projet-tassi/
├── vendor/                  # Dépendances Composer
├── .env                     # Variables d'environnement (ne pas committer)
├── .gitignore              # Fichiers à ignorer
├── composer.json           # Dépendances
├── config.php              # Configuration centralisée
└── index.php               # Votre application
```

### composer.json

```json
{
    "name": "mon-entreprise/mon-projet",
    "require": {
        "php": ">=7.4",
        "tassi/tassi-php": "dev-main",
        "vlucas/phpdotenv": "^5.0"
    }
}
```

### .gitignore

```
vendor/
.env
*.log
.DS_Store
```

### config.php

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuration Tassi
Tassi::setApiKey($_ENV['TASSI_API_KEY']);
Tassi::setEnvironment($_ENV['TASSI_ENVIRONMENT'] ?? 'sandbox');
```

### index.php

```php
<?php
require 'config.php';

use Tassi\Package;
use Tassi\Shipment;

// Votre code ici
$packages = Package::all();
echo "Packages: " . count($packages->packages) . "\n";
```

## Gestion des erreurs

```php
<?php
require 'vendor/autoload.php';

use Tassi\Tassi;
use Tassi\Package;
use Tassi\Shipment;
use Tassi\Error\TassiError;
use Tassi\Error\InvalidRequestError;
use Tassi\Error\ApiConnectionError;
use Tassi\Error\NotFoundError;

Tassi::setApiKey("votre_cle");

// Test récupération package inexistant
try {
    $package = Package::retrieve(999999);
} catch (NotFoundError $e) {
    echo "Package non trouvé\n";
} catch (ApiConnectionError $e) {
    echo "Erreur de connexion: HTTP " . $e->getHttpStatus() . "\n";
} catch (InvalidRequestError $e) {
    echo "Paramètres invalides: " . $e->getMessage() . "\n";
} catch (TassiError $e) {
    echo "Erreur Tassi: " . $e->getMessage() . "\n";
}

// Test confirmation route invalide
try {
    $result = Shipment::confirm(999999);
} catch (ApiConnectionError $e) {
    echo "Erreur confirmation (HTTP " . $e->getHttpStatus() . "): " . $e->getMessage() . "\n";
}
```

## Récapitulatif des méthodes

| Ressource | Méthode | Description |
|-----------|---------|-------------|
| **Package** | `all($params?)` | Lister les packages |
| | `retrieve($id)` | Récupérer un package |
| | `update($id, $params)` | Mettre à jour un package |
| **Shipment** | `create($params)` | Créer, obtenir options |
| | `confirm($routeId)` | Confirmer l'expédition |
| **Marketplace** | `retrieve($id)` | Récupérer une marketplace |
| | `update($id, $params)` | Mettre à jour |
| | `getWalletHistory()` | Historique wallet |

## Prochaines étapes

1. **Lire la documentation complète** : [README.md](README.md)
2. **Explorer les tests** : Voir les fichiers dans `tests/`
3. **Rejoindre la communauté** : [GitHub Issues](https://github.com/Tassi-pro/tassi-php/issues)

## Commandes utiles

```bash
# Installer les dépendances
composer install

# Mettre à jour Tassi
composer update tassi/tassi-php

# Lancer les tests
vendor/bin/phpunit

# Régénérer l'autoload
composer dump-autoload
```

## Besoin d'aide ?

- **Documentation** : [README.md](README.md)
- **Issues** : [GitHub Issues](https://github.com/Tassi-pro/tassi-php/issues)

---

Vous êtes maintenant prêt à utiliser le SDK Tassi PHP ! 🚀