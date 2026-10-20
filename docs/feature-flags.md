# Feature flags

Met de FeatureFlag-module zet je een deel van de applicatie per **doelgroep** aan of uit, per omgeving, zonder deploy.
Een flag die nooit is omgezet staat **uit** voor iedereen: alleen een opgeslagen schakelaar kan een flag aanzetten.

- De **flags zelf** (cid, naam, omschrijving) komen uit master data in de tabel `feature_flags`.
- Of een flag aan staat, per doelgroep, staat in de koppeltabel `feature_flag_audiences`. Alleen
  `FeatureFlagService::switchFlag()` schrijft daarin, vanuit superbeheer.
- De **doelgroepen** staan in de config van het project en worden bij het bouwen van de container gecontroleerd.
- Wie bij welke doelgroep hoort, bepaalt de **resolver** van het project.

## Tabellen en migratie

De migratie `Version20261019120000` maakt twee tabellen aan in **de database van elke consumer**, ook in projecten zonder
flags (ze blijven daar leeg).

| Tabel | Kolommen |
|-------|----------|
| `feature_flags` | `id`, `cid` (unieke sleutel, 50), `name` (100), `description` (TEXT, 1000, optioneel), `created_at`, `updated_at` |
| `feature_flag_audiences` | `id`, `feature_flag_id` (verwijst naar `feature_flags`, `ON DELETE CASCADE`), `audience` (key van de doelgroep, 20), `is_on`, `updated_at`, `updated_by` (180); uniek op flag en doelgroep |

- Geen regel in `feature_flag_audiences` = uit. Een regel van een doelgroep die het project niet meer heeft, telt niet
  mee en geeft geen fout.
- Heeft een project al een tabel met de naam `feature_flags` of `feature_flag_audiences`, hernoem of verwijder die dan
  **vóór** de bundle-update; anders faalt de migratie.
- Een project dat overstapt van een eigen kopie (zoals RENCO) verwijdert zijn eigen entity, repository, service,
  listener en migratie **in dezelfde commit** als de bundle-update. Twee entities op dezelfde tabel geven anders een
  dubbele mapping, en de eigen migratie botst met die van de bundle.

## Flags registreren in master data

Een flag is een regel in de master data van het project, bijvoorbeeld in `src/<Module>/.config/master_data/default.php`
(of `dev.php`, `test.php`, `prod.php` per omgeving):

```php
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;

return [
    'featureFlags' => [
        [
            'class' => FeatureFlag::class,
            'cid' => 'update_mails',
            'name' => 'Updates per mail',
            'description' => 'Leden ontvangen updates per mail.',
        ],
    ],
];
```

`bundle:master-data:load` maakt de flag aan, of werkt naam en omschrijving bij.

- De `cid` is de sleutel waarmee de code de flag vraagt, en blijft dus gelijk. Een flag hernoemen = de `name`
  aanpassen; de schakelaars blijven staan. Twee keer laden geeft één regel per cid.
- De `MasterDataHydrator` slaat de `cid` op wanneer de entity `setCid()` heeft, en zoekt een bestaande entity altijd op
  `cid` als de entity een `cid`-veld heeft, ook als `uniqueFields` dat niet noemt. Heeft een item ook andere
  `uniqueFields` (bijvoorbeeld `email`), dan wordt `cid` daaraan toegevoegd en moeten ze **allemaal** kloppen (AND). Een
  `cid` wijzigen maakt dus een nieuwe regel aan.

## Configuratie: doelgroepen

```yaml
# config/packages/project_template.yaml
project_template:
    feature_flags:
        audiences:
            leden:  { label: 'Leden' }
            beheer: { label: 'Beheer' }
            gasten: { label: 'Niet ingelogd', guest: true }
```

- De **key** begint met een letter, bestaat uit `a-z`, `0-9` en `_` en is hoogstens 20 tekens. Hij wordt als tekst in de
  database bewaard: wijzig een key nooit, want de schakelaars staan eronder.
- Het `label` is verplicht en verschijnt in superbeheer.
- Hoogstens één doelgroep heeft `guest: true`: die geldt voor een bezoeker zonder login.
- De volgorde van de config is de volgorde in de lijst van superbeheer.
- Een foute config laat het bouwen van de container falen; er is geen controle tijdens een request.
- Zonder `audiences` is er geen doelgroep: elke controle geeft `false`.

## De resolver: wiens verzoek is het?

Zodra er doelgroepen zijn ingesteld is een resolver **verplicht**; zonder bouwt de container niet
(`RequireFeatureFlagAudienceResolverPass`). Het project implementeert
`FeatureFlagAudienceResolverInterface` en zegt voor een ingelogde gebruiker bij welke doelgroepen hij hoort:

```php
namespace App\FeatureFlag;

use Symfony\Component\Security\Core\User\UserInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;

final class AccountAudienceResolver implements FeatureFlagAudienceResolverInterface
{
    public function resolveAudiencesOf(UserInterface $user): array
    {
        if (in_array('ROLE_ADMINISTRATOR', $user->getRoles(), true)) {
            return ['beheer'];
        }

        return ['leden'];
    }
}
```

```yaml
# config/services.yaml
services:
    VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface:
        alias: App\FeatureFlag\AccountAudienceResolver
```

- De resolver geeft keys terug. Een onbekende key wordt genegeerd. De key van de gastdoelgroep telt alleen als de
  resolver hem zelf teruggeeft; voor een ingelogde gebruiker wordt hij niet vanzelf toegevoegd.
- Een bezoeker zonder login wordt niet aan de resolver gevraagd: voor hem geldt de doelgroep met `guest: true`
  (zonder zo'n doelgroep geen enkele).
- Bij meer doelgroepen staat een flag **aan** zodra hij voor één ervan aan staat.

## Gebruik in code

```php
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

// Voor de huidige gebruiker (of de gast):
if ($this->featureFlagService->isOn('update_mails')) {
    // ...
}

// Voor een cron of queue-handler, die geen gebruiker heeft: de doelgroep noem je zelf.
if (!$this->featureFlagService->isOnFor('update_mails', 'leden')) {
    return;
}

#[RequiresFeatureFlag('update_mails')]
final class UpdateMailController extends AbstractController
{
}
```

- `isOn($cid)` kijkt naar de doelgroepen van de huidige gebruiker, of naar de gastdoelgroep.
- `isOnFor($cid, $audience)` kijkt naar één doelgroep. Een onbekende flag of doelgroep geeft `false`.
- `listCidsOn()` geeft de cids die voor de huidige gebruiker aan staan, gesorteerd.
- `#[RequiresFeatureFlag('cid')]` op een controller of actie geeft **404** zolang de flag uit staat voor de
  aanroeper. Meerdere attributen: ze moeten allemaal aan staan.
- De listener draait op `kernel.controller`, dus **vóór** `#[IsGranted]`: een gast krijgt 404 en niet 401 op een route
  die uit staat. Een `access_control`-regel van de firewall draait nog eerder (`kernel.request`): staat een pad daar
  achter een rol, dan krijgt een gast eerst 401.

## Een flag omzetten

`FeatureFlagService::switchFlag($cid, $audience, $on, $updatedBy)` schrijft de schakelaar (`updatedBy` wordt
afgekapt op 180 tekens). Een onbekende flag of doelgroep geeft `UnknownFeatureFlagException` en schrijft niets. Twee
super admins die tegelijk een nieuwe schakelaar omzetten geven één regel en geen fout. Dezelfde stand opnieuw
schrijft niets.

## Endpoints

De bundle beschermt alleen de superbeheer-routes (`ROLE_SUPER_ADMIN`). `GET /api/feature-flags` heeft geen
`#[IsGranted]`: het project beslist wie erbij mag.

| Route | Naam | Antwoord |
|-------|------|----------|
| `GET /api/feature-flags` | `feature_flags_list` | `{"featureFlags": ["update_mails", ...]}`: de cids die aan staan voor de aanroeper, gesorteerd |
| `GET /api/super-admin/feature-flags` | `super_admin_feature_flags_list` | alle flags met hun doelgroepen |
| `PUT /api/super-admin/feature-flags/{cid}/audiences/{audience}` | `super_admin_feature_flags_switch` | dezelfde lijst als de GET |

```json
{
  "featureFlags": [
    {
      "cid": "update_mails",
      "name": "Updates per mail",
      "description": "Leden ontvangen updates per mail.",
      "audiences": [
        {"key": "leden", "label": "Leden", "guest": false, "on": true,
         "updatedAt": "2026-10-20T09:15:00+00:00", "updatedBy": "rick@example.nl"},
        {"key": "gasten", "label": "Niet ingelogd", "guest": true, "on": false,
         "updatedAt": null, "updatedBy": null}
      ]
    }
  ]
}
```

- Flags staan gesorteerd op cid, doelgroepen in de volgorde van de config. `description` is `null` zonder omschrijving.
- Het `PUT`-lichaam is `{"on": true}`. Is `on` geen boolean, dan volgt 422 met
  `{"errors": {"on": "Kies aan of uit."}}`. Een onbekende cid of doelgroep geeft 404 met `{"error": "..."}`.

### `GET /api/feature-flags` openen voor gasten

Een gast kan ook flags hebben (de gastdoelgroep). Het project zet daarom het pad open in `access_control`, vóór de
algemene regel voor `/api`:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/api/feature-flags$, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: ROLE_USER }
```

De firewall van dat pad moet ook een bezoeker zonder token doorlaten.

## Cache

Alle standen zijn één cache-item, `project_template_feature_flag_states` in `cache.app`, per request of bericht één keer
gelezen en daarna in het geheugen bewaard. De levensduur is een uur, als vangnet.

- **Eis:** één gedeelde `cache.app` (bijvoorbeeld Redis) voor alle api- en worker-processen. Met een lokale cache per
  proces zien ze elkaars omzetting niet.
- De cache wordt pas gewist **nadat** de wijziging is vastgelegd, zodat geen ander proces de oude standen opnieuw
  cachet. Dat geldt voor `switchFlag()` en voor elke andere wijziging van een flag of schakelaar via de ORM (zoals het
  laden van master data), via `FeatureFlagCacheInvalidationListener`.
- Zet je om binnen de eigen buitenste transactie van het project, dan wordt de gedeelde cache gewist zodra die
  transactie eindigt, uiterlijk als het request, commando of bericht eindigt. Controles in hetzelfde proces kunnen tot
  dan de oude stand zien.

## Zonder config

Een project zonder `feature_flags`-config krijgt twee lege tabellen en de drie routes (ze komen via
`ProjectTemplateRouteLoader` mee; wie ze niet wil, importeert alleen de eigen mappen):

- elke controle (`isOn`, `isOnFor`, `#[RequiresFeatureFlag]`) is `false`/404;
- `GET /api/feature-flags` geeft `{"featureFlags": []}`;
- de superbeheer-lijst geeft de flags uit master data, met lege `audiences`;
- een `PUT` geeft 404, omdat er geen doelgroep bekend is;
- een resolver is niet nodig.

## Een flag verwijderen

Haal de regel uit de master data. De flag zelf blijft in de database staan, met zijn schakelaars: master data
verwijdert niets. Ruim hem op met een migratie in het project (`DELETE FROM feature_flags WHERE cid = '...'`; de
schakelaars gaan mee door `ON DELETE CASCADE`). Verwijder eerst de code die hem vraagt.
