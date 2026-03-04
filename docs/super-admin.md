# SuperAdmin-module

De SuperAdmin-module biedt een afgeschermd gedeelte van de applicatie met **eigen gebruikers en form-login**. Alleen SuperAdmin-gebruikers kunnen hierbij; gewone applicatiegebruikers (JWT/User) hebben geen toegang.

## Onderdelen

- **Eigen gebruikers**: entity `SuperAdminUser`, tabel `super_admin_users` (username, password, createdAt).
- **Login**: formulier op `/super-admin/login` (username + wachtwoord). Na succesvolle login: session; toegang tot alles onder `/super-admin`.
- **Cron-beheer**: webinterface op `/super-admin/crons` en REST API op `/super-admin/api/crons`. Beide alleen bereikbaar na SuperAdmin-login.

## Configuratie (applicatie)

Voeg in de app in `config/packages/security.yaml` het volgende toe.

### 1. Password hasher voor SuperAdminUser

```yaml
security:
    password_hashers:
        # ... bestaande hashers ...
        VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser: 'auto'
```

### 2. User provider voor SuperAdmin

```yaml
    providers:
        # ... bestaande providers ...
        super_admin_provider:
            entity:
                class: VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser
                property: username
```

### 3. Firewall voor SuperAdmin

```yaml
    firewalls:
        # ... bestaande firewalls (api, public, etc.) ...

        super_admin:
            pattern: ^/super-admin
            provider: super_admin_provider
            form_login:
                login_path: super_admin_login
                check_path: super_admin_login
                enable_csrf: true
            logout:
                path: super_admin_logout
                target: super_admin_login
```

### 4. Access control

```yaml
    access_control:
        # ... bestaande regels ...
        - { path: ^/super-admin/login, roles: PUBLIC_ACCESS }
        - { path: ^/super-admin, roles: ROLE_SUPER_ADMIN }
```

De route `super_admin_login` moet overeenkomen met de login-URL (GET/POST `/super-admin/login`). De bundle levert die route.

## Migratie

Draai de migraties van de bundle zodat de tabel `super_admin_users` wordt aangemaakt:

```bash
php bin/console doctrine:migrations:migrate
```

## Eerste SuperAdmin-gebruiker

Er is geen standaard-gebruiker. Maak minimaal één SuperAdmin-user aan, bijvoorbeeld:

- Via een console-command die een username/password vraagt en een `SuperAdminUser` aanmaakt (met de password hasher).
- Via een data fixture (alleen voor dev/setup).
- Via een eenmalig setup-script.

De bundle levert geen command of fixture; de app moet die zelf toevoegen of handmatig een user in de database invoeren (wachtwoord gehashed met de geconfigureerde hasher).

## Overzicht URLs

| URL | Toegang |
|-----|--------|
| `/super-admin/login` | Iedereen (loginformulier) |
| `/super-admin/logout` | Ingelogde SuperAdmin (logout) |
| `/super-admin/crons` | SuperAdmin (cron-webinterface) |
| `/super-admin/api/crons` | SuperAdmin (cron REST API) |
