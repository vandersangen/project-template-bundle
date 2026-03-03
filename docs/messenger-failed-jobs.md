# Messenger: failed jobs

The bundle configures a **failed** transport. When a queue job fails after the maximum number of retries (default 3), the message is moved to the **failed** transport and stored in a separate queue (Doctrine transport with `queue_name=failed`).

## Commands

- **List failed jobs**
  ```bash
  php bin/console messenger:failed:show failed
  ```

- **Retry failed jobs**
  ```bash
  # Interactive (choose per message: retry / discard / skip)
  php bin/console messenger:failed:retry failed

  # Retry all failed jobs at once
  php bin/console messenger:failed:retry failed --force
  ```

- **Bundle convenience command** (defaults transport to `failed`):
  ```bash
  php bin/console bundle:queue:retry-failed
  php bin/console bundle:queue:retry-failed --force
  ```

## Automatic retry on an interval

To retry failed jobs periodically (e.g. after a fix or temporary outage), run the retry command via cron:

```bash
# Every 5 minutes, retry all failed jobs
*/5 * * * * cd /path/to/your/app && php bin/console bundle:queue:retry-failed --force
```

Adjust the path and interval as needed.
