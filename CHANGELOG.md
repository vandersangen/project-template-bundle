# Changelog

Alle noemenswaardige wijzigingen per release. Consumers pinnen `^0.8`; elke tag bereikt
alle consumers bij hun eerstvolgende `composer update`.

## [0.9.0] - 2026-10-22

- **Nieuwe module FeatureFlag: flags uit master data, een schakelaar per doelgroep.** Een project zet delen van de
  applicatie per doelgroep aan of uit, per omgeving (`docs/feature-flags.md`).
  - De flags zijn de entity `FeatureFlag` (tabel `feature_flags`: `cid`, `name`, `description`) en komen uit master data
    (`bundle:master-data:load`). Of een flag aan staat per doelgroep staat in `feature_flag_audiences` (flag,
    doelgroep-key, `is_on`, `updated_at`, `updated_by`); alleen `FeatureFlagService::switchFlag()` schrijft daarin.
    Geen regel is uit; een onbekende flag of doelgroep is uit.
  - De doelgroepen staan in `project_template.feature_flags.audiences` (key: begint met een letter, hoogstens 20 tekens
    `a-z0-9_`; `label` verplicht; hoogstens één `guest: true`). Een foute config laat het bouwen van de container falen.
  - Een resolver van het project (`FeatureFlagAudienceResolverInterface`) zegt bij welke doelgroepen een ingelogde
    gebruiker hoort; een bezoeker zonder login krijgt de gastdoelgroep. De resolver is verplicht zodra er doelgroepen
    zijn (`RequireFeatureFlagAudienceResolverPass`).
  - `FeatureFlagService`: `isOn($cid)`, `isOnFor($cid, $audience)` (cron en queue), `listCidsOn()`, `switchFlag()`. Bij
    meer doelgroepen staat een flag aan zodra hij voor één ervan aan staat. `#[RequiresFeatureFlag('cid')]` geeft 404
    zolang de flag uit staat, vóór `#[IsGranted]`.
  - Endpoints: `GET /api/feature-flags` (ook voor gasten; het project opent het pad), `GET
    /api/super-admin/feature-flags` en `PUT /api/super-admin/feature-flags/{cid}/audiences/{audience}` met
    `{"on": bool}`. Het project beschermt `GET /api/feature-flags` zelf; de superbeheer-routes vragen `ROLE_SUPER_ADMIN`.
  - Alle standen zijn één item in `cache.app` (`project_template_feature_flag_states`), die pas na het vastleggen van
    een wijziging wordt gewist. Een omzetting binnen de eigen buitenste transactie van het project wist de gedeelde
    cache bij de eerstvolgende flush buiten een transactie, uiterlijk als het request, commando of bericht eindigt
    (terminate, worker-events, reset); een langlopend commando ziet tot dan de oude standen (of tot het vangnet van een
    uur), en een gelijktijdige herlaadactie kan zelden de oude stand opnieuw opslaan. In een cron of handler telt `isOn`
    als gast: gebruik daar `isOnFor`. Gelijktijdig omzetten reserveert de rij met `INSERT ... ON DUPLICATE KEY UPDATE
    id = id`, zonder deadlock. De superbeheer-routes vragen een eigen stateless firewall op `^/api/super-admin` (zie
    docs).
  - **Let op, migratie:** `Version20261019120000` maakt `feature_flags` en `feature_flag_audiences` aan in de database
    van elke consumer. Een eigen tabel met een van die namen moet weg zijn vóór deze migratie draait, en een migratie van het project kan dat niet doen: de migraties van de bundle draaien alfabetisch eerder dan die van het project. Alleen lokaal: verwijder eigen migratie en entity in dezelfde commit als de bundle-update, drop de tabel met de hand (`dbal:run-sql`) en verwijder de rij uit `doctrine_migration_versions`. In een gedeelde omgeving: hernoem of verwijder de tabel daar met de hand vóór het uitrollen (standen zo nodig met de hand overzetten). Zie docs.
  - **Zonder config** komen er twee lege tabellen bij en geeft elke controle `false`. `GET /api/feature-flags` geeft
    `{"featureFlags": []}`, de superbeheer-lijst de flags uit master data zonder doelgroepen en een `PUT` 404. Een
    resolver is dan niet nodig.
  - **Eis bij gebruik:** één gedeelde `cache.app` (bijvoorbeeld Redis) voor alle api- en worker-processen; de standen
    verlopen als vangnet na een uur.
- **Master data: `cid` opslaan en als terugvaloptie zoeken.** `MasterDataHydrator` slaat de `cid` op wanneer de entity
  `setCid()` heeft. Een bestaande entity wordt eerst gezocht op de opgegeven `uniqueFields`, zoals voorheen; vindt dat
  niets (of zijn er geen), dan wordt op `cid` alleen gezocht als het item een `cid` heeft en de entity een `cid`-veld.
  De gevonden regel krijgt de `cid` opgeslagen. Entities zonder `cid`-veld veranderen niet.
- **Cron-uitvoer live in `queue_job_logs`.** `RunCronMessageHandler` gooide de uitvoer van het commando weg (alleen
  bij een fout kwam de error-output in de exception); `stdout` bleef daardoor altijd leeg. De uitvoer (stdout en
  stderr, zoals ze kwamen) gaat nu ongeveer elke seconde naar de eigen log-regel van het bericht, ook als het
  commando daarna een tijd stil is, en blijft na afloop staan. Boven `project_template.queue.max_cron_output_bytes`
  (standaard 1 MB, minimaal 1024) valt het begin weg, zodat het eind (de uitkomst) altijd blijft.
  - `ProcessRunnerInterface::run()` kreeg een optionele `$onOutput`-callback. Zonder callback verandert er niets.
  - Nieuw `Queue\QueueJobLogContext`: de log-regel van het bericht dat nu afgehandeld wordt, zodat een handler
    naar zijn eigen regel kan schrijven. De middleware houdt hem bij; wat de handler erin zet blijft staan en wat
    hij echo't komt erachter.
  - `RunCronMessage` kreeg een optioneel `runId`. Het komt in `message_data`, zodat een met de hand gestarte run
    terug te vinden en live te volgen is.
- **Oude regels in `queue_job_logs` opruimen.** Nu er cron-uitvoer in staat, groeit de tabel bij elke run en niets
  ruimde hem op. Nieuw: `QueueJobLogRepository::removeStartedBefore()` en het commando
  `bundle:queue:remove-old-logs --days=30`. Het draait niet vanzelf: plan het als cron, of ruim op in de eigen
  opschoning van het project.
- **Nieuwe status `queued` in `queue_job_logs`.** De middleware draait ook bij het dispatchen en schreef dan een
  regel `completed`, terwijl er alleen iets op de wachtrij was gezet. Zo'n regel heet nu `queued` (zonder
  `completed_at`); de worker schrijft bij het afhandelen zijn eigen regel, zoals altijd. Wie `completed` telde, telt
  voortaan alleen echt afgehandelde berichten.

## [0.8.15] — 2026-09-22

- **`bundle:database:copy` struikelt niet meer over foreign keys.** De tabellen worden gekopieerd in
  de volgorde die MySQL teruggeeft — alfabetisch — en die staat volledig los van de
  foreign-key-afhankelijkheden. Verwijst een tabel naar een tabel die verderop in de lijst staat, dan
  faalt het `CREATE TABLE` met `SQLSTATE[HY000]: General error: 1824 Failed to open the referenced
  table '...'`; de `INSERT ... SELECT` per tabel heeft hetzelfde probleem. `FOREIGN_KEY_CHECKS` gaat
  nu uit voor de duur van de kopie en wordt in een `finally` weer aangezet. Schema's waarvan de FK's
  toevallig alleen naar alfabetisch eerdere tabellen wezen werkten al; een genormaliseerd model was
  niet te kopiëren (RENCO: `accounts` → `customers` → `sales_points` is in geen enkele alfabetische
  volgorde op te lossen).

## [0.8.14] — 2026-07-21

- **Bijlagen in `BrandedEmailMailer::send()`.** Nieuwe optionele `$attachments`-parameter die
  wordt doorgegeven aan `MailService::createAndSend()`. Nodig om mail mét bestand — zoals de
  factuur-PDF — door de gebrande route te sturen in plaats van er een losse, ongebrande mail
  van te maken. Achterwaarts compatibel: zonder de parameter verandert er niets.
- **Vier nieuwe template-keys** met NL default subject/body, zodat álle uitgaande post door
  dezelfde branded shell gaat en per tool bewerkbaar is in het E-mail-templates-admin:
  - `invoice` — factuurmail (referentie, factuurdatum, bedrag; PDF als bijlage)
  - `subscription_upcoming_charge` — aankondiging van een aanstaande incasso
  - `webhook_forward_failure` — operationele melding: webhook definitief niet afgeleverd
  - `daily_sync_summary` — operationele melding: dagelijkse sync-samenvatting

## [0.8.13] — 2026-07-21

- **Kwitantie-template-key** `payment_received` toegevoegd aan `EmailTemplateKey` met NL default
  subject/body (referentie/datum/omschrijving/bedrag). Bedoeld voor de branded betalingsbevestiging
  die payment-api op elke geïncasseerde betaling naar de klant stuurt; verschijnt automatisch in het
  E-mail-templates-admin naast de bestaande lifecycle-keys.

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
