# Kit validation report

Validated on 26 October 2026 in the authoring environment.

## Passed

- Python fixture checker: 15 JSON documents parsed; three package manifests checked
  against the included schema keyword subset; 18 payload hashes and byte counts matched.
- Props defaults/examples, theme data and embedded round-trip metadata, controller
  path/identifier mapping, literal Twig includes, composition references and dependency
  closure checks passed.
- Unsafe-path rejection examples and required document/milestone presence checks passed.
- Node 24.22.1 syntax check passed for the example Stimulus controller. This does not
  import the Stimulus package or exercise DOM behaviour.
- PyYAML parsed the reference Compose file and found app, db and node services.
- GNU Make dry-run expanded build, dependency installation, CSS build and startup targets.
- The final archive was checked for ZIP integrity and its content against SHA256SUMS.

## Not run

Docker and PHP were not installed in this authoring environment. Consequently, no image
tag pull/digest resolution, image build, FrankenPHP startup, Composer resolution,
Symfony command, Twig compilation/render, actual Tailwind/esbuild bundle, browser isolation,
full external JSON Schema validator or consumer integration test was run.

The independent production checks remain explicit gates in M00–M08. The included Python
checker validates only schema keywords used by the fixtures; it is not a production
import validator, an archive security implementation or proof of sandbox safety.

## Reproduce available checks

    python3 agent-kit/tools/check_kit.py
    node --check agent-kit/examples/packages/alert/assets/controllers/catalogue/awui/alert_controller.js
    make -n -f agent-kit/reference/Makefile build install assets up

After M00 adapts the references into the target repository, run docker compose config,
build/startup and the milestone-specific PHP/browser checks described in the plan.
