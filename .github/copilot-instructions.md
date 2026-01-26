# AI Coding Agent Instructions

This is a Laravel 12 attendance tracking system (`presensi-mu`) for educational institutions. Use this guide for productive contributions.

## Project Overview

**Purpose**: Student attendance tracking system with class management, curriculum planning, and Excel import/export.

**Tech Stack**: Laravel 12, Bootstrap 5, Maatwebsite Excel, SQLite

**Key Domains**:

- **Academic**: `Pelajaran` (lessons), `Rombel` (class groups), `TahunAjaran` (school years)
- **Students**: `Siswa` (students), `AnggotaPembelajaran` (class participants)
- **Tracking**: `Pembelajaran` (class sessions), `Presensi` (attendance records), `Jurnal` (journals)
- **Organization**: `Tag` (student categorization)

## Architecture Patterns

### UUID-Based Models

Most models use `HasUuids` trait for primary keys (not `AnggotaPembelajaran`). When querying or relating models:

```php
// Models use UUID PK except AnggotaPembelajaran
class Siswa extends Model { use HasUuids; }  // ✓ UUID
class AnggotaPembelajaran extends Model { }  // ✗ No UUID (different by design)
```

### Model Relationships

- `Siswa` → `Rombel` (many-to-one class assignment)
- `Siswa` ↔ `Tag` (many-to-many for categorization)
- `Pembelajaran` → `Pelajaran` + `TahunAjaran` (course instance)
- `Pembelajaran` → `AnggotaPembelajaran` (enrollments) → `Siswa`
- `Presensi` references `Siswa` + `Pembelajaran` (attendance check-in)
- `Jurnal` tracks `Pembelajaran` progress

### Import/Export Pattern

Uses Maatwebsite Excel:

- **Imports** (`app/Imports/`): `SiswaImport` uses `WithUpserts` (NISN-based deduplication)
- **Exports** (`app/Exports/`): `RekapPresensiExport` generates monthly attendance summaries by date columns

Validation in imports happens via `WithValidation::rules()`.

## Development Workflows

### Setup

```bash
composer run setup  # Install dependencies + db migrations + npm build
```

### Local Development

```bash
composer run dev  # Runs concurrent: php serve + queue + pail logs + vite
```

### Testing

```bash
composer run test  # Clears config + runs phpunit (Unit + Feature suites)
```

### Database

```bash
php artisan make:migration create_table_name  # New migration (stored in `database/migrations/`)
php artisan migrate  # Run pending migrations
php artisan tinker  # REPL for quick data inspection
```

## Critical Conventions

1. **Timestamps**: All models auto-include `created_at`/`updated_at` (Eloquent default). Use `protected $dates = ['tanggal']` if custom date fields.

2. **Route Groups**: Controllers are namespaced under `App\Http\Controllers`. Routes use prefix-based grouping (e.g., `/pembelajaran`, `/tags`, `/admin/database`).

3. **Fillable Protection**: Define `$fillable` arrays explicitly on all models to prevent mass-assignment vulnerabilities.

4. **Table Names**: Set `protected $table = 'snake_case'` when table name differs from model name pluralization (e.g., `Rombel` → `rombel`, not `rombels`).

5. **Date Formats**: Database stores dates as `YYYY-MM-DD` strings in `tanggal` columns; use `protected $dates` or `Carbon` for casting.

## Common Tasks

### Add a Field to a Student

1. Create migration: `php artisan make:migration add_field_to_siswa_table`
2. Update `Siswa` model `$fillable`
3. Modify controller to accept new field

### Implement New Report Export

Extend `Maatwebsite\Excel\Concerns` classes. Reference `RekapPresensiExport` for date-column patterns. Use `FromCollection` + `WithHeadings` + `WithMapping` for CSV/Excel.

### Modify Attendance Logic

Edit `Presensi` model relationships and `PresensiController`. Be aware `Presensi` has both `kelas_id` (legacy?) and `pembelajaran_id`—clarify in code comments which relationship to use.

## Key Files by Function

| Purpose         | Location                                                   |
| --------------- | ---------------------------------------------------------- |
| Student data    | [app/Models/Siswa.php](app/Models/Siswa.php)               |
| Attendance core | [app/Models/Presensi.php](app/Models/Presensi.php)         |
| Class sessions  | [app/Models/Pembelajaran.php](app/Models/Pembelajaran.php) |
| Routes          | [routes/web.php](routes/web.php)                           |
| Controllers     | [app/Http/Controllers/](app/Http/Controllers/)             |
| Migrations      | [database/migrations/](database/migrations/)               |

## Testing

- **Location**: `tests/Feature` (integration), `tests/Unit` (isolated)
- **Config**: `phpunit.xml` (SQLite in-memory for tests)
- **Auth**: Uses `middleware('auth')` for protected routes; review `AuthController`

Run tests before push:

```bash
composer run test
```

## External Dependencies

- **maatwebsite/excel**: ^3.1 (Excel import/export with validation & upserts)
- **laravel/framework**: ^12.0 (core framework, migrations, Eloquent ORM)
- **laravel/tinker**: ^2.10.1 (REPL)
- **laravel/pint**: ^1.24 (code formatting)

## Questions? Check These First

- Model relationships: Search for `public function` in [app/Models/](app/Models/)
- Route definitions: See [routes/web.php](routes/web.php) for endpoint structure
- Controller logic: [app/Http/Controllers/PresensiController.php](app/Http/Controllers/PresensiController.php) for attendance workflows
- Database schema: [database/migrations/](database/migrations/) numbered by date
