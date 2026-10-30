# OmniBuy — Sprint 4 CC6.1 Risk Assessment

**Proposed owner:** Kenneth Ly  
**Control:** CC6.1 — Logical Access Security  
**Status:** Draft; implementation not yet verified  
**Assessment environment:** To be recorded

## Scope and method

Review account registration, login, logout, password reset, authenticated profile pages, profile updates, and privacy preferences. Risks below are **hypotheses**, not confirmed findings. Confirm each with code review or controlled testing before creating a vulnerability ticket.

| ID | Potential risk | Impact | Initial priority | Verification | Potential mitigation | Finding status |
|---|---|---|---|---|---|---|
| R-01 | Weak or plaintext password storage | Account compromise | Critical | Review hashing code and sanitized test DB record | `password_hash` / `password_verify`; secure algorithm | Not assessed |
| R-02 | Invalid credentials grant access | Account takeover | High | Negative login tests | Correct server-side credential validation | Not assessed |
| R-03 | Unauthenticated access to account pages | Personal data exposure | High | Request protected routes without session | Server-side authentication guard | Not assessed |
| R-04 | Logout leaves session usable | Session reuse | High | Reuse cookie jar after logout | Destroy server session; expire cookie | Not assessed |
| R-05 | Predictable/reusable/expired reset tokens accepted | Account takeover | High | Token lifecycle test in staging | Random, expiring, single-use tokens | Not assessed |
| R-06 | Cross-account profile read/write | Personal data compromise | Critical | Two-account authorization tests | Ownership checks on every request | Not assessed |
| R-07 | Privacy preferences not enforced | Unauthorized data disclosure | High | Cross-account visibility tests | Enforce preferences on server | Not assessed |
| R-08 | Insecure session cookie configuration | Session theft | Medium | Inspect response headers | HttpOnly, Secure over HTTPS, SameSite | Not assessed |

## Risk assessment instructions

For each finding, capture affected file/endpoint, steps to reproduce, actual vs expected behavior, severity rationale, Jira bug, fix PR, retest result, and evidence link. Do not store sensitive secrets in screenshots. Coordinate adjacent ownership areas with the team.

## Approval

Group control allocation: Proposed / Pending confirmation  
Reviewer: TBD  
Review date: TBD
