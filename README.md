# tassi-php

Bibliothèque PHP officielle de l'[API logistique Tassi](https://docs.tassi.pro/fr/api).

- PHP 8.1 ou plus.
- Client HTTP au choix grâce à [PSR-18](https://www.php-fig.org/psr/psr-18/) : pas de conflit avec le Guzzle d'un autre plugin.
- Montants toujours en chaînes décimales (`"11.35"`), jamais en float.
- `Idempotency-Key` sur chaque écriture, nouvel essai automatique sur erreur réseau ou `5xx`.
- Vérification des signatures de webhooks, avec rotation de secret sans coupure.

> Version 0.1 : points de collecte, frais de port, expéditions, étiquettes, suivi, wallet et webhooks.
> Les autres opérations de l'API restent accessibles avec `$tassi->request()`.

## Installation

```bash
composer require tassi/tassi-php symfony/http-client nyholm/psr7
```

`symfony/http-client` et `nyholm/psr7` sont un exemple : tout client PSR-18 et toute implémentation PSR-7/PSR-17 conviennent. Si votre projet en a déjà un, la bibliothèque le trouve toute seule.

## Configuration

```php
use Tassi\TassiClient;

$tassi = new TassiClient(getenv('TASSI_API_KEY'));
```

La clé secrète `tassi_sec_…` ne doit jamais apparaître dans le code, un dépôt Git ou un navigateur. Lisez-la depuis l'environnement et appelez l'API uniquement côté serveur.

| Option | Défaut | Rôle |
|---|---|---|
| `base_url` | `https://sandbox-api.tassi.pro` | Sandbox par défaut. La production s'active en passant son URL. |
| `timeout` | `10` | Délai d'attente en secondes, appliqué automatiquement avec Symfony HttpClient ou Guzzle. |
| `max_retries` | `2` | Nouveaux essais sur erreur réseau, `409`, `429` et `5xx`. |
| `http_client` | détecté | Votre client PSR-18. Son délai d'attente est alors à régler de votre côté. |
| `request_factory`, `stream_factory` | détectés | Fabriques PSR-17. |
| `user_agent_suffix` | aucun | Ajouté au User-Agent, par exemple `tassi-woocommerce/1.0.0`. |

Un client correspond à un compte marchand. Un même processus peut en créer plusieurs, sans état partagé.

## Le parcours d'une commande

```php
use Tassi\IdempotencyKey;
use Tassi\Service\Rates;
use Tassi\Service\Shipments;

// 0. Une fois : le point de collecte. Donnez toujours latitude et longitude,
//    sinon Tassi géocode l'adresse et peut se tromper.
$point = $tassi->pickupPoints->create([
    'name' => 'Entrepôt Cotonou',
    'address' => 'Rue 12.150',
    'city' => 'Cotonou',
    'country_id' => 5,
    'latitude' => 6.3654,
    'longitude' => 2.4183,
])['pickup_point'];

// 1. Au paiement du panier : les frais de port.
$rates = $tassi->rates->get([
    'pickup_point_id' => $point['id'],
    'destination' => ['latitude' => 9.3514, 'longitude' => 2.6305, 'city' => 'Parakou', 'country_code' => 'BJ'],
    'package' => ['weight' => 1.2, 'dimensions' => '20x15x10'],
]);
foreach ($rates['rates'] as $rate) {
    echo $rate['option_type'], ' : ', $rate['total_price'], " €\n";
}

// 2. Commande validée : l'expédition.
$order = '1042';
$created = $tassi->shipments->create([
    'pickup_point_id' => $point['id'],
    'customer' => [
        'firstname' => 'Awa', 'lastname' => 'Diallo', 'email' => 'awa@example.com',
        'phone_prefix' => '+229', 'phone_number' => '97000000',
        'address' => 'Carrefour Banikanni', 'city' => 'Parakou', 'country_code' => 'BJ',
        'latitude' => 9.3514, 'longitude' => 2.6305,
    ],
    'package' => [
        'weight' => 1.2,
        'currency' => 'EUR',
        'order_reference' => $order,
        'items' => [['description' => 'Pagne', 'quantity' => 2, 'unit_value' => 15]],
    ],
], IdempotencyKey::forOrder($order, 'create'), international: $rates['international']);

// 3. Confirmation de l'option choisie : les fonds sont bloqués.
$option = Shipments::findOption($created, 'economy');
$tassi->shipments->confirm($option['route']['id'], IdempotencyKey::forOrder($order, 'confirm'));

// 4. Étiquette, quand les transporteurs ont accepté.
$label = $tassi->labels->latest($created['shipment']['id']);
if ($label !== null) {
    echo $label['download_url']; // valable 2 heures : à relire au moment de l'afficher
}

// Annulation, avant le départ du colis : les fonds sont libérés.
$tassi->shipments->cancel($created['shipment']['id'], 'Commande annulée par le client');
```

### Quel prix afficher

Affichez et facturez toujours le **`total_price`** de `rates->get()` : c'est le montant exact bloqué sur le wallet à la confirmation, commission comprise. Le champ `cost` des `delivery_options` est le prix du transporteur **sans** la commission. Ne l'affichez pas.

### Mettre les frais de port en cache

```php
$cacheKey = Rates::cacheKey($params); // même destination, poids arrondi à 0,1 kg
```

Gardez le résultat quelques minutes, et prévoyez des frais fixes de secours si l'appel dépasse le délai de votre plateforme.

## Idempotence et nouveaux essais

Chaque création, confirmation et annulation envoie un en-tête `Idempotency-Key`. Avec la même clé et les mêmes paramètres, Tassi renvoie la première réponse pendant 24 h au lieu de recommencer.

- **Dérivez la clé de la commande** avec `IdempotencyKey::forOrder($order, 'create')`. Même après un plantage du serveur, un nouvel essai ne crée pas de doublon. Sans clé fournie, la bibliothèque en tire une au hasard, qui ne protège que les essais d'un même appel.
- **Nouveaux essais automatiques** : sur erreur réseau, `409`, `429` ou `5xx`, avec la même clé.
- **Après une erreur `4xx` corrigée**, changez de clé, sinon l'ancienne rejoue l'erreur : `IdempotencyKey::forOrder($order, 'create', attempt: 2)`.

## Erreurs

Toutes les exceptions héritent de `Tassi\Exception\TassiException`.

| Exception | Cas |
|---|---|
| `InvalidParametersException` | Refusé avant l'appel : article manquant, adresse absente, code SH manquant à l'international, devise autre que l'euro. `getProblems()` liste des messages à montrer au marchand. |
| `InvalidRequestException` | 400 : requête invalide. |
| `AuthenticationException` | 401 : clé absente ou invalide. |
| `PermissionException` | 403 : action interdite. |
| `NotFoundException` | 404 : introuvable. |
| `IdempotencyException` | 409 : même clé encore en cours. |
| `UnprocessableEntityException` | 422 : règle métier (aucune route, fonds insuffisants…) ou validation. |
| `RateLimitException` | 429 : trop de requêtes. |
| `ServerException` | 5xx, après les nouveaux essais. |
| `NetworkException` | Pas de réponse, après les nouveaux essais. L'opération a pu réussir : réessayez avec la même clé. |

Les exceptions d'API exposent `getStatusCode()`, `getMessages()`, `getErrors()` et `getResponseBody()`. N'analysez pas le texte des messages, qui peut changer.

## Webhooks

```php
use Tassi\Exception\SignatureVerificationException;
use Tassi\Webhook;

$payload = file_get_contents('php://input');   // corps BRUT, avant json_decode
$header  = $_SERVER['HTTP_TASSI_SIGNATURE'] ?? '';

try {
    $event = Webhook::constructEvent($payload, $header, getenv('TASSI_WEBHOOK_SECRET'));
} catch (SignatureVerificationException) {
    http_response_code(400);
    exit;
}

http_response_code(200); // répondre en moins de 10 secondes, puis traiter
```

- **Doublons** : un même événement peut arriver plusieurs fois. Ignorez un `$event['id']` déjà traité.
- **Désordre** : comparez `$event['data']['object']['updated_at']` à la valeur enregistrée, et ignorez un événement plus ancien.
- **Rotation du secret** : après `webhookEndpoints->rollSecret()`, passez les deux secrets : `constructEvent($payload, $header, [$ancien, $nouveau])`.
- **Horloge** : un écart de plus de 5 minutes fait rejeter le webhook. Gardez l'horloge du serveur synchronisée (NTP).
- **Pas de redirection** : Tassi considère une réponse `301` ou `302` comme un échec. Déclarez l'URL finale exacte.
- **Tester** : `webhookEndpoints->test($id)` envoie un événement `ping` signé.

Le statut d'un colis vient des transporteurs : on le lit (webhooks, `shipments->get()`, `tracking->get()`), on ne l'écrit jamais.

## Opérations non couvertes

```php
$hsCodes = $tassi->request('GET', '/hs_codes', ['q' => 'coffee']);
```

## Tests

```bash
composer install
composer test   # tests unitaires, sans réseau

# Contre la sandbox : une clé, et un point de collecte avec coordonnées GPS
TASSI_API_KEY=tassi_sec_... TASSI_PICKUP_POINT_ID=109 composer test:integration
```

Le test du parcours complet bloque environ 11 € fictifs, puis annule l'expédition.

## Licence

MIT