# BSU-IACUC Portal

A web portal for managing BSU-IACUC services. Built with PHP (MVC) and MySQL.

## Requirements

- XAMPP (PHP 8 or newer, Apache, and MySQL)
- Apache `mod_rewrite` enabled (enabled by default in XAMPP)

## How to run

1. Copy this folder into `C:\xampp\htdocs\` and make sure it is named `bsu-iacuc`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. In the project folder, rename `.env.example` to `.env`. The `LOCAL_` settings are already configured for XAMPP.
4. Open `http://localhost/bsu-iacuc/portal/personnel/login` in your browser. The database and its tables will be automatically created.
5. Log in using the default staff account:
   - Username: `ccard_staff_7k2q`
   - Password: `uV$6lNmBB3zib4t0`

## After the first login

The default staff account is intended for initial setup only. Create your own staff account before using the portal.

1. Using the sidebar, navigate to `Administration` > `Accounts` and click on `Generate invite link`.
2. Open the generated link in a new tab and register for a staff account.
3. After submitting your account application, close the tab. Go back to `Administration` > `Accounts`, refresh the page, and approve your created account.
4. Open the account panel by clicking the account name (`Default` for the default staff account) at the top right of the page. Scroll down and click `Log Out`.
5. Log in using your new account and verify your email address or press `Remind me later`.
6. Navigate to `Administration` > `Accounts` and deactivate the default staff account.

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
4. Delete the file `storage\.schema_checked` to force the portal to run its database initialization check again.
5. Reload `http://localhost/bsu-iacuc/portal`.

The default staff account is only created when the database has no users yet. If the database already contains users, the account will not be created automatically.

If the problem persists, check `C:\xampp\apache\logs\error.log` for errors.

## Notes

- Email, CAPTCHA, live notifications, and file conversion require API keys in `.env`. The portal will still run without them, but these features will not work.
- The main folders are:
  - `app` contains the application logic, including controllers, models, views, and core files.
  - `portal` contains the public entry point and frontend assets, including CSS, JavaScript, and images.
  - `vendor` contains the installed libraries.
  - `storage` is created automatically to store uploaded files.
