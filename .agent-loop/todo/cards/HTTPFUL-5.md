# HTTPFUL-5: ClientTest::testHttpClient depends on a live www.google.com response

- **Ticket:** HTTPFUL-5
- **Lane:** BACKLOG
- **Status:** Selected
- **Domain:** tests
- **Created:** 2026-08-15T17:01:24+00:00
- **Updated:** 2026-08-15T17:01:24+00:00
- **Summary:** The test asserts a 405 from a real POST to http://www.google.com, so it fails behind any proxy or offline (400 here). Pre-existing; blocks a clean 'composer test' gate.
- **Next:** Point the assertion at the local test server or mark it skipped when offline.
- **Priority:** 5
- **Format version:** 1

## Agent Task Brief
Replace the live www.google.com assertion in tests/Httpful/ClientTest.php with the repository's own local test web server (already started by tests/bootstrap.php on localhost:1349), or skip the test when the endpoint is unreachable. Found while closing HTTPFUL-1: this is the only reason 'vendor/bin/phpunit' is not green, which forced HTTPFUL-1 to close with an accepted risk instead of a passed validation gate.
