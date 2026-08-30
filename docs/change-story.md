# ChangeStory Contract

`ChangeStory` is a typed interpretation snapshot produced from canonical history.

## Principles

- Funes history remains canonical and immutable.
- Kilgore snapshots are replaceable and rebuildable.
- Identity for supporting records uses opaque `funes_ref` values.
- Filesystem paths, locations, and SHAs are contextual metadata, not identity.

## Typed components

- `Comparison`
  - `comparisonLabel`: human-facing comparison meaning.
  - `labelId`: durable label identifier; labels remain ids.
  - `funesRefs`: evidence references backing the comparison.
  - `stackId`: optional stack identity when present.
- `DecisionCitation`
  - `decision`: interpreted decision statement.
  - `funesRefs`: cited evidence refs for the decision.
  - `subjectId`: optional subject identity.
- `PlanSummary`
  - `summary`: interpreted plan summary.
  - `funesRefs`: cited plan evidence refs.
  - `subjectId`: optional subject identity.
- `ResearchClaimSource`
  - `claim`: interpreted claim.
  - `funesRefs`: cited supporting evidence refs.
  - `subjectId`: optional subject identity.

The contract keeps interpretation and source evidence distinguishable: assertions may summarize, but cited evidence remains explicit and traceable.
