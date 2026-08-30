# Decision Rationale Answers (K22 slice)

Kilgore answers “why did we choose this?” by reconstructing decision context from recorded historical evidence.

## Contract

- Uses retrieve-first reconstruction through `PastDecisionContextService`.
- Keeps recorded rationale (facts) distinct from later inferred rationale.
- Includes cited alternatives with Funes identity.
- Preserves decision metadata: title, source, author account id.
- Emits citation edges (`label`, `locator`, `position`) as Funes-linked provenance.
- Missing rationale/alternatives stay explicit via unsuppressible uncertainty.

Apps only ask and render; rationale reconstruction remains in Kilgore.

Path/location/SHA are contextual metadata only and never identity.
