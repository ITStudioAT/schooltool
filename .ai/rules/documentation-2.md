---
paths:
  - '{UPDATES.md,public/documentation/**}'
---

# Documentation 2

## Build the visible changelog from Docusaurus sources
UPDATES.md is not loaded by the public changelog. Its Docusaurus source project is C:/docusaurus/schooltool; synchronize docs/releases/index.md and sidebar entries, run its npm run build, then copy the generated files into public/documentation. Update HTML and hashed JavaScript together: editing only HTML is overwritten by hydration. Preserve the standalone Schülerstundenpläne pages in both languages and their custom assets; never mirror-delete the target directory. Root npm run build builds the app only, not documentation.
