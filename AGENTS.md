# LitDecals Sandbox

This repository controls the WordPress storefront plugin on the sandbox only.
Keep the existing production store, sandbox authentication, customer data and catalog intact.

Edit the four storefront files in place. Do not replace the storefront with a standalone
mockup or remove existing behavior during bounded edits. Keep PHP compatible with 7.4.

GitHub Actions publishes source changes on main through the installed authenticated
sandbox connector. Use the connected GitHub tools; secrets are stored in Actions and
must never be printed or added to source. Wait for the Publish LitDecals Sandbox run
and check its logs before reporting a change as published.

The approved header keeps the Lit Decals logo, removes the visible site title and
description, and places design/findrc-parts-basher.webp flush right as a linked ad.
Logo and ad share the same displayed height; ad width must not exceed 80% of the
header canvas. Keep the selected artwork intact, without cropping or stretching.
The image is embedded in the PHP source and served through its versioned sandbox-root
image endpoint, since the connector accepts exactly four storefront files.

Do not ask the user to upload plugin ZIPs for changes managed by this repository.
