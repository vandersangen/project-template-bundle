# GitHub Actions Workflows

Deze repository bevat geautomatiseerde CI/CD pipelines die worden uitgevoerd bij elke push en pull request.

## Workflows

### 1. CI Pipeline (`ci.yml`)

**Trigger:** Push naar `main` of `develop` branches, of pull requests naar deze branches.

**Stappen:**
1. ✅ Checkout code
2. ✅ Build Docker images (PHP 8.5-FPM, MySQL 8.0, Nginx)
3. ✅ Start Docker containers
4. ✅ Wacht tot MySQL klaar is
5. ✅ Installeer Composer dependencies
6. ✅ Genereer JWT keys
7. ✅ Maak database schema aan
8. ✅ Laad master data
9. ✅ Maak test database aan
10. ✅ Maak test database schema aan
11. ✅ Laad test master data
12. ✅ Voer PHPUnit tests uit (35 tests)
13. ✅ Genereer code coverage rapport
14. ✅ Upload coverage naar Codecov (optioneel)
15. ✅ Toon test resultaten
16. ✅ Stop en verwijder containers

**Test Coverage:**
- Bundle functional tests (10 tests)
- Bundle unit tests (8 tests)
- Application happy flow tests (7 tests)
- Application unhappy flow tests (8 tests)
- Sample tests (2 tests)

**Totaal: 35 tests, 96 assertions**

### 2. Code Quality (`code-quality.yml`)

**Trigger:** Push naar `main` of `develop` branches, of pull requests naar deze branches.

**Stappen:**
1. ✅ Checkout code
2. ✅ Build Docker images
3. ✅ Start Docker containers
4. ✅ Installeer Composer dependencies
5. ✅ Run PHP CodeSniffer (PSR-12 coding standards)
6. ✅ Run PHPMD (PHP Mess Detector)
7. ✅ Run PHPStan (static analysis)
8. ✅ Run Rector (dry-run voor code modernization)
9. ✅ Stop en verwijder containers

**Note:** Code quality checks zijn momenteel ingesteld op `continue-on-error: true` zodat ze de build niet blokkeren. Dit kan later worden aangepast naar strikte checks.

## Lokaal Testen

Je kunt de workflows lokaal simuleren met de volgende commando's:

### CI Pipeline lokaal uitvoeren:

```bash
# Build en start containers
docker compose -f docker-compose-dev.yaml up -d --build

# Installeer dependencies
docker exec bundle_api composer install

# Maak JWT keys aan
docker exec bundle_api mkdir -p config/jwt
docker exec bundle_api openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:changeme 4096
docker exec bundle_api openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:changeme

# Maak databases aan
docker exec bundle_api php bin/console doctrine:schema:create --env=dev
docker exec bundle_api php bin/console bundle:master-data:load --env=dev

docker exec bundle_db mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS bundle_db_test_test;"
docker exec bundle_db mysql -u root -proot -e "GRANT ALL PRIVILEGES ON bundle_db_test_test.* TO 'bundle_user'@'%'; FLUSH PRIVILEGES;"
docker exec bundle_api php bin/console doctrine:schema:create --env=test
docker exec bundle_api php bin/console bundle:master-data:load --env=test

# Run tests
docker exec bundle_api php bin/phpunit --testdox
```

### Code Quality lokaal uitvoeren:

```bash
# PHP CodeSniffer
docker exec bundle_api vendor/bin/phpcs --standard=phpcs.xml src/ bundle/src/

# PHPMD
docker exec bundle_api vendor/bin/phpmd src/,bundle/src/ text phpmd.xml

# PHPStan
docker exec bundle_api vendor/bin/phpstan analyse -c phpstan.neon

# Rector
docker exec bundle_api vendor/bin/rector process --dry-run
```

## Status Badges

Je kunt de volgende badges toevoegen aan je README.md:

```markdown
![CI Pipeline](https://github.com/USERNAME/REPOSITORY/workflows/CI%20Pipeline/badge.svg)
![Code Quality](https://github.com/USERNAME/REPOSITORY/workflows/Code%20Quality/badge.svg)
```

Vervang `USERNAME` en `REPOSITORY` met je GitHub gebruikersnaam en repository naam.

## Configuratie

### Secrets

De workflows gebruiken geen secrets, maar als je Codecov wilt gebruiken, voeg dan de volgende secret toe:

- `CODECOV_TOKEN`: Je Codecov token (optioneel)

### Environment Variables

Alle environment variables worden ingesteld via de Docker Compose configuratie en `.env` bestanden.

## Troubleshooting

### MySQL connection errors

Als de MySQL container niet op tijd klaar is, verhoog dan de timeout in de "Wait for MySQL" step:

```yaml
for i in {1..60}; do  # Verhoog van 30 naar 60
```

### Composer memory errors

Als Composer out-of-memory errors geeft, voeg dan de volgende environment variable toe:

```yaml
- name: Install Composer dependencies
  run: docker exec bundle_api composer install --no-interaction --prefer-dist --optimize-autoloader
  env:
    COMPOSER_MEMORY_LIMIT: -1
```

### Test failures

Als tests falen in de CI maar lokaal werken, controleer dan:
1. Database configuratie (`.env.test`)
2. JWT keys zijn correct gegenereerd
3. Master data is correct geladen
4. Alle dependencies zijn geïnstalleerd

## Onderhoud

### Dependencies updaten

De workflows gebruiken de volgende GitHub Actions:
- `actions/checkout@v4` - Code checkout
- `docker/setup-buildx-action@v3` - Docker Buildx setup
- `codecov/codecov-action@v4` - Codecov upload (optioneel)

Update deze regelmatig naar de nieuwste versies.

### Docker images updaten

De workflows gebruiken de images gedefinieerd in `docker-compose-dev.yaml`:
- PHP 8.5-FPM
- MySQL 8.0
- Nginx Alpine
- Mailpit

Update deze in `Dockerfile.dev` en `docker-compose-dev.yaml` wanneer nieuwe versies beschikbaar zijn.

