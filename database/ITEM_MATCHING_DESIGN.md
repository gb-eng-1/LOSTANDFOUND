# Item Matching: How Reference ID Connects to Barcode ID

## Concepts

| Term | Meaning | When it’s used |
|------|--------|-----------------|
| **Barcode ID** | ID printed on the physical tag for a **found** item (e.g. `UB0019`). | When staff encode a found item in Inventory, the system generates this and it becomes the item’s `id`. |
| **Reference ID** | ID for a **lost-item report** or **claim** (e.g. `REF-9982213434`). | When someone reports a lost item, the system creates a record and assigns a reference ID. |

**Item matching** = linking a lost-item report (Reference ID) to a found item (Barcode ID) so the claimant can claim that specific physical item.

---

## Flow

1. **Found item**  
   Staff encode the item → system creates a row with `id = Barcode ID` (e.g. `UB0019`), status `Unclaimed Items`.

2. **Lost report**  
   User reports a lost item → system creates a record with `id = Reference ID` (e.g. `REF-xxx`), or reuses the same `items` table with a different “type” (e.g. lost report vs found item).

3. **Matching (admin)**  
   On **Item Matched**, admin sees reports that can be linked to found items. When they confirm “this report = this found item”, the system stores:
   - **Reference ID** (the report/claim)
   - **Barcode ID** (the found item it’s matched to)

4. **Claim**  
   When the claimant comes to claim, you look up by Reference ID, get the linked Barcode ID, and hand over that physical item.

---

## Option A: Link column on `items` (recommended for current schema)

Add one column to `items` so a row can point to the matched found item:

- **Column:** `matched_barcode_id` (VARCHAR, nullable).
- **Meaning:**
  - For a **found item** row: `id` = Barcode ID, `matched_barcode_id` = NULL.
  - For a **lost report** row: `id` = Reference ID, `matched_barcode_id` = Barcode ID of the found item once matched.

Then:

- **Reference ID** = `items.id` for the report/claim row.
- **Barcode ID** = `items.matched_barcode_id` for that same row (after matching).

Use the migration in `database/item_matching_migration.sql` to add this column.

---

## Option B: Separate link table (for full history)

Keep `items` unchanged and add a table that only stores “who was matched to what”:

- **Table:** `claim_matches`  
  - `reference_id` (e.g. lost report id)  
  - `barcode_id` (found item id)  
  - `matched_at`, `matched_by`, etc.

Then:

- **Reference ID** = id of the lost report (in `items` or a future `lost_reports` table).
- **Barcode ID** = id of the found item; the link is in `claim_matches`.

Use this if you want to keep a full history of matches (e.g. one report matched to different items over time).

---

## What to implement next

1. **Run the migration** (Option A): add `matched_barcode_id` to `items` (see `item_matching_migration.sql`).
2. **Generate Reference IDs** for lost reports (e.g. `REF-` + unique number) when creating the report.
3. **Item Matched page**: when admin clicks **Claim** or **Match**, open a flow to pick a found item (by Barcode ID) and save `matched_barcode_id` for the current report row.
4. **Display**: on Item Matched, optionally show “Matched to: UB0019” by reading `matched_barcode_id` for each row.

After that, Reference ID (report) and Barcode ID (found item) are connected through `matched_barcode_id` (or through `claim_matches` if you choose Option B).
