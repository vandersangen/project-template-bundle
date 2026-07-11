# Changelog

Alle noemenswaardige wijzigingen per release. Consumers pinnen `^0.8`; elke tag bereikt
alle consumers bij hun eerstvolgende `composer update`.

## [Unreleased]

## [0.8.10] — 2026-07-11

- Nieuw `Mail`-onderdeel voor per-tool abonnement-lifecycle-mails: `EmailTemplate`-entity
  (per `ownerKey` + `templateKey`), `EmailTemplateService` (render + gebrande shell +
  `{{ placeholders }}`, met tool-logo in de header en bedrijfslogo/legal-signatuur —
  naam, adres, KvK/BTW/IBAN — uit de `InvoiceTemplate`) en `BrandedEmailMailer`
  (branding-resolutie + per-tool afzender, achter `enabled`-override). NL-default subjects
  en teksten per lifecycle-status. Migratie: `email_templates` (`Version20260711000000`).

## [0.8.9] — 2026-07-10

- `PaymentApiClient::updateSubscriptionCustomer()` + `PaymentService::updateSubscriptionCustomer()`:
  klantgegevens (factuuradres) van een bestaand abonnement bijwerken via
  `PATCH /api/v1/subscriptions/{id}/customer` — voor backfill en profielwijzigingen.
- `PaymentApiClient::getInvoices()` / `getInvoicePdf()` +
  `PaymentService::getInvoicesForTenant()` / `getInvoicePdfForTenant()`: facturen van een
  tenant ophalen en de PDF downloaden (tool- en gebruiker-gescoped door de payment-api).

## [0.8.8] — 2026-07-10

- **Shopify-module v1**: store koppelen via custom-app-credentials (Admin API-token), geen
  OAuth nodig. Endpoints `POST/GET/DELETE /api/shopify/connection` + `/verify`; credentials
  versleuteld at rest via nieuwe `Shared\Service\CredentialEncryptor`
  (`project_template.encryption_secret`, default `APP_SECRET`). Migratie: `shopify_connections`.
- **Schema-verzoeningsmigratie** `Version20260710000000`: repareert databases die de
  op 9 juni in-place bewerkte migraties al hadden uitgevoerd (tenants/invoice_templates
  adresvelden, invoices `pdf_path` → `pdf_content`). No-op op up-to-date databases.
- Fix: phpcs-overtredingen in de Shopify-module.
- Dev: xdebug/pcov via `php-extension-installer` (PECL-kanaal opgeheven).

## [0.8.7] — 2026-07-10

- `PaymentService::createSubscription()` / `PaymentApiClient::createSubscription()` accepteren
  een optioneel `customer`-object (naam, bedrijfsnaam, e-mail, BTW/KvK, adres) dat naar de
  payment-api wordt doorgestuurd en op gegenereerde facturen belandt.

## [0.8.6] — eerder

- Invoice-module v1 (nummering, BTW, PDF via Gotenberg, templates), mail-attachments,
  payment/subscription-client, cron- en queue-modules. (Historie vóór dit changelog niet
  retroactief uitgewerkt.)
