---
paths:
  - '{scripts/git_helpers.ps1,scripts/git_branch_helpers.ps1,tests/Unit/GitBranchWorkflowTest.php}'
---

# Scripts Unit 2

## Bound release PHP tests with sequential owned-database batches
Full local release checks discover every tests/Unit and tests/Feature *Test.php, sort paths ordinally and execute at most ten files per fresh native PHP process, sequentially with stop-on-failure/error. Keep the existing integration-group exclusion; never raise global memory limits or skip failed files. Revalidate the active candidate path, self-created local database environment, matching successful ownership receipt and uncached test config before each batch. Preserve batch file manifests/stdout/stderr on failure. Run later release checks only after PHP batches pass. Bind Process.Handle immediately after Start-Process on Windows PowerShell 5.1 so ExitCode remains available after the process exits; verify native success and failure codes and literal file arguments.
