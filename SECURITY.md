# Security Policy

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability involving credentials,
authentication bypass, arbitrary file access, or remote code execution.

Report the problem privately to the repository owner and include:

- The affected version or commit.
- A concise description of the issue.
- Reproduction steps or a proof of concept.
- The expected security impact.
- Any proposed mitigation.

## Supported versions

Security fixes are applied to the latest version of the project.

## Deployment guidance

- Never commit `config.php` or a Jellyfin API key.
- Limit file permissions for the Playback Reporting database and media paths.
- Place the dashboard behind authentication or a trusted reverse proxy when it
  is reachable outside a trusted network.
- Keep PHP, the web server, Jellyfin, and installed extensions updated.
