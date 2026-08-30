# Current Versus Prior State (K09 slice)

Kilgore compares current state with prior state using explicit temporal baselines and cited Funes evidence.

## Contract

- Baseline and current timestamps are explicit inputs.
- Retrieval runs before interpretation for each baseline.
- Comparison outputs remain typed and citation-linked.
- Observations and interpretations remain distinct.
- Missing expected history and uncertainty remain visible.

## Classification

State comparisons classify each statement as:

- `added`: present in current state only
- `removed`: present in prior state only
- `persisted`: present in both baseline and current states

`ChangeStory` `Comparison` is preserved with Funes refs as identity. Path/location/SHA are contextual metadata only and never identity.
