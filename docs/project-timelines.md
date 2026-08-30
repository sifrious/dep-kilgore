# Project Timelines (K17 slice)

Kilgore builds project timelines from Funes history using explicit time semantics and citation-backed interpretation layers.

## Contract

- Timeline queries use explicit period boundaries.
- Deterministic retrieval runs before interpretation.
- Event selection and grouping are chronological.
- Observations and interpretations are separate typed layers.
- Every factual timeline event remains citation-linked to Funes refs.
- Missing expected history remains visible as uncertainty.

## Temporal semantics

- `exact_date`: evidence has an in-period timestamp and is grouped by `YYYY-MM-DD`.
- `undated`: evidence time is missing or interpretation has no supporting refs.

Path/location/SHA are context only and never identity.
