# UB Lost and Found – Database Setup (WAMP / phpMyAdmin)

## 1. Create the database

1. Start **WAMP** and ensure MySQL is running (green icon).
2. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
3. Click **Import** (or **SQL**).
4. Either:
   - **Import file**: Choose `lostandfound.sql` from this folder and click **Go**.
   - **Paste SQL**: Open `lostandfound.sql`, copy its contents, paste into the SQL tab, then click **Go**.

This will:

- Create the database `lostandfound_db`
- Create tables: `items`, `admins`, `activity_log`
- Insert a default admin user

## 2. Admin login

- **Email:** `admin@ub.edu.ph`
- **Password:** `Admin`

Only this user (stored in the `admins` table) can log in. Wrong credentials show "Invalid Credentials."

If you deleted the admin row: run **`create_admin.php`** once in your browser (e.g. `http://localhost/LOSTANDFOUND/create_admin.php`) to create the admin again; then delete that file.

## 3. Database configuration

Connection settings are in **`config/database.php`**:

- **Host:** `localhost`
- **Database:** `lostandfound_db`
- **User:** `root`
- **Password:** (empty by default in WAMP)

If your WAMP MySQL uses a different user or password, edit `config/database.php` accordingly.

## 4. Tables

| Table          | Purpose |
|----------------|--------|
| **items**      | All found/lost items. `status`: `Unclaimed Items`, `Unresolved Claimants`, `For Verification`. New encoded items are stored here with status `Unclaimed Items`. |
| **admins**     | Admin users for login (email + password hash). |
| **activity_log** | Optional; for future “Recent Activity” from the database. |

## 5. Item matching (Reference ID ↔ Barcode ID)

To link **lost-item reports** (Reference ID) to **found items** (Barcode ID), see **`ITEM_MATCHING_DESIGN.md`** in this folder. Optionally run **`item_matching_migration.sql`** to add the `matched_barcode_id` column to `items`.

## 6. Troubleshooting

- **“Database not found”**  
  Run `lostandfound.sql` in phpMyAdmin so that `lostandfound_db` and the tables are created.

- **“Could not save item” / “Database or table not found” when encoding a new item**  
  The app could not insert into the `items` table. Do this:
  1. Open phpMyAdmin → **Import** (or **SQL** tab).
  2. Run the full **`database/lostandfound.sql`** so that the database `lostandfound_db` and the table **`items`** exist.
  3. Ensure **MySQL is running** (WAMP icon green) and that **config/database.php** uses the correct host, database name, user, and password.

- **“Access denied”**  
  Check MySQL user/password in `config/database.php` and that the user has rights on `lostandfound_db`.

- **Blank dashboard or no items**  
  After importing, the `items` table is empty until you add items via **Encode New Item** in the admin inventory. The dashboard and reports will then read from the database.
