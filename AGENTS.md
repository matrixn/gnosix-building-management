# GNOSIX project conventions

- Product and business settings belong in platform administration and the database, not in `.env`. Add validated controls to the appropriate settings tab and use the settings service at runtime.
- Keep infrastructure connection details and bootstrap secrets (database, application key, deployment URL, initial admin credentials and passkey origin) separate from product settings. Do not introduce new `GNOSIX_*` environment switches for product behavior.
- Existing `GNOSIX_*` values are imported once by a migration; they must never override settings saved in administration afterward.
- Platform administrators use their own model, guard and cookie. Customer identities must never grant access to global settings.
- Keep SMTP centralized, protect settings mutations with recent administrator authentication, and never render or flash SMTP passwords.
- Validate changes with the relevant PHPUnit suite, a frontend build when assets change, and targeted browser checks for UI changes. Update the user documentation and release archive when deployment behavior changes.
- Keep README.md as the delivery checklist for all work: completed functionality uses `[x]`; planned or incomplete functionality uses `[ ]`. Update it with every subsequent task and never mark a demonstration or architecture-only module as completed operational functionality.
- Organization industry profiles and property/building types are independent. Resolve tenant membership and capability authorization server-side; use the central profile registry rather than industry checks in controllers or views. Profile changes must not delete domain data.
