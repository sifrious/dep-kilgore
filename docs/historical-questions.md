# Historical Questions (K01-K06 slice)

Kilgore answers historical questions through a strict retrieve-then-interpret pipeline.

## Execution order

1. Retrieve structured evidence from Funes (`funes_ref` identities).
2. Refuse interpretation if sufficient evidence is unavailable.
3. Interpret using only the retrieved evidence set.
4. Emit typed answer data with traceable refs.

Model prose never runs ahead of deterministic retrieval.

## Claim typing

Answer claims are explicit and typed:

- `assertionType = fact` requires one or more cited Funes refs.
- `assertionType = hypothesis` remains visibly distinct from fact and may optionally include supporting refs.

Each claim also carries `ResearchClaimKind` as epistemic status:

- `fact`
- `opinion`
- `synthesis`
- `dissent`
- `implication`

These values represent interpretation meaning, not copied persistence tables.

## Uncertainty and completeness

Completeness and uncertainty are first-class output structures and cannot be suppressed:

- Missing expected evidence from retrieval remains visible in the result.
- Missing-history signals are preserved alongside confidence.
- Confidence does not replace or hide uncertainty.

Path, filesystem location, and SHA are contextual metadata, not identity.
