# HTTPFUL-1: Support the QUERY HTTP method (RFC 10008)

- **Ticket:** HTTPFUL-1
- **Lane:** VERIFY
- **Status:** In Progress
- **Domain:** http-core
- **Created:** 2026-08-15T16:50:32+00:00
- **Updated:** 2026-08-15T17:01:24+00:00
- **Summary:** Add the safe, idempotent, body-carrying QUERY method to Httpful\Http, Request and Client.
- **Next:** Resolve the remaining PR review findings, rerun the current validation, and merge only after the current head is review-clean.
- **Validation:** vendor/bin/phpunit && vendor/bin/phpstan analyse
- **Priority:** 1
- **Claim:** Claude (agent-loop dogfood)|claimed=2026-08-15T16:52:40+00:00|expires=-|rev=2363ff56927bdcf3a9648d9fa4ffeeae30c4f815e618effa32fa0cfa8530b3c2
- **Format version:** 1

## Agent Task Brief
Add QUERY (RFC 10008) as a first-class HTTP method: Http::QUERY constant, correct classification as safe+idempotent, and Request::query()/Client::query() helpers that send a request body like POST does.
