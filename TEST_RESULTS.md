# Verification results — 30 October 2026

The corrected website was installed at the original XAMPP project folder after creating an original-file backup. Existing user records, passwords and bookings were preserved. An additive migration created the enquiries table; the demo schema was not re-imported over live records.

## Automated application checks

**37 of 37 checks passed.** Tests ran against a copied site and isolated temporary database using the included schema. PHP served real HTTP requests; simulated SMTP responses exercised email submission and failure handling. A race test used two independent PHP/MySQL connections. Temporary databases were removed afterward.

| ID | Input / scenario | Expected and actual result | Method | Result |
|---|---|---|---|---|
| TC1 | Open account.php | Both registration and sign-in forms render | HTTP + SQL / SMTP assertions | Pass |
| TC2 | Invalid email/phone/DOB/password | No new account; validation errors returned | HTTP + SQL / SMTP assertions | Pass |
| TC3 | Valid six-field registration | Account inserted and authenticated | HTTP + SQL / SMTP assertions | Pass |
| TC4 | Seat list Z999,Z999 | Rejected; wishlist unchanged | HTTP + SQL / SMTP assertions | Pass |
| TC5 | Preference rank -9 | Rejected; wishlist unchanged | HTTP + SQL / SMTP assertions | Pass |
| TC6 | Edit wishlist item 1 to D5,D6 | Original item updated; item count unchanged | HTTP + SQL / SMTP assertions | Pass |
| TC7 | Move item 2 up | Unique contiguous ranks remain | HTTP + SQL / SMTP assertions | Pass |
| TC8 | GET wishlist.php?action=clear | No deletion | HTTP + SQL / SMTP assertions | Pass |
| TC9 | POST wishlist mutation without token | HTTP 403; no mutation | HTTP + SQL / SMTP assertions | Pass |
| TC10 | Invalid name, card number, expiry and security code | Field errors; no booking created | HTTP + SQL / SMTP assertions | Pass |
| TC11 | Confirm wishlist choices 1 and 2 | Two bookings; unselected choice retained | HTTP + SQL / SMTP assertions | Pass |
| TC12 | Tamper stored estimate to $1 | Server recalculates $29 from current hall tiers | HTTP + SQL / SMTP assertions | Pass |
| TC13 | Successful multi-booking SMTP submission | One message to Admin@localhost with correct selected details | HTTP + SQL / SMTP assertions | Pass |
| TC14 | Repeat same successful payment POST | No extra booking or mail | HTTP + SQL / SMTP assertions | Pass |
| TC15 | Direct checkout for an already sold seat | Rejected; one confirmed owner remains | HTTP + SQL / SMTP assertions | Pass |
| TC16 | Simulated failed payment | Cancelled/failed record; wishlist retained | HTTP + SQL / SMTP assertions | Pass |
| TC17 | Inspect failure seat rows and SMTP calls | No reserved seats or success email | HTTP + SQL / SMTP assertions | Pass |
| TC18 | Submit unavailable stale wishlist entry | Rejected during confirmation | HTTP + SQL / SMTP assertions | Pass |
| TC19 | Invalid contact email and one-character message | Rejected; no enquiry inserted | HTTP + SQL / SMTP assertions | Pass |
| TC20 | Valid contact message | Persisted and acknowledged | HTTP + SQL / SMTP assertions | Pass |
| TC21 | Anonymous access to old mailbox URL | Account redirect; no receipts exposed | HTTP + SQL / SMTP assertions | Pass |
| TC22 | SMTP RCPT rejection | Visible failure instead of a success claim | HTTP + SQL / SMTP assertions | Pass |
| TC23 | Resend after simulated SMTP recovery | Successful local submission reported | HTTP + SQL / SMTP assertions | Pass |
| TC24 | Cancel own future booking | Status cancelled; seat no longer owned by a confirmed booking | HTTP + SQL / SMTP assertions | Pass |
| TC25 | Open admin page | Movie/screening controls and stored enquiry visible together | HTTP + SQL / SMTP assertions | Pass |
| TC26 | Delete screening with booking history | Refused; screening retained | HTTP + SQL / SMTP assertions | Pass |
| TC27 | Schedule in 2020 | Rejected as past | HTTP + SQL / SMTP assertions | Pass |
| TC28 | Edit movie with valid values | Fields persisted | HTTP + SQL / SMTP assertions | Pass |
| TC29 | Movie duration 0 | Rejected; original duration retained | HTTP + SQL / SMTP assertions | Pass |
| TC30 | Future screening in free hall slot | Inserted and acknowledged | HTTP + SQL / SMTP assertions | Pass |
| TC31 | Same hall, overlapping time | Rejected; no second screening | HTTP + SQL / SMTP assertions | Pass |
| TC32 | Delete unused new screening | Deleted successfully | HTTP + SQL / SMTP assertions | Pass |
| TC33 | Two shortlisted choices share screening and seats | Rejected before confirmation | HTTP + SQL / SMTP assertions | Pass |
| TC34 | Two customers simultaneously request B9 | One succeeds, one rejected; exactly one confirmed seat owner | HTTP + SQL / SMTP assertions + independent concurrent workers | Pass |
| TC35 | Misconfigure mail recipient to an external domain | Blocked before SMTP transmission | HTTP + SQL / SMTP assertions | Pass |
| TC36 | Open homepage quick booking | Working film and optional date controls are present | HTTP + SQL / SMTP assertions | Pass |
| TC37 | Choose a film and screening date | Film schedule filters to the chosen date; all-dates navigation remains available | HTTP + SQL / SMTP assertions | Pass |

## Syntax and live-installation checks

All 27 PHP files passed PHP syntax checks. Apache configuration passed its syntax check.

**16 of 16 live HTTP checks passed:**

| Route | Expected HTTP status | Actual | Result |
|---|---|---|---|
| index.php | 200 | 200 | Pass |
| movies.php?status=coming_soon | 200 | 200 | Pass |
| movie_details.php?id=1 | 200 | 200 | Pass |
| account.php | 200 | 200 | Pass |
| about.php | 200 | 200 | Pass |
| sent_emails/booking_SVC-2026-TEST88.html | 403 | 403 | Pass |
| includes/mail_config.php | 403 | 403 | Pass |
| sql/schema.sql | 403 | 403 | Pass |
| stitch_prototypes/index.html | 403 | 403 | Pass |
| README.md | 403 | 403 | Pass |
| css/cinema-theme.css | 200 | 200 | Pass |
| css/fonts.css | 200 | 200 | Pass |
| fonts/nunito-sans-400.ttf | 200 | 200 | Pass |
| fonts/nunito-sans-700.ttf | 200 | 200 | Pass |
| fonts/barlow-condensed-700.ttf | 200 | 200 | Pass |
| .impeccable/design.json | 403 | 403 | Pass |

## Browser checks

- Chrome: ODEON-inspired navy/blue restyling with locally hosted fonts, responsive navigation, poster-led listings and a functional white quick-book bar. Representative homepage, catalog, seat map, wishlist and checkout layouts were captured at desktop and phone sizes. All existing content and film art were retained. No live customer payment was submitted during visual verification.
- In-app browser: combined account form visually inspected. At a 390px emulated viewport, the account layout computed to one column with no horizontal document overflow. This is browser emulation, not physical-device or full screen-reader certification.
- The existing Vue/Tailwind seat picker remains separate from the base application and posts to the same protected booking handler.
- Additional phone-width DOM checks found no page-level horizontal overflow on About, Feedback, My Bookings, movie details and the enhanced seat picker. Admin styling is applied by the same theme and its functional tests passed; its final rendered design was not independently visually verified.
- An independent visual reviewer returned ship at the reviewed scope after fresh mobile captures confirmed lazy-loaded posters. This is a scoped design review, not certification of every possible state.

## Real Mercury delivery

The real local SMTP server responded on port 25, and POP3 responded on port 110. The updated mail function successfully submitted a labelled test message, SVC-LOCAL-TEST-20261030, to Admin@localhost. The test message was found in Mercury's Admin mailbox. This test did not create a live cinema booking.

## Remaining manual evidence

- Set/enter the local mailbox POP3 password yourself and retrieve the acknowledgement in Thunderbird; capture the Inbox and message details for the report.
- Add your own final-report screenshots and explanations; authorship and course-reuse rules remain the students' responsibility.
- Complete any lecturer-required device/browser, accessibility and load scenarios beyond the targeted checks documented here.
- These results describe the executed cases, not an assurance that no software defect can remain or a prediction of the project grade.
