# Project Template Bundle - Development Environment

Dit is een standalone Symfony applicatie voor het ontwikkelen en testen van de `project-template-bundle`.

## 📁 Structuur

```
project-template-bundle/
├── bundle/                    # De eigenlijke bundle code (tracked by Git)
│   ├── src/                   # Bundle source code
│   ├── config/                # Bundle configuratie
│   ├── tests/                 # Bundle tests
│   └── composer.json          # Bundle package definitie
│
├── src/                       # Test Symfony app (NIET tracked by Git)
├── config/                    # Test app config (NIET tracked by Git)
├── docker-compose-dev.yaml    # Onafhankelijke Docker setup
├── Dockerfile.dev             # PHP/Symfony Docker image
├── Makefile                   # Handige commando's
└── composer.json              # Test app dependencies
```

## 🚀 Quick Start

### 1. Start de Docker omgeving

```bash
# Start alle containers
make up

# Of handmatig:
docker-compose -f docker-compose-dev.yaml up -d
```

### 2. Installeer dependencies

```bash
# Via Makefile
make composer-install

# Of handmatig:
docker exec bundle_api composer install
```

### 3. Verifieer de installatie

```bash
docker exec bundle_api php bin/console --version
# Output: Symfony 7.4.5 (env: dev, debug: true)
```

## 🐳 Docker Omgeving

De bundle heeft een **volledig onafhankelijke** Docker setup:

- **Database**: MySQL 8.0 op poort **3307**
- **API**: PHP 8.5-FPM container (`bundle_api`)
- **Web Server**: Nginx op poort **8081**
- **Mailer**: Mailpit op poorten **1026/8026**

**Beschikbare URLs:**
- Web: http://localhost:8081
- Mailpit: http://localhost:8026

## 🛠️ Makefile Commando's

```bash
make help              # Toon alle beschikbare commando's
make up                # Start de Docker containers
make down              # Stop de Docker containers
make restart           # Herstart de Docker containers
make shell             # Open een shell in de bundle_api container
make composer-install  # Installeer Composer dependencies
make composer-update   # Update Composer dependencies
make test              # Voer tests uit
make logs              # Toon logs
make build             # Bouw Docker images opnieuw
```

## 💻 Development Workflow

### Bundle ontwikkelen

1. **Start de omgeving**:
   ```bash
   make up
   ```

2. **Maak wijzigingen** in `bundle/src/`

3. **Test wijzigingen** in de omliggende Symfony applicatie

4. **Commit wijzigingen** (alleen bundle files worden getracked):
   ```bash
   git add bundle/
   git commit -m "Jouw wijzigingen"
   git push
   ```

### Bundle configureren in test app

De bundle is al geconfigureerd in de test applicatie via een path repository in `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./bundle",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "larsvandersangen/project-template-bundle": "@dev"
    }
}
```

Wijzigingen in `bundle/` zijn direct zichtbaar via de symlink.

## 📦 Publiceren naar Packagist

### 1. Push naar GitHub

```bash
git remote add origin https://github.com/larsvandersangen/project-template-bundle.git
git push -u origin main
```

### 2. Tag een release

```bash
git tag -a v1.0.0 -m "First release"
git push origin v1.0.0
```

### 3. Submit naar Packagist

- Ga naar https://packagist.org/packages/submit
- Voer repository URL in: `https://github.com/larsvandersangen/project-template-bundle`
- Enable auto-update hook

### 4. Gebruik in andere projecten

```bash
composer require larsvandersangen/project-template-bundle:^1.0
```

## 🔒 Git Configuratie

**Belangrijk**: Alleen de `bundle/` directory wordt getracked door Git!

De `.gitignore` is zo geconfigureerd dat:
- ✅ `bundle/` directory wordt getracked
- ✅ `.gitignore` en `README.md` worden getracked
- ❌ Alle test Symfony applicatie bestanden worden genegeerd

## 🧪 Testing

```bash
# Voer alle tests uit
make test

# Of handmatig:
docker exec bundle_api php bin/phpunit
```

## 📝 Notities

- Deze omgeving is **volledig onafhankelijk** van het main `project-template` project
- Beide projecten kunnen **tegelijkertijd** draaien zonder conflicten
- De bundle moet **gepubliceerd** worden naar Packagist om gebruikt te kunnen worden in andere projecten
- Local development via path repository tussen projecten is **niet mogelijk** vanwege Docker isolatie

