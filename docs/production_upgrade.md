# Production Upgrade: from the July version to the university-data version

Production (`/var/www/jenn` on the VPS) runs the July version (commit `35de301`, "vps x landingpage").
This guide upgrades it to the current version: real university data, curriculum mapping, the student
pages, roles and permissions, and the redesign. It does this **without breaking the live site while you
prepare**, and with a **one-command rollback**.

**Time:** about 1–2 hours the first time, most of it waiting for loads and checking.
**Downtime:** under a minute (a folder swap).

---

## Read this first: why this isn't a normal `git pull`

1. **Production auto-deploys.** `.github/workflows/deploy.yml` runs `git pull` on the server for every push
   to `main`. If you push the new code to `main` first, production immediately runs new code against the
   old database, and pages break. So **don't push to `main` until Step 6.**
2. **The database changes completely.** The new version has a different structure (programmes keyed by
   university codes, roles, a curriculum mapping…). Rather than altering the live database, we build a
   **new database (`jenn_v2`) next to the old one**. The old one stays untouched for rollback.
3. **Don't use the migration scripts on production.** The scripts in `database/migrations/` use
   MariaDB-only syntax (`ADD COLUMN IF NOT EXISTS`…), and the deployment guide installs MySQL 8. Building
   `jenn_v2` from `database/schema.sql` avoids them entirely.
4. **`.env` is currently committed to git (in a public repository).** The upgrade stops tracking it. The
   new folder gets its own untracked `.env`, so later pulls never touch it. The old folder is only pulled
   again if you roll back (covered in Rollback). **Change the database password and the RapidAPI key
   afterwards** (Step 7.3), because they've been public.
5. **The university dump contains personal data.** It goes to the server over SSH only, is deleted after
   loading, and never enters git.

### The plan at a glance

```
Step 0  Local: commit, stop tracking .env, push to a release branch (NOT main)
Step 1  Server: checks and backups                                      ┐
Step 2  Server: new code in /var/www/jenn-next                          │ live site unaffected
Step 3  Server: new databases (jenn_v2 + campus_dynamics) and users     │
Step 4  Server: load university data, carry over jobs, set up           │
Step 5  Server: test the new version privately                          ┘
Step 6  Switch: swap folders, merge to main                              ← under a minute
Step 7  Verify, then tidy up
Rollback at any point after Step 6: swap the folders back
```

Throughout this guide:
- `you@vps` means your SSH login.
- `<db-password>` and similar placeholders mean you choose a value.
- Commands starting with `$` run on **your laptop**; commands starting with `vps$` run **on the server**.

---

## Step 0: Prepare the code locally

### 0.1 Check the working tree

```bash
$ cd ~/final_years/jenn
$ git status
```

**Must not be committed:**
- `passbook.sql`, `docs/demo_accounts.local.md`, `database/backups/`, `.codex/` and `.github/hooks/` are
  already in `.gitignore`.
- **Never `git add -A` without looking.**

### 0.2 Stop tracking `.env` (keep your local copy)

```bash
$ git rm --cached .env          # removes it from git, keeps the file on your laptop
$ grep -x ".env" .gitignore     # should print .env (already ignored)
```

`.env.example` (committed) documents every setting without secrets.

### 0.3 Commit and push to a release branch, not `main`

```bash
$ git checkout -b release/university-data
$ git add .gitignore .env.example database docs public src
$ git status                    # review: no passbook.sql, no .env, no *.local.md, no backups
$ git commit -m "University data integration, curriculum mapping, student pages, roles and permissions"
$ git push -u origin release/university-data
```

Pushing a branch other than `main` does **not** trigger the deploy.

---

## Step 1: Server checks and backups

```bash
$ ssh you@vps
```

### 1.1 Versions

```bash
vps$ php -v                         # needs PHP 8.1 or newer (8.3 per the deployment guide)
vps$ php -m | grep -iE "pdo_mysql|mbstring|curl"   # all three must be listed
vps$ mysql --version                # MySQL 8.x or MariaDB 10.5+
```

If PHP is older than 8.1, upgrade it first. The new code uses PHP 8.1 features.

### 1.2 Back up everything

```bash
vps$ mkdir -p ~/backups && cd ~/backups
vps$ sudo mysqldump jenn_db > jenn_db_before_upgrade_$(date +%F).sql
vps$ cp /var/www/jenn/.env ~/backups/env_before_upgrade
vps$ sudo tar czf jenn_code_before_upgrade_$(date +%F).tgz -C /var/www jenn
vps$ ls -lh ~/backups               # three files, none empty
```

(If production's database isn't called `jenn_db`, check `DB_NAME` in `/var/www/jenn/.env` and use that
name everywhere below.)

---

## Step 2: Put the new code next to the old

```bash
vps$ cd /var/www
vps$ sudo git clone -b release/university-data https://github.com/Mr-Righteousdev/jenn.git jenn-next
vps$ sudo chown -R $USER:$USER /var/www/jenn-next
```

The live site still serves `/var/www/jenn`. Nothing has changed for users.

---

## Step 3: Databases and users

```bash
vps$ sudo mysql
```

```sql
-- 1. The new app database (the old jenn_db stays as it is)
CREATE DATABASE jenn_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON jenn_v2.* TO 'jenn_user'@'localhost';

-- 2. The university data copy, and two users for it:
--    sis_loader can (re)build it; sis_reader can only read it (the app uses this one)
CREATE DATABASE campus_dynamics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sis_loader'@'localhost' IDENTIFIED BY '<loader-password>';
GRANT ALL PRIVILEGES ON campus_dynamics.* TO 'sis_loader'@'localhost';
CREATE USER 'sis_reader'@'localhost' IDENTIFIED BY '<reader-password>';
GRANT SELECT ON campus_dynamics.* TO 'sis_reader'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

(`jenn_user` is the app's existing database user from the deployment guide. If yours is different, use
that name.)

Why two users for the university data: the app connects as `sis_reader`, which **can only SELECT**. Even
apart from the app's own read-only mode, the database won't let it change the university's records.

### 3.1 Create the tables

```bash
vps$ cd /var/www/jenn-next
vps$ sed 's/jenn_db/jenn_v2/g' database/schema.sql | mysql -u jenn_user -p
vps$ mysql -u jenn_user -p jenn_v2 -e "SHOW TABLES; SELECT slug, scope FROM roles; SELECT COUNT(*) FROM skills;"
```

**Expect:**
- 19 tables
- 5 roles: `super_admin`/`career_officer` with scope `all`, the rest `faculty`
- 15 skills

> If this step reports a syntax error on MySQL, copy the error. The schema was generated on MariaDB and
> hasn't been run on MySQL 8 yet, so a small adjustment may be needed. Nothing live is affected; fix
> and re-run (drop `jenn_v2` first).

### 3.2 The new `.env` for `jenn-next`

```bash
vps$ cp /var/www/jenn-next/.env.example /var/www/jenn-next/.env
vps$ nano /var/www/jenn-next/.env
```

Fill in:

```ini
DB_HOST=localhost
DB_NAME=jenn_v2
DB_USERNAME=jenn_user
DB_PASSWORD=<jenn_user's password, from ~/backups/env_before_upgrade>
RAPIDAPI_KEY=<new key, see Step 7.3>
UNI_DB_HOST=localhost
UNI_DB_NAME=campus_dynamics
UNI_DB_USERNAME=sis_reader
UNI_DB_PASSWORD=<reader-password>
```

Leave `UNI_AUTH_MODE` **unset** for now (see Step 5.3).

```bash
vps$ chmod 640 /var/www/jenn-next/.env
```

---

## Step 4: Load the data

### 4.1 Upload the university dump (personal data: handle carefully)

On your laptop:

```bash
$ scp ~/final_years/jenn/passbook.sql you@vps:~/passbook.sql
```

On the server:

```bash
vps$ chmod 600 ~/passbook.sql
```

### 4.2 Load the academic tables into `campus_dynamics`

```bash
vps$ cd /var/www/jenn-next
vps$ UNI_DB_LOADER_USERNAME=sis_loader UNI_DB_LOADER_PASSWORD='<loader-password>' \
     php src/scripts/load_university_dump.php ~/passbook.sql
```

**Expect a list like:** `acad_course ~6358 rows · acad_faculty 7 · acad_programme 97 · acad_results
370961 · acad_student 14124 · staff_users 47 · portal_users 50801 …` It takes under a minute.
HR, payroll and admissions tables are skipped.

The loader rebuilds `campus_dynamics` from scratch each time it runs. That's why it needs `sis_loader`,
which can drop and create that one database.

**Delete the dump now** that it's loaded:

```bash
vps$ shred -u ~/passbook.sql 2>/dev/null || rm -f ~/passbook.sql
```

### 4.3 Carry over the jobs from the old database

The old version's job postings (and their detected skills) are worth keeping.

First check that the skills line up. Job skills reference skills by id:

```bash
vps$ mysql -u jenn_user -p -e "SELECT skill_id, skill_name FROM jenn_db.skills ORDER BY skill_id;" > /tmp/old_skills
vps$ mysql -u jenn_user -p -e "SELECT skill_id, skill_name FROM jenn_v2.skills ORDER BY skill_id;" > /tmp/new_skills
vps$ diff /tmp/old_skills /tmp/new_skills && echo "skills match"
```

- **If it prints `skills match`,** copy the jobs:

  ```bash
  vps$ sudo mysqldump --no-create-info --skip-triggers jenn_db jobs job_skills > ~/backups/jobs_carryover.sql
  vps$ mysql -u jenn_user -p jenn_v2 < ~/backups/jobs_carryover.sql
  vps$ mysql -u jenn_user -p jenn_v2 -e "SELECT COUNT(*) jobs FROM jobs; SELECT COUNT(*) job_skills FROM job_skills;"
  ```

- **If the skills differ,** copy only `jobs` (drop `job_skills` from the dump command) and re-detect skills
  later by fetching fresh jobs, or start with no jobs and use **Jobs → Fetch from API** after the
  switch.

### 4.4 First-time setup

```bash
vps$ cd /var/www/jenn-next
vps$ php src/scripts/seed.php
```

It:
1. creates the emergency admin `admin@jenn.edu`
2. syncs the university structure
3. switches on the 5 IT programmes
4. drafts the curriculum mapping
5. syncs the students and results

**Expect:** about 608 students, 47 staff accounts, ~5,881 results and ~608 profiles. It takes a few
minutes.

### 4.5 Set a real admin password immediately

The seed uses `admin123`. Replace it:

```bash
vps$ cd /var/www/jenn-next
vps$ read -s -p "New admin password: " P; echo
vps$ ADMIN_PW="$P" php -r 'require "src/config/app.php"; require "src/config/database.php";
  Database::getConnection()->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
    ->execute([password_hash(getenv("ADMIN_PW"), PASSWORD_DEFAULT), "admin@jenn.edu"]); echo "updated\n";'
vps$ unset P
```

### 4.6 Permissions

Give the new folder the **same owner as the live one**, so the deploy action (which runs `git pull` as
your SSH user) keeps working after the swap:

```bash
vps$ OWNER=$(stat -c '%U:%G' /var/www/jenn); echo "$OWNER"     # e.g. www-data:www-data
vps$ sudo chown -R "$OWNER" /var/www/jenn-next
vps$ sudo chmod 640 /var/www/jenn-next/.env
vps$ sudo chgrp www-data /var/www/jenn-next/.env               # PHP (www-data) must be able to read it
```

---

## Step 5: Test the new version privately (before anyone sees it)

### 5.1 Run it on a private port and reach it through SSH

On the server:

```bash
vps$ cd /var/www/jenn-next && sudo -u www-data php -S 127.0.0.1:8081 -t public
```

On your laptop, in a second terminal:

```bash
$ ssh -L 8081:127.0.0.1:8081 you@vps
```

Open **http://localhost:8081** in your browser. You're looking at the new version on the server, with
production data, invisible to everyone else.

### 5.2 Smoke test

- [ ] Sign in as `admin@jenn.edu` with the **new** password.
- [ ] **Overview:** about 608 students, figures and charts render.
- [ ] **Manage → University data:** "Up to date", source `localhost/campus_dynamics`, 5 IT programmes in use.
- [ ] **Curriculum:** Bachelor of IT shows units with skill tags.
- [ ] **Jobs:** the carried-over jobs are there.
- [ ] **Manage → Users:** 47 university staff accounts plus the admin.
- [ ] **Manage → Roles and permissions:** 5 roles.

### 5.3 Student and staff sign-in: decide the mode

University accounts sign in with their **portal password**. The system checks it against the portal's
stored hash using the standard ASP.NET methods, but **which method the university uses isn't confirmed
yet.**

**Test it properly:** ask one IT student (and one staff member) who knows their portal password to sign
in, with their consent.
- **If they get in,** the hash method is confirmed. Leave `UNI_AUTH_MODE` unset. You're in real
  production mode.
- **If "Those details don't match" appears** even with the right password, the method differs. Then
  either:
  - get the method from the university's IT team (or their verify endpoint) and adjust
    `University::aspNetPasswordMatches()`, **or**
  - for a supervised demo only, set `UNI_AUTH_MODE=dev` and a strong `DEV_LOGIN_PASSWORD` in `.env`.
    **Understand what this means:** anyone who knows that one password can sign in as any synced student
    or staff member. Don't leave it on for a public site.

Stop the test server (`Ctrl+C`) when done.

---

## Step 6: Switch over (under a minute)

### 6.1 Swap the folders

```bash
vps$ cd /var/www
vps$ sudo mv jenn jenn-old && sudo mv jenn-next jenn
vps$ sudo systemctl reload php8.3-fpm     # clears PHP's cached paths (use your PHP version)
```

The site now serves the new version from `/var/www/jenn`. It's the same path, so nginx needs no change.

### 6.2 Allow long requests for "Sync now"

A full sync takes a few minutes. The code already lifts PHP's time limit for the sync; nginx must also
wait. In your site config (`/etc/nginx/sites-available/jenn`), inside `location ~ \.php$ { … }`, add:

```nginx
fastcgi_read_timeout 300;
```

```bash
vps$ sudo nginx -t && sudo systemctl reload nginx
```

(Alternatively, always run syncs from the command line: `php /var/www/jenn/src/scripts/sync.php`.)

### 6.3 Make `main` the new version, so auto-deploy keeps working

On your laptop:

```bash
$ git checkout main
$ git merge --ff-only release/university-data     # or open and merge a pull request on GitHub
$ git push origin main
```

This triggers the deploy action. It runs `git pull origin main` in `/var/www/jenn`, which is now the new
clone. On the server, make sure that clone follows `main`:

Do this **after** the push above. Before it, `main` on GitHub is still the old version, and checking it
out would put the old code back. Run git as the folder's owner (the same `$OWNER` as in Step 4.6):

```bash
vps$ cd /var/www/jenn
vps$ OWNER_USER=$(stat -c '%U' /var/www/jenn)
vps$ sudo -u "$OWNER_USER" git fetch origin
vps$ sudo -u "$OWNER_USER" git checkout main
vps$ sudo -u "$OWNER_USER" git pull origin main
vps$ git log --oneline -1   # the upgrade commit
vps$ ls -l .env             # still there: it's untracked, so pulls never touch it
```

If `git` complains about "dubious ownership", run
`git config --global --add safe.directory /var/www/jenn` as that user.

From now on, every push to `main` deploys as before.

---

## Step 7: Verify, then tidy up

### 7.1 On the live site
- [ ] Home page loads; **Sign in** works for the admin.
- [ ] One full page tour: Overview, University data, Curriculum, Jobs, Analytics, Users, Roles.
- [ ] One student and one staff sign-in (per your choice in 5.3).
- [ ] **Manage → University data → Sync now** completes (a few minutes) and shows "Up to date".
- [ ] `tail -n 50 /var/log/nginx/error.log` shows no new PHP errors.

### 7.2 Keep the old version for a week, then remove it

```bash
vps$ sudo rm -rf /var/www/jenn-old                # after a week without problems
vps$ sudo mysql -e "DROP DATABASE jenn_db;"       # only after you're sure; you also have ~/backups
```

### 7.3 Security follow-ups (do these the same day)

- [ ] **Rotate the leaked secrets.** The old `.env` was public on GitHub:
  - change the MySQL password of `jenn_user` (`ALTER USER 'jenn_user'@'localhost' IDENTIFIED BY '<new>';`)
    and update `.env`
  - regenerate the RapidAPI key on rapidapi.com and update `.env`
- [ ] Deactivate any leftover test accounts on **Manage → Users**.
- [ ] Confirm MySQL only listens locally: `sudo ss -ltnp | grep 3306` should show `127.0.0.1`.
- [ ] Confirm `.env` isn't reachable from the web: `curl -I https://<your-domain>/.env` must not return 200.
  (The web root is `public/`, so it shouldn't be.)
- [ ] The SSL certificate: see `ssl.md` if it has expired.

### 7.4 Scheduled sync (later)

While the university data is a one-off copy, a nightly sync changes nothing. Once the university's API
(or a live read-only connection) replaces the copy, schedule it:

```bash
vps$ ( crontab -l 2>/dev/null; echo "0 2 * * * php /var/www/jenn/src/scripts/sync.php >> /var/log/jenn-sync.log 2>&1" ) | crontab -
```

Until then, to refresh from a newer dump, repeat Step 4.1–4.2 (load) and run
`php /var/www/jenn/src/scripts/sync.php`.

---

## Rollback

If anything is wrong after Step 6:

```bash
vps$ cd /var/www
vps$ sudo mv jenn jenn-broken && sudo mv jenn-old jenn
vps$ sudo systemctl reload php8.3-fpm
```

The old version is back immediately, still using `jenn_db`, which was never modified.

**Stop the next push from re-deploying the new code into the restored folder** until you've fixed it.
Either:
- disable the workflow on GitHub (**Actions → Deploy → … → Disable workflow**), or
- push a revert to `main`.

Note: the old folder still tracks `.env` in git. If a pull brings in the "stop tracking `.env`" commit,
restore it from `~/backups/env_before_upgrade`.

---

## Quick reference: what changed in production

| Area | July version | New version |
|---|---|---|
| Database | `jenn_db` | `jenn_v2` (new structure), plus `campus_dynamics` (university copy, read-only user) |
| Students | Self-registration, CSV import | From the university system; sign in with portal login |
| Admin pages | CBC levels, skills, import, jobs, reports | + University data, Curriculum, Users, Roles; import removed |
| Access | 3 fixed roles | Editable roles, 11 permissions, faculty scope |
| Settings | `.env` in git | `.env` untracked; `.env.example` documents it |
| New PHP requirement | – | PHP 8.1+ with `pdo_mysql`, `mbstring`, `curl` |
| New nginx setting | – | `fastcgi_read_timeout 300` for "Sync now" |
