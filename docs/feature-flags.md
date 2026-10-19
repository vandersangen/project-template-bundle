# Feature flags

Met de FeatureFlag-module zet je een deel van de applicatie per **doelgroep** aan of uit, per omgeving, zonder deploy.
Een flag die nooit is omgezet staat **uit** voor iedereen: alleen een opgeslagen regel kan een flag aanzetten.

- Opslag: tabel `feature_flags`, één regel per flag en doelgroep (`feature_flag`, `audience`, `is_on`, `updated_at`,
  `updated_by`). Flag en doelgroep staan er als gewone strings in (hun `key()`).
- Geen regel = uit. Een regel van een flag of doelgroep die het project niet meer heeft, telt niet mee en geeft geen
  fout.

## Migratie: let op

De migratie `Version20261019120000` maakt `feature_flags` aan in **de database van elke consumer**, ook in projecten
zonder flags (de tabel blijft daar leeg).

- Heeft een project al een tabel met de naam `feature_flags`, hernoem of verwijder die dan **vóór** de bundle-update;
  anders faalt de migratie.
- Een project dat overstapt van een eigen kopie (zoals RENCO) verwijdert zijn eigen entity, repository, service,
  listener en migratie **in dezelfde commit** als de bundle-update. Twee entities op dezelfde tabel geven anders een
  dubbele mapping, en de eigen migratie botst met die van de bundle.

## Configuratie

Het project levert twee backed enums en noemt ze in de config:

```yaml
# config/packages/project_template.yaml
project_template:
    feature_flags:
        flags: App\FeatureFlag\Enum\FeatureFlag
        audiences: App\FeatureFlag\Enum\FeatureFlagAudience
```

Beide zijn optioneel (zie [Zonder config](#zonder-config)), maar `flags` zonder `audiences` mag niet. De config wordt
gecontroleerd bij het bouwen van de container (`cache:clear`, deploy); een fout laat dat bouwen falen, met een melding
die zegt wat er mis is en welke class het betreft. Fout is:

- een class die geen backed enum is, of de interface niet implementeert (de melding noemt dan ook de interface);
- twee cases met dezelfde `key()`;
- een key van een flag langer dan 50 tekens, of van een doelgroep langer dan 20 (de lengte van de kolommen);
- `flags` zonder `audiences`;
- een flag waarvan `backgroundAudience()` geen case is van de geconfigureerde doelgroepen-enum.

```php
enum FeatureFlag: string implements FeatureFlagInterface
{
    case UPDATE_MAILS = 'update_mails'; // key: max. 50 tekens, nooit meer wijzigen

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return match ($this) {
            self::UPDATE_MAILS => 'Updates per mail naar de leden',
        };
    }

    // Wiens schakelaar telt voor werk zonder ingelogde gebruiker (cron, queue-handler).
    public function backgroundAudience(): FeatureFlagAudienceInterface
    {
        return match ($this) {
            self::UPDATE_MAILS => FeatureFlagAudience::MEMBERS,
        };
    }
}

enum FeatureFlagAudience: string implements FeatureFlagAudienceInterface
{
    case MEMBERS = 'leden'; // key: max. 20 tekens, nooit meer wijzigen

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return 'Leden';
    }
}
```

Een case verwijderen mag: de opgeslagen regels ervan worden genegeerd.

### Wiens verzoek is het? (resolver)

`#[RequiresFeatureFlag]` en `GET /api/feature-flags` vragen de doelgroep van het huidige verzoek aan
`FeatureFlagAudienceResolverInterface`. De standaard (`SingleAudienceResolver`) geeft de **eerste** geconfigureerde
doelgroep; genoeg voor een project met één doelgroep. Heeft een project er meer, dan levert het een eigen resolver
(bijvoorbeeld op basis van de rollen van de ingelogde gebruiker):

```yaml
# config/services.yaml
VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface:
    alias: App\FeatureFlag\Service\RoleBasedAudienceResolver
```

**Let op:** de resolver draait voor `#[RequiresFeatureFlag]` op `kernel.controller`, dus **vóór** `#[IsGranted]` op de
controller. Hij moet dus ook een verzoek zonder ingelogde gebruiker aankunnen (geef dan bijvoorbeeld de doelgroep met
de minste rechten terug), en mag daar niet op stuklopen.

## Gebruik in code

```php
// Voor de ingelogde gebruiker, met de doelgroep die je zelf bepaalt:
$featureFlagService->isOnFor(FeatureFlag::UPDATE_MAILS, $audience);

// In een cron of queue-handler: de backgroundAudience() van de flag beslist.
$featureFlagService->isOnForBackgroundWork(FeatureFlag::UPDATE_MAILS);

// Omzetten (doet ook de superbeheer-endpoint). Let op: bij een wijziging flusht dit de hele unit of work.
$featureFlagService->switchFlag(FeatureFlag::UPDATE_MAILS, FeatureFlagAudience::MEMBERS, true, $user->getUserIdentifier());
```

Een hele controller of één actie achter een flag: zolang de flag uit staat voor de doelgroep van de aanroeper,
antwoordt de route **404**, alsof hij niet bestaat.

```php
#[RequiresFeatureFlag(FeatureFlag::UPDATE_MAILS)]
final class UpdateMailController extends AbstractController { /* ... */ }
```

Een controller zonder het attribuut merkt er niets van: geen resolver, geen cache, geen query.

## Endpoints

Beide controllers zitten in het blok `feature_flag_controllers` van de `config/routes.yaml` van de bundle. Een project
dat de routes via `ProjectTemplateRouteLoader` laadt krijgt ze dus vanzelf; een project dat alleen losse mappen
importeert, importeert `src/FeatureFlag/Controller/` zelf. **Het project beschermt de paden**:

- `GET /api/feature-flags` (route `feature_flags_list`): achter de gewone API-firewall (JWT). De controller eist
  `IS_AUTHENTICATED`.

  ```json
  {"featureFlags": ["update_mails"]}
  ```

  Alleen de keys van de flags die aan staan voor de doelgroep van de aanroeper. Zonder geconfigureerde flags: `[]`.
- `GET /api/super-admin/feature-flags` (route `super_admin_feature_flags_list`) en
  `PUT /api/super-admin/feature-flags/{key}` (route `super_admin_feature_flags_switch`): achter de
  superbeheer-authenticatie van het project. De controller eist `ROLE_SUPER_ADMIN`; een gewone gebruiker krijgt 403.

  ```json
  {"featureFlags": [{
      "key": "update_mails",
      "description": "Updates per mail naar de leden",
      "audiences": [
          {"key": "leden", "description": "Leden", "on": true, "updatedAt": "2026-10-19T12:00:00+02:00", "updatedBy": "rick"},
          {"key": "beheer", "description": "Beheer", "on": false, "updatedAt": null, "updatedBy": null}
      ]
  }]}
  ```

  De PUT krijgt `{"audience": "leden", "on": true}` en antwoordt dezelfde lijst. `updatedBy` is de
  `getUserIdentifier()` van de ingelogde super admin. Onbekende flag: 404. Doelgroep ontbreekt of is onbekend: 422
  `{"errors": {"audience": "Kies voor wie."}}`. `on` ontbreekt of is geen boolean: 422
  `{"errors": {"on": "Kies aan of uit."}}`. Een body die geen JSON-object is (kapotte JSON, lege body, een losse
  string): 400. In al die gevallen wordt niets opgeslagen.

## Cache

De opgeslagen standen staan in de app-cache (`cache.app`, sleutel `project_template_feature_flag_states`). Ze worden
gewist na elke `switchFlag()` en door een entity listener bij elke persist, update of remove van een
`FeatureFlagSetting`, ook als die buiten de service om via de ORM gebeurt. Een omzetting telt dus meteen.

- **Eis:** een project dat flags omzet heeft één gedeelde `cache.app` (bijvoorbeeld Redis) voor alle api- en
  worker-processen. Met een cache per proces (filesystem per pod, APCu) wist een omzetting alleen de cache van het
  proces dat hem deed; de andere lezen tot een uur lang de oude stand.
- **Vangnet:** de standen verlopen na een uur. Valt het wissen van de cache samen met een transactie die nog niet is
  gecommit, dan kan een ander proces net de oude stand opnieuw in de cache zetten; die blijft dan hooguit een uur staan.
- Wie de tabel met de hand (SQL) wijzigt, wist de cache zelf: `FeatureFlagService::clearCachedStates()` of
  `cache:pool:clear cache.app`.

`switchFlag()` reserveert de regel binnen één transactie met `INSERT IGNORE` in de **tegenovergestelde** stand, leest
hem met een schrijflock en zet hem via de ORM om. Een eerste omzetting is daardoor altijd een echte wijziging (een
update) voor entity listeners en een onFlush-audit, ook naar uit. Niemand buiten de transactie ziet de gereserveerde
stand, en twee processen die tegelijk voor het eerst omzetten botsen niet op de unieke sleutel: de tweede wacht tot de
eerste gecommit is en past dan de opgeslagen regel aan. `changedBy` is hooguit 180 tekens; langer geeft een
`\InvalidArgumentException` voordat er iets geschreven wordt. De service is de schrijver van `FeatureFlagSetting`: een
ongeflushte wijziging die de aanroeper zelf aan dezelfde instelling deed, gaat verloren.

## Zonder config

Een project dat de bundle bijwerkt maar geen `feature_flags` configureert, merkt dit:

- De migratie maakt een lege tabel `feature_flags` aan.
- Laadt het project de routes via `ProjectTemplateRouteLoader`, dan bestaan de endpoints ook daar:
  - `GET /api/feature-flags` geeft een ingelogde gebruiker `{"featureFlags": []}`;
  - `/api/super-admin/feature-flags` geeft een gewone gebruiker 403; een super admin krijgt op GET
    `{"featureFlags": []}` en op een PUT 404.
- Wil een project die routes niet, dan gebruikt het de route loader niet en importeert het alleen de mappen met
  controllers die het wel wil (zoals RENCO doet).
- Elke controle geeft `false`; `#[RequiresFeatureFlag]` komt er niet voor.
