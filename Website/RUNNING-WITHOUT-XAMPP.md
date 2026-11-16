# Running PrimeCare without XAMPP / WAMP

The Android app and the website both talk to the PHP + database backend, so *something* has to run
PHP and a database. You just don't have to use XAMPP/WAMP's control panel. Pick one:

## Option 1 — Docker (recommended: one command, nothing to configure)

1. Install **Docker Desktop** and start it.
2. In the project folder:
   ```
   docker compose up -d --build
   docker compose exec web php database/seed-staff.php --demo
   ```
   The first command builds PHP/Apache + MariaDB and imports `schema.sql`, `staff_migration.sql` and
   `invites_migration.sql` automatically. The second adds demo doctors, patients and logins.
3. Check it works: <http://localhost:8080/primecarewebsite/api/staff/ping.php>
4. In the app, **Settings → Server address**:
   - Emulator: `http://10.0.2.2:8080/primecarewebsite/`
   - Real phone on the same Wi-Fi: `http://<your-PC-IP>:8080/primecarewebsite/`
5. Stop with `docker compose down` (add `-v` to also wipe the database).

## Option 2 — PHP's built-in server (no Apache)

Needs PHP 8 (with `pdo_mysql`) and a MySQL/MariaDB you already have running (e.g. the one from XAMPP/WAMP,
or MariaDB installed on its own). Import the three SQL files in `database/` once, then:

```
php -S 0.0.0.0:8000
```
run from the project folder. App server address: `http://10.0.2.2:8000/` (emulator) or
`http://<your-PC-IP>:8000/` (phone). Database settings default to `root` / no password on `localhost`;
override with the `PRIMECARE_DB_HOST`, `PRIMECARE_DB_NAME`, `PRIMECARE_DB_USER`, `PRIMECARE_DB_PASS`
environment variables.

## Option 3 — Host it online

Upload the folder to any PHP 8 + MySQL host, set the `PRIMECARE_DB_*` environment variables (or edit
`api/config/db.php`), import the SQL files, and use `https://your-domain/` as the server address.
This also gives you a URL that works for your demo video without your PC being on.

## First admin account

* With demo data: `admin@primecare.com` / `admin123` (**change it**).
* Without demo data: `php database/create-invite.php you@clinic.co.za ADMIN` prints a one-time code;
  use it in the app under *Create a staff account*. (With Docker: `docker compose exec web php database/create-invite.php ...`.)

## Using the app on a real phone

The phone must be able to reach the PC that runs the backend.

1. **Same Wi-Fi.** Connect the phone and the PC to the same network (a phone hotspot also works).
2. **Find the PC's IP.** Windows: `ipconfig` > "IPv4 Address" (e.g. `192.168.1.50`). Mac/Linux: `ifconfig` / `ip a`.
3. **Allow it through the firewall** (Windows, run as Administrator):
   `netsh advfirewall firewall add rule name="PrimeCare" dir=in action=allow protocol=TCP localport=8080`
4. **Test from the phone's browser:** `http://192.168.1.50:8080/primecarewebsite/api/staff/ping.php`
   You should see "PrimeCare staff API is reachable." If not, it's the network or firewall, not the app.
5. **Install the app** (see the app README / chat instructions): USB debugging from Android Studio, or an APK file.
6. In the app: **Server settings** (login screen) > enter `http://192.168.1.50:8080/primecarewebsite/` > Test connection.

Notes: `10.0.2.2` only works on the emulator, never on a phone. The PC's IP can change when you reconnect to
Wi-Fi, so re-check it if the app says "Could not reach the server". For a demo that doesn't depend on your PC or
Wi-Fi, host the backend online (Option 3) and use its `https://` address.
