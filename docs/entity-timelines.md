# Entity Timelines (K18 slice)

Kilgore builds entity timelines across historical records and traversed relationships using opaque entity `funes_ref` scope.

## Contract

- Input scope uses `entityFunesRef` (never filesystem path identity).
- Relationship traversal happens inside Kilgore and yields provenance-cited edges.
- Retrieved evidence is interpreted only after retrieval (deterministic tools-first).
- Timeline events are grouped chronologically with explicit temporal semantics.
- Observation and interpretation layers remain distinct.
- Missing relationship/history evidence remains visible in uncertainty.

## Provenance

Relationship edges and timeline events both carry Funes citations, preserving traceability from derived interpretation back to canonical history.

Path/location/SHA are contextual metadata only and never identity.
