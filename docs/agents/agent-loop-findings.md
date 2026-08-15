# agent-loop workflow findings

Findings about the **workflow tooling itself**, collected while dogfooding
[`voku/agent-loop`](https://github.com/voku/agent-loop) `0.16.3` on HTTPFUL-1
(*Support the QUERY HTTP method, RFC 10008*) in this repository.

These are deliberately **not** fixed inline. Each one was hit during a real governed task, and
each is written so it can be reproduced without re-running the task. Follow-up work is tracked
on the board as HTTPFUL-2, HTTPFUL-3 and HTTPFUL-4.

Environment: PHP 8.4.19, Composer 2.8.12, agent-loop 0.16.3 installed as an isolated tool
project under `tools/agent-loop/` (httpful supports PHP `^8.0`, agent-loop requires `^8.3`).

---

## A. Install-layout portability

The package README recommends an isolated tool project when a host repository's PHP constraint
conflicts with a tool's - that is exactly httpful's situation - but the projected assets assume
the default `vendor/` layout.

### A1. Projected Claude hooks break on an isolated tool-project install

`.claude/hooks/context.php` resolves its runtime as
`dirname(__DIR__, 2) . '/vendor/autoload.php'`. httpful *has* that file, for its own
dependencies, and it knows nothing about `voku\AgentLoop\*`. The `is_file()` branch therefore
succeeds, the "no installed dependencies" fallback never runs, and the hook dies:

```
$ php .claude/hooks/context.php --event=SessionStart
agent-loop Claude context hook failed: Class "voku\AgentLoop\AgentGuidance\AgentDisciplineHook" not found
```

A hard error on every `SessionStart`, caused by optional tooling.

**Suggested fix:** probe the candidate install roots (`vendor/`, `tools/*/vendor/`) and treat
"autoloader loaded but class still missing" as the not-installed case rather than as success.

*Worked around here* by the host-owned bundle in `docs/agents/claude-hooks/`, which is the
documented extension point.

### A2. Projected skills hardcode `vendor/bin/agent-loop` (144 occurrences)

Every projected skill and subagent tells the agent to run `vendor/bin/agent-loop ...`. In this
repository the binary is at `tools/agent-loop/vendor/bin/agent-loop`, so an agent following the
skills verbatim gets "command not found" and falls back to ad-hoc behaviour - precisely what
the skills exist to prevent.

**Suggested fix:** render the resolved CLI path into the assets at sync time (the installer
already knows it), or have the skills reference a stable repo-local shim.

### A3. `init sync-hooks` silently drops helper files a bundle depends on

A host bundle may only contain files named as commands in `hooks.json`. Adding
`hooks/runtime.php` beside the two hooks and requiring it from both gives:

```
[OK] validate hooks: hooks.json and 2 hook file(s) valid
[OK] sync hooks: synced 2 hook file(s) into /home/user/httpful/.claude
```

and then, at runtime:

```
require_once(.claude/hooks/runtime.php): Failed to open stream: No such file or directory
```

Both the validator and the sync report success for a bundle that cannot execute. Sharing one
helper between two hooks is an obvious thing to want; today the only option is to duplicate the
code in every hook file, which is what this repository now does.

**Suggested fix:** sync the whole `hooks/` directory (or follow `require`s), and make
`init validate --kind=hooks` actually lint each hook instead of only checking that command
paths sit inside the client directory.

### A4. agent-map writes a 16 MB cache into the legacy `.agent-map/` root

The upgrade guide says `.agent-map/ -> .agent-loop/map/` and to delete the old directory after
migrating. `map build` nevertheless creates `/.agent-map/phpstan-cache/` (16 MB here) in the
repository root - untracked, not covered by the `.gitattributes`/ignore advice that
`init scaffold` gives, and immediate noise in `git status` on a fresh setup.

**Suggested fix:** keep it under `.agent-loop/map/`, or have `init scaffold` add the ignore
entry it implies.

---

## B. CLI ergonomics

### B1. Option parsing is inconsistent between namespaces, and fails as an uncaught fatal

`workflow` accepts the space form; `board` (voku/agent-kanban) requires the equals form. The
mismatch is not reported as a usage error:

```
$ agent-loop board card claim HTTPFUL-1 --by "Claude" --move-to-doing
PHP Fatal error:  Uncaught voku\AgentKanban\Exception\ValidationException: Option --by requires
a value using --by=<value>. in .../agent-kanban/src/Cli/ArgvParser.php:54
Stack trace:
#0 .../agent-kanban/src/Cli/CliApplication.php(104): voku\AgentKanban\Cli\ArgvParser::parse()
...
```

Two problems: one CLI should parse options the same way in every namespace, and a delegated
package's `ValidationException` must be caught by the dispatcher and rendered as a normal
error. A stack trace as the response to a wrong flag is the kind of noise the discipline
guidance tells agents to avoid producing.

### B2. `board card create` cannot set a field `board verify` requires

```
$ agent-loop board card create HTTPFUL-1 --title=... --lane=READY --status=Selected
create HTTPFUL-1: (new) -> fb1e9e8d...
$ agent-loop board verify
Board verification failed.
[ERROR] missing-task-brief: Card HTTPFUL-1 is missing required field "taskBrief" for lane READY.
```

`--brief` exists on `card update` but not on `card create`, so the CLI can only create a card
that is immediately invalid.

**Suggested fix:** accept `--brief` on `card create`, or do not require `taskBrief` until a card
leaves BACKLOG.

### B3. `init scaffold` produces a board that cannot archive its own demo card

```
$ agent-loop board card archive DEMO-1
ERROR: No archiveDirectory is configured for this board.
```

The first cleanup a real repository needs is blocked by the scaffold's own output, so `DEMO-1`
either stays on the board forever or is deleted by hand outside the CLI (what happened here).

**Suggested fix:** have `init scaffold` write an `archiveDirectory` into the board metadata, or
let `card archive` create the default directory on demand.

### B4. `workflow plan` prints a "Next:" command that cannot be pasted

```
Next:
  agent-loop workflow approve HTTPFUL-1 --by Claude (agent-loop dogfood)
```

Unquoted - pasting it is a shell syntax error. The hint should shell-quote the values it echoes.

---

## C. Guidance and reporting quality

### C1. Released 0.16.3 lacks the `init status` sections its own router documents

The main-branch `AGENTS.md` router tells the agent to read the `Activation:` and `Next:`
sections of `init status`. Release 0.16.3 prints only *Source paths*, *Agent aliases*, *Target
manifests* and *Stale managed entries*. A fresh agent following the router looks for guidance
that does not exist and has to guess the activation commands.

### C2. Released 0.16.3 cannot project the instruction router at all

The same docs describe `init sync-instructions` and a package-managed
`agent-loop:project-instructions` block for `AGENTS.md`/`CLAUDE.md`. Neither the command nor
`docs/agents/project-instructions.md` ships in 0.16.3, so the router every other instruction
depends on has to be hand-written per repository - as it was here.

### C3. Compiled Recall repeats one navigation fact verbatim

`system.md` -> *Navigation Facts* listed
`/home/user/httpful/.agent-loop/map/php-symbols.json` four times: context budget spent on
nothing, in the document whose entire purpose is a bounded briefing. Dedupe before render.

### C4. `review blindspots` has an unclearable substring warning

```
[WARN] security_sensitive_context: Recall artifacts mention security-sensitive terms.
  - Matched markers: auth
```

The match is the substring `auth` inside `Request::hasBasicAuth`, which the map put in the
navigation evidence. Nothing in HTTPFUL-1 touches credentials, and there is no way to
acknowledge or waive the marker - so the review stays `warn` forever and the meaning of `warn`
degrades to noise.

**Suggested fix:** match whole words/symbols rather than substrings, and support a recorded
acknowledgement that clears a reviewed marker. The checkpoint mechanism already does exactly
this for `missing_review_checkpoint`.

### C5. Ranked navigation leads were all wrong for this task - and that was handled well

Every ranked lead pointed at URI query-string code (`Uri::withQuery`, `Uri::composeComponents`,
`Factory::createRequest`); none pointed at `Http.php`, where the change actually belonged. The
lexical and semantic channels both collapsed "the QUERY method" into "URI query string".

Recorded as a positive: the briefing labels these **INFERRED** and says "open the file before
treating any of them as the place to change, and do not cite a rank as evidence." The guidance
was correct and the wrong ranking cost nothing. Worth keeping as a regression case if the
ranking is ever tuned.

### C6. `workflow status` reported `ready_to_close` with failed validation evidence on record

`workflow status HTTPFUL-1` printed `Overall: ready_to_close` and
`Next: agent-loop workflow close HTTPFUL-1 --status done`, while a `failed` observation for the
declared command was already recorded. `close` then refused:

```
[FAIL] validation: validation evidence missing or not passed for: vendor/bin/phpunit (failed)
[FAIL] workflow close: gates failed; session was not closed.
```

The gate is right; the status projection is wrong, and it points the agent at a command that
cannot succeed.

**Suggested fix:** derive the validation row in `status` from the same predicate `close` uses,
and name the blocking gate in the `Next:` line.

*Checked and working as documented, for the record:* `workflow status --expect <state>` really
does exit non-zero on a mismatch (exit 1 for `--expect complete` against the actual `blocked`),
so it is safe to use as a CI assertion.

---

## D. Process gap

### D1. The workflow has no place for findings about the workflow

This document exists because there is no first-class way to record "the tooling got in the way
here". `workflow learn` records findings about *the task*; `learn finding-*` records durable
*project* learning. Dogfooding friction is neither, so it lands in an ad-hoc markdown file that
nothing validates and nothing exports upstream.

**Suggested fix:** a tooling-kind finding a host repository can accumulate and hand back to the
package.
