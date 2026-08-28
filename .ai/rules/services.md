---
paths:
  - app/Services/AbaLocalDocumentTextExtractor.php
---

# Services

## Do not install document extractors at runtime
Mammoth is optional and may be invoked only when already installed. Never call npx --yes or another runtime network installer for an uploaded document; keep the local XML/PhpWord fallbacks deterministic.
