# LitDecals Sandbox

WordPress storefront source for https://litdecals.com/sandbox-redesign/.

The four files under storefront/ are the installed litdecals-storefront plugin.
The initial source is preserved in commit 465eea2bf238b5b38c1473d5b71b59384e574193.

## Publishing

Changes to storefront files on main trigger the Publish LitDecals Sandbox workflow.
It validates PHP, JavaScript, Python, and header behavior, authenticates through Directory
Privacy and WordPress separately, verifies sandbox identity, deploys all four source
files with SHA-256 checksums, enables the preview, and reads back the visible header.

Credentials remain in GitHub Actions secrets:
- LITDECALS_BASIC_USER
- LITDECALS_BASIC_PASSWORD
- ITDECALS_WP_APP_PASSWORD (existing name; LITDECALS_WP_APP_PASSWORD is also supported)

The publisher targets only the existing sandbox connector. The production store is
not a deployment target. The connector retains the original and previous storefront
files in WordPress options before writing updates.

The connection/ directory contains the installed sandbox-only two-layer
authentication compatibility fix and its guarded one-time installer.

The separate installed language/label plugin is not managed by this source yet.
