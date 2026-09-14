# Running OmniBuy in Docker (Windows Setup Guide)

This guide walks through everything needed to get OmniBuy running in Docker on
a Windows machine, including a one-time firmware fix that is required before
Docker Desktop / WSL2 will work at all.

It was written after diagnosing a real failure on this machine, so the
troubleshooting section reflects actual error messages you may see.

---

## 0. One-time fix: enable virtualization in BIOS/UEFI

Docker Desktop on Windows runs containers inside a lightweight Linux VM via
WSL2. That VM requires hardware virtualization (Intel VT-x or AMD-V) to be
turned on in the motherboard firmware. On this machine it was found to be
**disabled**, which causes Docker Desktop to fail with:

```
starting engine: engine linux/wsl failed to start: installing main
distribution: importing distribution: importing WSL distro: running
wslexec: WSL2 is not supported with your current machine configuration.
```

You can confirm the current state at any time with:

```powershell
systeminfo | findstr /C:"Virtualization Enabled In Firmware" /C:"VM Monitor Mode"
```

- `VM Monitor Mode Extensions: Yes` → your CPU supports virtualization.
- `Virtualization Enabled In Firmware: No` → it's turned off in BIOS/UEFI.

### Steps to enable it

1. Restart the computer.
2. Enter BIOS/UEFI setup during boot. The key differs by manufacturer:
   - Dell: `F2`
   - HP: `F10` or `Esc`
   - Lenovo: `F1` or `F2` (sometimes `Enter` then `F1`)
   - ASUS: `F2` or `Del`
   - MSI: `Del`
   - Custom-built PC: check the motherboard brand (Del is most common)
3. Find the virtualization setting. It's usually under **Advanced**, **CPU
   Configuration**, or **Security**, and may be labeled:
   - Intel: `Intel VT-x`, `Intel Virtualization Technology`
   - AMD: `SVM Mode`, `AMD-V`
4. Set it to **Enabled**.
5. If present, also enable **VT-d** / **IOMMU** (not always required, but
   some setups need it).
6. Save and exit (usually `F10`).
7. Once back in Windows, re-run the `systeminfo` check above and confirm it
   now says `Virtualization Enabled In Firmware: Yes`.

If virtualization is greyed out or missing entirely, it may be locked by the
manufacturer, a corporate/MDM policy, or need a firmware update — in that
case Docker/WSL2 cannot run on this machine as-is.

---

## 1. One-time fix: make sure WSL2 itself is installed

Even with virtualization enabled, a fresh Windows install may have no WSL
distributions registered at all (`wsl -l -v` reports "Windows Subsystem for
Linux has no installed distributions"). Fix this from an **Administrator**
PowerShell:

```powershell
wsl --install
```

Reboot after this completes. This single command enables the
`Microsoft-Windows-Subsystem-Linux` and `VirtualMachinePlatform` Windows
features, installs the WSL2 kernel, and sets WSL2 as the default version.

Verify:

```powershell
wsl --status
wsl --version
```

---

## 2. Start Docker Desktop

1. Launch **Docker Desktop** from the Start menu.
2. Wait for the whale icon in the system tray to stop animating (fully
   started). First start after install/reboot can take a minute or two.
3. Verify the daemon answers:

```powershell
docker info
```

If this instead prints something like `ERROR: Error response from daemon:
Docker Desktop is unable to start`, or a `500 Internal Server Error` on
`dockerDesktopLinuxEngine`, it almost always traces back to step 0 or step 1
above not being fully applied yet (or not rebooted since).

---

## 3. Configure environment variables

From the `Omnibuy/` project folder:

```bash
cp .env.example .env
```

Edit `.env` and set real values if you want something other than the
defaults:

```env
MYSQL_DATABASE=omnibuy
MYSQL_USER=omnibuy_user
MYSQL_PASSWORD=change_me
MYSQL_ROOT_PASSWORD=change_me
```

Do not commit real credentials.

---

## 4. Build and start the containers

From the `Omnibuy/` project folder:

```bash
docker compose up -d
```

Check everything is running:

```bash
docker compose ps
```

You should see three services: `omnibuy-web` (PHP/Apache), `omnibuy-db`
(MySQL 8.0), and `omnibuy-phpmyadmin`.

The database container mounts the project root as
`/docker-entrypoint-initdb.d`, so `omnibuy_database.sql` and `seed_data.sql`
are imported automatically **the first time the `mysql_data` volume is
created**. If you change the schema/seed files later, you need to reset the
volume for them to re-import:

```bash
docker compose down -v
docker compose up -d
```

(`-v` deletes the database volume — only do this if you're OK losing local
data in it.)

---

## 5. Verify the app works

- **App**: http://localhost:8080 — should show the OmniBuy homepage
  (`public/index.php`), not a directory listing. (The Dockerfile now points
  Apache's document root at `public/` instead of the project root — this was
  fixed alongside this guide; if you're on an older copy of the Dockerfile
  and see an "Index of /" page instead of the app, that's why.)
- **phpMyAdmin**: http://localhost:8081 — log in with the `MYSQL_USER` /
  `MYSQL_PASSWORD` (or `root` / `MYSQL_ROOT_PASSWORD`) from `.env`, and
  confirm the `omnibuy` database and its tables exist.
- **Logs**, if something looks wrong:

```bash
docker compose logs web
docker compose logs db
```

---

## 6. Stopping everything

```bash
docker compose down
```

Add `-v` only if you also want to wipe the database volume (see step 4).

---

## Troubleshooting quick reference

| Symptom | Cause | Fix |
|---|---|---|
| `wsl -l -v` says no installed distributions | WSL2 never initialized | Run `wsl --install` as Administrator, reboot |
| `checking preconditions: WSL update required` | WSL kernel out of date | `wsl --update` (as Administrator), reboot Docker Desktop |
| `WSL2 is not supported with your current machine configuration` | Virtualization disabled in firmware | Enable VT-x/AMD-V in BIOS/UEFI (Section 0) |
| `Docker Desktop is unable to start` / `500 Internal Server Error ... dockerDesktopLinuxEngine` | WSL2 backend not fully up yet, or one of the above | Fix Section 0/1, reboot, relaunch Docker Desktop |
| App shows a file/directory listing instead of the homepage | Apache document root was the whole repo, not `public/` | Already fixed in `Dockerfile` (`APACHE_DOCUMENT_ROOT`) |
