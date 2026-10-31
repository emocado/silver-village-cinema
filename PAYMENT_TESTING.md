# Checkout testing

The customer sees a normal card form, booking summary and a single Pay button. They do not choose a success/failure outcome. A small test-mode notice prevents confusion about real charges.

This local coursework version is not connected to a payment processor and does not charge real cards. Do not use real financial information.

| Field | Approved test payment | Declined test payment |
|---|---|---|
| Name on card | Any test name, e.g. Alex Tan | Any test name |
| Card number | 4242 4242 4242 4242 | 4000 0000 0000 0002 |
| Expiry | Any future MM/YY, e.g. 12/39 | Any future MM/YY |
| Security code | 123 | 123 |

Choose seats, proceed to checkout, fill the fields and click Pay. The approved card confirms tickets and sends the local acknowledgement. The declined card shows a normal declined-payment message, creates cancelled/failed records, reserves no seats and sends no success email. Invalid or expired details show field errors without creating bookings. Repeated submissions of the same completed checkout do not create another booking.

Card fields are checked in memory and are not saved to the database, session, acknowledgement or application logs. Full card numbers and security codes are cleared when a validation response is rendered. Pricing and seat availability are recalculated and checked by the booking service.

For an actual public deployment, replace includes/payment.php with a real hosted provider integration and verified server-side payment callbacks. The current local adapter accepts only the two test numbers above; it rejects other card numbers.
