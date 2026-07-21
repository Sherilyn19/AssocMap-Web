#requires -Version 5.1
<#
.SYNOPSIS
    Installs the AssocMap Association Management module into a Laravel 12 project.

.DESCRIPTION
    This patch creates the Association model, Form Requests, service, controller,
    policy, Blade views, JavaScript, route definitions, and a guarded database
    migration. It also updates Vite's app.js and attempts a safe sidebar insertion.

    The patch is idempotent:
    - Generated files are replaced consistently.
    - Existing files are backed up before their first change in this run.
    - Route/import/sidebar markers prevent duplicate insertion.
    - The database migration uses PostgreSQL catalog checks.

.NOTES
    Target project: D:\Capstone-AssocMap-Web
    Encoding: UTF-8 without BOM
#>

[CmdletBinding()]
param(
    [string]$ProjectRoot = "D:\Capstone-AssocMap-Web",
    [switch]$SkipComposerValidation,
    [switch]$SkipNodeValidation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ---------------------------------------------------------------------------
# Console helpers
# ---------------------------------------------------------------------------
function Write-Step {
    param([string]$Message)
    Write-Host "[ASSOCMAP] $Message" -ForegroundColor Cyan
}

function Write-Success {
    param([string]$Message)
    Write-Host "[OK] $Message" -ForegroundColor Green
}

function Write-WarningMessage {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Failure {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

# ---------------------------------------------------------------------------
# File helpers
# ---------------------------------------------------------------------------
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$BackupRoot = Join-Path $ProjectRoot ".assocmap-backups\association-management-$Timestamp"
$script:BackedUpFiles = @{}

function Assert-LaravelProject {
    param([string]$Root)

    $required = @(
        (Join-Path $Root "artisan"),
        (Join-Path $Root "composer.json"),
        (Join-Path $Root "routes\web.php"),
        (Join-Path $Root "app"),
        (Join-Path $Root "resources")
    )

    foreach ($path in $required) {
        if (-not (Test-Path -LiteralPath $path)) {
            throw "Laravel project validation failed. Missing: $path"
        }
    }
}

function Ensure-Directory {
    param([string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Backup-FileOnce {
    param([string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    $fullPath = [System.IO.Path]::GetFullPath($Path)
    if ($script:BackedUpFiles.ContainsKey($fullPath)) {
        return
    }

    $relative = $fullPath.Substring([System.IO.Path]::GetFullPath($ProjectRoot).Length).TrimStart('\', '/')
    $backupPath = Join-Path $BackupRoot $relative
    Ensure-Directory (Split-Path -Parent $backupPath)
    Copy-Item -LiteralPath $fullPath -Destination $backupPath -Force
    $script:BackedUpFiles[$fullPath] = $true
}

function Write-Utf8NoBom {
    param(
        [string]$Path,
        [string]$Content
    )

    Ensure-Directory (Split-Path -Parent $Path)

    $normalized = $Content -replace "`r?`n", "`r`n"
    $existing = if (Test-Path -LiteralPath $Path) {
        [System.IO.File]::ReadAllText($Path)
    } else {
        $null
    }

    if ($existing -eq $normalized) {
        Write-Host "  Unchanged: $($Path.Substring($ProjectRoot.Length).TrimStart('\'))" -ForegroundColor DarkGray
        return
    }

    Backup-FileOnce $Path
    [System.IO.File]::WriteAllText($Path, $normalized, $Utf8NoBom)
    Write-Host "  Written:   $($Path.Substring($ProjectRoot.Length).TrimStart('\'))" -ForegroundColor Gray
}

function Add-MarkerBlock {
    param(
        [string]$Path,
        [string]$StartMarker,
        [string]$EndMarker,
        [string]$Block
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Cannot patch missing file: $Path"
    }

    $content = [System.IO.File]::ReadAllText($Path)
    $escapedStart = [regex]::Escape($StartMarker)
    $escapedEnd = [regex]::Escape($EndMarker)
    $pattern = "(?s)\r?\n?$escapedStart.*?$escapedEnd\r?\n?"

    $cleanBlock = $Block.TrimEnd()
    if ([regex]::IsMatch($content, $pattern)) {
        $updated = [regex]::Replace(
            $content,
            $pattern,
            "`r`n$cleanBlock`r`n",
            1
        )
    } else {
        $updated = $content.TrimEnd() + "`r`n`r`n" + $cleanBlock + "`r`n"
    }

    if ($updated -ne $content) {
        Backup-FileOnce $Path
        [System.IO.File]::WriteAllText($Path, $updated, $Utf8NoBom)
    }
}

function Add-LineIfMissing {
    param(
        [string]$Path,
        [string]$Line,
        [string]$AnchorPattern
    )

    $content = [System.IO.File]::ReadAllText($Path)
    if ($content.Contains($Line)) {
        return
    }

    Backup-FileOnce $Path

    if ($AnchorPattern -and [regex]::IsMatch($content, $AnchorPattern)) {
        $updated = [regex]::Replace(
            $content,
            $AnchorPattern,
            { param($m) $m.Value + "`r`n" + $Line },
            1
        )
    } else {
        $updated = $Line + "`r`n" + $content
    }

    [System.IO.File]::WriteAllText($Path, $updated, $Utf8NoBom)
}

function Resolve-AdminLayout {
    $candidates = @(
        (Join-Path $ProjectRoot "resources\views\admin-pages\admin-area-management\index.blade.php"),
        (Join-Path $ProjectRoot "resources\views\admin-pages\admin-user-management\index.blade.php"),
        (Join-Path $ProjectRoot "resources\views\admin\areas\index.blade.php"),
        (Join-Path $ProjectRoot "resources\views\admin\users\index.blade.php")
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            $content = [System.IO.File]::ReadAllText($candidate)
            $layoutMatch = [regex]::Match($content, "@extends\(\s*['""]([^'""]+)['""]\s*\)")
            $sectionMatch = [regex]::Match($content, "@section\(\s*['""]([^'""]+)['""]\s*\)")

            if ($layoutMatch.Success) {
                return @{
                    Layout = $layoutMatch.Groups[1].Value
                    Section = if ($sectionMatch.Success) { $sectionMatch.Groups[1].Value } else { "content" }
                }
            }
        }
    }

    return @{
        Layout = "layouts.app"
        Section = "content"
    }
}

function Try-PatchSidebar {
    $sidebarCandidates = Get-ChildItem -Path (Join-Path $ProjectRoot "resources\views") `
        -Recurse -File -Filter "*.blade.php" |
        Where-Object {
            $_.Name -match "sidebar|navigation|nav" -or
            $_.FullName -match "sidebar|navigation"
        }

    foreach ($file in $sidebarCandidates) {
        $content = [System.IO.File]::ReadAllText($file.FullName)

        if ($content.Contains("admin.associations.index")) {
            Write-Success "Association sidebar link already exists."
            return
        }

        if ($content -match "admin\.areas\.index") {
            $link = @'

{{-- ASSOCMAP_ASSOCIATION_NAV_START --}}
<a href="{{ route('admin.associations.index') }}"
   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
          {{ request()->routeIs('admin.associations.*')
              ? 'bg-slate-800 text-white'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M18 18.72a9.094 9.094 0 0 0 3.742-.479 3 3 0 0 0-4.682-2.72m.94 3.198v.75c0 .414-.336.75-.75.75H6.75a.75.75 0 0 1-.75-.75v-.75m12 0a5.971 5.971 0 0 0-3-5.197m-6 5.197a5.971 5.971 0 0 1 3-5.197m3 0a3 3 0 1 0-6 0m6 0a5.97 5.97 0 0 0-3-1.197 5.97 5.97 0 0 0-3 1.197M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
    </svg>
    <span>Association Management</span>
</a>
{{-- ASSOCMAP_ASSOCIATION_NAV_END --}}
'@

            # Insert after the first complete anchor that contains the Area Management route.
            $pattern = "(?s)(<a\b[^>]*href\s*=\s*[""'][^""']*admin\.areas\.index[^""']*[""'][^>]*>.*?</a>)"
            if ([regex]::IsMatch($content, $pattern)) {
                $updated = [regex]::Replace($content, $pattern, { param($m) $m.Value + $link }, 1)
                Backup-FileOnce $file.FullName
                [System.IO.File]::WriteAllText($file.FullName, $updated, $Utf8NoBom)
                Write-Success "Sidebar link added to $($file.FullName.Substring($ProjectRoot.Length).TrimStart('\'))."
                return
            }
        }
    }

    Write-WarningMessage "No safe sidebar insertion point was found. The module route is ready at /admin/associations."
}

# ---------------------------------------------------------------------------
# Validate project and resolve existing layout conventions
# ---------------------------------------------------------------------------
try {
    Write-Step "Validating Laravel project at $ProjectRoot"
    Assert-LaravelProject $ProjectRoot
    Ensure-Directory $BackupRoot

    $layoutInfo = Resolve-AdminLayout
    $AdminLayout = $layoutInfo.Layout
    $ContentSection = $layoutInfo.Section

    Write-Success "Laravel project detected."
    Write-Host "  Blade layout: $AdminLayout" -ForegroundColor DarkGray
    Write-Host "  Content section: $ContentSection" -ForegroundColor DarkGray

    # -----------------------------------------------------------------------
    # PHP: Association model
    # -----------------------------------------------------------------------
    Write-Step "Creating domain model and relationships"

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Models\Association.php") @'
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central BFAR SAAD association record.
 *
 * Operational condition is stored in status_id, while is_archived controls
 * whether the record remains in current administrative use.
 */
final class Association extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area_unit_id',
        'sub_unit_id',
        'program_component_id',
        'field_officer_id',
        'representative_member_id',
        'status_id',
        'address',
        'date_joined',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'date_joined' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    public function areaUnit(): BelongsTo
    {
        return $this->belongsTo(AreaUnit::class);
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function programComponent(): BelongsTo
    {
        return $this->belongsTo(ProgramComponent::class);
    }

    public function fieldOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_officer_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'representative_member_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function memberApplications(): HasMany
    {
        return $this->hasMany(MemberApplication::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function gisLocations(): HasMany
    {
        return $this->hasMany(GisLocation::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeAssignedTo(Builder $query, int $fieldOfficerId): Builder
    {
        return $query->where('field_officer_id', $fieldOfficerId);
    }
}
'@

    # -----------------------------------------------------------------------
    # PHP: Form Requests
    # -----------------------------------------------------------------------
    Write-Step "Creating server-side validation requests"

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Http\Requests\Admin\StoreAssociationRequest.php") @'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreAssociationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware and AssociationPolicy enforce administrator access.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            'address' => preg_replace('/\s+/', ' ', trim((string) $this->input('address'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'area_unit_id' => [
                'required',
                'integer',
                Rule::exists('area_units', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'sub_unit_id' => [
                'required',
                'integer',
                Rule::exists('sub_units', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'program_component_id' => ['required', 'integer', Rule::exists('program_components', 'id')],
            'field_officer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'representative_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')],
            'address' => ['required', 'string', 'max:500'],
            'date_joined' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateBarangayMunicipalityPair($validator);
                $this->validateFieldOfficer($validator);
                $this->validateOperationalStatus($validator);
                $this->validateRepresentativeForCreation($validator);
                $this->validateNormalizedDuplicate($validator);
            },
        ];
    }

    private function validateBarangayMunicipalityPair(Validator $validator): void
    {
        if (!$this->filled(['area_unit_id', 'sub_unit_id'])) {
            return;
        }

        $valid = DB::table('sub_units')
            ->where('id', $this->integer('sub_unit_id'))
            ->where('area_unit_id', $this->integer('area_unit_id'))
            ->where('is_archived', false)
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'sub_unit_id',
                'The selected barangay does not belong to the selected municipality.'
            );
        }
    }

    private function validateFieldOfficer(Validator $validator): void
    {
        if (!$this->filled('field_officer_id')) {
            return;
        }

        $valid = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $this->integer('field_officer_id'))
            ->where('users.is_active', true)
            ->where('roles.role_name', 'Field Officer')
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'field_officer_id',
                'The selected user is not an active Field Officer.'
            );
        }
    }

    private function validateOperationalStatus(Validator $validator): void
    {
        if (!$this->filled('status_id')) {
            return;
        }

        $valid = DB::table('statuses')
            ->where('id', $this->integer('status_id'))
            ->whereIn('status_name', ['Active', 'Inactive'])
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'status_id',
                'Association status must be Active or Inactive.'
            );
        }
    }

    private function validateRepresentativeForCreation(Validator $validator): void
    {
        if (!$this->filled('representative_member_id')) {
            return;
        }

        // A representative is normally assigned after creation because there are
        // no official members until the association record already exists.
        $memberExists = Member::query()
            ->whereKey($this->integer('representative_member_id'))
            ->where('is_archived', false)
            ->exists();

        if (!$memberExists) {
            $validator->errors()->add(
                'representative_member_id',
                'The selected representative is not an active member.'
            );
        }
    }

    private function validateNormalizedDuplicate(Validator $validator): void
    {
        if (!$this->filled(['name', 'area_unit_id'])) {
            return;
        }

        $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $this->input('name'))));

        $duplicate = DB::table('associations')
            ->where('area_unit_id', $this->integer('area_unit_id'))
            ->whereRaw("LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')) = ?", [$normalizedName])
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'name',
                'An association with this name already exists in the selected municipality.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'area_unit_id.required' => 'Please select a municipality.',
            'sub_unit_id.required' => 'Please select a barangay.',
            'program_component_id.required' => 'Please select a program component.',
            'field_officer_id.required' => 'Please select a Field Officer.',
            'status_id.required' => 'Please select an operational status.',
            'date_joined.before_or_equal' => 'The date joined must not be later than today.',
        ];
    }
}
'@

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Http\Requests\Admin\UpdateAssociationRequest.php") @'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Association;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAssociationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            'address' => preg_replace('/\s+/', ' ', trim((string) $this->input('address'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'area_unit_id' => [
                'required',
                'integer',
                Rule::exists('area_units', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'sub_unit_id' => [
                'required',
                'integer',
                Rule::exists('sub_units', 'id')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'program_component_id' => ['required', 'integer', Rule::exists('program_components', 'id')],
            'field_officer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'representative_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')],
            'address' => ['required', 'string', 'max:500'],
            'date_joined' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $association = $this->route('association');

                if (!$association instanceof Association) {
                    $validator->errors()->add('association', 'The association record could not be resolved.');
                    return;
                }

                $this->validateBarangayMunicipalityPair($validator);
                $this->validateFieldOfficer($validator);
                $this->validateOperationalStatus($validator);
                $this->validateRepresentative($validator, $association);
                $this->validateNormalizedDuplicate($validator, $association);
            },
        ];
    }

    private function validateBarangayMunicipalityPair(Validator $validator): void
    {
        if (!$this->filled(['area_unit_id', 'sub_unit_id'])) {
            return;
        }

        $valid = DB::table('sub_units')
            ->where('id', $this->integer('sub_unit_id'))
            ->where('area_unit_id', $this->integer('area_unit_id'))
            ->where('is_archived', false)
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'sub_unit_id',
                'The selected barangay does not belong to the selected municipality.'
            );
        }
    }

    private function validateFieldOfficer(Validator $validator): void
    {
        if (!$this->filled('field_officer_id')) {
            return;
        }

        $valid = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $this->integer('field_officer_id'))
            ->where('users.is_active', true)
            ->where('roles.role_name', 'Field Officer')
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'field_officer_id',
                'The selected user is not an active Field Officer.'
            );
        }
    }

    private function validateOperationalStatus(Validator $validator): void
    {
        if (!$this->filled('status_id')) {
            return;
        }

        $valid = DB::table('statuses')
            ->where('id', $this->integer('status_id'))
            ->whereIn('status_name', ['Active', 'Inactive'])
            ->exists();

        if (!$valid) {
            $validator->errors()->add('status_id', 'Association status must be Active or Inactive.');
        }
    }

    private function validateRepresentative(Validator $validator, Association $association): void
    {
        if (!$this->filled('representative_member_id')) {
            return;
        }

        $valid = DB::table('members')
            ->where('id', $this->integer('representative_member_id'))
            ->where('association_id', $association->id)
            ->where('is_archived', false)
            ->exists();

        if (!$valid) {
            $validator->errors()->add(
                'representative_member_id',
                'The selected representative is not an active member of this association.'
            );
        }
    }

    private function validateNormalizedDuplicate(Validator $validator, Association $association): void
    {
        if (!$this->filled(['name', 'area_unit_id'])) {
            return;
        }

        $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $this->input('name'))));

        $duplicate = DB::table('associations')
            ->where('area_unit_id', $this->integer('area_unit_id'))
            ->where('id', '!=', $association->id)
            ->whereRaw("LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g')) = ?", [$normalizedName])
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'name',
                'An association with this name already exists in the selected municipality.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'area_unit_id.required' => 'Please select a municipality.',
            'sub_unit_id.required' => 'Please select a barangay.',
            'date_joined.before_or_equal' => 'The date joined must not be later than today.',
        ];
    }
}
'@

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Http\Requests\Admin\AssignAssociationRepresentativeRequest.php") @'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Association;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AssignAssociationRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'representative_member_id' => [
                'nullable',
                'integer',
                Rule::exists('members', 'id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $association = $this->route('association');

                if (!$association instanceof Association || !$this->filled('representative_member_id')) {
                    return;
                }

                $valid = DB::table('members')
                    ->where('id', $this->integer('representative_member_id'))
                    ->where('association_id', $association->id)
                    ->where('is_archived', false)
                    ->exists();

                if (!$valid) {
                    $validator->errors()->add(
                        'representative_member_id',
                        'The selected representative is not an active member of this association.'
                    );
                }
            },
        ];
    }
}
'@

    # -----------------------------------------------------------------------
    # PHP: Service
    # -----------------------------------------------------------------------
    Write-Step "Creating transaction-safe association service"

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Services\AssociationManagementService.php") @'
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AssociationManagementService
{
    /**
     * Return the administrator list with constrained eager loading and calculated counts.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Association::query()
            ->with([
                'areaUnit:id,name',
                'subUnit:id,area_unit_id,name',
                'programComponent:id,name',
                'fieldOfficer:id,name,email',
                'representative:id,association_id,first_name,middle_name,last_name,role_in_assoc',
                'status:id,status_name',
            ])
            ->withCount([
                'members as members_count' => fn (Builder $query) => $query->where('is_archived', false),
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        $this->applyIntegerFilter($query, 'area_unit_id', $filters['area_unit_id'] ?? null);
        $this->applyIntegerFilter($query, 'sub_unit_id', $filters['sub_unit_id'] ?? null);
        $this->applyIntegerFilter($query, 'program_component_id', $filters['program_component_id'] ?? null);
        $this->applyIntegerFilter($query, 'field_officer_id', $filters['field_officer_id'] ?? null);
        $this->applyIntegerFilter($query, 'status_id', $filters['status_id'] ?? null);

        match ((string) ($filters['archive_state'] ?? 'current')) {
            'archived' => $query->where('is_archived', true),
            'all' => null,
            default => $query->where('is_archived', false),
        };

        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $query->orderByDesc('name'),
            'date_joined_desc' => $query->orderByDesc('date_joined')->orderBy('name'),
            'date_joined_asc' => $query->orderBy('date_joined')->orderBy('name'),
            'created_desc' => $query->orderByDesc('created_at'),
            'updated_desc' => $query->orderByDesc('updated_at'),
            default => $query->orderBy('name'),
        };

        return $query->paginate(10)->withQueryString();
    }

    /**
     * @return array{total:int, active:int, inactive:int, archived:int}
     */
    public function summary(): array
    {
        $rows = DB::table('associations')
            ->leftJoin('statuses', 'statuses.id', '=', 'associations.status_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE associations.is_archived = FALSE AND statuses.status_name = 'Active') AS active")
            ->selectRaw("COUNT(*) FILTER (WHERE associations.is_archived = FALSE AND statuses.status_name = 'Inactive') AS inactive")
            ->selectRaw('COUNT(*) FILTER (WHERE associations.is_archived = TRUE) AS archived')
            ->first();

        return [
            'total' => (int) ($rows->total ?? 0),
            'active' => (int) ($rows->active ?? 0),
            'inactive' => (int) ($rows->inactive ?? 0),
            'archived' => (int) ($rows->archived ?? 0),
        ];
    }

    /**
     * @return array<string, Collection<int, object>>
     */
    public function formOptions(): array
    {
        return [
            'municipalities' => DB::table('area_units')
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'name']),

            'barangays' => DB::table('sub_units')
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'area_unit_id', 'name']),

            'programComponents' => DB::table('program_components')
                ->orderBy('name')
                ->get(['id', 'name']),

            'fieldOfficers' => DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.role_name', 'Field Officer')
                ->where('users.is_active', true)
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email']),

            'associationStatuses' => DB::table('statuses')
                ->whereIn('status_name', ['Active', 'Inactive'])
                ->orderByRaw("CASE WHEN status_name = 'Active' THEN 1 ELSE 2 END")
                ->get(['id', 'status_name']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $actorId): Association
    {
        return DB::transaction(function () use ($data, $actorId): Association {
            $association = Association::query()->create([
                ...$data,
                'representative_member_id' => null,
                'is_archived' => false,
            ]);

            $this->writeAudit(
                $actorId,
                'CREATE',
                $association->id,
                "Created association '{$association->name}'."
            );

            return $association->fresh();
        }, 3);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Association $association, array $data, int $actorId): Association
    {
        return DB::transaction(function () use ($association, $data, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                throw new RuntimeException('Archived associations must be restored before editing.');
            }

            $beforeOfficer = $locked->field_officer_id;
            $beforeRepresentative = $locked->representative_member_id;
            $beforeStatus = $locked->status_id;

            $locked->fill($data);
            $locked->save();

            $changes = array_keys($locked->getChanges());
            $this->writeAudit(
                $actorId,
                'UPDATE',
                $locked->id,
                'Updated association master information: '.implode(', ', $changes).'.'
            );

            if ($beforeOfficer !== $locked->field_officer_id) {
                $this->writeAudit(
                    $actorId,
                    'ASSIGN_OFFICER',
                    $locked->id,
                    "Reassigned Field Officer from user {$beforeOfficer} to user {$locked->field_officer_id}."
                );
            }

            if ($beforeStatus !== $locked->status_id) {
                $this->writeAudit(
                    $actorId,
                    'STATUS_CHANGE',
                    $locked->id,
                    "Changed operational status from {$beforeStatus} to {$locked->status_id}."
                );
            }

            if ($beforeRepresentative !== $locked->representative_member_id) {
                $this->auditRepresentativeChange(
                    $actorId,
                    $locked->id,
                    $beforeRepresentative,
                    $locked->representative_member_id
                );
            }

            return $locked->fresh();
        }, 3);
    }

    public function archive(Association $association, int $actorId): Association
    {
        return DB::transaction(function () use ($association, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                return $locked;
            }

            $locked->forceFill(['is_archived' => true])->save();

            if (Schema::hasTable('gis_locations')) {
                DB::table('gis_locations')
                    ->where('association_id', $locked->id)
                    ->where('is_published', true)
                    ->update([
                        'is_published' => false,
                        'updated_at' => now(),
                    ]);
            }

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $locked->id,
                "Archived association '{$locked->name}' and unpublished its GIS locations."
            );

            return $locked->fresh();
        }, 3);
    }

    public function restore(Association $association, int $actorId): Association
    {
        return DB::transaction(function () use ($association, $actorId): Association {
            /** @var Association $locked */
            $locked = Association::query()
                ->with(['areaUnit:id,is_archived', 'subUnit:id,area_unit_id,is_archived'])
                ->lockForUpdate()
                ->findOrFail($association->id);

            if (!$locked->is_archived) {
                return $locked;
            }

            if ($locked->areaUnit?->is_archived || $locked->subUnit?->is_archived) {
                throw new RuntimeException(
                    'The association cannot be restored while its municipality or barangay is archived.'
                );
            }

            if ((int) $locked->subUnit?->area_unit_id !== (int) $locked->area_unit_id) {
                throw new RuntimeException(
                    'The association cannot be restored because its barangay no longer belongs to its municipality.'
                );
            }

            if ($locked->representative_member_id !== null) {
                $validRepresentative = Member::query()
                    ->whereKey($locked->representative_member_id)
                    ->where('association_id', $locked->id)
                    ->where('is_archived', false)
                    ->exists();

                if (!$validRepresentative) {
                    $locked->representative_member_id = null;
                }
            }

            $locked->is_archived = false;
            $locked->save();

            $this->writeAudit(
                $actorId,
                'RESTORE',
                $locked->id,
                "Restored association '{$locked->name}'. GIS locations remain unpublished."
            );

            return $locked->fresh();
        }, 3);
    }

    public function assignRepresentative(
        Association $association,
        ?int $representativeMemberId,
        int $actorId
    ): Association {
        return DB::transaction(function () use (
            $association,
            $representativeMemberId,
            $actorId
        ): Association {
            /** @var Association $locked */
            $locked = Association::query()->lockForUpdate()->findOrFail($association->id);

            if ($locked->is_archived) {
                throw new RuntimeException('Restore the association before changing its representative.');
            }

            $previous = $locked->representative_member_id;
            $locked->representative_member_id = $representativeMemberId;
            $locked->save();

            $this->auditRepresentativeChange(
                $actorId,
                $locked->id,
                $previous,
                $representativeMemberId
            );

            return $locked->fresh();
        }, 3);
    }

    public function findDetailed(Association $association): Association
    {
        return $association->load([
            'areaUnit:id,name',
            'subUnit:id,name,area_unit_id',
            'programComponent:id,name',
            'fieldOfficer:id,name,email',
            'representative:id,association_id,first_name,middle_name,last_name,role_in_assoc',
            'status:id,status_name',
        ])->loadCount([
            'members as members_count' => fn (Builder $query) => $query->where('is_archived', false),
            'memberApplications as pending_applications_count' => function (Builder $query): void {
                $query->whereHas('status', fn (Builder $status) => $status->where('status_name', 'Pending'));
            },
            'projects as projects_count' => fn (Builder $query) => $query->where('is_archived', false),
            'trainings as trainings_count' => fn (Builder $query) => $query->where('is_archived', false),
            'gisLocations as gis_locations_count',
            'gisLocations as published_gis_locations_count' => fn (Builder $query) => $query->where('is_published', true),
        ]);
    }

    /**
     * @return Collection<int, Member>
     */
    public function eligibleRepresentatives(Association $association): Collection
    {
        return Member::query()
            ->where('association_id', $association->id)
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'association_id',
                'first_name',
                'middle_name',
                'last_name',
                'role_in_assoc',
            ]);
    }

    private function applyIntegerFilter(Builder $query, string $column, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            $query->where($column, (int) $value);
        }
    }

    private function auditRepresentativeChange(
        int $actorId,
        int $associationId,
        ?int $previous,
        ?int $current
    ): void {
        $action = match (true) {
            $previous === null && $current !== null => 'ASSIGN_REPRESENTATIVE',
            $previous !== null && $current === null => 'REMOVE_REPRESENTATIVE',
            default => 'CHANGE_REPRESENTATIVE',
        };

        $this->writeAudit(
            $actorId,
            $action,
            $associationId,
            "Representative changed from ".($previous ?? 'none')." to ".($current ?? 'none')."."
        );
    }

    private function writeAudit(
        int $actorId,
        string $action,
        int $associationId,
        string $details
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $action,
            'module' => 'Association',
            'record_id' => $associationId,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}
'@

    # -----------------------------------------------------------------------
    # PHP: Controller
    # -----------------------------------------------------------------------
    Write-Step "Creating thin resource controller"

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Http\Controllers\Admin\AssociationManagementController.php") @'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignAssociationRepresentativeRequest;
use App\Http\Requests\Admin\StoreAssociationRequest;
use App\Http\Requests\Admin\UpdateAssociationRequest;
use App\Models\Association;
use App\Services\AssociationManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class AssociationManagementController extends Controller
{
    public function __construct(
        private readonly AssociationManagementService $service
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only([
            'search',
            'area_unit_id',
            'sub_unit_id',
            'program_component_id',
            'field_officer_id',
            'status_id',
            'archive_state',
            'sort',
        ]);

        return view('admin-pages.admin-association-management.index', [
            'associations' => $this->service->paginate($filters),
            'summary' => $this->service->summary(),
            'filters' => $filters,
            ...$this->service->formOptions(),
        ]);
    }

    public function store(StoreAssociationRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $this->actorId($request));

        return redirect()
            ->route('admin.associations.index')
            ->with('success', 'Association created successfully.');
    }

    public function show(Association $association): View
    {
        return view('admin-pages.admin-association-management.show', [
            'association' => $this->service->findDetailed($association),
            'eligibleRepresentatives' => $this->service->eligibleRepresentatives($association),
        ]);
    }

    public function update(
        UpdateAssociationRequest $request,
        Association $association
    ): RedirectResponse {
        try {
            $updated = $this->service->update(
                $association,
                $request->validated(),
                $this->actorId($request)
            );

            $message = $association->field_officer_id !== $updated->field_officer_id
                ? 'Association updated and Field Officer reassigned successfully.'
                : 'Association updated successfully.';

            return redirect()
                ->route('admin.associations.index')
                ->with('success', $message);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function archive(Request $request, Association $association): RedirectResponse
    {
        try {
            $this->service->archive($association, $this->actorId($request));

            return back()->with('success', 'Association archived successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The association could not be archived. Please try again.');
        }
    }

    public function restore(Request $request, Association $association): RedirectResponse
    {
        try {
            $this->service->restore($association, $this->actorId($request));

            return back()->with('success', 'Association restored successfully.');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function representative(
        AssignAssociationRepresentativeRequest $request,
        Association $association
    ): RedirectResponse {
        $this->service->assignRepresentative(
            $association,
            $request->validated('representative_member_id'),
            $this->actorId($request)
        );

        return back()->with('success', 'Association Representative updated successfully.');
    }

    private function actorId(Request $request): int
    {
        $actorId = auth()->id()
            ?? $request->session()->get('user_id')
            ?? $request->session()->get('authenticated_user_id');

        abort_if(!$actorId, 401, 'Authenticated user could not be identified.');

        return (int) $actorId;
    }
}
'@

    # -----------------------------------------------------------------------
    # PHP: Policy
    # -----------------------------------------------------------------------
    Write-Step "Creating policy for server-side authorization"

    Write-Utf8NoBom (Join-Path $ProjectRoot "app\Policies\AssociationPolicy.php") @'
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Association;
use App\Models\User;

final class AssociationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->role_name, [
            'System Administrator',
            'Field Officer',
        ], true);
    }

    public function view(User $user, Association $association): bool
    {
        return match ($user->role?->role_name) {
            'System Administrator' => true,
            'Field Officer' => (int) $association->field_officer_id === (int) $user->id,
            'Association Member' => (int) $user->association_id === (int) $association->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role?->role_name === 'System Administrator';
    }

    public function update(User $user, Association $association): bool
    {
        return $user->role?->role_name === 'System Administrator';
    }

    public function archive(User $user, Association $association): bool
    {
        return $user->role?->role_name === 'System Administrator';
    }

    public function restore(User $user, Association $association): bool
    {
        return $user->role?->role_name === 'System Administrator';
    }
}
'@

    # -----------------------------------------------------------------------
    # Database migration
    # -----------------------------------------------------------------------
    Write-Step "Creating guarded PostgreSQL integrity migration"

    $migrationPath = Join-Path $ProjectRoot "database\migrations\2026_07_17_000001_harden_association_management_constraints.php"
    Write-Utf8NoBom $migrationPath @'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Fail clearly before applying NOT NULL when legacy data is incomplete.
        $invalidRows = DB::table('associations')
            ->whereNull('sub_unit_id')
            ->orWhereNull('program_component_id')
            ->orWhereNull('address')
            ->orWhereNull('date_joined')
            ->count();

        if ($invalidRows > 0) {
            throw new RuntimeException(
                "Association constraint migration stopped: {$invalidRows} record(s) contain required NULL values."
            );
        }

        DB::statement('ALTER TABLE associations ALTER COLUMN sub_unit_id SET NOT NULL');
        DB::statement('ALTER TABLE associations ALTER COLUMN program_component_id SET NOT NULL');
        DB::statement('ALTER TABLE associations ALTER COLUMN address SET NOT NULL');
        DB::statement('ALTER TABLE associations ALTER COLUMN date_joined SET NOT NULL');

        // A composite FK makes an invalid municipality/barangay pair impossible.
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'sub_units_id_area_unit_id_unique'
    ) THEN
        ALTER TABLE sub_units
            ADD CONSTRAINT sub_units_id_area_unit_id_unique
            UNIQUE (id, area_unit_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_associations_sub_unit_area'
    ) THEN
        ALTER TABLE associations
            ADD CONSTRAINT fk_associations_sub_unit_area
            FOREIGN KEY (sub_unit_id, area_unit_id)
            REFERENCES sub_units (id, area_unit_id)
            ON UPDATE RESTRICT
            ON DELETE RESTRICT;
    END IF;
END
$$;
SQL);

        // Enforce duplicate checking after trimming, collapsing spaces, and lowercasing.
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS associations_normalized_name_area_unique
ON associations (
    area_unit_id,
    LOWER(REGEXP_REPLACE(BTRIM(name), '\s+', ' ', 'g'))
)
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS associations_normalized_name_area_unique');
        DB::statement('ALTER TABLE associations DROP CONSTRAINT IF EXISTS fk_associations_sub_unit_area');

        // Required columns intentionally remain NOT NULL during rollback because
        // loosening production data integrity is unsafe and not needed by the module.
    }
};
'@

    # -----------------------------------------------------------------------
    # Blade views
    # -----------------------------------------------------------------------
    Write-Step "Creating accessible Tailwind administration views"

    $indexTemplate = @'
@extends('__ADMIN_LAYOUT__')

@section('__CONTENT_SECTION__')
<div
    class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8"
    data-association-page
    data-barangays='@json($barangays)'
>
    {{-- Page heading --}}
    <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                BFAR SAAD Phase II
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Association Management
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Create, organize, assign, and maintain fisherfolk associations under the BFAR SAAD Phase II program.
            </p>
        </div>

        <button
            type="button"
            data-open-modal="create-association-modal"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5
                   text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700
                   focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Association
        </button>
    </header>

    {{-- Flash and validation feedback --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
             role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
             role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
             role="alert">
            <p class="font-semibold">Please correct the highlighted information.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Summary cards --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Association summary">
        @php
            $cards = [
                ['label' => 'Total Associations', 'value' => $summary['total'], 'hint' => 'All records'],
                ['label' => 'Active Associations', 'value' => $summary['active'], 'hint' => 'Current and operational'],
                ['label' => 'Inactive Associations', 'value' => $summary['inactive'], 'hint' => 'Current but inactive'],
                ['label' => 'Archived Associations', 'value' => $summary['archived'], 'hint' => 'Retained historical records'],
            ];
        @endphp

        @foreach ($cards as $card)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    {{-- Filters --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.associations.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <div class="relative mt-1.5">
                        <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-4.35-4.35m1.1-5.4a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Association name or address"
                            class="min-h-11 w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-3
                                   text-sm text-slate-900 placeholder:text-slate-400
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Municipality</span>
                    <select name="area_unit_id" data-filter-municipality
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2
                                   text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All municipalities</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}"
                                @selected((string) ($filters['area_unit_id'] ?? '') === (string) $municipality->id)>
                                {{ $municipality->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Barangay</span>
                    <select name="sub_unit_id" data-filter-barangay
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2
                                   text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All barangays</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Program component</span>
                    <select name="program_component_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All components</option>
                        @foreach ($programComponents as $component)
                            <option value="{{ $component->id }}"
                                @selected((string) ($filters['program_component_id'] ?? '') === (string) $component->id)>
                                {{ $component->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Field Officer</span>
                    <select name="field_officer_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All Field Officers</option>
                        @foreach ($fieldOfficers as $officer)
                            <option value="{{ $officer->id }}"
                                @selected((string) ($filters['field_officer_id'] ?? '') === (string) $officer->id)>
                                {{ $officer->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Operational status</span>
                    <select name="status_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Active and inactive</option>
                        @foreach ($associationStatuses as $status)
                            <option value="{{ $status->id }}"
                                @selected((string) ($filters['status_id'] ?? '') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Record state</span>
                    <select name="archive_state"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="current" @selected(($filters['archive_state'] ?? 'current') === 'current')>Current</option>
                        <option value="archived" @selected(($filters['archive_state'] ?? '') === 'archived')>Archived</option>
                        <option value="all" @selected(($filters['archive_state'] ?? '') === 'all')>All records</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select name="sort"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>Name A–Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z–A</option>
                        <option value="date_joined_desc" @selected(($filters['sort'] ?? '') === 'date_joined_desc')>Newest joined</option>
                        <option value="date_joined_asc" @selected(($filters['sort'] ?? '') === 'date_joined_asc')>Oldest joined</option>
                        <option value="created_desc" @selected(($filters['sort'] ?? '') === 'created_desc')>Recently created</option>
                        <option value="updated_desc" @selected(($filters['sort'] ?? '') === 'updated_desc')>Recently updated</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('admin.associations.index') }}"
                   class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 py-2
                          text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset filters
                </a>
                <button type="submit"
                        class="inline-flex min-h-10 items-center rounded-lg bg-slate-800 px-4 py-2
                               text-sm font-semibold text-white hover:bg-slate-700">
                    Apply filters
                </button>
            </div>
        </form>
    </section>

    {{-- Desktop table --}}
    <section class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Association', 'Location', 'Program', 'Field Officer', 'Representative', 'Members', 'Status', 'Record State', 'Actions'] as $heading)
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ $heading }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($associations as $association)
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-4">
                                <p class="max-w-xs font-semibold text-slate-900">{{ $association->name }}</p>
                                <p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $association->address }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                <p>{{ $association->areaUnit?->name ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $association->subUnit?->name ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                {{ $association->programComponent?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                <p>{{ $association->fieldOfficer?->name ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $association->fieldOfficer?->email }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">
                                @if ($association->representative)
                                    {{ $association->representative->first_name }}
                                    {{ $association->representative->last_name }}
                                @else
                                    <span class="text-slate-500">Not assigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm font-semibold tabular-nums text-slate-900">
                                {{ $association->members_count }}
                            </td>
                            <td class="px-4 py-4">
                                @php $isActive = $association->status?->status_name === 'Active'; @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $association->status?->status_name ?? 'Unknown' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                    {{ $association->is_archived ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $association->is_archived ? 'Archived' : 'Current' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.associations.show', $association) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold
                                              text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>

                                    @unless ($association->is_archived)
                                        <button type="button"
                                                data-edit-association='@json([
                                                    "id" => $association->id,
                                                    "name" => $association->name,
                                                    "area_unit_id" => $association->area_unit_id,
                                                    "sub_unit_id" => $association->sub_unit_id,
                                                    "program_component_id" => $association->program_component_id,
                                                    "field_officer_id" => $association->field_officer_id,
                                                    "status_id" => $association->status_id,
                                                    "address" => $association->address,
                                                    "date_joined" => optional($association->date_joined)->format("Y-m-d"),
                                                    "representative_member_id" => $association->representative_member_id,
                                                    "update_url" => route("admin.associations.update", $association),
                                                ])'
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold
                                                       text-slate-700 hover:bg-slate-50">
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('admin.associations.archive', $association) }}"
                                              data-confirm-form
                                              data-confirm-message="Archive this association? Existing records will remain and published GIS locations will be unpublished.">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold
                                                           text-red-700 hover:bg-red-50">
                                                Archive
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.associations.restore', $association) }}"
                                              data-confirm-form
                                              data-confirm-message="Restore this association? GIS locations will remain unpublished.">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold
                                                           text-emerald-700 hover:bg-emerald-50">
                                                Restore
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                    <svg class="h-6 w-6 text-slate-500" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M18 18.72a9.094 9.094 0 0 0 3.742-.479 3 3 0 0 0-4.682-2.72M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </div>
                                <h2 class="mt-4 font-semibold text-slate-900">No associations found</h2>
                                <p class="mt-1 text-sm text-slate-500">Adjust the filters or create the first association record.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Mobile cards --}}
    <section class="space-y-3 lg:hidden">
        @forelse ($associations as $association)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ $association->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $association->subUnit?->name }}, {{ $association->areaUnit?->name }}
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ $association->is_archived ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-800' }}">
                        {{ $association->is_archived ? 'Archived' : 'Current' }}
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Program</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $association->programComponent?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Members</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $association->members_count }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-slate-500">Field Officer</dt>
                        <dd class="mt-1 font-medium text-slate-800">{{ $association->fieldOfficer?->name ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <a href="{{ route('admin.associations.show', $association) }}"
                       class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2
                              text-sm font-semibold text-slate-700">
                        View details
                    </a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <p class="font-semibold text-slate-900">No associations found</p>
                <p class="mt-1 text-sm text-slate-500">Adjust the filters or create a record.</p>
            </div>
        @endforelse
    </section>

    <div>
        {{ $associations->links() }}
    </div>

    {{-- Create modal --}}
    <div id="create-association-modal" data-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="create-association-title">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h2 id="create-association-title" class="text-lg font-bold text-slate-900">Add Association</h2>
                        <p class="mt-1 text-sm text-slate-500">Create the official BFAR SAAD association master record.</p>
                    </div>
                    <button type="button" data-close-modal
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">
                        <span aria-hidden="true" class="text-xl">&times;</span>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.associations.store') }}" class="space-y-6 p-6">
                    @csrf
                    @include('admin-pages.admin-association-management.partials.form-fields', [
                        'prefix' => 'create',
                        'association' => null,
                    ])
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" data-close-modal
                                class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit"
                                class="min-h-11 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Create Association
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit modal --}}
    <div id="edit-association-modal" data-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/50" data-close-modal></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="edit-association-title">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <div>
                        <h2 id="edit-association-title" class="text-lg font-bold text-slate-900">Edit Association</h2>
                        <p class="mt-1 text-sm text-slate-500">Update the association master information.</p>
                    </div>
                    <button type="button" data-close-modal
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">
                        <span aria-hidden="true" class="text-xl">&times;</span>
                    </button>
                </div>

                <form method="POST" action="" data-edit-form class="space-y-6 p-6">
                    @csrf
                    @method('PUT')
                    @include('admin-pages.admin-association-management.partials.form-fields', [
                        'prefix' => 'edit',
                        'association' => null,
                    ])
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button type="button" data-close-modal
                                class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit"
                                class="min-h-11 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
'@

    $indexTemplate = $indexTemplate.Replace('__ADMIN_LAYOUT__', $AdminLayout).Replace('__CONTENT_SECTION__', $ContentSection)
    Write-Utf8NoBom (Join-Path $ProjectRoot "resources\views\admin-pages\admin-association-management\index.blade.php") $indexTemplate

    Write-Utf8NoBom (Join-Path $ProjectRoot "resources\views\admin-pages\admin-association-management\partials\form-fields.blade.php") @'
@php
    $inputClass = 'mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200';
@endphp

<section aria-labelledby="{{ $prefix }}-basic-heading">
    <h3 id="{{ $prefix }}-basic-heading" class="text-sm font-bold uppercase tracking-wide text-slate-700">
        Association Information
    </h3>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block md:col-span-2">
            <span class="text-sm font-medium text-slate-700">Association name <span class="text-red-600">*</span></span>
            <input type="text" name="name" data-field="name" maxlength="255" required
                   value="{{ old('name', $association?->name) }}"
                   class="{{ $inputClass }}">
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Municipality <span class="text-red-600">*</span></span>
            <select name="area_unit_id" data-field="area_unit_id" data-municipality required class="{{ $inputClass }}">
                <option value="">Select municipality</option>
                @foreach ($municipalities as $municipality)
                    <option value="{{ $municipality->id }}"
                        @selected((string) old('area_unit_id', $association?->area_unit_id) === (string) $municipality->id)>
                        {{ $municipality->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Barangay <span class="text-red-600">*</span></span>
            <select name="sub_unit_id" data-field="sub_unit_id" data-barangay required class="{{ $inputClass }}">
                <option value="">Select municipality first</option>
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Program component <span class="text-red-600">*</span></span>
            <select name="program_component_id" data-field="program_component_id" required class="{{ $inputClass }}">
                <option value="">Select component</option>
                @foreach ($programComponents as $component)
                    <option value="{{ $component->id }}"
                        @selected((string) old('program_component_id', $association?->program_component_id) === (string) $component->id)>
                        {{ $component->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Date joined <span class="text-red-600">*</span></span>
            <input type="date" name="date_joined" data-field="date_joined" required max="{{ now()->format('Y-m-d') }}"
                   value="{{ old('date_joined', optional($association?->date_joined)->format('Y-m-d')) }}"
                   class="{{ $inputClass }}">
        </label>

        <label class="block md:col-span-2">
            <span class="text-sm font-medium text-slate-700">Complete address <span class="text-red-600">*</span></span>
            <textarea name="address" data-field="address" rows="3" maxlength="500" required
                      class="{{ $inputClass }}">{{ old('address', $association?->address) }}</textarea>
        </label>
    </div>
</section>

<section class="border-t border-slate-200 pt-5" aria-labelledby="{{ $prefix }}-assignment-heading">
    <h3 id="{{ $prefix }}-assignment-heading" class="text-sm font-bold uppercase tracking-wide text-slate-700">
        Assignment and Status
    </h3>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block">
            <span class="text-sm font-medium text-slate-700">Field Officer <span class="text-red-600">*</span></span>
            <select name="field_officer_id" data-field="field_officer_id" required class="{{ $inputClass }}">
                <option value="">Select active Field Officer</option>
                @foreach ($fieldOfficers as $officer)
                    <option value="{{ $officer->id }}"
                        @selected((string) old('field_officer_id', $association?->field_officer_id) === (string) $officer->id)>
                        {{ $officer->name }} — {{ $officer->email }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Operational status <span class="text-red-600">*</span></span>
            <select name="status_id" data-field="status_id" required class="{{ $inputClass }}">
                @foreach ($associationStatuses as $status)
                    <option value="{{ $status->id }}"
                        @selected((string) old('status_id', $association?->status_id ?? $associationStatuses->firstWhere('status_name', 'Active')?->id) === (string) $status->id)>
                        {{ $status->status_name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <p class="mt-3 text-xs leading-5 text-slate-500">
        The representative is assigned from the association detail page after official members are available.
    </p>
</section>
'@

    $showTemplate = @'
@extends('__ADMIN_LAYOUT__')

@section('__CONTENT_SECTION__')
<div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('admin.associations.index') }}"
               class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                ← Back to Association Management
            </a>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                {{ $association->name }}
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                {{ $association->subUnit?->name }}, {{ $association->areaUnit?->name }}
                · {{ $association->programComponent?->name }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-semibold
                {{ $association->status?->status_name === 'Active'
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-amber-100 text-amber-800' }}">
                {{ $association->status?->status_name }}
            </span>
            <span class="rounded-full px-3 py-1 text-xs font-semibold
                {{ $association->is_archived
                    ? 'bg-slate-200 text-slate-700'
                    : 'bg-blue-100 text-blue-800' }}">
                {{ $association->is_archived ? 'Archived' : 'Current' }}
            </span>
        </div>
    </header>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6" aria-label="Related record counts">
        @foreach ([
            ['Official members', $association->members_count],
            ['Pending applications', $association->pending_applications_count],
            ['Projects', $association->projects_count],
            ['Trainings', $association->trainings_count],
            ['GIS locations', $association->gis_locations_count],
            ['Published GIS', $association->published_gis_locations_count],
        ] as [$label, $value])
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">{{ $value }}</p>
            </article>
        @endforeach
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-lg font-bold text-slate-900">Association overview</h2>
            <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                @foreach ([
                    ['Official name', $association->name],
                    ['Municipality', $association->areaUnit?->name],
                    ['Barangay', $association->subUnit?->name],
                    ['Program component', $association->programComponent?->name],
                    ['Date joined', optional($association->date_joined)->format('F j, Y')],
                    ['Field Officer', $association->fieldOfficer?->name],
                    ['Representative', $association->representative
                        ? $association->representative->first_name.' '.$association->representative->last_name
                        : 'Not assigned'],
                    ['Created', optional($association->created_at)->format('F j, Y g:i A')],
                    ['Last updated', optional($association->updated_at)->format('F j, Y g:i A')],
                ] as [$label, $value])
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach

                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Complete address</dt>
                    <dd class="mt-1 text-sm leading-6 text-slate-900">{{ $association->address }}</dd>
                </div>
            </dl>
        </section>

        <aside class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Association Representative</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Only an active official member of this association can be assigned.
            </p>

            @unless ($association->is_archived)
                <form method="POST"
                      action="{{ route('admin.associations.representative', $association) }}"
                      class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Representative</span>
                        <select name="representative_member_id"
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Not assigned</option>
                            @foreach ($eligibleRepresentatives as $member)
                                <option value="{{ $member->id }}"
                                    @selected((string) $association->representative_member_id === (string) $member->id)>
                                    {{ $member->first_name }} {{ $member->last_name }}
                                    @if ($member->role_in_assoc) — {{ $member->role_in_assoc }} @endif
                                </option>
                            @endforeach
                        </select>
                    </label>

                    @if ($eligibleRepresentatives->isEmpty())
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            No eligible members available.
                        </p>
                    @endif

                    <button type="submit"
                            class="min-h-11 w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold
                                   text-white hover:bg-slate-700">
                        Update Representative
                    </button>
                </form>
            @else
                <p class="mt-5 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">
                    Restore this association before changing its representative.
                </p>
            @endunless
        </aside>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Related modules</h2>
        <p class="mt-2 text-sm text-slate-600">
            Association Management provides summaries. Detailed operations remain in their respective modules.
        </p>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['Members', 'Applications', 'Projects', 'Trainings', 'Monitoring', 'GIS Locations', 'Audit History'] as $module)
                <div class="rounded-lg border border-slate-200 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">{{ $module }}</p>
                    <p class="mt-1 text-xs text-slate-500">Module navigation becomes active when its route is implemented.</p>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
'@

    $showTemplate = $showTemplate.Replace('__ADMIN_LAYOUT__', $AdminLayout).Replace('__CONTENT_SECTION__', $ContentSection)
    Write-Utf8NoBom (Join-Path $ProjectRoot "resources\views\admin-pages\admin-association-management\show.blade.php") $showTemplate

    # -----------------------------------------------------------------------
    # JavaScript
    # -----------------------------------------------------------------------
    Write-Step "Creating dependency-free module JavaScript"

    Write-Utf8NoBom (Join-Path $ProjectRoot "resources\js\admin-association-management.js") @'
/**
 * Association Management interactions.
 *
 * Uses native DOM APIs only so the module remains small, testable, and compatible
 * with the existing Vite/Tailwind stack.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-association-page]');

    if (!page) {
        return;
    }

    const barangays = safelyParseJson(page.dataset.barangays, []);

    function safelyParseJson(value, fallback) {
        try {
            return JSON.parse(value ?? '');
        } catch {
            return fallback;
        }
    }

    function openModal(modal) {
        if (!modal) return;

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => {
            modal.querySelector('input, select, textarea, button')?.focus();
        }, 0);
    }

    function closeModal(modal) {
        if (!modal) return;

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function populateBarangays(select, municipalityId, selectedId = '') {
        if (!select) return;

        const matching = barangays.filter(
            (barangay) => String(barangay.area_unit_id) === String(municipalityId)
        );

        select.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = municipalityId
            ? (matching.length ? 'Select barangay' : 'No active barangays available')
            : 'Select municipality first';
        select.appendChild(placeholder);

        matching.forEach((barangay) => {
            const option = document.createElement('option');
            option.value = String(barangay.id);
            option.textContent = barangay.name;
            option.selected = String(barangay.id) === String(selectedId);
            select.appendChild(option);
        });

        select.disabled = !municipalityId || matching.length === 0;
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(document.getElementById(button.dataset.openModal));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('[data-modal]')));
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal(modal);
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        const municipality = form.querySelector('[data-municipality]');
        const barangay = form.querySelector('[data-barangay]');

        if (!municipality || !barangay) return;

        populateBarangays(barangay, municipality.value, barangay.dataset.selectedValue ?? '');

        municipality.addEventListener('change', () => {
            populateBarangays(barangay, municipality.value);
        });
    });

    const filterMunicipality = document.querySelector('[data-filter-municipality]');
    const filterBarangay = document.querySelector('[data-filter-barangay]');

    if (filterMunicipality && filterBarangay) {
        const params = new URLSearchParams(window.location.search);
        populateBarangays(
            filterBarangay,
            filterMunicipality.value,
            params.get('sub_unit_id') ?? ''
        );

        filterMunicipality.addEventListener('change', () => {
            populateBarangays(filterBarangay, filterMunicipality.value);
        });
    }

    document.querySelectorAll('[data-edit-association]').forEach((button) => {
        button.addEventListener('click', () => {
            const association = safelyParseJson(button.dataset.editAssociation, null);
            const modal = document.getElementById('edit-association-modal');
            const form = modal?.querySelector('[data-edit-form]');

            if (!association || !modal || !form) return;

            form.action = association.update_url;

            Object.entries(association).forEach(([key, value]) => {
                const field = form.querySelector(`[data-field="${key}"]`);
                if (field && key !== 'sub_unit_id') {
                    field.value = value ?? '';
                }
            });

            const municipality = form.querySelector('[data-municipality]');
            const barangay = form.querySelector('[data-barangay]');
            populateBarangays(barangay, municipality?.value, association.sub_unit_id);

            openModal(modal);
        });
    });

    document.querySelectorAll('[data-confirm-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirmMessage || 'Continue with this action?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
'@

    # -----------------------------------------------------------------------
    # Routes and Vite entry
    # -----------------------------------------------------------------------
    Write-Step "Updating Laravel routes and Vite entry"

    $webRoutes = Join-Path $ProjectRoot "routes\web.php"
    Add-LineIfMissing `
        -Path $webRoutes `
        -Line "use App\Http\Controllers\Admin\AssociationManagementController;" `
        -AnchorPattern "(?m)^<\?php\s*$"

    $routeBlock = @'
// ASSOCMAP_ASSOCIATION_ROUTES_START
Route::prefix('admin/associations')
    ->name('admin.associations.')
    ->middleware('assocmap.auth:System Administrator')
    ->controller(AssociationManagementController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{association}', 'show')->name('show');
        Route::put('/{association}', 'update')->name('update');
        Route::patch('/{association}/archive', 'archive')->name('archive');
        Route::patch('/{association}/restore', 'restore')->name('restore');
        Route::patch('/{association}/representative', 'representative')->name('representative');
    });
// ASSOCMAP_ASSOCIATION_ROUTES_END
'@

    Add-MarkerBlock `
        -Path $webRoutes `
        -StartMarker "// ASSOCMAP_ASSOCIATION_ROUTES_START" `
        -EndMarker "// ASSOCMAP_ASSOCIATION_ROUTES_END" `
        -Block $routeBlock

    $appJs = Join-Path $ProjectRoot "resources\js\app.js"
    if (-not (Test-Path -LiteralPath $appJs)) {
        Write-Utf8NoBom $appJs "import './admin-association-management';`r`n"
    } else {
        Add-LineIfMissing `
            -Path $appJs `
            -Line "import './admin-association-management';" `
            -AnchorPattern "(?m)^import .+;\s*$"
    }

    Try-PatchSidebar

    # -----------------------------------------------------------------------
    # Verification
    # -----------------------------------------------------------------------
    Write-Step "Running static verification"

    $expectedFiles = @(
        "app\Models\Association.php",
        "app\Http\Requests\Admin\StoreAssociationRequest.php",
        "app\Http\Requests\Admin\UpdateAssociationRequest.php",
        "app\Http\Requests\Admin\AssignAssociationRepresentativeRequest.php",
        "app\Services\AssociationManagementService.php",
        "app\Http\Controllers\Admin\AssociationManagementController.php",
        "app\Policies\AssociationPolicy.php",
        "database\migrations\2026_07_17_000001_harden_association_management_constraints.php",
        "resources\views\admin-pages\admin-association-management\index.blade.php",
        "resources\views\admin-pages\admin-association-management\show.blade.php",
        "resources\views\admin-pages\admin-association-management\partials\form-fields.blade.php",
        "resources\js\admin-association-management.js"
    )

    foreach ($relativePath in $expectedFiles) {
        $absolutePath = Join-Path $ProjectRoot $relativePath
        if (-not (Test-Path -LiteralPath $absolutePath)) {
            throw "Verification failed. Missing generated file: $relativePath"
        }
    }

    Push-Location $ProjectRoot
    try {
        if (-not $SkipComposerValidation) {
            $php = Get-Command php -ErrorAction SilentlyContinue
            if ($php) {
                Write-Step "Checking generated PHP syntax"
                $phpFiles = $expectedFiles |
                    Where-Object { $_.EndsWith(".php") } |
                    ForEach-Object { Join-Path $ProjectRoot $_ }

                foreach ($phpFile in $phpFiles) {
                    & php -l $phpFile | Out-Host
                    if ($LASTEXITCODE -ne 0) {
                        throw "PHP syntax validation failed: $phpFile"
                    }
                }

                Write-Step "Refreshing Laravel caches"
                & php artisan optimize:clear | Out-Host
                if ($LASTEXITCODE -ne 0) {
                    throw "php artisan optimize:clear failed."
                }

                Write-Step "Verifying association routes"
                & php artisan route:list --name=admin.associations | Out-Host
                if ($LASTEXITCODE -ne 0) {
                    throw "Laravel route verification failed."
                }
            } else {
                Write-WarningMessage "PHP was not found in PATH. PHP syntax and route checks were skipped."
            }
        }

        if (-not $SkipNodeValidation) {
            $npm = Get-Command npm -ErrorAction SilentlyContinue
            if ($npm -and (Test-Path -LiteralPath (Join-Path $ProjectRoot "package.json"))) {
                Write-Step "Building Vite assets"
                & npm run build | Out-Host
                if ($LASTEXITCODE -ne 0) {
                    throw "Vite build failed."
                }
            } else {
                Write-WarningMessage "npm was not found in PATH. Vite build was skipped."
            }
        }
    } finally {
        Pop-Location
    }

    Write-Success "Backups saved to: $BackupRoot"
    Write-Host ""
    Write-Host "Next required command:" -ForegroundColor Cyan
    Write-Host "  php artisan migrate" -ForegroundColor White
    Write-Host ""
    Write-Host "Patch completed successfully." -ForegroundColor Green
}
catch {
    Write-Failure $_.Exception.Message
    Write-Host "Review the backup directory before retrying: $BackupRoot" -ForegroundColor Yellow
    exit 1
}
