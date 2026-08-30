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

## Period summaries

Period summaries use the same deterministic retrieval-first pipeline and return typed, cited results over an explicit period boundary:

- Evidence is selected by requested period start/end when timestamps are available.
- Observations (fact assertions) and interpretations (inference/hypothesis assertions) remain distinct.
- Output is grouped chronologically (`YYYY-MM-DD`) with an `undated` bucket when event time is missing or intentionally uncited hypotheses are present.
- Missing expected history and uncertain timing remain visible through uncertainty signals.

## Current versus prior state

Current-versus-prior state comparisons apply explicit temporal baselines and produce typed change classifications (`added`, `removed`, `persisted`) with Funes citations.
Kilgore owns baseline interpretation, evidence linkage, change classification, and uncertainty propagation; applications only choose scope/baseline and render results.

## Project timelines

Project timelines reuse retrieval-first interpretation and chronological grouping to produce layered events:

- observation layer: evidence-backed facts
- interpretation layer: inferences/hypotheses with explicit support linkage

Timeline groups carry explicit temporal semantics (`exact_date` or `undated`) with citations and unsuppressible uncertainty.

## Entity timelines

Entity timelines scope history by opaque `entityFunesRef`, traverse related entities with cited provenance, and build chronological layered events from that expanded evidence scope.
Traversal uncertainty (for missing relationship evidence or sparse edges) remains explicit and cannot be suppressed.

## Current project state explanation

Current project state explanation composes current-vs-prior comparisons and project timelines to explain *why* the project is in its present state.
It keeps facts, explicit inferences, contradiction signals, citations, and uncertainty distinct so applications only select project scope and render.

## Past decision context

Past decision context reconstructs the surrounding historical rationale for a decision from Funes provenance.
It keeps recorded rationale distinct from later inference, preserves author as account id, links identities via `funes_ref`/stack identity, and models decision-citation edges with label/locator/position semantics.
