# HTTPFUL-3: agent-loop: CLI ergonomics (option parsing, card create, scaffold defaults)

- **Ticket:** HTTPFUL-3
- **Lane:** BACKLOG
- **Status:** Selected
- **Domain:** agent-tooling
- **Created:** 2026-08-15T16:59:43+00:00
- **Updated:** 2026-08-15T16:59:56+00:00
- **Summary:** board vs workflow parse options differently and a wrong flag surfaces as an uncaught fatal; card create cannot set the brief that verify requires; scaffold's demo card cannot be archived.
- **Next:** Open upstream issues against voku/agent-loop and voku/agent-kanban for B1-B4.
- **Priority:** 3
- **Format version:** 1

## Agent Task Brief
Parse options identically in every namespace; catch delegated ValidationExceptions in the dispatcher and render a usage error instead of a stack trace; accept --brief on board card create; give the scaffolded board an archiveDirectory; shell-quote the values echoed in 'Next:' hints. Details: docs/agents/agent-loop-findings.md section B.
