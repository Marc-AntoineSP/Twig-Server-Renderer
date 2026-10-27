Review the current implementation against AGENTS.md, the current milestone,
docs/styleguide/CONTRACTS.md, SECURITY.md and ACCEPTANCE.md.
Find correctness/security/integration defects, prioritizing reproducible failures.
Pay special attention to untrusted Twig, same-origin preview execution, missing
Tailwind classes, generated CSS round-trip, controller registration, dependency
closure, unsafe archives and installer overwrites/partial commits.
Run focused checks where available. Report findings with file locations and
reproduction steps; distinguish tested failures from risks. Do not rewrite the
application during a review unless the user requested fixes.
