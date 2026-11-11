# Checkout testing

This local coursework version simulates payment approval without contacting a payment processor. Use fictional card details.

| Field | Example demo input |
|---|---|
| Name on card | Alex Tan |
| Card number | Any 16 digits, e.g. 1111 2222 3333 4444 |
| Expiry | An unexpired MM/YY, e.g. 12/39 |
| Security code | Any 3 digits, e.g. 786 |

Choose seats, proceed to checkout, fill the fields and click Pay. Valid demo details confirm the booking and send the local acknowledgement. Incorrect lengths or an expired date show field errors without creating a booking. Repeated submissions of the same completed checkout do not create another booking.

Card fields are checked in memory and are not saved to the database, session, acknowledgement or application logs. Pricing and seat availability are recalculated by the booking service.
