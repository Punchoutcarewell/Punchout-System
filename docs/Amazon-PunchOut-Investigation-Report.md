# Amazon cXML PunchOut – Investigation & Fix Report

**System:** Carewell PunchOut (Laravel) – https://punchout.carewellgroup.com.au
**Date:** 30 September 2026
**Issue reported by Amazon:** `RuntimeError: Illegal character "&"`. The PunchOut does not load correctly.

> No SharedSecret or other credential values appear in this report.

---

## 1. Summary

- The error is **not caused by our cXML messages.** Every cXML document we generate escapes `&` correctly, exactly once.
- The cause is **Amazon's configuration.** Both URLs Amazon configured point to **browser/HTML pages**, not to our cXML endpoints. Amazon's system tried to read our HTML as XML. HTML allows a raw `&` (e.g. in a font link), but XML does not, so Amazon's XML parser failed.
- **Fix needed on Amazon's side:** update the two URLs (see section 4).
- **On our side:** we added better diagnostics for malformed XML, a resilience fix for the order endpoint, and automated tests covering the full flow.

---

## 2. Root cause (with evidence)

Amazon's current configuration:

| Amazon setting | Currently configured | Problem |
|---|---|---|
| PunchOut Catalog URL | `https://punchout.carewellgroup.com.au/storefront` | This is the browser storefront page, not the cXML setup endpoint |
| PO Transmission URL | `https://punchout.carewellgroup.com.au/admin/punchout-preview/complete` | This is an internal admin-only preview page, not the order endpoint |

What our server returns on those URLs (reproduced locally):

| Request | Response | Contains raw `&`? |
|---|---|---|
| `POST /storefront` (cXML body) | HTTP 405, HTML error page | Not XML |
| `GET /storefront` | HTTP 302 → `/storefront/no-token` (HTML page) | **Yes**: font link `...800&family=IBM+Plex+Mono...&display=swap` |
| `POST /admin/punchout-preview/complete` | HTTP 302 → `/admin/login` (HTML page) | **Yes**: font link `...700&display=swap` and inline JavaScript `&&` |

- The error text `RuntimeError: Illegal character "&"` matches the format of the error Ruby's REXML XML parser raises when it finds a raw `&` in text it is parsing.
- **Conclusion:** the invalid `&` came from our **HTML pages** (outbound). Those pages were returned because the configured URLs are the wrong ones. It did not come from Amazon's request, and it did not come from any cXML we generate.

---

## 3. Verification of our cXML handling

- **Outbound cXML** (PunchOutSetupResponse, PunchOutOrderMessage, OrderRequest response) is built with DOM APIs (text nodes and attributes). Every `&` is escaped once, with no double-encoding.
  - `Carewell Health & Medical` → `Carewell Health &amp; Medical`, which parses back to the original.
  - `https://example.com/return?a=1&b=2` → `a=1&amp;b=2`, which parses back to the original URL.
- **Inbound malformed XML** (e.g. a raw `&` from a buyer) is rejected in a controlled way:
  - The response is a valid cXML `400 Malformed request.`
  - No HTML page, no stack trace, no internal details.
- **Credentials:** a wrong SharedSecret returns a cXML `401 Unauthorized.` The secret is never logged. Stored payloads have the SharedSecret replaced with `[REDACTED]`.

---

## 4. Correct URLs for Amazon

| Amazon setting | Correct value |
|---|---|
| **PunchOut Catalog / Setup URL** | `https://punchout.carewellgroup.com.au/api/punchout/setup` |
| **PO Transmission URL** | `https://punchout.carewellgroup.com.au/api/punchout/order` |
| Protocol | cXML |
| Supplier Domain / Identity / SharedSecret | Unchanged, as already configured |

**How the flow works:**

1. Amazon's server POSTs a cXML `PunchOutSetupRequest` to `/api/punchout/setup`.
2. We validate the credentials, create a session, and reply with a cXML `PunchOutSetupResponse` containing a `StartPage` URL.
3. Amazon sends the user's browser to that StartPage URL. The user lands in the Carewell storefront.
4. When the user checks out, the cart is returned to Amazon as a `PunchOutOrderMessage`. It is posted to the **BrowserFormPost URL** that Amazon supplies in its setup request.
5. After approval, Amazon's server POSTs the purchase order (cXML `OrderRequest`) to `/api/punchout/order`. We reply with a cXML status.

The **BrowserFormPost URL** (cart return) and the **PO Transmission URL** (order delivery) are different things:
- **BrowserFormPost:** Amazon sends it to us in every setup request. It is required.
- **PO Transmission URL:** our endpoint for receiving purchase orders.

---

## 5. Buyer Identity / Buyer Domain (Amazon's question)

Our system authenticates each request using the cXML header:

| cXML header | Represents | Checked by us |
|---|---|---|
| `From` (domain + identity) | **Amazon (buyer)** | Yes, must exactly match our configured credential |
| `To` (domain + identity) | **Carewell (supplier)** | Yes, must exactly match our configured credential |
| `Sender` SharedSecret | Shared password | Yes, must match |

- Our application **does not require a fixed Amazon value**. It requires the `From` domain and identity that Amazon **sends** to exactly match what we configure in our admin panel.
- **What we need from Amazon:** the exact **Buyer (From) Domain** (e.g. `DUNS` or `NetworkID`) and **Buyer (From) Identity** they will send in PunchOutSetupRequest and OrderRequest messages.
- Once Amazon confirms these, Carewell will enter them on the production credential in admin.
- **Note:** until this is done, Amazon's requests may be rejected with `401 Unauthorized` even after the URL fix. The existing credential may still hold earlier test values from the Coupa setup.

---

## 6. Changes made to the application

### Files changed

| File | Status | Change |
|---|---|---|
| `app/Modules/Punchout/Cxml/XmlSecurity.php` | Modified | When incoming XML is malformed, the log now records the error position (libxml code, line, column). Any quoted content is masked so no secret can leak. The response to the caller is unchanged. |
| `app/Modules/Punchout/Http/Controllers/OrderRequestController.php` | Modified | If the first log write fails, the order endpoint now still answers with a valid cXML error instead of an HTML server-error page. |
| `tests/Unit/Punchout/XmlSecurityTest.php` | Modified | Added tests for error location and secret-free diagnostics. |
| `tests/Unit/Punchout/XmlEscapingTest.php` | New | Tests that `&` is escaped exactly once in URLs, text and attributes, and parses back correctly. |
| `tests/Feature/Punchout/PunchoutProtocolFlowTest.php` | New | End-to-end tests of the full PunchOut flow (listed in section 7). |
| `docs/Amazon-PunchOut-Investigation-Report.md` | New | This report. |

No database, infrastructure, Nginx, SSL or credential changes were made.

Example of the new diagnostic log entry for a malformed request:

```
The request body is not well-formed XML (libxml 68 at line 30, column 58: xmlParseEntityRef: no name).
```

---

## 7. Test results

New tests (all passing):

```
PASS  Tests\Feature\Punchout\PunchoutProtocolFlowTest
✓ runs the full PunchOutSetupRequest -> StartPage -> storefront handshake
✓ stores an escaped BrowserFormPost URL decoded, exactly as the buyer meant it
✓ answers a PunchOutSetupRequest carrying a raw "&" with a controlled cXML 400 and a located, secret-free diagnostic
✓ answers an OrderRequest carrying a raw "&" with a controlled cXML 400 and creates no PurchaseOrder
✓ rejects a wrong shared secret on both endpoints without ever logging either secret
✓ accepts a valid OrderRequest at /api/punchout/order, the production PO transmission endpoint
✓ still answers an OrderRequest with cXML, not an HTML 500, when the inbound log write itself fails
✓ keeps /admin/punchout-preview/complete an admin-only preview, never a buyer endpoint
✓ is not a cXML endpoint at /storefront, which only serves the browser

PASS  Tests\Unit\Punchout\XmlEscapingTest
✓ escapes an ampersand in the StartPage URL exactly once and round-trips it
✓ escapes an ampersand in a Status text attribute exactly once and round-trips it
✓ escapes ampersands in PunchOutOrderMessage text and identities exactly once and round-trips them
✓ decodes correctly escaped ampersands in an inbound PunchOutSetupRequest

PASS  Tests\Unit\Punchout\XmlSecurityTest
✓ loads a well-formed document with a real cXML DOCTYPE
✓ rejects an empty body
✓ rejects XML that is not well-formed
✓ reports where a raw unescaped ampersand broke the document
✓ never quotes payload content, which could be a secret, in its diagnostics
✓ never expands a local-file external entity

Tests: 19 passed (116 assertions)
```

Full test suite: **298 passed, 5 failed.**
- The 5 failures are unrelated Excel import/export tests. They fail because a spreadsheet library is missing from the local development environment.
- They fail the same way without our changes.
- Code style check (Pint): passed.

---

## 8. Information still needed from Amazon

1. **Buyer (From) Domain and Identity** that Amazon will send in PunchOutSetupRequest and OrderRequest messages.
2. Confirmation that the **Supplier (To) Domain and Identity** Amazon sends match Carewell's configured supplier identity.
3. **How the storefront is displayed:** inside an iframe, a pop-up, or a full-page redirect? If an iframe, which exact Amazon domain(s) host it? We must allow those domains in our security settings, otherwise the page is blocked from displaying.
4. Whether Amazon is testing in **test or production** mode (the `deploymentMode` value).
5. A **sample OrderRequest** from Amazon, to confirm our order parsing against a real Amazon purchase order.

---

## 9. Next steps

| # | Action | Owner |
|---|---|---|
| 1 | Update PunchOut Catalog URL to `/api/punchout/setup` and PO Transmission URL to `/api/punchout/order` | Amazon |
| 2 | Provide Buyer (From) Domain and Identity, and the other items in section 8 | Amazon |
| 3 | Configure Amazon's From Domain and Identity on the production credential | Carewell |
| 4 | If Amazon uses an iframe, add Amazon's domain(s) to the storefront frame permission setting | Carewell |
| 5 | Deploy the code changes (no database changes). Clear the application cache and reload PHP. | Carewell |
| 6 | Amazon retests the PunchOut; Carewell checks the PunchOut logs | Both |

---

## 10. Other notes / risks

- Buyer-facing screens still say "Coupa" (e.g. "Transfer cart to Coupa"). Amazon users will see this wording. It is cosmetic, and a small follow-up change can make it neutral.
- A legacy Coupa-only shortcut places the shared secret in a URL. Amazon should **not** use it; the standard cXML POST flow above is the correct method.
- The code changes are backward-compatible. Existing Coupa behaviour is unchanged.
