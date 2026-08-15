---
name: agent-loop-discipline
description: Keep governed agent-* work resumable, map-first, deterministic in workflow state, exact in evidence, and gated by the current execution contract when L2 policy is selected. Use for agent-loop workflow, navigation, delegation, review routing, prompt controls, and guidance changes. Engineering implementation rules belong to loadable agent-skills, not this session bootstrap.
---

# Agent Loop Discipline

Keep workflow state, evidence, navigation, and human attention bounded; persisted workflow state beats conversational state. This bootstrap owns orchestration discipline, not a general engineering handbook.

## Governed Workflow

```text
PLAN -> APPROVE -> CONTEXT -> CONTRACT -> IMPLEMENT -> VALIDATE -> REVIEW -> LEARN -> VERIFY -> CLOSE
```

1. Reuse the stable task id and inspect `workflow status` before mutation.
2. Resume its active session; never create a parallel active session.
3. Mutating work requires the approved Contract.
4. If it selects L2 policy, mutation also requires a current `ready` execution contract bound to its revision and recall bundle.
5. Scope or policy drift returns to PLAN. Changed recall invalidates an L2 contract and returns to CONTRACT.
6. Use `agent-loop-workflow` for phase-specific mechanics and evidence requirements.

Before governed mutation:

```bash
vendor/bin/agent-loop workflow status <task-id> --format=json
```

A SessionStart/SubagentStart resume hint is navigation only. Resolve multiple unfinished tasks from the request and repository context; never infer approval, contract readiness, validation, review, learning, product intent, or a next command from a hint.

Human gates are limited to Contract approval, real risk/irreversible action, and genuinely missing product intent. Reads, edits, tests, diagnostics, execution-contract construction from approved evidence, reports, and agent-owned checkpoints remain agent work.

## Prompt Controls

When the approved Contract explicitly selects the bundled L1 controls, apply them without inventing new workflow authority:

- `checkpoint-autonomy`: at the supplied explicit anchor, inspect scope, evidence, relevant validation, blockers, and the current done condition. If the checkpoint passes and no real human-only gate is present, record a concise session checkpoint and continue automatically. Never persist a synthetic human/self approval.
- `momentum`: reuse still-valid files, symbols, commands, constraints, decisions, and evidence from the work already completed. Re-check authority, freshness, repository scope, and assumptions when they may have changed instead of restarting discovery from scratch.

Both controls use the normal approved operating-prompt policy. They are L1-only and do not create an L2 execution-contract gate by themselves. Explicit L2 engineering policy, when separately selected, keeps its normal contract gate.

## Navigate Before Editing

State behavior, non-goals, owner, validation, and contract state briefly. Trace the real call path before changing shared behavior:

```bash
vendor/bin/agent-loop map query <symbol>
vendor/bin/agent-loop map related <symbol>
vendor/bin/agent-loop map file <path>
vendor/bin/agent-loop map changed --base=<ref>
```

Skip map ceremony for trivial docs or already-localized edits. Never dump map databases; map output selects bounded source reads and is not source evidence.

## L2 Execution Contract

For an approved L2 recipe, construct one project-specific L1 before mutation with exactly:

```text
Goal
Context
Constraints
Verification
Done When
```

`Verification` says how reality is measured. `Done When` says which observed result permits success.

```bash
vendor/bin/agent-loop workflow contract <task-id> \
  --status ready \
  --from <project-specific-l1.md> \
  --by <actor>
```

`missing`, `stale`, `invalid`, `blocked`, or `rejected` means IMPLEMENT is unavailable. Record BLOCKED/REJECTED with evidence and the minimum required contract change; never weaken approved policy merely to reach `ready`.

## Engineering Skill Routing

`agent-loop` owns orchestration, not reusable engineering judgment.

- Simple coding/refactoring -> load `coding-simplicity` when installed.
- PHP implementation -> load `php-best-practices` when relevant.
- Engineering review -> choose one dominant installed `code-review-*` lens and at most one evidence-backed handoff.
- Missing required skill -> name the capability gap; do not recreate its rules here.

`coding-simplicity` owns implementation search order, root-cause, safety, and verification floors. Do not inject those rules into unrelated sessions.

## Role Routing

Use narrow roles only when their verified contract fits:

- definitions/callers/tests -> `agent-loop-investigate`;
- understood 1–2 file edit -> `agent-loop-surgical-edit`;
- correctness review -> `agent-loop-code-review`;
- current-diff complexity -> `agent-loop-simplify-review`;
- repo-wide complexity -> `agent-loop-simplify-audit`;
- ambiguous, architectural, new-feature, or 3+ file work -> main governed workflow.

A narrow role never silently widens scope or bypasses the execution contract. Do not delegate trivial work merely to look agentic.

## Uncertainty Is State

Delete hedging; keep uncertainty.

- Never fabricate versions, paths, lines, commands/results, approvals, contract state, validation/review results, product intent, or runtime facts.
- Prefer the owning state/source or a safe probe.
- Otherwise state the exact unknown and whether it blocks the phase.
- Repeated equivalent failure means inspect the suspect assumption and return to CONTEXT, CONTRACT, or PLAN when necessary.

Preserve exact paths, symbols, commands, numbers, constraints, negation, errors, diffs, tests, static-analysis output, contracts, and verification artifacts. Summaries may point to evidence; they never replace it.

## Workflow Output

Update only when result, blocker, scope, decision, or phase changes:

```text
RESULT: <verified result, decision, artifact, or blocker>
STATE: <phase> <task-id> <Contract revision when known>
NEXT: <one agent-owned action or exact human gate>
```

On completion:

```text
RESULT: <what changed and why>
EVIDENCE: <exact validation results and decisive artifacts>
OMITTED: <deliberate omissions plus revisit trigger, or none>
```

Receipts compress narration, never evidence. Lead with the useful result; remove filler, repeated state, ceremonial preambles, and speculative feature tours.

## Hook Boundary

Hooks are behavioral guardrails, never correctness or security boundaries. Code, CI, trust-boundary validation, and offline installation must remain correct without them. Resume hints may expose validated unfinished task identifiers/projected state only; authoritative state comes from `workflow status`.

## Validation And Close

Run the narrowest proof first, then the gates required by the Contract and L1 Verification section. Claim a pass only after observing its result. Stop when approved behavior is satisfied and all required gates are closed; do not manufacture follow-up work.

At `ready_to_close`, task reflection is optional when deeper scrutiny is useful:

```bash
vendor/bin/agent-loop workflow reflect <task-id> --scope task
```

`RETURN_TO_REVIEW` means the completion bar was not actually met: route the concrete gap back through REVIEW/IMPLEMENT/PLAN as appropriate before closing. Otherwise optional extra depth remains optional.

After successful close, project reflection may be used when the completed work exposed a meaningful future investment:

```bash
vendor/bin/agent-loop workflow reflect <task-id> --scope project
```

Report at most one highest-leverage direction or that nothing worthwhile emerged. Reflection is read-only, is not a close gate, does not approve learning, and does not create follow-up work automatically.

`workflow close --status done` requires any selected L2 contract to remain current and `ready`. `--accept-risk` never bypasses that boundary.
