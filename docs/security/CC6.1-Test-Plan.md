# OmniBuy — Sprint 4 CC6.1 Security Test Plan

**Proposed owner:** Kenneth Ly  
**Control objective:** Only authenticated and appropriately authorized users can access protected account functions and information.  
**Test environment:** Local/staging with disposable accounts; never production.

## Preconditions

- Confirm actual PHP routes, request formats, CSRF handling, and test database.
- Create two disposable accounts A and B, with distinct profile data.
- Ensure tests cannot email or alter real users.
- Capture git commit SHA, build identifier, date, and tester.

## Test cases and acceptance criteria

| Test ID | Procedure | Expected result | Method | Evidence |
|---|---|---|---|---|
| SEC-01 | Inspect registration/login password handling and sanitized DB record | Strong one-way hashes; no plaintext storage | Code review | Redacted code reference and review notes |
| SEC-02 | Submit wrong password for known test account | No authenticated session; protected route denied | Automated HTTP + manual | PHPUnit output and response status |
| SEC-03 | Open profile as guest | Denied or redirected to login without private data | Automated HTTP | PHPUnit output |
| SEC-04 | Login as A, logout, reuse A session | Protected page no longer accessible | Automated HTTP | PHPUnit output |
| SEC-05 | Inspect session cookie flags | HttpOnly; Secure for HTTPS; appropriate SameSite | Manual header review | Sanitized header checklist |
| SEC-06 | Request reset, try expired and reused tokens | Invalid tokens rejected, no password change | Controlled manual/integration | Redacted test log |
| SEC-07 | Login as A, attempt B profile read/update | No unauthorized read/update; B data unchanged | Two-account manual/integration | Redacted before/after results |
| SEC-08 | Change privacy preference; view as B | Server enforces configured visibility | Two-account manual/integration | Redacted response results |

## Decision rules

- **Pass:** Expected behavior verified, with reproducible evidence.
- **Fail:** Actual behavior violates the criterion; open a Jira Bug, fix, and retest.
- **Not assessed:** Missing endpoint, fixture, implementation, or test execution. Never report as Pass.
- **Blocked:** Environment/test dependency prevents evaluation; document blocker.
- **Overall:** CC6.1 assessment can be marked satisfied for this scoped review only after every mandatory test passes and evidence is linked; otherwise mark open gaps or not assessed.

## Execution and remediation

1. Baseline code review and identify routes.
2. Execute automated tests; track skipped/incomplete separately.
3. Run manual reset/profile/privacy tests using disposable accounts.
4. Log confirmed gaps in Jira with severity and reproduction steps.
5. Fix existing application code; submit PR and coordinate CI with Emily.
6. Rerun failing tests and update the evidence matrix.

## Evidence hygiene

Store screenshots, CI logs, commit/PR URLs, and redacted HTTP statuses. Never capture real passwords, full session cookies, reset tokens, personal data, or secrets.
