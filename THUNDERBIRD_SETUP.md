# Booking acknowledgements in Thunderbird

The connection is: **website → Mercury SMTP → local Admin mailbox → Thunderbird POP3**.

The updated website sends acknowledgements only to **Admin@localhost**. Your website login email is kept as account information; it does not cause delivery to Gmail, Outlook or any external mailbox. You do not need to change a customer's website email to Admin@localhost.

## 1. Start the local services

1. Open XAMPP Control Panel.
2. Start **Apache**, **MySQL**, and **Mercury**.
3. Open Mercury using its **Admin** button if its window is not already visible.
4. In Mercury, check that **MercuryS (SMTP server)** and **MercuryP (POP3 server)** are running. The expected ports are **25** and **110**. These are separate from your website's Apache port **8000**.

Do not configure an external SMTP relay or Gmail connection. This project uses the mail server on the same computer.

## 2. Prepare your local mailbox

1. In the Mercury window, open **Configuration → Manage local users**.
2. Select the existing **Admin** user and open its settings. If it is absent, add the local user **Admin** first.
3. Set a **POP3 password** yourself and save the user settings. Keep it private. This is a Mercury mailbox password, separate from the cinema website password.
4. Confirm Mercury's local domain is **localhost** under **Configuration → Mercury core module → Local domains**. The supplied installation already lists localhost in MERCURY.INI.

The website does not need the POP3 password. It submits mail to the local SMTP service; Thunderbird uses the password to read the mailbox.

## 3. Add the account in Thunderbird

Install Thunderbird from [its official website](https://www.thunderbird.net/) if needed.

1. Open Thunderbird and choose **Account Settings → Account Actions → Add Mail Account**, or **New Account → Email** if your version uses the newer account setup screen.
2. Enter your display name and email **Admin@localhost**. Enter the POP3 password you set in Mercury.
3. Choose **Manual Configuration / Configure manually** rather than accepting an automatically discovered provider.
4. Enter these values:

| Setting | Incoming | Outgoing |
|---|---|---|
| Protocol | POP3 | SMTP |
| Server / hostname | 127.0.0.1 | 127.0.0.1 |
| Port | 110 | 25 |
| Connection security | None | None |
| Authentication | Normal password | No authentication |
| Username | Admin | Leave blank when authentication is disabled |

These settings match the default local XAMPP/Mercury setup inspected on this computer. If you intentionally configured SMTP authentication in Mercury, match that setting in Thunderbird instead. No encryption is used for this same-computer demonstration; do not use these settings for a remote mail server.

5. Choose **Re-test**, then finish adding the account. Any account-setup acknowledgement must be completed by you.
6. Under **Account Settings → Server Settings**, enable **Leave messages on server** for convenient repeated project demonstrations. Avoid automatic deletion if you want Mercury to retain the demonstration messages.
7. Open **Admin@localhost → Inbox**, then choose **Get Messages**. Enter the Mercury POP3 password if prompted.

Mozilla documents the manual-configuration process in [Manual Account Configuration](https://support.mozilla.org/en-US/kb/manual-account-configuration). Menu wording can vary by Thunderbird version.

## 4. Test it with a real booking

1. Keep Apache, MySQL and Mercury running.
2. Open [the local website](http://localhost:8000/silver-village-cinema/).
3. Sign in with your existing cinema account.
4. Choose a **future** screening, select available seats, and either add them to your wishlist or proceed directly to checkout.
5. On the payment page, enter the approved test card from PAYMENT_TESTING.md and click **Pay**.
6. The result should say that Mercury accepted the acknowledgement. It also displays the local mailbox address.
7. In Thunderbird, click **Get Messages** and open the newest message with subject **Booking acknowledgement SVC-… - CinéPop**.
8. Check the customer name, movie, booking reference, showtime, hall, seats and total. A multi-booking checkout includes each selected booking in the same email.
9. Demonstrate a declined payment with the declined test card from PAYMENT_TESTING.md separately: the booking is recorded as cancelled / failed, no seats are reserved, the wishlist choices remain, and **no successful-booking email is sent**.

If Mercury was stopped during a successful booking, the booking remains saved. Start Mercury, open **My Bookings**, and click **Resend email** for that booking. Each manual resend intentionally creates another acknowledgement message.

## Troubleshooting

| Problem | Check |
|---|---|
| Website says Mercury is unavailable | Mercury must be running with MercuryS on port 25. Starting Apache/MySQL alone is insufficient. |
| Mercury rejects delivery | Confirm the local Admin user and localhost domain exist. The website intentionally does not fall back to an external address or pretend delivery succeeded. |
| Thunderbird cannot connect | Incoming server must be 127.0.0.1:110 and MercuryP must be running. Do not use Apache's port 8000. |
| Thunderbird rejects the password | Username is Admin, not the full email. Use the POP3 password from Mercury's local-user settings, not your cinema password. |
| Website says accepted, but Inbox is empty | Click Get Messages, confirm the selected account is Admin@localhost, and inspect Mercury's core/SMTP windows for queue or local delivery errors. SMTP acceptance is not proof that Thunderbird has retrieved it. |
| Emails appear twice | The updated code uses one SMTP submission. An intentional Resend email creates another copy. Old messages generated by the previous implementation may already exist. |
| Gmail/Outlook receives nothing | Expected: the project deliberately delivers only to the local Admin mailbox. |

## Implementation details

- Local-only SMTP settings are in `includes/mail_config.php`.
- Sender and recipient default to Admin@localhost; SMTP host is restricted to 127.0.0.1.
- To use a different local Mercury user, create it yourself, then set recipient in the configuration to that user's `@localhost` address and use the corresponding username in Thunderbird.
- SMTP replies and timeouts are checked. The application distinguishes saved bookings from mail-service failure.
- The retired web mailbox redirects to My Bookings. Archived messages in sent_emails are blocked from direct HTTP access; new acknowledgement delivery does not rely on that folder.

Passwords were not read, created or changed during the website repair. Actual retrieval in your Thunderbird Inbox must be completed after you configure its local account.

## Verified on this installation

On 30 October 2026, Mercury was started and responded on SMTP port 25 and POP3 port 110. A clearly labelled test acknowledgement, reference **SVC-LOCAL-TEST-20261030**, was submitted through the updated mail function and found in the local Admin mailbox. No customer booking was created for this test. After adding the account in Thunderbird, use Get Messages to retrieve it. Thunderbird login/retrieval has not yet been tested because its mailbox password is for you to configure and enter.
