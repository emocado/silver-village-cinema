# CinéPop — IE4727 Theme 5

Cinema booking project using HTML, external CSS, vanilla JavaScript, PHP and MySQL. The additional seat-selection version uses Vue 3 and Tailwind; modern dependencies and JSON serialization are confined to enhanced/.

## Run locally

1. Install XAMPP and place this project at `C:\xampp\htdocs\silver-village-cinema`.
2. Start Apache, MySQL and Mercury.
3. For a **new database only**, import sql/schema.sql through phpMyAdmin. This script rebuilds the demo tables and seed data; do not re-import it over bookings you want to keep.
4. For an **existing installation**, run only sql/upgrade_20261030.sql. It adds the enquiries table without resetting users or bookings.
5. This computer's Apache uses port 8000: open http://localhost:8000/silver-village-cinema/ . If your Apache uses port 80, omit :8000.
6. Follow THUNDERBIRD_SETUP.md to receive the local acknowledgements in Thunderbird.

The original customer demo credentials are user@silvervillage.local / Pass1234! and sarah@silvervillage.local / Pass1234!. The administrator demo is admin@silvervillage.local / Admin123!. Existing installations keep their current user records and passwords; the update does not reset them.

## Booking flow

Browse movies → view future screening schedule → select seats → shortlist in preference order → select one or more choices → review → enter payment details and pay.

- Success confirms the selected bookings, removes only those wishlist choices and submits one local acknowledgement containing all selected booking details.
- Failure saves cancelled/failed records, reserves no seats, keeps wishlist choices and sends no success email.
- Direct checkout uses the same validation and transaction service.
- Availability is rechecked while screening rows are locked. Concurrent requests cannot sell the same seat through these booking handlers.
- Repeated payment submissions use a consumed session checkout token and cannot create another transaction in the same active session.
- My Bookings supports cancellation of future bookings and resending a paid booking acknowledgement.

## Base page inventory

There are **11 rendered base pages: one homepage plus ten content pages**, including administration:

| Page | Purpose |
|---|---|
| index.php | Homepage |
| movies.php | Catalog and search |
| movie_details.php | Movie information and screening table |
| account.php | Sign-in and registration forms on one page |
| booking.php | Standard seat selection and wishlist editing |
| wishlist.php | Ranked choices, availability and final selection |
| confirmation.php | Booking review and payment result |
| my_bookings.php | Booking history, cancellation and email resend |
| feedback.php | Customer reviews |
| about.php | Cinema information and saved contact enquiries |
| admin/index.php | Dashboard, movies, screenings, booking report and enquiries on one page |

login.php, register.php, mailbox.php and the old admin URLs are compatibility redirects and contain no content views. logout.php is a POST action. Includes are not directly accessible. The enhanced version is additional work. The static Stitch prototypes are reference-only and are blocked from HTTP access; they are not base content pages or wireframes.

## Local email

Delivery path: website → MercuryS at 127.0.0.1:25 → Admin@localhost → MercuryP at port 110 → Thunderbird.

Every customer's demo acknowledgement goes to the configured local mailbox, as required for the course. No external mail delivery, PHP mail() fallback, direct Mercury queue writes, public email viewer or fabricated delivery-success status is used. See THUNDERBIRD_SETUP.md for manual configuration and troubleshooting.

## Validation and protections

Prepared statements; hashed passwords; session regeneration; local-only post-login redirects; CSRF validation on POST handlers; ownership checks; server-side seat/rank/date validation; atomic multi-booking writes; screening locks; protected archives/includes; server-side contact validation and persistence; administrative validation and deletion guards that preserve booking history.

Testing evidence is in TEST_RESULTS.md. Project design/report notes are in implementation_plan.md. These materials help prepare the report; they do not establish authorship, replace the students' final report, or guarantee a grade.

## Customer checkout

Checkout uses a normal card form and a single Pay button. The local adapter determines approval or decline; customers do not choose an outcome. Use the test details in PAYMENT_TESTING.md. This version rejects real cards and does not collect payment.


## Visual design

The user selected ODEON.co.uk as the visual reference. The original project implementation now uses a shared navy/blue theme, white booking controls, poster-led movie listings and locally hosted Nunito Sans / Barlow Condensed fonts. Existing pages, film artwork, copy, forms and booking workflows are retained. A responsive homepage quick-book bar links to a film's schedule and optionally filters its screening date. No ODEON templates, logos, offers or external social links were imported.

Design tokens and guidance are in DESIGN.md and css/cinema-theme.css. The font licenses ship in fonts/. The original-artwork hashes and provenance are recorded in protected development metadata under .impeccable/. The reference prototypes remain local design artifacts rather than public base pages.
