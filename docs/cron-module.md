# Cron module

De Cron-module laat je commando's op een schema (cron-expressie) in de wachtrij zetten. Ze worden asynchroon uitgevoerd en verschijnen in `queue_job_logs`.

## Beheer (SuperAdmin)

Cron-beheer zit in de **SuperAdmin-module**. Alleen ingelogde SuperAdmin-gebruikers hebben toegang.

- **API (JSON)**: `GET/POST/PUT/DELETE /super-admin/api/crons` — na inloggen op SuperAdmin (session).
- **Webinterface**: `/super-admin/crons` — lijst, aanmaken, bewerken, verwijderen. Na SuperAdmin-login.

Zie [docs/super-admin.md](super-admin.md) voor het configureren van de SuperAdmin-module en gebruikers.

## System cron

Voer elke minuut het schedule-commando uit (bijv. via crontab):

```bash
* * * * * cd /path/to/your/app && php bin/console bundle:cron:schedule
```

Daarmee worden alle "due" crons naar de async queue gestuurd; de worker voert ze uit en ze worden gelogd in `queue_job_logs`.

## Migratie

Na installatie van de bundle:

```bash
php bin/console doctrine:migrations:migrate
```

De tabel `crons` wordt dan aangemaakt (migratie uit de bundle).
