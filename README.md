# Project Template Bundle - Development Environment

This is a standalone Symfony application for developing and testing the `project-template-bundle`.

## Structure

- `bundle/` - The actual bundle code (tracked by Git)
- Root directory - Test Symfony application (NOT tracked by Git)

## Development

The bundle is symlinked via Composer's path repository, so changes in `bundle/` are immediately reflected.

### Running the Application

Use the Docker environment from the main `project-template` project:

```bash
# From the project-template directory
docker-compose -f docker-compose-dev.yaml up -d

# Install dependencies in this project
docker exec -it symfony_api bash
cd /var/www/project-template-bundle
composer install
```

### Testing the Bundle

Make changes in `bundle/src/` and test them in this Symfony application.

## Git

Only the `bundle/` directory is tracked by Git. The test application files are ignored.

