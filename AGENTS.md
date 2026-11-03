# LitDecals Sandbox

This repository controls the WordPress storefront plugin on the sandbox only.
Keep the existing production store, sandbox authentication, customer data and catalog intact.

Edit the four storefront files in place. Do not replace the storefront with a standalone
mockup or remove existing behavior during bounded edits. Keep PHP compatible with 7.4.

GitHub Actions publishes source changes on main through the installed authenticated
sandbox connector. Use the connected GitHub tools; secrets are stored in Actions and
must never be printed or added to source. Wait for the Publish LitDecals Sandbox run
and check its logs before reporting a change as published.

The first publication test requires the visible site-title text to read Lit Decals.
The title verification in scripts/publish-sandbox.py should be maintained as the
branding changes. Administration labels and the stored WordPress site name retain
their existing sandbox identification.

Do not ask the user to upload plugin ZIPs for changes managed by this repository.
