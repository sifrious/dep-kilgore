# Past Decision Context (K20 slice)

Kilgore reconstructs context around a past decision from provenance-bearing Funes history.

## Contract

- Scope uses opaque decision `funes_ref` identity (never filesystem path identity).
- Retrieval runs before interpretation.
- Recorded rationale (historical fact) is distinct from later inference.
- Decision metadata includes title, source, and author account id.
- Linked identities use `funes_ref` and optional stack identity.
- Decision citation edges keep `label`, `locator`, and `position` as Funes-linked citations, not raw URL identity.
- Completeness and uncertainty remain first-class and unsuppressible.

## Output meaning

- `recordedRationales`: factual rationale directly grounded in cited historical evidence.
- `inferredRationales`: interpretation layered on top of evidence, visibly distinct from fact.
- `citationEdges`: provenance edges back to Funes references.

Path/location/SHA are contextual metadata only and never identity.
