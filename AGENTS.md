# httpful agent instructions

## agent-loop workflow router

This repository uses [`voku/agent-loop`](https://github.com/voku/agent-loop) for governed
coding work. Keep this file a small router: the detailed procedures live in the projected
skills under `.claude/skills/` and in the CLI help.

### The CLI is **not** at `vendor/bin/agent-loop` here

httpful supports PHP `^8.0`, agent-loop requires PHP `^8.3`. Adding agent-loop to the root
`require-dev` would break `composer install` for every contributor and CI job on PHP 8.0-8.2,
so it lives in an isolated tool project instead:

```bash
composer install --working-dir=tools/agent-loop   # once, needs PHP >= 8.3
tools/agent-loop/vendor/bin/agent-loop help
```

The projected skills and subagents were written for the default layout and say
`vendor/bin/agent-loop`. In this repository read every such command as
`tools/agent-loop/vendor/bin/agent-loop`. `composer agent-loop -- <args>` is the shorthand.

### Working on a task

For non-trivial coding, review, debugging, or repository-maintenance work:

- Use the installed `agent-loop-*` skills and `agent-recall-consumer` when their descriptions
  match the task. Do not recreate their procedures as ad-hoc prompt text.
- Use `map` for bounded source discovery before broad reads. Build or refresh the map and the
  search index *before* `workflow approve`, because approval compiles Recall from evidence
  that already exists.
- When a task has a durable Contract or task id, inspect
  `workflow status <task-id> --format=json` before mutation and continue from persisted state
  rather than conversational memory.
- Run the validation declared by the governed task and preserve exact command/output evidence.
  Do not turn workflow completion into a merged, shipped, or released claim without exact Git
  candidate evidence.

Workflow state lives below `.agent-loop/`; the Kanban board is `.agent-loop/todo/board.md`.

### Findings about the workflow itself

Friction in the agent-loop workflow is tracked in
[`docs/agents/agent-loop-findings.md`](docs/agents/agent-loop-findings.md) rather than fixed
ad hoc mid-task.

## Project conventions

- Source is PSR-0 under `src/Httpful/`, tests are PSR-4 under `tests/Httpful/`.
- Validation gates: `vendor/bin/phpunit` and `vendor/bin/phpstan analyse`.
