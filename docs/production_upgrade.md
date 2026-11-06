# Production Upgrade (in place, one database user)

This upgrades production (`/var/www/jenn` on the VPS) from the July version (commit `35de301`) to the
university-data version (commit `70a3c14` and later), **in the same folder**, using **one database user**
(`jenn_user`) for all databases.

- **Time:** about 30–45 minutes.
- **Downtime:** from the moment you push `main` until Step 5 is done. Pages will error in between
  because the new code needs the new database. Do it in one sitting.
- **Rollback:** Step R at the bottom. The old database `jenn_db` is never modified.

Commands starting with `$` run on **your laptop**; `vps$` runs **on the server**.

---

## Step 1: On the server, check and back up (BEFORE pushing anything)

```bash
$ ssh you@vps
vps$ cd /var/www/jenn
vps$ git log --oneline -1        # should be 35de301 (the July version)
vps$ git status --short          # note anything listed, especially " M .env"
vps$ php -v                      # must be 8.1 or newer
vps$ php -m | grep -iE "pdo_mysql|mbstring|curl"   # all three must appear

vps$ mkdir -p ~/backups
vps$ sudo mysqldump jenn_db > ~/backups/jenn_db_before_upgrade.sql
vps$ cp .env ~/backups/env_before_upgrade       # IMPORTANT: the next pull deletes .env from the folder
vps$ sudo tar czf ~/backups/code_before_upgrade.tgz -C /var/www jenn
vps$ ls -lh ~/backups                           # three files, none empty
```

**Why `.env` is backed up:** the new commit stops tracking `.env` in git (it held secrets in a public
repository). When the server pulls that commit, git removes `.env` from the folder. You put it back in
Step 3.

If `git status` showed `M .env` (edited on the server), the automatic deploy's pull will **refuse** to run
("local changes would be overwritten"). That's fine: Step 3 handles it.

---

## Step 2: On your laptop, put the new version on `main` (this triggers the deploy)

You're on the branch `release/university-data`. Update `main` **without switching to it**:

```bash
$ cd ~/final_years/jenn
$ git push origin release/university-data:main     # GitHub's main = the new version → deploy runs
$ git branch -f main release/university-data        # your local main matches too
```

From now on you can work on `main` normally (`git checkout main` works, because `main` no longer tracks
`.env`).

Watch the deploy on GitHub (**Actions** tab). It runs `git pull origin main` on the server.

---

## Step 3: On the server, confirm the code and restore `.env`

```bash
vps$ cd /var/www/jenn
vps$ git log --oneline -1          # must show 70a3c14 (or newer)
```

If it still shows `35de301`, the automatic pull failed (usually because `.env` was edited on the server).
Do it by hand:

```bash
vps$ mv .env ~/backups/env_server_copy      # already backed up in Step 1
vps$ git pull origin main
vps$ git log --oneline -1                   # now 70a3c14
```

Now create the new `.env`, starting from your old one so you keep the passwords:

```bash
vps$ cp ~/backups/env_before_upgrade /var/www/jenn/.env
vps$ nano /var/www/jenn/.env
```

Make it contain these settings (keep your existing `DB_USERNAME`, `DB_PASSWORD` and `RAPIDAPI_KEY`
values):

```ini
DB_HOST=localhost
DB_NAME=jenn_v2
DB_USERNAME=jenn_user
DB_PASSWORD=<existing password>
RAPIDAPI_KEY=<existing key>

UNI_DB_NAME=campus_dynamics
# One database user for everything: UNI_DB_USERNAME / UNI_DB_PASSWORD are left out, so the app uses
# DB_USERNAME / DB_PASSWORD for the university copy too. The app still opens it read-only.
```

Do **not** add `UNI_AUTH_MODE` yet (see Step 6).

```bash
vps$ sudo chown $(stat -c '%U:%G' /var/www/jenn) .env && sudo chmod 640 .env && sudo chgrp www-data .env
```

---

## Step 4: Create the databases (one user, two new databases)

```bash
vps$ sudo mysql
```

```sql
CREATE DATABASE jenn_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE campus_dynamics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON jenn_v2.* TO 'jenn_user'@'localhost';
GRANT ALL PRIVILEGES ON campus_dynamics.* TO 'jenn_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

(Use your app user's name if it isn't `jenn_user`: it's `DB_USERNAME` in `.env`.)

**Why a new database, not upgrading `jenn_db`:** the structure changed a lot, and the upgrade scripts in
`database/migrations/` are written for MariaDB. A fresh `jenn_v2` built from `schema.sql` works on both
MySQL and MariaDB, and leaves `jenn_db` untouched for rollback.

Build the tables:

```bash
vps$ cd /var/www/jenn
vps$ sed 's/jenn_db/jenn_v2/g' database/schema.sql | mysql -u jenn_user -p
vps$ mysql -u jenn_user -p jenn_v2 -e "SHOW TABLES; SELECT COUNT(*) FROM roles; SELECT COUNT(*) FROM skills;"
```

**Expect:** 19 tables, 5 roles, 15 skills.

> If MySQL reports a syntax error here, copy the exact message. The schema was generated on MariaDB and
> may need a small adjustment for MySQL 8. Drop `jenn_v2`, fix, and re-run this step.

---

## Step 5: Load the data and set up

### 5.1 Upload the university dump (contains personal data)

```bash
$ scp ~/final_years/jenn/passbook.sql you@vps:~/passbook.sql        # from your laptop
vps$ chmod 600 ~/passbook.sql
```

### 5.2 Load the academic tables into `campus_dynamics`

```bash
vps$ cd /var/www/jenn
vps$ php src/scripts/load_university_dump.php ~/passbook.sql
vps$ shred -u ~/passbook.sql 2>/dev/null || rm -f ~/passbook.sql      # delete it once loaded
```

**Expect** a table list ending with `acad_results 370961 rows`, `acad_student 14124 rows`,
`portal_users 50801 rows`. HR, payroll and admissions data are skipped. It takes about a minute.

### 5.3 Keep production's existing job postings

First check that the skills are numbered the same in both databases:

```bash
vps$ mysql -u jenn_user -p -e "SELECT skill_id, skill_name FROM jenn_db.skills ORDER BY skill_id" > /tmp/a
vps$ mysql -u jenn_user -p -e "SELECT skill_id, skill_name FROM jenn_v2.skills ORDER BY skill_id" > /tmp/b
vps$ diff /tmp/a /tmp/b && echo "skills match"
```

- **If `skills match`:**
  ```bash
  vps$ sudo mysqldump --no-create-info --skip-triggers jenn_db jobs job_skills | mysql -u jenn_user -p jenn_v2
  ```
- **If they differ:** copy only `jobs` (remove `job_skills` from the command), or skip this and use
  **Jobs → Fetch from API** after the upgrade.

### 5.4 First-time setup

```bash
vps$ cd /var/www/jenn
vps$ php src/scripts/seed.php
```

**Expect:**
```
Admin created (admin@jenn.edu / admin123)
Structure: 6 faculties, 97 programmes, 6352 course units
IT programmes switched on
Drafted skills for 300 course units
Synced 608 students, 47 staff accounts, 5881 results; 608 profiles computed
```
It takes under a minute.

### 5.5 Replace the default admin password immediately

```bash
vps$ cd /var/www/jenn
vps$ read -s -p "New admin password: " P; echo
vps$ ADMIN_PW="$P" php -r 'require "src/config/app.php"; require "src/config/database.php";
  Database::getConnection()->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
    ->execute([password_hash(getenv("ADMIN_PW"), PASSWORD_DEFAULT), "admin@jenn.edu"]); echo "updated\n";'
vps$ unset P
```

### 5.6 Give long requests room, and refresh PHP

In your nginx site config (`/etc/nginx/sites-available/jenn`), inside `location ~ \.php$ { … }`, add:

```nginx
fastcgi_read_timeout 300;
```

```bash
vps$ sudo nginx -t && sudo systemctl reload nginx
vps$ sudo systemctl reload php8.3-fpm        # use your PHP version
```

The site is now up on the new version.

---

## Step 6: Check it, and decide how students and staff sign in

### 6.1 Smoke test (in the browser, on your domain)

- [ ] Home page loads; sign in as `admin@jenn.edu` with the **new** password.
- [ ] **Overview** shows about 608 students and the charts.
- [ ] **Administration → University data:** "Up to date", source `localhost/campus_dynamics`, 5 IT programmes in use.
- [ ] **Curriculum**, **Jobs**, **Analytics**, **Administration → Users** (47 staff), **Administration → Roles** (5 roles) all open.
- [ ] `sudo tail -n 30 /var/log/nginx/error.log` shows no new PHP errors.

### 6.2 Student and staff sign-in

Students sign in with their **registration number** and **portal password**; staff with their **admin
system username**. The app checks the password against the portal's stored hash using the standard
ASP.NET methods, but **which method the university uses hasn't been confirmed.**

Ask one IT student who knows their portal password to try it, with their consent:
- **If they get in,** the check works. Leave `UNI_AUTH_MODE` unset. That's real production sign-in.
- **If it says "Those details don't match"** with the right password, the method differs. Either:
  - get the method from the university's IT team and adjust `University::aspNetPasswordMatches()`, **or**
  - for a supervised demo only, add to `.env`:
    ```ini
    UNI_AUTH_MODE=dev
    DEV_LOGIN_PASSWORD=<a strong password>
    ```
    **Know what this does:** anyone with that one password can sign in as any synced student or staff
    member. Remove it after the demo.

---

## Step 7: Same day: security follow-ups

- [ ] **Rotate the leaked secrets.** The old `.env` was public on GitHub.
  - New MySQL password for the app user:
    ```bash
    vps$ sudo mysql -e "ALTER USER 'jenn_user'@'localhost' IDENTIFIED BY '<new password>';"
    ```
    then update `DB_PASSWORD` in `/var/www/jenn/.env`.
  - Regenerate the RapidAPI key on rapidapi.com and update `RAPIDAPI_KEY`.
- [ ] MySQL listens only locally: `sudo ss -ltnp | grep 3306` shows `127.0.0.1`.
- [ ] `.env` isn't reachable from the web: `curl -I https://<your-domain>/.env` must **not** return 200.
- [ ] After a week without problems, remove the old database:
  `sudo mysql -e "DROP DATABASE jenn_db;"` (you still have `~/backups/jenn_db_before_upgrade.sql`).

From now on, every push to `main` deploys automatically again, and `.env` is never touched by pulls.

---

## Step R: Rollback (if something is wrong)

```bash
vps$ cd /var/www/jenn
vps$ git reset --hard 35de301                       # back to the July code (brings the old tracked .env back)
vps$ cp ~/backups/env_before_upgrade .env           # the old settings, pointing at jenn_db
vps$ sudo systemctl reload php8.3-fpm
```

The old site is back on the untouched `jenn_db`. Then **stop the next push from re-deploying** until it's
fixed: GitHub → **Actions → Deploy → ⋯ → Disable workflow**.

---

## Refreshing the university data later

Until the university's API replaces the copy, to load a newer dump:

```bash
vps$ php /var/www/jenn/src/scripts/load_university_dump.php ~/new_dump.sql && shred -u ~/new_dump.sql
vps$ php /var/www/jenn/src/scripts/sync.php
```

Once there's a live connection or API, schedule the sync nightly:

```bash
vps$ ( crontab -l 2>/dev/null; echo "0 2 * * * php /var/www/jenn/src/scripts/sync.php >> /var/log/jenn-sync.log 2>&1" ) | crontab -
```
