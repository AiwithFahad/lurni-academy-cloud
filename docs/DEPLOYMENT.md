# Deployment — Lurni Academy Cloud

## The PHP application (production)
The full product is a Core PHP 8 + MySQL application. It needs a host with:
- PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (or imagick)
- MySQL 8 (or MariaDB 10.4+)
- Apache (with the included patterns) or Nginx + PHP-FPM

Steps:
1. Upload the project (excluding `vercel-demo/`) to the web root.
2. Copy `.env.example` to `.env` and set `APP_URL`, `DB_*`, mail settings.
3. Create the database, then visit `/install.php` once (or run
   `php install.php`) to import `database.sql` and seed demo data.
4. Delete `install.php`.
5. Make `uploads/` and `storage/` writable by the PHP user.
6. Point the domain at the project root; force HTTPS.

Managed options that fit: any cPanel/VPS PHP host, Laravel Forge-style
servers, Ploi, or a small Docker stack (`php:8.2-apache` + `mysql:8`).

## The Vercel demo (showcase)
`vercel-demo/` is a **static** HTML/CSS/JS replica of the landing page and
the admin/instructor/student dashboards with realistic mock data. It exists
so prospects can click through the product instantly.

- Deploy it by importing the repository in Vercel and setting the project
  root directory to `vercel-demo` (its `vercel.json` is already configured),
  or by dragging the folder into a new Vercel project.
- It has no backend: forms, charts and buttons are demonstrative only.

> Honest note: Vercel cannot run the PHP/MySQL application itself. The live
> demo is the static showcase; the commercial product runs on PHP/MySQL
> hosting as described above.
