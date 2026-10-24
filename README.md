# MiniTally: month-end billing website

A Laravel 12 + MySQL website (installable as an app on phone, tablet and computer) for month-end billing:

1. **Daily entries** with the office's columns: S.NO, ISSUED DATE, PARTY NAME, REFERENCE NO., INVOICE NUMBER, IR/NA, DESTINATION, CERTIFICATE, AMOUNT, TRANSACTION AMOUNT, MAIL BY. Add, edit and list them on the site, or import an Excel/CSV sheet.
2. **Month-end bills**: one bill per client per month, as a PDF in the Rahul Chandak invoice format (S.No, Party name, Type = IR/NA, Certificate, Country = Destination, Invoice no., Amount, total in words, bank details).
3. **Email every bill** with the PDF attached, and **send it on WhatsApp** (free WhatsApp link, or fully automatic through the WhatsApp Business Cloud API).
4. **Bank statement upload** (PNB and most Indian banks, XLS/XLSX/CSV): credits are matched to clients by name or exact amount, you confirm, payments are saved and bills are marked paid or part paid.
5. **Outstanding reminders** by email (with pending bills attached) and WhatsApp, by hand or automatically on set days.

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
   - Mail: for Gmail, turn on 2-step verification on the Gmail account, create an **App Password** (Google Account → Security → App passwords) and put it in `MAIL_PASSWORD`. Keep `MAIL_USERNAME` and `MAIL_FROM_ADDRESS` as that Gmail address.
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

Optional dummy data (8 clients, 50 entries for this month): `php artisan db:seed --class=DemoSeeder`. Sample Excel files: `php artisan billing:samples` writes them to `storage/app/samples`, and both are downloadable from the Entries and Bank pages.

## Monthly routine

1. Enter entries every day (or import the day's sheet).
2. Month end: **Month-end billing → Make bills → Email all unsent → WhatsApp all**.
3. When payments come in: **Bank statement → upload → check matches → Save payments**.
4. **Outstanding → Email reminder to all**, and WhatsApp the ones that matter.

## WhatsApp

- **Link mode (default, free):** each Send button opens WhatsApp (phone or WhatsApp Web) with the message and the bill link typed in. You press send.
- **Automatic mode:** needs a WhatsApp Business Cloud API number from Meta and two approved message templates:
  - `monthly_bill`: header = Document, body with 4 variables: `{{1}}` client, `{{2}}` bill no., `{{3}}` month, `{{4}}` amount.
  - `payment_reminder`: body with 3 variables: `{{1}}` client, `{{2}}` amount due, `{{3}}` pending bills.
  Put `WHATSAPP_TOKEN` and `WHATSAPP_PHONE_NUMBER_ID` in `.env`, then pick "WhatsApp Business API" in Settings.

## Development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build        # rebuild public/build after changing CSS/JS or Blade classes, and commit it
php artisan serve
php artisan test     # 29 tests: amounts in words, Excel/bank parsing, billing, email, WhatsApp, bank matching, reminders, scheduler
```
