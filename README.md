# Project Template Bundle

[![CI Pipeline](https://github.com/vanDerSangen/project-template-bundle/workflows/CI%20Pipeline/badge.svg)](https://github.com/vanDerSangen/project-template-bundle/actions)
[![Code Quality](https://github.com/vanDerSangen/project-template-bundle/workflows/Code%20Quality/badge.svg)](https://github.com/vanDerSangen/project-template-bundle/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A reusable Symfony bundle providing authentication, user management, and master data loading functionality.

## Features

- **JWT Authentication**: Complete authentication system with login, register, password reset
- **User Management**: User entity, repository, and service layer
- **Master Data Loading**: Flexible master data loading from PHP configuration files
- **Database Tools**: Git branch-based database naming, database copy command
- **Health Checks**: API health check endpoints
- **Queue (Messenger)**: Async message handling, `QueueJobLog` for monitoring, failed jobs in a separate transport
- **Fully Tested**: 35 tests with 96 assertions (100% passing)

## Installation

### Via Composer (from Packagist)

```bash
composer require vanDerSangen/project-template-bundle
```

## Requirements

- PHP >= 8.5
- Symfony >= 7.4
- Doctrine ORM >= 3.6
- Lexik JWT Authentication Bundle >= 3.2

## Quick Start

### 1. Register the Bundle

Add to `config/bundles.php`:

```php
return [
    // ...
    VanDerSangen\ProjectTemplateBundle\ProjectTemplateBundle::class => ['all' => true],
];
```

### 2. Configure the Bundle

Create `config/packages/project_template.yaml`:

```yaml
project_template:
    mailer_sender: 'noreply@example.com'
```

### 3. Import Routes

Add to `config/routes.yaml`:

```yaml
project_template:
    resource: '@ProjectTemplateBundle/config/routes.yaml'
    prefix: /
```

### 4. Configure Security

Update `config/packages/security.yaml`:

```yaml
security:
    password_hashers:
        VanDerSangen\ProjectTemplateBundle\User\Entity\User: 'auto'
    
    providers:
        app_user_provider:
            entity:
                class: VanDerSangen\ProjectTemplateBundle\User\Entity\User
                property: email
    
    firewalls:
        public:
            pattern: ^/(api/health|api/auth/(login|register|forgot-password|reset-password))
            security: false
        
        api:
            pattern: ^/api
            stateless: true
            provider: app_user_provider
            custom_authenticators:
                - VanDerSangen\ProjectTemplateBundle\Auth\Security\JwtAuthenticator
            entry_point: VanDerSangen\ProjectTemplateBundle\Auth\Security\JwtAuthenticator
    
    access_control:
        - { path: ^/api/health, roles: PUBLIC_ACCESS }
        - { path: ^/api/auth/(login|register|forgot-password|reset-password), roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: ROLE_USER }
```

### 5. Generate JWT Keys

```bash
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:changeme 4096
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:changeme
```

### 6. Configure JWT

Create `config/packages/lexik_jwt_authentication.yaml`:

```yaml
lexik_jwt_authentication:
    secret_key: '%kernel.project_dir%/config/jwt/private.pem'
    public_key: '%kernel.project_dir%/config/jwt/public.pem'
    pass_phrase: 'changeme'
```

### 7. Run Migrations

The bundle automatically registers its migrations in your application. When you run the migrations command, both your application's migrations and the bundle's migrations will be executed:

```bash
php bin/console doctrine:migrations:migrate
```

The bundle's migrations are registered under the `DoctrineMigrations\ProjectTemplateBundle` namespace and will create the required tables (`users`, `mails`, `queue_job_logs`).

### 8. Load Master Data

```bash
php bin/console bundle:master-data:load
```

## Usage

### Authentication Endpoints

The bundle provides the following authentication endpoints:

#### Register
```bash
POST /api/auth/register
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "SecurePass123!",
    "firstName": "John",
    "lastName": "Doe"
}
```

#### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "SecurePass123!"
}
```

Response:
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "user": {
        "id": 1,
        "email": "user@example.com",
        "firstName": "John",
        "lastName": "Doe"
    }
}
```

#### Forgot Password
```bash
POST /api/auth/forgot-password
Content-Type: application/json

{
    "email": "user@example.com"
}
```

#### Reset Password
```bash
POST /api/auth/reset-password
Content-Type: application/json

{
    "token": "reset-token-from-email",
    "password": "NewSecurePass123!"
}
```

### Queue and failed jobs

The bundle registers a **failed** Messenger transport. Jobs that fail after all retries are moved there. To list or retry them:

```bash
php bin/console messenger:failed:show failed
php bin/console bundle:queue:retry-failed --force
```

See [docs/messenger-failed-jobs.md](docs/messenger-failed-jobs.md) for details and cron examples.

### Protected Endpoints

Use the JWT token in the Authorization header:

```bash
GET /api/users/1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

## License

MIT

## Support

- **Issues**: [GitHub Issues](https://github.com/vanDerSangen/project-template-bundle/issues)
- **Source**: [GitHub Repository](https://github.com/vanDerSangen/project-template-bundle)

