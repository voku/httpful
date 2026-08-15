# HTTPFUL-2: agent-loop: make projected assets work on an isolated tool-project install

- **Ticket:** HTTPFUL-2
- **Lane:** BACKLOG
- **Status:** Selected
- **Domain:** agent-tooling
- **Created:** 2026-08-15T16:59:43+00:00
- **Updated:** 2026-08-15T16:59:56+00:00
- **Summary:** Hooks and skills assume vendor/bin/agent-loop; on a tools/ install the SessionStart hook hard-fails and 144 skill commands point at a missing binary.
- **Next:** Open upstream issues against voku/agent-loop for A1-A4.
- **Priority:** 2
- **Format version:** 1

## Agent Task Brief
Probe candidate install roots (vendor/, tools/*/vendor/) in the package hooks and treat 'autoloader loaded but class missing' as not-installed; render the resolved CLI path into projected skills/subagents at sync time; sync the whole hooks/ directory so a bundle can share a helper; keep the agent-map phpstan cache under .agent-loop/map/. Details: docs/agents/agent-loop-findings.md section A.
