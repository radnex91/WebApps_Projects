# Importation des Employes - Module RH

**Date:** 2026-05-05
**Status:** Approved

## Context

BrenFinance lacks a dedicated HR module. Employee data is split between `utilisateurs` (identity/access) and `contrats_employes` (contract/salary). No CRUD exists for `contrats_employes` -- the table is only read by the payroll module. No import functionality exists anywhere in the project.

## Decision

Create a new `modules/rh/` module with CSV employee import that creates records in both `utilisateurs` and `contrats_employes` in a single operation.

## Module Structure

### Files

| File | Purpose |
|------|---------|
| `modules/rh/index.php` | Dashboard: KPIs (active employees, by contract type, by agency) + action buttons |
| `modules/rh/employes.php` | Employee list with search/filters |
| `modules/rh/import_employes.php` | CSV import page: upload form, template download, results report |
| `modules/rh/template_employes.csv` | Downloadable CSV template |

### Sidebar

Add "Ressources Humaines" entry with `fa-users` icon linking to `modules/rh/index.php`.

### Permissions

New `rh` module in permission tree with actions: `voir`, `importer`, `modifier`.

## CSV Format

### Columns

| Column | Required | Description | Example |
|--------|----------|-------------|---------|
| `nom` | Yes | Last name | DUPONT |
| `prenom` | Yes | First name | Marie |
| `matricule` | No | Employee number (auto-generated if empty) | EMP-2025-001 |
| `email` | Yes | Unique email | marie@exemple.cm |
| `telephone` | No | Phone number | +237 699123456 |
| `agence` | No | Agency name (must exist in `agences` table) | Douala |
| `service` | No | Service name (must exist in `services` table) | Comptabilite |
| `role` | Yes | Role name (must exist in `roles` table) | demandeur |
| `salaire_base` | Yes | Base salary in FCFA | 250000 |
| `date_embauche` | Yes | Hire date (YYYY-MM-DD) | 2024-01-15 |
| `type_contrat` | No | cdi/cdd/stage/prestataire/interim (default: cdi) | cdi |
| `matricule_cnps` | No | CNPS number | CNPS-12345 |
| `numero_compte` | No | Bank RIB | CM21... |
| `banque` | No | Bank name | SGBC |

### Format Rules

- Separator: comma `,` or semicolon `;` (auto-detected)
- Encoding: UTF-8
- First row: header row (column names)
- Password: auto-generated (12 random characters), user changes on first login

## Import Flow

### Step 1: Upload

User selects CSV file via form on `import_employes.php`. Template is available for download.

### Step 2: Read & Parse

- File read with `fgetcsv()`
- Auto-detect separator by examining first line for `,` vs `;` frequency
- Header row mapped to column names
- Each data row becomes an associative array

### Step 3: Validation (line by line)

Validation rules:
- Required fields present and non-empty: `nom`, `prenom`, `email`, `role`, `salaire_base`, `date_embauche`
- Email format valid (filter_var FILTER_VALIDATE_EMAIL)
- Email unique: not already in `utilisateurs` table
- Email unique within file: first occurrence wins, subsequent marked as duplicate
- `role` exists in `roles` table (by name, case-insensitive)
- `agence` exists in `agences` table (by name, if provided)
- `service` exists in `services` table (by name, if provided)
- `type_contrat` is one of: cdi, cdd, stage, prestataire, interim (default: cdi)
- `salaire_base` is a positive number
- `date_embauche` is a valid date in YYYY-MM-DD format

### Step 4: Insertion

For each valid row, a PDO transaction:
1. `INSERT INTO utilisateurs` with generated password (`PASSWORD_BCRYPT`, cost 12)
2. `INSERT INTO contrats_employes` with new user's ID
3. If `matricule` is empty, generate via `generateNumero('EMP')`
4. Commit transaction; on failure, rollback and mark row as error

### Step 5: Report

Display results:
- Summary: total lines, imported, errors
- Error table: line number + reason (in French)
- Link to download error report as CSV
- Flash message with import summary

### Partial Import Behavior

- Valid rows are imported even if other rows have errors
- No rollback of successful imports when errors occur
- Each row processed independently (no single transaction wrapping all rows)

## Audit

Each imported employee triggers `auditLog()` with:
- Action: `import_employe`
- Module: `rh`
- Table: `utilisateurs`
- Record ID: new user ID
- New values JSON: imported employee data

## Technical Details

- No external dependencies (no Composer, no PhpSpreadsheet)
- Uses native `fgetcsv()` for CSV parsing
- Follows PRG pattern: POST processes import, then redirects with flash message for summary
- Detailed errors stored in `$_SESSION['import_errors']` so they survive the redirect and display on `import_employes.php` after PRG
- Error report cleared from session after display
- All DB operations use PDO prepared statements
- All output uses `sanitize()` for escaping