# PrimeCare Medical Centre

**Clinic Queue and Patient Record Management System** — developed by **Tech Titans**

| | |
|---|---|
| **GitHub repository** |  |
| **Azure DevOps repository** | https://dev.azure.com/ST10457058/_git/Prime%20Care%20Medical%20Center |
| **Website demo video** | https://youtu.be/e0TeuPSbWnw |
| **Mobile app demo video** | https://youtu.be/KIvWMZuWXw8|
| **Release notes** | [RELEASE_NOTES.md](RELEASE_NOTES.md) |
| **Signed APK** | `TODO: link to the signed APK in GitHub Releases` |

## Team

| Name | Role |
| Buhle Dlamini | Back-End Developer |
| Unathi Morake | Front-End Developer |

---

## 1. Purpose of the system

PrimeCare Medical Centre relied on manual, paper-based processes for patient registration, appointment scheduling and medical records. This caused long waiting times, duplicate or misplaced records and poor coordination between reception and doctors.

PrimeCare replaces this with two connected products that share one backend and one database:

- **Patient website** — patients book and manage appointments, see their records and prescriptions, and request repeat prescriptions.
- **Staff Android app** — receptionists/administrators and doctors run the clinic day: check patients in, manage the queue, assign doctors, record consultations and issue prescriptions.

The aim is to reduce manual administration, improve patient flow and give both patients and staff a faster, more reliable experience.

## 2. Features

### Patient website (HTML, CSS, JavaScript, PHP)
- Information about the centre, services and doctors
- Patient registration, login, password recovery and reset
- Booking, rescheduling and cancelling appointments
- Upcoming appointments and appointment history
- Medical records and prescriptions, with repeat-prescription requests
- Profile management, notifications and a contact form

### Staff Android app (Kotlin) — role-based

**Administrator / reception**
- Dashboard with the day's statistics
- Today's appointments with search and status filters
- Check-in of booked patients and registration of walk-in patients
- Live queue with auto-refresh
- Assigning patients to available doctors
- **Inviting new staff** with one-time invitation codes (new in this release)

**Doctor**
- Dashboard with assigned patients
- Starting and saving consultations (notes, diagnosis)
- Issuing prescriptions
- Scheduling follow-up appointments
- Approving or declining repeat-prescription requests

**Both roles**
- Secure login (email + password, or Google sign-in), profile editing, password change
- Configurable server address and a built-in help / user guide


## 4. Design considerations

### One design system across website and app
The website's design tokens (`assets/css/style.css`) are mirrored in the app (`res/values/colors.xml`), so patients and staff experience one product: the same primary blue (`#0F62C4`), neutral greys, green/amber/red status colours, 16 dp rounded cards and clear status pills. Colour is never the only signal; every status also has a text label.

### Role-based design
Staff land on a different dashboard depending on their role (administrator or doctor), and only see the actions their role needs. The server enforces this independently of the app, so hiding a button is never the only protection.

### Usability
- Large touch targets (52 dp buttons) and one primary action per screen
- Clear, plain-language error messages instead of technical ones
- Forms validate **as the user types** and again on submit, showing all problems at once
- Pull-to-refresh and auto-refresh keep the queue current
- A built-in help screen explains each workflow

### Three-tier architecture
- **Presentation:** patient website and staff Android app
- **Business logic:** PHP REST-style JSON API (`api/staff/...` for the app, `api/...` for the website)
- **Data:** MySQL database (`primecare_db`)

The app has no data of its own. Every action is an authenticated request to the API, so the website and the app always show the same information.

### Security and privacy (POPIA)
- Passwords are stored only as bcrypt hashes (PHP `password_hash`), never as plain text
- Staff sessions use random tokens that expire after 12 hours; the app keeps its token in `EncryptedSharedPreferences`
- Login and registration are rate-limited per IP address to slow down guessing
- SQL uses prepared statements throughout
- Role checks are made on the server for every staff endpoint
- Staff registration uses one-time invitation codes (see below)

### Staff registration: one-time invitation codes
The prototype used a single shared clinic code for registration. A shared code can be reused by anyone who learns it, cannot be revoked and does not identify who issued it. It was replaced with per-person invitation codes:

| Property | Effect |
|---|---|
| Created by an administrator in the app | Only authorised staff can invite people |
| Tied to one email address and one role | A leaked code is useless to anyone else; users cannot choose their own role |
| Single use | The code is destroyed when the account is created |
| Expires after 48 hours | Limits the time window for misuse |
| Random 10-character code (about 10^15 combinations, no confusing characters like 0/O/1/I) | Cannot be guessed |
| Stored only as a keyed hash | A database leak does not reveal usable codes |
| Locked after 5 wrong attempts | Blocks brute-force guessing |
| Same error message for every failure | Does not reveal which emails have invitations |

The first administrator of a fresh install is created from the command line: `php database/create-invite.php you@clinic.co.za ADMIN`.

### Input validation (app and server)
The same rules are checked on the phone and again on the server: names (letters, spaces, hyphens, apostrophes), email format and length, passwords of 8–72 characters with a letter and a number, rejection of common passwords and of passwords containing the email name, and invitation-code format.

## 5. Technologies and tools

| Area | Tools |
|---|---|
| Website | HTML5, CSS3, JavaScript |
| Backend | PHP 8, MySQL / MariaDB |
| Android app | Kotlin, Android Studio, Material 3, Navigation component, Retrofit + OkHttp, Gson, AndroidX Security (EncryptedSharedPreferences), Android Credentials API (Google sign-in) |
| Local server | WAMP Server (Docker setup also provided, see `RUNNING-WITHOUT-XAMPP.md`) |
| Version control | Git, GitHub, Azure DevOps |
| Automation | GitHub Actions |

## 6. GitHub and GitHub Actions

The source code for both the website and the Android app lives in the GitHub repository above; the team also tracks work items and keeps a mirror on Azure DevOps.

**GitHub Actions** builds the app automatically (`.github/workflows/`):

| Workflow | Runs when | What it does |
|---|---|---|
| **Android CI** (`android.yml`) | every push to `main`/`master`, every pull request, or manually | Sets up JDK 17 and Gradle with caching, runs the unit tests (`testDebugUnitTest`), builds the debug APK (`assembleDebug`), then uploads the **unit-test report** and the **debug APK** as downloadable artifacts |
| **Build APK** (`build.yml`) | every push to `main`, or manually | Builds the debug APK and uploads it as an artifact |

Benefits for the team: every change is compiled on a clean machine, so "it works on my laptop" problems are found immediately; test results are visible to everyone; and a fresh installable APK is available after every push, which the QA tester uses for testing on real phones.

To download a build: **GitHub → Actions → choose a run → Artifacts**.

## 7. Running the system

### Backend (website and API) with WAMP
1. Install and start **WAMP Server**.
2. Copy the website folder into WAMP's `www` folder and name it `primecarewebsite`.
3. In phpMyAdmin, import these files into the `primecare_db` database, **in this order**:
   1. `database/schema.sql`
   2. `database/staff_migration.sql`
   3. `database/invites_migration.sql`
4. Check the database settings in `api/config/db.php` (WAMP defaults: user `root`, empty password). This file's real credentials are not committed.
5. Check the API: open `http://localhost/primecarewebsite/api/staff/ping.php`.
6. Create the first administrator: either `php database/seed-staff.php --demo` (demo data and logins) or `php database/create-invite.php you@clinic.co.za ADMIN` (prints a one-time code to use in the app's *Create a staff account* screen).

Without WAMP, see [RUNNING-WITHOUT-XAMPP.md](RUNNING-WITHOUT-XAMPP.md) (Docker or PHP's built-in server).

### Android app
1. Open the Android project in **Android Studio** and let Gradle sync (minimum Android 7.0, API 24).
2. Run it on an emulator or a USB-connected phone.
3. On the login screen open **Server settings** and enter the server address:
   - Emulator: `http://10.0.2.2/primecarewebsite/`
   - Real phone on the same Wi-Fi: `http://<your-PC-IP>/primecarewebsite/`
4. Tap **Test connection**, then log in.

More detail for phones: see [INSTALL-ON-PHONE.md](INSTALL-ON-PHONE.md).

## 8. Testing

JUnit unit tests (run with `./gradlew testDebugUnitTest`, and automatically by GitHub Actions) cover the validators, API error handling, app settings, appointment data models and formatting helpers. The QA tester also tested the system manually against the role restrictions, forms, authentication and appointment/queue flows on emulators and real devices.

## 9. Release notes

See [RELEASE_NOTES.md](RELEASE_NOTES.md) for every update since the prototype, including the new features of this version.

## 10. Publishing

`TODO: add the evidence required for publication — signed release APK, screenshots, and a screenshot of the app in the Google Play Console.`

## 11. Notes

- Real database credentials, keystores and patient data are never committed to the repository.
- The app allows plain HTTP only so it can reach a local development server. A production deployment must use HTTPS.
