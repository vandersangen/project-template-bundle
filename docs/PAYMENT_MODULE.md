# Payment Module

De payment module maakt het mogelijk om vanuit elke project-template implementatie recurring payments te beheren via een centrale **payment-api**. De payment-api fungeert als tussenlaag naar Mollie en Stripe. De module is volledig ingebakken in de `vandersangen/project-template-bundle` en biedt out-of-the-box entiteiten, services, queue-handlers, events en een webhook-endpoint.

---

## Inhoudsopgave

1. [Architectuur](#architectuur)
2. [Configuratie](#configuratie)
3. [Tenant & Hoofdgebruiker](#tenant--hoofdgebruiker)
4. [Subscriptions aanmaken](#subscriptions-aanmaken)
5. [Subscriptions annuleren — "nog één keer incasseren"](#subscriptions-annuleren)
6. [Subscription status ophalen & syncen](#subscription-status-ophalen--syncen)
7. [Payments (eenmalig)](#payments-eenmalig)
8. [Webhook ontvangen](#webhook-ontvangen)
9. [Events — extensiepunten](#events--extensiepunten)
10. [Queue Messages (async)](#queue-messages-async)
11. [Databankschema](#databankschema)
12. [Tests draaien](#tests-draaien)

---

## Architectuur

```
project-template tool                payment-api              Mollie / Stripe
─────────────────────────────       ─────────────────        ─────────────────
PaymentService
  └─ PaymentApiClient  ──── POST /api/v1/subscriptions ───►  creates subscription
                            GET  /api/v1/subscriptions/{id}
                            PATCH /api/v1/subscriptions/{id}/cancel

PaymentWebhookController ◄── POST /api/payment/webhook ◄──── forwarded webhook
  └─ WebhookHandler                                           (Mollie/Stripe → payment-api → tool)
       └─ PaymentService (syncSubscription / syncPayment)
```

**Gegevensstromen:**

1. De tool roept `PaymentService::createSubscription()` aan.
2. `PaymentService` roept de payment-api aan via `PaymentApiClient`.
3. De payment-api maakt de subscription aan bij de provider en retourneert een `checkoutUrl`.
4. De gebruiker wordt naar de `checkoutUrl` gestuurd en betaalt.
5. De provider stuurt een webhook naar de payment-api.
6. De payment-api stuurt de webhook door naar `POST /api/payment/webhook` in de tool.
7. `WebhookHandler` verwerkt de webhook: maakt lokale `Payment` records aan en synct de subscription-status.
8. Er worden Symfony **Events** gedispatcht zodat de applicatie op elke stap kan inhaken.

---

## Configuratie

### 1. Omgevingsvariabelen

Voeg toe aan `.env` (en stel in als GitHub Secret / k8s Secret voor productie):

```dotenv
PAYMENT_API_BASE_URL=http://app.payment-api.localhost:4243
PAYMENT_API_TOKEN=<JWT token van de payment-api voor deze tool>
PAYMENT_WEBHOOK_SECRET=<willekeurige veilige string, ook ingesteld in de payment-api>
```

### 2. Bundle configuratie

In `config/packages/project_template.yaml`:

```yaml
project_template:
    mailer_sender: '%env(MAILER_SENDER)%'
    payment:
        api_base_url: '%env(PAYMENT_API_BASE_URL)%'
        api_token: '%env(PAYMENT_API_TOKEN)%'
        webhook_secret: '%env(PAYMENT_WEBHOOK_SECRET)%'
```

### 3. Security — webhook endpoint publiek

De webhook-route (`/api/payment/webhook`) moet buiten de JWT-firewall vallen. In `config/packages/security.yaml`:

```yaml
firewalls:
    public:
        pattern: ^/(api/health|api/auth/(login|register|forgot-password|reset-password)|api/payment/webhook)
        security: false

access_control:
    - { path: ^/api/payment/webhook, roles: PUBLIC_ACCESS }
```

### 4. Migratie uitvoeren

```bash
bin/console doctrine:migrations:migrate
```

Dit voert `Version20260314000000` uit en maakt de tabellen `tenants`, `payment_subscriptions` en `payments` aan.

---

## Tenant & Hoofdgebruiker

Een **Tenant** is de betalende entiteit. Elke tenant heeft één **eigenaar** (`ownerUserId`). De eigenaar kan **niet** worden verwijderd of gedeactiveerd zolang de tenant een actieve of pending-cancellation subscription heeft.

### Tenant aanmaken

```php
use VanDerSangen\ProjectTemplateBundle\Tenant\Service\TenantService;

$tenant = $tenantService->createTenant(
    name: 'Acme Corp',
    ownerUserId: $user->getId(),
    companyName: 'Acme BV',
    vatNumber: 'NL123456789B01',
    billingEmail: 'billing@acme.com',
);
```

### Eigenaar bescherming

Roep dit aan **vóór** het verwijderen of deactiveren van een gebruiker:

```php
use VanDerSangen\ProjectTemplateBundle\Tenant\Exception\TenantOwnerProtectionException;

try {
    $tenantService->assertUserIsDeletable($userId, $tenantId);
    // Ga door met verwijderen
} catch (TenantOwnerProtectionException $e) {
    // Foutmelding tonen: de tenant-subscription moet eerst worden opgezegd
}
```

### Factuurgegevens bijwerken

```php
$tenantService->updateBillingInfo(
    tenant: $tenant,
    billingAddressLine1: 'Hoofdstraat 1',
    billingCity: 'Amsterdam',
    billingPostalCode: '1234AB',
    billingCountry: 'NL',
);
```

---

## Subscriptions aanmaken

```php
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;

$subscription = $paymentService->createSubscription(
    tenantId: $tenant->getId(),
    userId: $user->getId(),
    provider: PaymentProvider::MOLLIE,
    amountCents: 999,                        // bedrag in centen
    interval: SubscriptionInterval::MONTHLY,
    returnUrl: 'https://yourtool.com/subscription/success',
    currency: 'EUR',
    description: 'Premium abonnement',
    cancelUrl: 'https://yourtool.com/subscription/cancel',
);

// Stuur de gebruiker naar de checkout
header('Location: ' . $subscription->getCheckoutUrl());
```

**Beschikbare intervals:** `monthly`, `quarterly`, `yearly`  
**Beschikbare providers:** `mollie`, `stripe`

### Via de queue (aanbevolen in API-context)

```php
use VanDerSangen\ProjectTemplateBundle\Payment\Message\CreateSubscriptionMessage;

$queueService->dispatch(new CreateSubscriptionMessage(
    tenantId: $tenant->getId(),
    userId: $user->getId(),
    provider: 'mollie',
    amountCents: 999,
    interval: 'monthly',
    returnUrl: 'https://yourtool.com/subscription/success',
));
```

---

## Subscriptions annuleren

### Directe annulering

```php
$paymentService->cancelSubscription(
    subscription: $subscription,
    immediate: true,
    reason: 'user_request',
);
```

### Annuleren op einde van periode

```php
$paymentService->cancelSubscription(
    subscription: $subscription,
    immediate: false,
    reason: 'user_request',
);
// Status wordt: pending_cancellation
// Subscription loopt door tot endsAt
```

### "Nog één keer incasseren" patroon

Wanneer een gebruiker zijn abonnement opzegt maar de lopende periode nog mag meenemen:

```php
$paymentService->cancelSubscription(
    subscription: $subscription,
    immediate: false,
    reason: 'user_request',
    allowOneMoreCharge: true,  // ← zet maxCharges = chargeCount + 1
);
```

Na de volgende succesvolle incasso (via webhook) annuleert de module de subscription automatisch. Er wordt geen extra actie van de applicatie verwacht.

### Via de queue

```php
use VanDerSangen\ProjectTemplateBundle\Payment\Message\CancelSubscriptionMessage;

$queueService->dispatch(new CancelSubscriptionMessage(
    subscriptionId: $subscription->getId(),
    immediate: false,
    reason: 'user_request',
    allowOneMoreCharge: true,
));
```

---

## Subscription status ophalen & syncen

### Lokale status controleren

```php
if ($paymentService->isSubscriptionActive($subscription)) {
    // Gebruiker heeft actief abonnement en mag de feature gebruiken
}
```

`isSubscriptionActive()` retourneert `false` bij:
- Status niet `active`
- `maxCharges` bereikt (automatisch gezet door "nog één keer" patroon)

### Syncen met de payment-api

```php
$subscription = $paymentService->syncSubscription($subscription);
// Status, nextBillingDate, failedChargeCount bijgewerkt
```

Of via de queue:

```php
use VanDerSangen\ProjectTemplateBundle\Payment\Message\SyncSubscriptionMessage;

$queueService->dispatch(new SyncSubscriptionMessage($subscription->getId()));
```

### Subscription statussen

| Status | Betekenis |
|--------|-----------|
| `pending` | Aangemaakt, checkout nog niet afgerond |
| `active` | Actief — incasso's lopen |
| `past_due` | Betaling mislukt, opnieuw proberen |
| `payment_method_required` | Betaalmethode vereist |
| `pending_cancellation` | Opgezegd, loopt nog door tot `endsAt` |
| `cancelled` | Definitief beëindigd |

---

## Payments (eenmalig)

```php
$payment = $paymentService->createPayment(
    tenantId: $tenant->getId(),
    userId: $user->getId(),
    provider: PaymentProvider::MOLLIE,
    amountCents: 2500,
    returnUrl: 'https://yourtool.com/payment/success',
    currency: 'EUR',
    description: 'Eenmalige aankoop',
);

header('Location: ' . $payment->getCheckoutUrl());
```

### Payment status syncen

```php
$payment = $paymentService->syncPayment($payment, forceSync: true);
```

Of via de queue:

```php
use VanDerSangen\ProjectTemplateBundle\Payment\Message\SyncPaymentMessage;

$queueService->dispatch(new SyncPaymentMessage($payment->getId(), forceSync: true));
```

---

## Webhook ontvangen

De bundle registreert automatisch `POST /api/payment/webhook`. De payment-api stuurt een `X-Webhook-Secret` header mee.

### Verwacht payloadformaat

De payment-api stuurt webhooks in dit formaat:

```json
{
  "type": "subscription.payment.succeeded",
  "subscriptionId": 42,
  "paymentId": 123,
  "data": {
    "amountCents": 999,
    "currency": "EUR",
    "providerPaymentId": "tr_abc123"
  }
}
```

### Ondersteunde webhook-types

| Type | Actie in de module |
|------|--------------------|
| `subscription.*` | Synct de lokale subscription-status |
| `subscription.payment.succeeded` | Maakt een lokale `Payment` aan + synct subscription |
| `payment.*` | Synct de lokale payment-status |

Alle overige types worden genegeerd door de ingebouwde handler maar ontvangen wel een `WebhookReceivedEvent`.

---

## Events — extensiepunten

De module dispatcht Symfony events op alle sleutelmomenten. Registreer een EventListener of EventSubscriber in de applicatie om er op in te haken.

### Beschikbare events

| Event | Wanneer |
|-------|---------|
| `SubscriptionCreatedEvent` | Subscription aangemaakt via `PaymentService::createSubscription()` |
| `SubscriptionStatusChangedEvent` | Subscription-status veranderd (ook via webhook) |
| `SubscriptionCancelledEvent` | Subscription opgezegd via `PaymentService::cancelSubscription()` |
| `PaymentCreatedEvent` | Eenmalige payment aangemaakt |
| `PaymentStatusChangedEvent` | Payment-status veranderd (ook via webhook) |
| `WebhookReceivedEvent` | Elk binnenkomend webhook-bericht (ongeacht type) |

### Voorbeeldgebruik

```php
// src/EventListener/SubscriptionCreatedListener.php
use VanDerSangen\ProjectTemplateBundle\Payment\Event\SubscriptionCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class SubscriptionCreatedListener
{
    public function __invoke(SubscriptionCreatedEvent $event): void
    {
        $subscription = $event->getSubscription();
        // Stuur welkomstmail, activeer features, log activiteit, etc.
    }
}
```

```php
// Luisteren op webhook voor custom verwerking
use VanDerSangen\ProjectTemplateBundle\Payment\Event\WebhookReceivedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class CustomWebhookListener
{
    public function __invoke(WebhookReceivedEvent $event): void
    {
        if ($event->getType() === 'subscription.payment.failed') {
            // Stuur een herinnering-mail
        }
    }
}
```

---

## Queue Messages (async)

Alle zware operaties kunnen via de Symfony Messenger queue worden gedispatcht.

| Message | Actie |
|---------|-------|
| `CreateSubscriptionMessage` | Maakt een nieuwe subscription aan via de payment-api |
| `SyncSubscriptionMessage` | Synct één subscription met de payment-api |
| `CancelSubscriptionMessage` | Zegt een subscription op |
| `SyncPaymentMessage` | Synct één payment met de payment-api |

De handlers zijn geregistreerd als `messenger.message_handler` en worden automatisch opgepikt door de `messenger:consume async` worker.

---

## Databankschema

### `tenants`

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `id` | INT PK | |
| `name` | VARCHAR(255) | Weergavenaam van de tenant |
| `company_name` | VARCHAR(255) | Bedrijfsnaam (voor facturen) |
| `vat_number` | VARCHAR(50) | BTW-nummer |
| `billing_email` | VARCHAR(255) | E-mailadres voor facturen |
| `billing_address_line1` | VARCHAR(255) | Factuuradres |
| `billing_address_line2` | VARCHAR(255) | Toevoeging |
| `billing_city` | VARCHAR(100) | Stad |
| `billing_postal_code` | VARCHAR(20) | Postcode |
| `billing_country` | VARCHAR(2) | ISO-landcode (bijv. `NL`) |
| `owner_user_id` | INT | Verwijst naar `users.id` |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

### `payment_subscriptions`

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `id` | INT PK | |
| `tenant_id` | INT | Verwijst naar `tenants.id` |
| `user_id` | INT | Verwijst naar `users.id` |
| `tool_user_reference` | VARCHAR(255) | Referentie naar de payment-api (bijv. `tenant-42`) |
| `payment_api_subscription_id` | INT UNIQUE NULL | ID in de payment-api |
| `provider` | VARCHAR(20) | `mollie` of `stripe` |
| `status` | VARCHAR(30) | Zie statustabel hierboven |
| `amount_cents` | INT | Bedrag in centen |
| `currency` | VARCHAR(3) | ISO-valutacode |
| `interval` | VARCHAR(20) | `monthly`, `quarterly`, `yearly` |
| `description` | VARCHAR(255) NULL | |
| `checkout_url` | TEXT NULL | URL naar de betaalpagina (provider) |
| `provider_subscription_id` | VARCHAR(255) NULL | Bijv. `sub_abc123` |
| `provider_customer_id` | VARCHAR(255) NULL | Bijv. `cus_xyz789` |
| `next_billing_date` | DATETIME NULL | Volgende incassodatum |
| `failed_charge_count` | INT | Aantal mislukte incasso's |
| `max_charges` | INT NULL | Limiet (null = onbeperkt) |
| `charge_count` | INT | Totaal succesvolle incasso's |
| `ends_at` | DATETIME NULL | Einddatum bij pending_cancellation |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

### `payments`

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| `id` | INT PK | |
| `tenant_id` | INT | Verwijst naar `tenants.id` |
| `user_id` | INT | Verwijst naar `users.id` |
| `subscription_id` | INT NULL FK | Verwijst naar `payment_subscriptions.id` |
| `payment_api_payment_id` | INT UNIQUE NULL | ID in de payment-api |
| `provider_payment_id` | VARCHAR(255) NULL | Bijv. `pi_abc123` of `tr_abc123` |
| `provider` | VARCHAR(20) | `mollie` of `stripe` |
| `status` | VARCHAR(20) | `pending`, `paid`, `failed`, `cancelled`, `expired`, `refunded` |
| `amount_cents` | INT | Bedrag in centen |
| `currency` | VARCHAR(3) | ISO-valutacode |
| `description` | VARCHAR(255) NULL | |
| `checkout_url` | TEXT NULL | URL naar de betaalpagina |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

---

## Tests draaien

De module wordt volledig gedekt door unit- en functionele tests in de bundle.

### Alle tests

```bash
cd api/bundle
./vendor/bin/phpunit tests/Unit/Payment/
./vendor/bin/phpunit tests/Functional/Payment/
```

Of via de project Makefile:

```bash
make test
```

### Testdekking per bestand

| Testbestand | Wat wordt getest |
|-------------|------------------|
| `Unit/Payment/PaymentEnumsTest` | Alle enum-waarden, `isActive()`, `isCancellable()` |
| `Unit/Payment/SubscriptionEntityTest` | Entity-defaults, setters, `maxCharges`-logica, `hasReachedMaxCharges()`, `incrementChargeCount()`, "één keer meer"-patroon |
| `Unit/Payment/TenantEntityTest` | Entity-defaults, setters, `toArray()`, `TenantOwnerProtectionException` |
| `Unit/Payment/PaymentApiClientTest` | HTTP-verzoeken (MockHttpClient), request-bodies, Bearer-token, URL-opbouw |
| `Unit/Payment/PaymentServiceTest` | `createSubscription`, `syncSubscription`, `cancelSubscription` + `allowOneMoreCharge`, `createPayment`, `syncPayment`, automatisch annuleren bij max-charges, `isSubscriptionActive` |
| `Unit/Payment/WebhookHandlerTest` | Alle webhook-types, event-dispatching, bestaande payments overslaan, unknown IDs negeren |
| `Unit/Payment/TenantServiceTest` | `createTenant`, `assertUserIsDeletable` (owner/non-owner, actief/pending, geen subscription), `isOwner`, `updateBillingInfo` |
| `Unit/Payment/PaymentQueueTest` | Alle Message-getters, alle Handlers (delegeren aan service, exception bij not-found) |
| `Functional/Payment/PaymentWebhookControllerTest` | Correct secret → 200, fout secret → 401, geen secret → 401, ongeldige JSON → 400, GET → 405, payload doorgeven aan handler |
