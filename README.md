# MiniTally: month-end billing website

A Laravel 12 + MySQL website (installable as an app on phone, tablet and computer) for month-end billing:

1. **Daily entries** with the office's columns: S.NO, ISSUED DATE, PARTY NAME, REFERENCE NO., INVOICE NUMBER, IR/NA, DESTINATION, CERTIFICATE, AMOUNT, TRANSACTION AMOUNT, MAIL BY. Add, edit and list them on the site, or import an Excel/CSV sheet.
2. **Month-end bills**: one bill per exporter per month, as a PDF in the Rahul Chandak invoice format (S.No, Party name, Type = IR/NA, Certificate, Country = Destination, Invoice no., Amount, total in words, bank details).
3. **Email every bill** with the PDF attached, and **send it on WhatsApp** (free WhatsApp link, or fully automatic through the WhatsApp Business Cloud API).
4. **Bank statement upload** (PNB and most Indian banks, XLS/XLSX/CSV): credits are matched to exporters by name or exact amount, you confirm, payments are saved and bills are marked paid or part paid.
5. **Outstanding reminders** by email (with pending bills attached) and WhatsApp, by hand or automatically on set days.

6. **Exporters**: name, email ID, mobile number, address, DGFT user ID and password, ICEGATE ID and password, DSC validity date and DSC status (Available / Delivered / Expired). Passwords are stored encrypted with the site's `APP_KEY` and stay hidden until you tap **Show**. The Exporters list warns you 30 days before a DSC runs out and marks expired ones in red.

Keep a copy of `APP_KEY` from `.env`: the saved DGFT and ICEGATE passwords can only be read with the same key.

Who a bill goes to is a setting: **Party name** (default), **Mail by**, or **chosen on each entry**.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `gd`, `zip`, `xml`, `intl` (optional)
- MySQL 8 (or MariaDB 10.6+)
- Composer
- Node.js is **not** needed on the server: the built CSS/JS is committed in `public/build`.

## Install on a server (e.g. Hostinger)

1. **Create the database.** In hPanel go to *Databases → MySQL Databases*, create a database and a user, and note the database name, user name and password. (On a VPS: `CREATE DATABASE minitally CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`)
2. **Upload the code**, for example with `git clone https://github.com/aasthasrkwebapp-code/minitally.git` over SSH, into a folder next to `public_html` (e.g. `~/minitally`).
3. **Point the domain at the `public` folder.** Either set the website's document root to `minitally/public`, or replace `public_html` with a link: `rm -rf ~/public_html && ln -s ~/minitally/public ~/public_html` (back up `public_html` first).
4. **Install and configure:**
   ```bash
   cd ~/minitally
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
   Edit `.env`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 1
   - Email and WhatsApp keys: see **Keys to fill in** below.
   - Login user: `ADMIN_EMAIL` and `ADMIN_PASSWORD`
5. **Create the tables and the login user:**
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   Then remove `ADMIN_PASSWORD` from `.env`; the password is stored encrypted in the database and can be changed in **Settings**.
6. **Cron job** (for automatic month-end bills and reminders). In hPanel *Advanced → Cron Jobs*, every minute:
   ```
   cd ~/minitally && php artisan schedule:run >> /dev/null 2>&1
   ```
   Then switch the automation on in **Settings → Automatic sending**.
7. **HTTPS** must be on (free SSL in hPanel). The "Install app" button and offline page only work over HTTPS.

Optional dummy data (8 exporters, 50 entries for this month): `php artisan db:seed --class=DemoSeeder`. Sample Excel files: `php artisan billing:samples` writes them to `storage/app/samples`, and both are downloadable from the Entries and Bank pages.

## Monthly routine

1. Enter entries every day (or import the day's sheet).
2. Month end: **Month-end billing → Make bills → Email all unsent → WhatsApp all**.
3. When payments come in: **Bank statement → upload → check matches → Save payments**.
4. **Outstanding → Email reminder to all**, and WhatsApp the ones that matter.

## Keys to fill in (`.env`)

Email and WhatsApp are already wired up. Only these values are needed; after editing `.env` on the server run `php artisan config:cache`.

**Email (Gmail SMTP)**

| Key | Value |
|---|---|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `smtp.gmail.com` |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | the Gmail address, e.g. `exporteradda@gmail.com` |
| `MAIL_PASSWORD` | the 16-letter **App Password** (Google Account → Security → 2-Step Verification on → App passwords), no spaces |
| `MAIL_FROM_ADDRESS` | same Gmail address |
| `MAIL_FROM_NAME` | `"RAHUL CHANDAK"` |

Check it with **Settings → Send a test email**.

**WhatsApp Business Cloud API (Meta)**

| Key | Where to find it |
|---|---|
| `WHATSAPP_TOKEN` | Meta Business Suite → Business settings → System users → generate a **permanent** token with `whatsapp_business_messaging` permission |
| `WHATSAPP_PHONE_NUMBER_ID` | developers.facebook.com → your app → WhatsApp → API Setup → *Phone number ID* (not the phone number itself) |
| `WHATSAPP_BILL_TEMPLATE` | `monthly_bill` (or the name you approved) |
| `WHATSAPP_REMINDER_TEMPLATE` | `payment_reminder` |
| `WHATSAPP_TEMPLATE_LANGUAGE` | the template's language code, e.g. `en` or `en_US` |

Create these two message templates in WhatsApp Manager (category *Utility*) and wait for approval:

- `monthly_bill`: Header = **Document**. Body, for example: `Dear {{1}}, your bill {{2}} for {{3}} is Rs. {{4}}. The PDF is attached. Thank you.`
- `payment_reminder`: Body, for example: `Dear {{1}}, Rs. {{2}} is outstanding against your account. Pending bills: {{3}}. Kindly clear it at the earliest.`

`APP_URL` must be your real https address, because Meta downloads the bill PDF from it.

As soon as `WHATSAPP_TOKEN` and `WHATSAPP_PHONE_NUMBER_ID` are set, bills and reminders are sent on WhatsApp automatically (with the PDF). Without them, the site opens WhatsApp with the message and bill link ready and you press send. Settings → WhatsApp can force the link mode. If Meta refuses a message, the reason is shown on screen.

## Database file

Instead of `php artisan migrate --seed` you can import `minitally-database.sql` (tables + login user) in phpMyAdmin: select your database → Import → choose the file → Go. `minitally-database-with-50-demo-entries.sql` also has 8 dummy exporters and 50 entries for trying things out.

## Development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build        # rebuild public/build after changing CSS/JS or Blade classes, and commit it
php artisan serve
php artisan test     # 33 tests: amounts in words, Excel/bank parsing, billing, email, WhatsApp, bank matching, reminders, scheduler
```
