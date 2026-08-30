# Kilgore

> **License:** Copyright © 2026 Sifrious. All rights reserved. This is
> publicly viewable proprietary software, not open-source software. See
> [LICENSE.md](LICENSE.md).

## Why “Kilgore”?

Kilgore is named after Kilgore Trout, Kurt Vonnegut’s fictional science-fiction writer: an observer who takes scattered, ordinary details and turns them into explanations of the strange systems people live inside.

The name fits a tool whose job is not merely to retain history, but to examine it, connect it, and help explain what it means.

## What is Kilgore?

Kilgore is a Laravel package for interpreting project history. It turns records such as commits, tasks, conversations, documents, decisions, commands, and agent runs into evidence-backed answers, timelines, summaries, and working context.

## The problem

Projects accumulate history faster than people can understand it.

Important decisions become buried in conversations. Failed approaches disappear into shell history. Plans outlive the assumptions that produced them. Documentation drifts away from observed reality. After enough time passes, resuming work requires reconstructing the project from fragments spread across many kinds of records.

Search helps locate fragments, but finding a matching document is not the same as understanding what happened. A useful interpretation must retrieve relevant evidence, account for time and change, distinguish observation from inference, and admit when the available history is incomplete.

Kilgore provides the structure for doing that consistently.

## What Kilgore helps answer

Kilgore is intended to answer practical questions such as:

- What was I doing when I stopped working on this?
- What changed since I last worked here?
- Why did we make this decision?
- What approaches have already been tried?
- Where was this discussed or implemented?
- Which problems keep recurring?
- Which plans were abandoned, restarted, or superseded?
- How has an architectural idea changed over time?
- Does the current documentation agree with observed reality?
- What context would someone need to resume this work safely?

## Problems it solves

### Evidence-backed questions

Kilgore retrieves relevant historical evidence before producing an answer. Supporting records remain traceable so readers can inspect the underlying commits, conversations, tasks, documents, or other history for themselves.

### Facts, inference, and uncertainty

Historical interpretation is rarely all-or-nothing. Kilgore keeps direct evidence separate from conclusions drawn from that evidence. Facts, inferences, hypotheses, and recommendations remain distinguishable, and incomplete or conflicting history is surfaced instead of being hidden behind confident prose.

### Timelines and change

Kilgore can organize heterogeneous events into project and entity timelines, compare current state with prior state, and explain significant changes over a selected period.

### Resuming interrupted work

Kilgore can assemble recent actions, unfinished tasks, unresolved questions, relevant decisions, changed files, produced artifacts, and likely points of continuation into a concise briefing. The goal is to reduce the time spent reconstructing context before useful work can resume.

### Recurring patterns

Across enough history, isolated events become patterns. Kilgore can identify repeated failures, recurring decisions, commonly used tools and workflows, abandoned work, and relationships that cross project boundaries.

### Documentation and plan drift

Kilgore can compare claims in documentation and plans with later observable history. This makes it possible to flag material that appears stale, contradicted, abandoned, or superseded without rewriting the original record.

### Derived knowledge

Kilgore can build project vocabulary, relationships, state summaries, and knowledge graphs from historical evidence. These interpretations are useful views, not replacements for the records from which they were produced.

## Design principles

- **History remains authoritative.** Kilgore interprets records without changing what was originally observed.
- **Evidence precedes interpretation.** Answers begin with retrieval, not model intuition.
- **Claims remain traceable.** Factual statements point back to supporting history.
- **Inference is labeled.** Conclusions and hypotheses are not presented as observations.
- **Uncertainty is explicit.** Missing, stale, or conflicting evidence is part of the result.
- **Interpretations are disposable.** Derived views can be deleted and rebuilt when logic improves.
- **Multiple readings are allowed.** The same history may support different named interpretations without forcing one to become canonical.
- **Small capabilities compose.** Questions, timelines, comparisons, briefings, and pattern detection build on focused historical entity support.

## Project status

Kilgore is in early development. Its public contracts and first end-to-end capability are being defined around a narrow standard: retrieve historical evidence, produce typed and traceable assertions, report completeness, and rebuild the interpretation without altering the underlying history.

Installation and usage instructions will be added when that first supported release is available.

## Deterministic retrieval before prose

Kilgore's public contract requires deterministic, structured history retrieval before any model interpretation prose:

1. Retrieve a provenance-bearing evidence set from Funes.
2. Validate evidence sufficiency and visible gaps.
3. Interpret only against that explicit evidence set.
4. Return typed answers where facts remain citation-backed and hypotheses stay explicitly labeled.

Path, file location, and SHA are context only. They are never historical identity.

The same contract applies to period summaries: retrieve cited Funes evidence first, then produce chronological observations and interpretations with explicit uncertainty.
It also applies to current-versus-prior state comparison: retrieve baseline/current evidence first, then classify state changes with traceable citations and explicit uncertainty.
Project timelines follow the same rule: retrieve structured Funes evidence first, then emit chronological, citation-linked observation/interpretation layers with explicit temporal semantics and uncertainty.
Entity timelines extend this by scoping to opaque entity Funes refs and traversing cited relationships before rendering grouped timeline layers.
Current project state explanations compose state comparison and timeline reasoning with explicit inferences, contradiction surfacing, citations, and unsuppressible uncertainty.
Past decision context reconstruction follows the same retrieve-first rule while separating recorded rationale from later inference and preserving citation/link identity through Funes refs and stack identity.
Left-off context uses bounded history to detect the last confirmed work episode, reconstruct active work with cited evidence, rank context, and expose unresolved inferences/uncertainty for safe resumption.
Decision rationale answers explain why a decision was chosen by reusing past decision context, preserving recorded-vs-inferred rationale separation, cited alternatives, and explicit uncertainty when rationale is incomplete.
