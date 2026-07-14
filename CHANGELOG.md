# Changelog

Alle noemenswaardige wijzigingen per release. Consumers pinnen `^0.8`; elke tag bereikt
alle consumers bij hun eerstvolgende `composer update`.

## [Unreleased]

## [0.8.12] — 2026-07-14

- **Pluggable auth-mail-sender.** Nieuw `Auth\Mail\AuthMailSenderInterface` +
  `DefaultAuthMailSender` (huidig gedrag: render + verstuur lokaal). `AuthService` verstuurt
  welkom/wachtwoord-reset/reset-bevestiging nu via die interface, zodat een consumerend project
  z'n systeemmails kan omleiden (bijv. naar een centrale branded mail-service) door de interface
  te aliassen naar een eigen implementatie. Backward compatible: zonder override verandert er niets.
- **Auth-template-keys** toegevoegd aan `EmailTemplateKey` (`welcome`, `password_reset`,
  `password_reset_confirmation`) met NL default subject/body — zo verschijnen ze in het branded
  mailsysteem en het E-mail-templates-admin, naast de bestaande subscription-keys.

## [0.8.11] — 2026-07-14

- **Optionele tweestapsverificatie (TOTP)**, opt-in per gebruiker. Nieuw op de `User`-entity:
  `totpEnabled` + versleutelde `totpSecret`/`totpPendingSecret` en gehashte `totpBackupCodes`
  (migratie `Version20260713120000`, schema-defensief). `TotpService` (secret + otpauth-URI +
  code-verificatie met ±1 tijdvenster, RFC-160-bit secret, herstelcodes) en
  `TwoFactorChallengeService` (kortlevend, tamper-proof challenge via `CredentialEncryptor`).
- Login is nu tweestaps voor 2FA-accounts: `AuthService::login()` geeft bij ingeschakelde 2FA
  een `{ twoFactorRequired, challenge }` i.p.v. een token; `POST /api/auth/2fa/verify`
  (publiek) wisselt challenge + code (TOTP óf eenmalige herstelcode) in voor de echte JWT.
  Gedrag zonder 2FA is ongewijzigd.
- Enrollment-endpoints (JWT-vereist): `POST /api/profile/2fa/setup|enable|disable`,
  `GET /api/profile/2fa/status`. `enable` bevestigt met een geldige code en levert eenmalig
  8 herstelcodes; `disable` vereist een geldige code.
- Nieuwe config `project_template.two_factor.issuer` (default `App`) voor de naam in de
  authenticator-app. Dependency: `spomky-labs/otphp ^11.2`.
- **Consumers moeten `^/api/auth/2fa/verify` publiek maken** in hun `security.yaml`
  (public firewall + `access_control`), zoals in de test-app-referentieconfig.

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
