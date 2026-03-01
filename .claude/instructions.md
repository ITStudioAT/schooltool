## Laravel Boost (MCP) default behavior

For any Laravel/project questions, DO NOT guess. Always inspect the real project state first using Laravel Boost MCP tools and/or local project commands.

Use Boost tools by default for:

- routing (route definitions, middleware stacks, route groups)
- configuration/env/runtime state
- container bindings and service providers
- middleware registration and aliases
- database/migrations/models/policies
- auth (Sanctum, guards, policies)

When using Boost Tinker, ALWAYS force output:

- prefer `dump(<expr>)` / `var_dump(<expr>)`
- or `echo <expr>;`
  Never send bare expressions because the wrapper can return empty output.

When reporting findings, include:

- exact file path(s)
- line numbers when possible
- the exact code snippet (1–3 lines)outputs
