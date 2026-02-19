# Project Template Bundle

A reusable Symfony bundle providing authentication, user management, and master data loading functionality.

## Features

- **JWT Authentication**: Complete authentication system with login, register, password reset
- **User Management**: User entity, repository, and service layer
- **Master Data Loading**: Flexible master data loading from YAML/PHP configuration files
- **Database Tools**: Git branch-based database naming, database copy command
- **Health Checks**: API health check endpoints

## Installation

### Via Composer (from Packagist)

```bash
composer require larsvandersangen/project-template-bundle
```

### Via Local Path (for development)

Add to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../project-template-bundle"
        }
    ],
    "require": {
        "larsvandersangen/project-template-bundle": "@dev"
    }
}
```

Then run:

```bash
composer require larsvandersangen/project-template-bundle:@dev
```

## Configuration

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    LarsVanDerSangen\ProjectTemplateBundle\ProjectTemplateBundle::class => ['all' => true],
];
```

Configure the bundle in `config/packages/project_template.yaml`:

```yaml
project_template:
    mailer_sender: 'noreply@example.com'
```

## Usage

### Authentication

The bundle provides authentication controllers at:
- `POST /api/auth/login`
- `POST /api/auth/register`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`

### Master Data Loading

Load master data from configuration files:

```bash
php bin/console app:master-data:load
```

### Database Commands

Copy database between branches:

```bash
php bin/console app:database:copy
```

## Requirements

- PHP >= 8.5
- Symfony >= 7.4
- Doctrine ORM >= 3.6

## License

MIT

