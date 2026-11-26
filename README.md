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

## Troubleshooting

If the portal does not load or you cannot log in, check the following.

### If the portal does not load:

1. Make sure the project folder is located in `C:\xampp\htdocs\` and is named `bsu-iacuc`.
2. Open the XAMPP Control Panel and make sure Apache and MySQL are running.
3. Check that the `.env.example` file has been copied and renamed to `.env`.
4. Open `.env` and make sure the `LOCAL_` settings are configured for XAMPP.
5. Open `http://localhost/bsu-iacuc/portal` in your browser.

If the portal still does not load, check `C:\xampp\apache\logs\error.log` for errors.

### If the default staff account was not created:

If you see the error "No account found with that username or email." when logging in:

1. Make sure the `.env.example` file has been renamed to `.env`.
2. Open `.env` and check that `DEFAULT_STAFF_USERNAME` and `DEFAULT_STAFF_PASSWORD` are set correctly, with no spaces around the `=`.
3. Make sure the default staff account has not already been created with different credentials.
4. Delete the file `storage\.schema_checked`.
5. Reload `http://localhost/bsu-iacuc/portal`.

The default staff account is only created when the database has no users yet. If the database already contains users, the account will not be created automatically.

If the problem persists, check `C:\xampp\apache\logs\error.log` for errors.

## Notes

- Email, CAPTCHA, live notifications, and file conversion require API keys in `.env`. The portal will still run without them, but these features will not work.
- The main folders are:
  - `app` contains the controllers, models, views, and core files.
  - `portal` contains the public files, including the index file, CSS, JavaScript, and images.
  - `vendor` contains the installed libraries.
  - `storage` is created automatically to store uploaded files.
