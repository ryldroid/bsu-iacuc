# BSU-IACUC Portal

A web portal for managing BSU-IACUC services. Built with PHP (MVC) and MySQL.

## Requirements

- XAMPP (PHP 8 or newer, Apache, and MySQL)
- Apache `mod_rewrite` enabled (enabled by default in XAMPP)

## How to run

1. Copy this folder into `C:\xampp\htdocs\` and rename it to `bsu-iacuc`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. In the project folder, copy `.env.example` and rename the copy to `.env`.
4. Open `.env`. The `LOCAL_` settings are already configured for XAMPP.
5. Open `http://localhost/bsu-iacuc/portal` in your browser. The database and tables will be created automatically on first access.
6. Log in at `http://localhost/bsu-iacuc/portal/personnel/login` using the default staff account:
   - Username: `ccard_staff_7k2q`
   - Password: `uV$6lNmBB3zib4t0`

## After the first login

The default staff account is intended for initial setup only. Create your own staff account before using the portal.

1. Go to the Accounts page and generate a registration link for staff.
2. Register your own staff account using that link.
3. Log in with your new account and deactivate the default staff account.

Staff can also create registration links for reviewers. Researchers can register on their own.

## Notes

- Email, CAPTCHA, live notifications, and file conversion require API keys in `.env`. The portal will still run without them, but these features will not work.
- The main folders are:
  - `app` contains the controllers, models, views, and core files.
  - `portal` contains the public files, including the index file, CSS, JavaScript, and images.
  - `vendor` contains the installed libraries.
  - `storage` is created automatically to store uploaded files.
