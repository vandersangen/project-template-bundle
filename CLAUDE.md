# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Shared Symfony bundle (`vandersangen/project-template-bundle` on Packagist) consumed by
**payment-api**, **qonnecthub** and **project-template**. Provides User/Auth, Mail (queued, with
attachments), Queue (logging middleware), Cron, Payment client (talks to the payment-api),
Invoice (numbering/VAT/PDF), Tenant, SuperAdmin and Shopify modules.

## Releases & branches — IMPORTANT

- `main` is the release branch. A **git tag (e.g. `0.8.9`) publishes to Packagist immediately**,
  and consumers pin `^0.8` — every tag reaches all consumers on their next `composer update`.
- Only tag a commit after CI is green on it. Never push a tag without explicit approval from Lars.
- Update `CHANGELOG.md` with every release.
- Consumers deploy from different branches: payment-api from `development`, qonnecthub from `develop`.

## Migrations — EXTRA IMPORTANT here

Bundle migrations run in **every consumer's database** (registered automatically via
`ProjectTemplateExtension`). **Never edit or delete a merged migration** — Doctrine tracks them by
class name and already-migrated databases silently miss the change (this caused a month-long
invoicing outage in June/July 2026; see `migrations/Version20260710000000.php` for the repair).
Always add a NEW migration. CI enforces this (`migration-guard.yml`).

## Key Commands

```bash
docker compose -f docker-compose-dev.yaml up -d
docker compose -f docker-compose-dev.yaml exec php composer install
docker compose -f docker-compose-dev.yaml exec php ./vendor/bin/phpunit    # full suite
docker compose -f docker-compose-dev.yaml exec php ./vendor/bin/phpcs -s   # style (strict Squiz docblock rules!)
make ci-setup   # containers + composer + JWT keys + dev/test databases + migrations + master data
```

Note: the DB container publishes host port 3307, which may clash with other local projects; tests
only need the internal network, so an override that drops the port mapping is fine.

## Style gotchas (phpcs)

The ruleset requires: full `@param` lists once any parameter is documented, `@throws` tags,
tag comments starting with a capital and ending with a full stop, no string-literal concatenation,
and a blank line between `@param` and `@return` groups.
