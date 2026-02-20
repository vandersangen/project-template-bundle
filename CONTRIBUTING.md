# Contributing to Project Template Bundle

Bedankt voor je interesse om bij te dragen aan de Project Template Bundle! 🎉

## 📋 Inhoudsopgave

- [Development Setup](#development-setup)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Requests](#pull-requests)
- [Git Workflow](#git-workflow)

## 🚀 Development Setup

### Vereisten

- Docker & Docker Compose
- Git
- Make (optioneel, maar handig)

### Eerste keer setup

1. **Clone de repository**:
   ```bash
   git clone https://github.com/larsvandersangen/project-template-bundle.git
   cd project-template-bundle
   ```

2. **Start de development omgeving**:
   ```bash
   make setup
   # Of handmatig:
   # make up
   # make composer-install
   # make db-create
   # make db-migrate
   ```

3. **Verifieer de installatie**:
   ```bash
   docker exec bundle_api php bin/console --version
   # Output: Symfony 7.4.5 (env: dev, debug: true)
   ```

4. **Open in je browser**:
   - Web: http://localhost:8081
   - Mailpit: http://localhost:8026

## 💻 Development Workflow

### Bundle code wijzigen

Alle bundle code staat in de `bundle/` directory:

```
bundle/
├── src/              # Source code
├── config/           # Bundle configuratie
├── tests/            # Tests
└── composer.json     # Package definitie
```

### Wijzigingen testen

De omliggende Symfony applicatie (root directory) is een test applicatie waar je de bundle kunt testen:

1. **Maak wijzigingen** in `bundle/src/`
2. **Test in de applicatie** via http://localhost:8081
3. **Voer tests uit**: `make test`

### Handige commando's

```bash
make help              # Toon alle beschikbare commando's
make shell             # Open shell in container
make console CMD="..."  # Voer Symfony console commando uit
make cache-clear       # Clear cache
make test              # Voer tests uit
make logs              # Bekijk logs
```

## 📝 Coding Standards

### PHP

- **PHP Version**: 8.5+
- **Symfony Version**: 7.4+
- **PSR-12**: Code style standard
- **Type hints**: Gebruik altijd type hints voor parameters en return types
- **Strict types**: Gebruik `declare(strict_types=1);` in alle PHP files

### Naamgeving

- **Classes**: PascalCase (bijv. `UserRepository`)
- **Methods**: camelCase (bijv. `getUserById`)
- **Constants**: UPPER_SNAKE_CASE (bijv. `MAX_RETRY_COUNT`)
- **Properties**: camelCase (bijv. `$userName`)

### Documentatie

- Voeg PHPDoc toe aan alle public methods
- Leg complexe logica uit met inline comments
- Update de README.md bij nieuwe features

## 🧪 Testing

### Tests uitvoeren

```bash
# Alle tests
make test

# Met coverage
make test-coverage
```

### Tests schrijven

- Plaats tests in `bundle/tests/`
- Gebruik PHPUnit voor unit tests
- Zorg voor minimaal 80% code coverage voor nieuwe code
- Test edge cases en error scenarios

### Test structuur

```php
<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testExample(): void
    {
        $this->assertTrue(true);
    }
}
```

## 🔀 Pull Requests

### Voor je een PR maakt

1. ✅ Alle tests slagen
2. ✅ Code volgt de coding standards
3. ✅ Nieuwe features hebben tests
4. ✅ Documentatie is bijgewerkt
5. ✅ Commit messages zijn duidelijk

### PR Template

```markdown
## Beschrijving
[Beschrijf wat deze PR doet]

## Type wijziging
- [ ] Bug fix
- [ ] Nieuwe feature
- [ ] Breaking change
- [ ] Documentatie update

## Checklist
- [ ] Tests toegevoegd/bijgewerkt
- [ ] Documentatie bijgewerkt
- [ ] Alle tests slagen
- [ ] Code review gedaan
```

## 📌 Git Workflow

### Branches

- `main` - Stable release branch
- `develop` - Development branch
- `feature/naam` - Feature branches
- `bugfix/naam` - Bug fix branches

### Commit Messages

Gebruik duidelijke, beschrijvende commit messages:

```bash
# Goed ✅
git commit -m "Add user authentication service"
git commit -m "Fix null pointer exception in UserRepository"
git commit -m "Update README with installation instructions"

# Slecht ❌
git commit -m "fix"
git commit -m "updates"
git commit -m "wip"
```

### Workflow

1. **Fork de repository** (voor externe contributors)
2. **Maak een feature branch**:
   ```bash
   git checkout -b feature/mijn-feature
   ```
3. **Maak je wijzigingen** in `bundle/`
4. **Commit je wijzigingen**:
   ```bash
   make git-add
   make git-commit MSG="Add nieuwe feature"
   ```
5. **Push naar je fork**:
   ```bash
   git push origin feature/mijn-feature
   ```
6. **Maak een Pull Request** naar `develop`

## ❓ Vragen?

Heb je vragen? Open een issue op GitHub of neem contact op via de support kanalen.

Bedankt voor je bijdrage! 🙏

