# Contributing

Contributions are welcome through focused issues and pull requests.

## Before opening a pull request

1. Base the work on the latest default branch.
2. Keep each pull request limited to one logical change.
3. Do not commit `config.php`, API keys, database files, logs, or cached data.
4. Preserve the project's dependency-light approach unless a new dependency
   provides a clear and documented benefit.
5. Keep user-facing text and code comments in English.

## Validation

Run syntax checks for every PHP file:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Refresh the dashboard cache after backend changes:

```bash
sudo -u www-data php bin/refresh-dashboard.php
```

Check the dashboard at desktop and mobile widths before submitting UI changes.

## Coding style

- Use strict types in PHP files.
- Use four spaces for indentation.
- Prefer explicit, readable control flow over compressed one-line statements.
- Add return and parameter types where PHP supports them.
- Escape all user-visible or external data before rendering it as HTML.
- Keep terminology consistent with the README and interface.

## Commit messages

Use an imperative summary that describes the change, for example:

```text
Improve dashboard cache error handling
```
