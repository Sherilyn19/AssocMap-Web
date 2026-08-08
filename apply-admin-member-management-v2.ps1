#requires -Version 5.1

<#
.SYNOPSIS
    Safely applies the AssocMap System Administrator Member Management module.

.DESCRIPTION
    Production-quality, idempotent patch for D:\Capstone-AssocMap-Web.

    The patch:
      - validates the Laravel project and clean Git baseline
      - backs up only existing files that will change
      - fixes the Association Management authenticated actor resolver
      - removes duplicate Association Management JS imports
      - adds Admin Member Management (official members + read-only applications)
      - adds Form Request validation, Policies, Services, accessible Blade UI, and JS
      - adds PostgreSQL integrity hardening verified by defensive migration checks
      - adds focused route/policy tests
      - verifies PHP, Blade, routes, tests, Vite, Git whitespace, and patch intent

    Business boundary:
      - System Administrator may manage official member records system-wide.
      - Administrator does NOT approve or reject member applications.
      - Association Representative remains the only approval/rejection authority.
      - Members are archived, never hard deleted.
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$PatchName = 'Admin Member Management'
$ExpectedScriptName = 'apply-admin-member-management.ps1'
$KnownProjectRoot = 'D:\Capstone-AssocMap-Web'
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'

$Utf8NoBom = New-Object System.Text.UTF8Encoding -ArgumentList $false
$Utf8Strict = New-Object System.Text.UTF8Encoding -ArgumentList $false, $true

$script:ProjectRoot = $null
$script:BackupDirectory = $null
$script:ChangePlan = New-Object 'System.Collections.Generic.List[object]'
$script:CreatedThisRun = New-Object 'System.Collections.Generic.List[string]'
$script:BackedUpRelativePaths = New-Object 'System.Collections.Generic.HashSet[string]'
$script:WritesStarted = $false
$script:LocationPushed = $false
$script:ScriptLeaf = Split-Path -Leaf $PSCommandPath
$script:ScriptDirectory = Split-Path -Parent $PSCommandPath

function Write-Banner {
    Write-Host ''
    Write-Host '============================================================' -ForegroundColor DarkCyan
    Write-Host ' AssocMap - Admin Member Management Patch' -ForegroundColor Cyan
    Write-Host '============================================================' -ForegroundColor DarkCyan
    Write-Host ''
}

function Write-Step {
    param(
        [Parameter(Mandatory = $true)][int]$Number,
        [Parameter(Mandatory = $true)][int]$Total,
        [Parameter(Mandatory = $true)][string]$Message
    )

    Write-Host ("[{0}/{1}] {2}" -f $Number, $Total, $Message) -ForegroundColor Cyan
}

function Write-Pass {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host ("      PASS  {0}" -f $Message) -ForegroundColor Green
}

function Write-Skip {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host ("      SKIP  {0}" -f $Message) -ForegroundColor DarkGray
}

function Write-Update {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host ("      UPDATE {0}" -f $Message) -ForegroundColor Yellow
}

function Write-Create {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host ("      CREATE {0}" -f $Message) -ForegroundColor Yellow
}

function Write-Warn {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host ("      WARN  {0}" -f $Message) -ForegroundColor Yellow
}

function Test-LaravelProject {
    param([Parameter(Mandatory = $true)][string]$Path)

    return (
        (Test-Path -LiteralPath (Join-Path $Path 'artisan') -PathType Leaf) -and
        (Test-Path -LiteralPath (Join-Path $Path 'composer.json') -PathType Leaf) -and
        (Test-Path -LiteralPath (Join-Path $Path 'routes\web.php') -PathType Leaf) -and
        (Test-Path -LiteralPath (Join-Path $Path 'app') -PathType Container) -and
        (Test-Path -LiteralPath (Join-Path $Path 'resources') -PathType Container)
    )
}

function Resolve-ProjectRoot {
    if ($script:ScriptDirectory -and (Test-LaravelProject -Path $script:ScriptDirectory)) {
        return (Resolve-Path -LiteralPath $script:ScriptDirectory).Path
    }

    if (Test-LaravelProject -Path $KnownProjectRoot) {
        return (Resolve-Path -LiteralPath $KnownProjectRoot).Path
    }

    throw (
        "AssocMap Laravel project was not found. Place {0} in the project root or verify {1}." -f
        $ExpectedScriptName,
        $KnownProjectRoot
    )
}

function Get-ProjectPath {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    $platformRelative = $RelativePath.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
    return Join-Path $script:ProjectRoot $platformRelative
}

function Assert-FileExists {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    $path = Get-ProjectPath -RelativePath $RelativePath

    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw ("Required file is missing: {0}" -f $RelativePath)
    }
}

function Assert-CommandAvailable {
    param([Parameter(Mandatory = $true)][string]$Name)

    if (-not (Get-Command -Name $Name -ErrorAction SilentlyContinue)) {
        throw ("Required command is not available in PATH: {0}" -f $Name)
    }
}

function Read-Utf8File {
    param([Parameter(Mandatory = $true)][string]$Path)

    try {
        return [System.IO.File]::ReadAllText($Path, $Utf8Strict)
    }
    catch {
        throw ("Could not read '{0}' as valid UTF-8: {1}" -f $Path, $_.Exception.Message)
    }
}

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$Content
    )

    $parent = Split-Path -Parent $Path

    if ($parent -and -not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }

    [System.IO.File]::WriteAllText($Path, $Content, $Utf8NoBom)
}

function Convert-ToLf {
    param([Parameter(Mandatory = $true)][AllowEmptyString()][string]$Text)

    return (($Text -replace "`r`n", "`n") -replace "`r", "`n")
}

function Get-NewlineStyle {
    param([Parameter(Mandatory = $true)][AllowEmptyString()][string]$Text)

    if ($Text.Contains("`r`n")) {
        return "`r`n"
    }

    return "`n"
}

function Convert-FromLf {
    param(
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$Text,
        [Parameter(Mandatory = $true)][string]$Newline
    )

    $lf = Convert-ToLf -Text $Text

    if ($Newline -eq "`r`n") {
        return $lf.Replace("`n", "`r`n")
    }

    return $lf
}

function Assert-ExpectedText {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Description
    )

    if (-not $Content.Contains($Expected)) {
        throw ("Expected source block was not found: {0}" -f $Description)
    }
}

function Replace-RequiredText {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string]$Old,
        [Parameter(Mandatory = $true)][string]$New,
        [Parameter(Mandatory = $true)][string]$Description
    )

    if (-not $Content.Contains($Old)) {
        throw ("Cannot patch {0}: expected source block was not found." -f $Description)
    }

    return $Content.Replace($Old, $New)
}

function Register-ManagedFile {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$DesiredContent
    )

    $path = Get-ProjectPath -RelativePath $RelativePath
    $desiredLf = Convert-ToLf -Text $DesiredContent

    if (Test-Path -LiteralPath $path -PathType Leaf) {
        $current = Read-Utf8File -Path $path

        if ((Convert-ToLf -Text $current) -eq $desiredLf) {
            Write-Skip ("Already correct: {0}" -f $RelativePath)
            return
        }

        throw (
            "Managed patch file already exists with different content: {0}. " +
            "Review that file before rerunning so the patch does not overwrite manual work." -f
            $RelativePath
        )
    }

    $script:ChangePlan.Add([pscustomobject]@{
        RelativePath = $RelativePath
        Path = $path
        Content = $desiredLf
        Operation = 'CREATE'
    })
}

function Register-UpdatedFile {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string]$DesiredContent
    )

    $path = Get-ProjectPath -RelativePath $RelativePath
    $current = Read-Utf8File -Path $path

    if ((Convert-ToLf -Text $current) -eq (Convert-ToLf -Text $DesiredContent)) {
        Write-Skip ("Already correct: {0}" -f $RelativePath)
        return
    }

    $script:ChangePlan.Add([pscustomobject]@{
        RelativePath = $RelativePath
        Path = $path
        Content = $DesiredContent
        Operation = 'UPDATE'
    })
}

function Backup-File {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    if ($script:BackedUpRelativePaths.Contains($RelativePath)) {
        return
    }

    $source = Get-ProjectPath -RelativePath $RelativePath

    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        return
    }

    $destination = Join-Path $script:BackupDirectory (
        $RelativePath.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
    )
    $destinationParent = Split-Path -Parent $destination

    if (-not (Test-Path -LiteralPath $destinationParent)) {
        New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
    }

    Copy-Item -LiteralPath $source -Destination $destination -Force
    [void] $script:BackedUpRelativePaths.Add($RelativePath)
}

function Restore-FileChanges {
    if (-not $script:WritesStarted) {
        return
    }

    Write-Warn 'A required step failed. Restoring filesystem changes from this run...'

    foreach ($relativePath in $script:BackedUpRelativePaths) {
        $backupPath = Join-Path $script:BackupDirectory (
            $relativePath.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
        )
        $targetPath = Get-ProjectPath -RelativePath $relativePath

        if (Test-Path -LiteralPath $backupPath -PathType Leaf) {
            $targetParent = Split-Path -Parent $targetPath

            if (-not (Test-Path -LiteralPath $targetParent)) {
                New-Item -ItemType Directory -Path $targetParent -Force | Out-Null
            }

            Copy-Item -LiteralPath $backupPath -Destination $targetPath -Force
        }
    }

    foreach ($relativePath in $script:CreatedThisRun) {
        $targetPath = Get-ProjectPath -RelativePath $relativePath

        if (Test-Path -LiteralPath $targetPath -PathType Leaf) {
            Remove-Item -LiteralPath $targetPath -Force
        }
    }

    Write-Warn ("Filesystem rollback completed. Backup directory: {0}" -f $script:BackupDirectory)
}

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter()][string[]]$Arguments = @(),
        [switch]$CaptureOutput
    )

    if ($CaptureOutput) {
        $output = & $FilePath @Arguments 2>&1
        $exitCode = $LASTEXITCODE

        if ($exitCode -ne 0) {
            $message = ($output | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine
            throw (
                "Command failed with exit code {0}: {1} {2}{3}{4}" -f
                $exitCode,
                $FilePath,
                ($Arguments -join ' '),
                [Environment]::NewLine,
                $message
            )
        }

        return @($output | ForEach-Object { $_.ToString() })
    }

    & $FilePath @Arguments
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        throw (
            "Command failed with exit code {0}: {1} {2}" -f
            $exitCode,
            $FilePath,
            ($Arguments -join ' ')
        )
    }
}

function Assert-CleanGitBaseline {
    $status = & git status --porcelain 2>&1
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        throw ("git status failed with exit code {0}." -f $exitCode)
    }

    $scriptLeaf = [regex]::Escape($script:ScriptLeaf)

    $unexpected = @(
        $status | Where-Object {
            $line = $_.ToString()

            if ([string]::IsNullOrWhiteSpace($line)) {
                return $false
            }

            # The patch script itself is expected to be an untracked file in the project root.
            if ($line -match ("^\?\?\s+{0}$" -f $scriptLeaf)) {
                return $false
            }

            return $true
        }
    )

    if ($unexpected.Count -gt 0) {
        throw (
            "Git working tree is not clean. Commit or intentionally resolve these files before patching:`n{0}" -f
            ($unexpected -join "`n")
        )
    }
}

function Assert-NoTrailingWhitespace {
    param([Parameter(Mandatory = $true)][string[]]$RelativePaths)

    foreach ($relativePath in $RelativePaths) {
        $path = Get-ProjectPath -RelativePath $relativePath

        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
            continue
        }

        $lines = (Convert-ToLf -Text (Read-Utf8File -Path $path)) -split "`n"

        for ($index = 0; $index -lt $lines.Count; $index++) {
            if ($lines[$index] -match '[ \t]+$') {
                throw (
                    "Trailing whitespace found in {0} at line {1}." -f
                    $relativePath,
                    ($index + 1)
                )
            }
        }
    }
}

Write-Banner

try {
    Write-Step -Number 1 -Total 8 -Message 'Checking Laravel project and clean baseline...'

    $script:ProjectRoot = Resolve-ProjectRoot
    Push-Location $script:ProjectRoot
    $script:LocationPushed = $true

    Assert-CommandAvailable -Name 'php'
    Assert-CommandAvailable -Name 'npm'
    Assert-CommandAvailable -Name 'git'

    foreach ($required in @(
        'artisan',
        'composer.json',
        'routes/web.php',
        'app/Http/Controllers/Admin/AssociationManagementController.php',
        'app/Models/Member.php',
        'app/Models/MemberApplication.php',
        'app/Models/Association.php',
        'app/Models/User.php',
        'app/Models/Sex.php',
        'app/Models/Status.php',
        'app/Providers/AppServiceProvider.php',
        'resources/js/app.js',
        'resources/views/components/sidebar.blade.php',
        'resources/views/components/dashboard-layout.blade.php',
        'resources/views/admin-pages/admin-association-management/index.blade.php',
        'vite.config.js',
        'package.json'
    )) {
        Assert-FileExists -RelativePath $required
    }

    Assert-CleanGitBaseline
    Invoke-CheckedCommand -FilePath 'php' -Arguments @('artisan', 'route:list', '--path=admin') | Out-Null

    Write-Pass ("Project: {0}" -f $script:ProjectRoot)
    Write-Pass 'Required files, PHP, npm, Git, Laravel boot, and Git baseline are valid.'

    Write-Step -Number 2 -Total 8 -Message 'Preparing deterministic patch content...'

    $ManagedFiles = [ordered]@{
    'app/Services/SessionUserResolver.php' = @'
<?php

// app/Services/SessionUserResolver.php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class SessionUserResolver
{
    /**
     * Resolve the real database user behind AssocMap's custom auth_user session.
     *
     * AssocMap currently stores the authenticated identity in session('auth_user')
     * instead of Laravel's default guard. Centralizing the lookup prevents each
     * controller from guessing a different session key.
     */
    public function resolve(Request $request): User
    {
        $sessionUser = $request->session()->get('auth_user');
        $actorId = is_array($sessionUser) ? ($sessionUser['id'] ?? null) : null;

        $actorId ??= auth()->id();
        $actorId ??= $request->session()->get('user_id');
        $actorId ??= $request->session()->get('authenticated_user_id');

        abort_if(!$actorId, 401, 'Authenticated user could not be identified.');

        $user = User::query()
            ->with('role:id,role_name')
            ->find((int) $actorId);

        abort_if(!$user, 401, 'Authenticated user account could not be found.');
        abort_if(!$user->is_active, 403, 'This account is inactive.');

        return $user;
    }
}
'@
    'app/Policies/MemberPolicy.php' = @'
<?php

// app/Policies/MemberPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

final class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return in_array($user->role?->role_name, [
            'System Administrator',
            'Field Officer',
            'Association Member',
        ], true);
    }

    public function view(User $user, Member $member): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return match ($user->role?->role_name) {
            'System Administrator' => true,
            'Field Officer' => (int) $member->association?->field_officer_id === (int) $user->id,
            'Association Member' => (int) $user->association_id === (int) $member->association_id,
            default => false,
        };
    }

    public function update(User $user, Member $member): bool
    {
        return $user->is_active
            && $user->role?->role_name === 'System Administrator';
    }

    public function archive(User $user, Member $member): bool
    {
        return $user->is_active
            && $user->role?->role_name === 'System Administrator';
    }
}
'@
    'app/Policies/MemberApplicationPolicy.php' = @'
<?php

// app/Policies/MemberApplicationPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MemberApplication;
use App\Models\User;

final class MemberApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return in_array($user->role?->role_name, [
            'System Administrator',
            'Field Officer',
            'Association Member',
        ], true);
    }

    public function view(User $user, MemberApplication $application): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return match ($user->role?->role_name) {
            'System Administrator' => true,
            'Field Officer' => (int) $application->association?->field_officer_id === (int) $user->id,
            'Association Member' => (int) $user->association_id === (int) $application->association_id,
            default => false,
        };
    }
}
'@
    'app/Http/Requests/Admin/UpdateMemberRequest.php' = @'
<?php

// app/Http/Requests/Admin/UpdateMemberRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Services\SessionUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateMemberRequest extends FormRequest
{
    private const ASSOCIATION_ROLES = [
        'President',
        'Secretary',
        'Treasurer',
        'Member',
    ];

    public function authorize(): bool
    {
        $member = $this->route('member');

        if (!$member instanceof Member) {
            return false;
        }

        $user = app(SessionUserResolver::class)->resolve($this);

        return Gate::forUser($user)->allows('update', $member);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->normalizeText($this->input('first_name')),
            'middle_name' => $this->normalizeNullableText($this->input('middle_name')),
            'last_name' => $this->normalizeText($this->input('last_name')),
            'role_in_assoc' => $this->normalizeNullableText($this->input('role_in_assoc')),
            'beneficiary_type' => $this->normalizeNullableText($this->input('beneficiary_type')),
            'contact_number' => $this->normalizeNullableText($this->input('contact_number')),
            'address' => $this->normalizeNullableText($this->input('address')),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthday' => ['required', 'date', 'before_or_equal:today'],
            'sex_id' => ['required', 'integer', Rule::exists('sex', 'id')],
            'role_in_assoc' => ['nullable', 'string', Rule::in(self::ASSOCIATION_ROLES)],
            'beneficiary_type' => ['nullable', 'string', 'max:100'],
            'contact_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9+\-\s().]{7,50}$/',
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'date_registered' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $member = $this->route('member');

                if (!$member instanceof Member) {
                    $validator->errors()->add('member', 'The member record could not be resolved.');
                    return;
                }

                $this->validateNormalizedDuplicate($validator, $member);
            },
        ];
    }

    public function messages(): array
    {
        return [
            'birthday.before_or_equal' => 'Birthday must not be later than today.',
            'date_registered.before_or_equal' => 'Date registered must not be later than today.',
            'contact_number.regex' => 'Contact number contains unsupported characters.',
            'role_in_assoc.in' => 'Association role must be President, Secretary, Treasurer, or Member.',
        ];
    }

    private function validateNormalizedDuplicate(Validator $validator, Member $member): void
    {
        if (!$this->filled(['first_name', 'last_name', 'birthday'])) {
            return;
        }

        $firstName = $this->normalizeIdentity((string) $this->input('first_name'));
        $middleName = $this->normalizeIdentity((string) ($this->input('middle_name') ?? ''));
        $lastName = $this->normalizeIdentity((string) $this->input('last_name'));

        $duplicate = DB::table('members')
            ->where('association_id', $member->association_id)
            ->where('id', '!=', $member->id)
            ->whereDate('birthday', (string) $this->input('birthday'))
            ->whereRaw(
                "LOWER(REGEXP_REPLACE(BTRIM(first_name), '\\s+', ' ', 'g')) = ?",
                [$firstName]
            )
            ->whereRaw(
                "COALESCE(NULLIF(LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\\s+', ' ', 'g')), ''), '') = ?",
                [$middleName]
            )
            ->whereRaw(
                "LOWER(REGEXP_REPLACE(BTRIM(last_name), '\\s+', ' ', 'g')) = ?",
                [$lastName]
            )
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'first_name',
                'A member with the same name and birthday already exists in this association.'
            );
        }
    }

    private function normalizeText(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeIdentity(string $value): string
    {
        return mb_strtolower($this->normalizeText($value));
    }
}
'@
    'app/Http/Controllers/Admin/MemberManagementController.php' = @'
<?php

// app/Http/Controllers/Admin/MemberManagementController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMemberRequest;
use App\Models\Member;
use App\Services\MemberManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class MemberManagementController extends Controller
{
    private const LIST_STATE_KEYS = [
        'search',
        'association_id',
        'area_unit_id',
        'sub_unit_id',
        'sex_id',
        'role_in_assoc',
        'beneficiary_type',
        'record_state',
        'registered_from',
        'registered_to',
        'sort',
        'per_page',
        'page',
    ];

    public function __construct(
        private readonly MemberManagementService $service,
        private readonly SessionUserResolver $sessionUser
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('viewAny', Member::class);

        $filters = $request->only([
            'search',
            'association_id',
            'area_unit_id',
            'sub_unit_id',
            'sex_id',
            'role_in_assoc',
            'beneficiary_type',
            'record_state',
            'registered_from',
            'registered_to',
            'sort',
            'per_page',
        ]);

        return view('admin-pages.admin-member-management.index', [
            'members' => $this->service->paginate($filters),
            'summary' => $this->service->summary(),
            'analytics' => $this->service->analytics(),
            'filters' => $filters,
            'listState' => $this->listState($request),
            ...$this->service->filterOptions(),
        ]);
    }

    public function show(Request $request, Member $member): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $member);

        return view('admin-pages.admin-member-management.show', [
            'member' => $this->service->findDetailed($member),
            'backToListUrl' => route('members.index', $this->listState($request)),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('update', $member);

        try {
            $this->service->update($member, $request->validated(), $actor->id);

            return back()->with('success', 'Member profile updated successfully.');
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with(
                'error',
                'The member profile could not be updated. Please try again.'
            );
        }
    }

    public function archive(Request $request, Member $member): RedirectResponse
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('archive', $member);

        try {
            $this->service->archive($member, $actor->id);

            return back()->with('success', 'Member archived successfully.');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The member could not be archived. Please try again.'
            );
        }
    }

    /**
     * Keep only scalar, non-empty GET state when linking to a detail record.
     *
     * @return array<string, string>
     */
    private function listState(Request $request): array
    {
        $state = [];

        foreach (self::LIST_STATE_KEYS as $key) {
            $value = $request->query($key);

            if (!is_scalar($value) || $value === '') {
                continue;
            }

            $state[$key] = (string) $value;
        }

        if (isset($state['page']) && (!ctype_digit($state['page']) || (int) $state['page'] < 1)) {
            unset($state['page']);
        }

        return $state;
    }
}
'@
    'app/Http/Controllers/Admin/MemberApplicationManagementController.php' = @'
<?php

// app/Http/Controllers/Admin/MemberApplicationManagementController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberApplication;
use App\Services\MemberApplicationManagementService;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MemberApplicationManagementController extends Controller
{
    private const LIST_STATE_KEYS = [
        'search',
        'association_id',
        'status_id',
        'submitted_from',
        'submitted_to',
        'sort',
        'per_page',
        'page',
    ];

    public function __construct(
        private readonly MemberApplicationManagementService $service,
        private readonly SessionUserResolver $sessionUser
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('viewAny', MemberApplication::class);

        $filters = $request->only([
            'search',
            'association_id',
            'status_id',
            'submitted_from',
            'submitted_to',
            'sort',
            'per_page',
        ]);

        return view('admin-pages.admin-member-management.applications', [
            'applications' => $this->service->paginate($filters),
            'summary' => $this->service->summary(),
            'filters' => $filters,
            ...$this->service->filterOptions(),
        ]);
    }

    public function show(Request $request, MemberApplication $application): View
    {
        $actor = $this->sessionUser->resolve($request);
        Gate::forUser($actor)->authorize('view', $application);

        return view('admin-pages.admin-member-management.application-show', [
            'application' => $this->service->findDetailed($application),
            'backToListUrl' => route('members.applications.index', $this->listState($request)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function listState(Request $request): array
    {
        $state = [];

        foreach (self::LIST_STATE_KEYS as $key) {
            $value = $request->query($key);

            if (!is_scalar($value) || $value === '') {
                continue;
            }

            $state[$key] = (string) $value;
        }

        if (isset($state['page']) && (!ctype_digit($state['page']) || (int) $state['page'] < 1)) {
            unset($state['page']);
        }

        return $state;
    }
}
'@
    'app/Services/MemberManagementService.php' = @'
<?php

// app/Services/MemberManagementService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Association;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemberManagementService
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const ASSOCIATION_ROLES = [
        'President',
        'Secretary',
        'Treasurer',
        'Member',
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Member::query()
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,field_officer_id,representative_member_id,is_archived',
                'association.areaUnit:id,name',
                'association.subUnit:id,area_unit_id,name',
                'sex:id,sex_name',
                'application:id,association_id,status_id,reviewed_by_member_id,reviewed_at,created_at',
                'application.status:id,status_name',
                'application.reviewer:id,first_name,middle_name,last_name',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = "%{$search}%";

            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->whereRaw(
                        "CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) ILIKE ?",
                        [$term]
                    )
                    ->orWhereHas(
                        'association',
                        fn (Builder $association) => $association->where('name', 'ilike', $term)
                    );
            });
        }

        $this->applyIntegerFilter($query, 'association_id', $filters['association_id'] ?? null);
        $this->applyIntegerFilter($query, 'sex_id', $filters['sex_id'] ?? null);

        if (filter_var($filters['area_unit_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $areaUnitId = (int) $filters['area_unit_id'];
            $query->whereHas(
                'association',
                fn (Builder $association) => $association->where('area_unit_id', $areaUnitId)
            );
        }

        if (filter_var($filters['sub_unit_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $subUnitId = (int) $filters['sub_unit_id'];
            $query->whereHas(
                'association',
                fn (Builder $association) => $association->where('sub_unit_id', $subUnitId)
            );
        }

        $role = trim((string) ($filters['role_in_assoc'] ?? ''));
        if ($role !== '' && in_array($role, self::ASSOCIATION_ROLES, true)) {
            $query->where('role_in_assoc', $role);
        }

        $beneficiaryType = trim((string) ($filters['beneficiary_type'] ?? ''));
        if ($beneficiaryType !== '') {
            $query->where('beneficiary_type', $beneficiaryType);
        }

        match ((string) ($filters['record_state'] ?? 'current')) {
            'archived' => $query->where('is_archived', true),
            'all' => null,
            default => $query->where('is_archived', false),
        };

        if ($this->validDate($filters['registered_from'] ?? null)) {
            $query->whereDate('date_registered', '>=', (string) $filters['registered_from']);
        }

        if ($this->validDate($filters['registered_to'] ?? null)) {
            $query->whereDate('date_registered', '<=', (string) $filters['registered_to']);
        }

        match ((string) ($filters['sort'] ?? 'name_asc')) {
            'name_desc' => $query->orderByDesc('last_name')->orderByDesc('first_name'),
            'registered_desc' => $query->orderByDesc('date_registered')->orderBy('last_name'),
            'registered_asc' => $query->orderBy('date_registered')->orderBy('last_name'),
            'association_asc' => $query
                ->orderBy(
                    Association::query()
                        ->select('name')
                        ->whereColumn('associations.id', 'members.association_id')
                )
                ->orderBy('last_name'),
            default => $query->orderBy('last_name')->orderBy('first_name'),
        };

        $perPage = (int) ($filters['per_page'] ?? 15);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{
     *     total:int,
     *     current:int,
     *     archived:int,
     *     representatives:int,
     *     associations_with_members:int
     * }
     */
    public function summary(): array
    {
        $memberCounts = DB::table('members')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_archived = FALSE) AS current')
            ->selectRaw('COUNT(*) FILTER (WHERE is_archived = TRUE) AS archived')
            ->selectRaw('COUNT(DISTINCT association_id) AS associations_with_members')
            ->first();

        $representatives = DB::table('associations')
            ->join('members', 'members.id', '=', 'associations.representative_member_id')
            ->where('members.is_archived', false)
            ->count();

        return [
            'total' => (int) ($memberCounts->total ?? 0),
            'current' => (int) ($memberCounts->current ?? 0),
            'archived' => (int) ($memberCounts->archived ?? 0),
            'representatives' => (int) $representatives,
            'associations_with_members' => (int) ($memberCounts->associations_with_members ?? 0),
        ];
    }

    /**
     * Compact aggregate/detail data for clickable KPI modals.
     *
     * No complete member dataset is sent to the browser.
     *
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        return [
            'sex_distribution' => DB::table('members')
                ->join('sex', 'sex.id', '=', 'members.sex_id')
                ->select('sex.sex_name')
                ->selectRaw('COUNT(*) AS member_count')
                ->groupBy('sex.sex_name')
                ->orderByDesc('member_count')
                ->get(),

            'beneficiary_distribution' => DB::table('members')
                ->selectRaw("COALESCE(NULLIF(BTRIM(beneficiary_type), ''), 'Unspecified') AS beneficiary_type")
                ->selectRaw('COUNT(*) AS member_count')
                ->groupByRaw("COALESCE(NULLIF(BTRIM(beneficiary_type), ''), 'Unspecified')")
                ->orderByDesc('member_count')
                ->limit(8)
                ->get(),

            'role_distribution' => DB::table('members')
                ->where('is_archived', false)
                ->selectRaw("COALESCE(NULLIF(BTRIM(role_in_assoc), ''), 'Unassigned') AS role_name")
                ->selectRaw('COUNT(*) AS member_count')
                ->groupByRaw("COALESCE(NULLIF(BTRIM(role_in_assoc), ''), 'Unassigned')")
                ->orderByDesc('member_count')
                ->get(),

            'members_by_association' => DB::table('associations')
                ->join('members', 'members.association_id', '=', 'associations.id')
                ->leftJoin('area_units', 'area_units.id', '=', 'associations.area_unit_id')
                ->leftJoin('sub_units', 'sub_units.id', '=', 'associations.sub_unit_id')
                ->select([
                    'associations.id',
                    'associations.name',
                    'area_units.name as municipality_name',
                    'sub_units.name as barangay_name',
                ])
                ->selectRaw('COUNT(members.id) AS total_count')
                ->selectRaw('COUNT(members.id) FILTER (WHERE members.is_archived = FALSE) AS current_count')
                ->selectRaw('COUNT(members.id) FILTER (WHERE members.is_archived = TRUE) AS archived_count')
                ->groupBy(
                    'associations.id',
                    'associations.name',
                    'area_units.name',
                    'sub_units.name'
                )
                ->orderByDesc('total_count')
                ->orderBy('associations.name')
                ->limit(10)
                ->get(),

            'recent_registrations' => DB::table('members')
                ->join('associations', 'associations.id', '=', 'members.association_id')
                ->where('members.is_archived', false)
                ->orderByDesc('members.date_registered')
                ->orderByDesc('members.id')
                ->limit(6)
                ->get([
                    'members.id',
                    'members.first_name',
                    'members.middle_name',
                    'members.last_name',
                    'members.role_in_assoc',
                    'members.date_registered',
                    'associations.name as association_name',
                ]),

            'representatives' => DB::table('associations')
                ->join('members', 'members.id', '=', 'associations.representative_member_id')
                ->leftJoin('area_units', 'area_units.id', '=', 'associations.area_unit_id')
                ->leftJoin('sub_units', 'sub_units.id', '=', 'associations.sub_unit_id')
                ->where('members.is_archived', false)
                ->orderBy('associations.name')
                ->limit(10)
                ->get([
                    'members.id as member_id',
                    'members.first_name',
                    'members.middle_name',
                    'members.last_name',
                    'members.role_in_assoc',
                    'members.contact_number',
                    'associations.name as association_name',
                    'area_units.name as municipality_name',
                    'sub_units.name as barangay_name',
                ]),
        ];
    }

    /**
     * @return array<string, Collection<int, object>|array<int, string>|array<int, int>>
     */
    public function filterOptions(): array
    {
        return [
            'associations' => DB::table('associations')
                ->orderBy('name')
                ->get(['id', 'name', 'area_unit_id', 'sub_unit_id', 'is_archived']),

            'municipalities' => DB::table('area_units')
                ->orderBy('name')
                ->get(['id', 'name', 'is_archived']),

            'barangays' => DB::table('sub_units')
                ->orderBy('name')
                ->get(['id', 'area_unit_id', 'name', 'is_archived']),

            'sexOptions' => DB::table('sex')
                ->orderBy('sex_name')
                ->get(['id', 'sex_name']),

            'roleOptions' => self::ASSOCIATION_ROLES,

            'beneficiaryTypes' => DB::table('members')
                ->whereNotNull('beneficiary_type')
                ->whereRaw("BTRIM(beneficiary_type) <> ''")
                ->distinct()
                ->orderBy('beneficiary_type')
                ->pluck('beneficiary_type'),

            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ];
    }

    public function findDetailed(Member $member): Member
    {
        return $member->load([
            'association:id,name,area_unit_id,sub_unit_id,field_officer_id,representative_member_id,is_archived',
            'association.areaUnit:id,name',
            'association.subUnit:id,area_unit_id,name',
            'sex:id,sex_name',
            'application:id,association_id,status_id,reviewed_by_member_id,reviewed_at,rejection_reason,created_at',
            'application.status:id,status_name',
            'application.reviewer:id,first_name,middle_name,last_name',
            'user:id,name,email,is_active',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Member $member, array $data, int $actorId): Member
    {
        return DB::transaction(function () use ($member, $data, $actorId): Member {
            /** @var Member $locked */
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($locked->is_archived) {
                throw new RuntimeException(
                    'Archived members are historical records and cannot be edited.'
                );
            }

            $previousRole = $locked->role_in_assoc;

            $locked->forceFill([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'birthday' => $data['birthday'],
                'sex_id' => (int) $data['sex_id'],
                'role_in_assoc' => $data['role_in_assoc'] ?? null,
                'beneficiary_type' => $data['beneficiary_type'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'address' => $data['address'] ?? null,
                'date_registered' => $data['date_registered'],
            ]);
            $locked->save();

            $changedFields = array_values(array_filter(
                array_keys($locked->getChanges()),
                fn (string $field): bool => $field !== 'updated_at'
            ));

            if ($changedFields !== []) {
                $this->writeAudit(
                    $actorId,
                    'UPDATE',
                    $locked->id,
                    'Updated member fields: '.implode(', ', $changedFields).'.'
                );
            }

            if ($previousRole !== $locked->role_in_assoc) {
                $this->writeAudit(
                    $actorId,
                    'ROLE_CHANGE',
                    $locked->id,
                    'Changed association role from '
                        .($previousRole ?: 'unassigned')
                        .' to '
                        .($locked->role_in_assoc ?: 'unassigned')
                        .'.'
                );
            }

            return $locked->fresh();
        }, 3);
    }

    public function archive(Member $member, int $actorId): Member
    {
        return DB::transaction(function () use ($member, $actorId): Member {
            /** @var Member $locked */
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($locked->is_archived) {
                return $locked;
            }

            $isRepresentative = DB::table('associations')
                ->where('representative_member_id', $locked->id)
                ->lockForUpdate()
                ->exists();

            if ($isRepresentative) {
                throw new RuntimeException(
                    'This member is currently the Association Representative. '
                    .'Assign a different representative before archiving this member.'
                );
            }

            $locked->forceFill(['is_archived' => true])->save();

            $this->writeAudit(
                $actorId,
                'ARCHIVE',
                $locked->id,
                'Archived member record. The record remains available for history and reporting.'
            );

            return $locked->fresh();
        }, 3);
    }

    private function applyIntegerFilter(Builder $query, string $column, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            $query->where($column, (int) $value);
        }
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function writeAudit(
        int $actorId,
        string $action,
        int $memberId,
        string $details
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action_type' => $action,
            'module' => 'Member',
            'record_id' => $memberId,
            'details' => $details,
            'performed_at' => now(),
        ]);
    }
}
'@
    'app/Services/MemberApplicationManagementService.php' = @'
<?php

// app/Services/MemberApplicationManagementService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\MemberApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MemberApplicationManagementService
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MemberApplication::query()
            ->with([
                'association:id,name,area_unit_id,sub_unit_id,representative_member_id,is_archived',
                'association.areaUnit:id,name',
                'association.subUnit:id,area_unit_id,name',
                'association.representative:id,association_id,first_name,middle_name,last_name,role_in_assoc,is_archived',
                'sex:id,sex_name',
                'status:id,status_name',
                'reviewer:id,association_id,first_name,middle_name,last_name',
                'member:id,association_id,application_id,first_name,middle_name,last_name,is_archived',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = "%{$search}%";

            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->whereRaw(
                        "CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) ILIKE ?",
                        [$term]
                    )
                    ->orWhereHas(
                        'association',
                        fn (Builder $association) => $association->where('name', 'ilike', $term)
                    );
            });
        }

        if (filter_var($filters['association_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $query->where('association_id', (int) $filters['association_id']);
        }

        if (filter_var($filters['status_id'] ?? null, FILTER_VALIDATE_INT) !== false) {
            $statusId = (int) $filters['status_id'];

            $allowedStatus = DB::table('statuses')
                ->where('id', $statusId)
                ->whereIn('status_name', ['Pending', 'Approved', 'Rejected'])
                ->exists();

            if ($allowedStatus) {
                $query->where('status_id', $statusId);
            }
        }

        if ($this->validDate($filters['submitted_from'] ?? null)) {
            $query->whereDate('created_at', '>=', (string) $filters['submitted_from']);
        }

        if ($this->validDate($filters['submitted_to'] ?? null)) {
            $query->whereDate('created_at', '<=', (string) $filters['submitted_to']);
        }

        match ((string) ($filters['sort'] ?? 'submitted_desc')) {
            'name_asc' => $query->orderBy('last_name')->orderBy('first_name'),
            'name_desc' => $query->orderByDesc('last_name')->orderByDesc('first_name'),
            'submitted_asc' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = (int) ($filters['per_page'] ?? 15);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{total:int,pending:int,approved:int,rejected:int}
     */
    public function summary(): array
    {
        $row = DB::table('member_applications')
            ->join('statuses', 'statuses.id', '=', 'member_applications.status_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE statuses.status_name = 'Pending') AS pending")
            ->selectRaw("COUNT(*) FILTER (WHERE statuses.status_name = 'Approved') AS approved")
            ->selectRaw("COUNT(*) FILTER (WHERE statuses.status_name = 'Rejected') AS rejected")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
        ];
    }

    /**
     * @return array<string, Collection<int, object>|array<int, int>>
     */
    public function filterOptions(): array
    {
        return [
            'associations' => DB::table('associations')
                ->orderBy('name')
                ->get(['id', 'name', 'is_archived']),

            'applicationStatuses' => DB::table('statuses')
                ->whereIn('status_name', ['Pending', 'Approved', 'Rejected'])
                ->orderByRaw(
                    "CASE status_name WHEN 'Pending' THEN 1 WHEN 'Approved' THEN 2 ELSE 3 END"
                )
                ->get(['id', 'status_name']),

            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ];
    }

    public function findDetailed(MemberApplication $application): MemberApplication
    {
        return $application->load([
            'association:id,name,area_unit_id,sub_unit_id,representative_member_id,is_archived',
            'association.areaUnit:id,name',
            'association.subUnit:id,area_unit_id,name',
            'association.representative:id,association_id,first_name,middle_name,last_name,role_in_assoc,is_archived',
            'sex:id,sex_name',
            'status:id,status_name',
            'reviewer:id,association_id,first_name,middle_name,last_name',
            'member:id,association_id,application_id,first_name,middle_name,last_name,is_archived',
        ]);
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
'@
    'resources/views/components/admin-modal.blade.php' = @'
{{--
    resources/views/components/admin-modal.blade.php

    Generic accessible modal shell for authenticated Admin pages.
    JavaScript owns opening, focus trapping, Escape/backdrop close, and focus return.
--}}
@props([
    'id',
    'title',
    'description' => null,
    'size' => 'lg',
])

@php
    $maxWidth = match ($size) {
        'sm' => 'max-w-md',
        'md' => 'max-w-2xl',
        'xl' => 'max-w-6xl',
        default => 'max-w-4xl',
    };
@endphp

<div
    id="{{ $id }}"
    data-modal
    aria-hidden="true"
    class="fixed inset-0 z-50 hidden"
>
    <div
        data-modal-backdrop
        class="flex min-h-full items-center justify-center bg-slate-950/50 p-4"
    >
        <section
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-title"
            @if($description) aria-describedby="{{ $id }}-description" @endif
            tabindex="-1"
            class="flex max-h-[90vh] w-full {{ $maxWidth }} flex-col overflow-hidden rounded-2xl
                   border border-slate-200 bg-white shadow-2xl"
        >
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="{{ $id }}-title" class="text-lg font-bold text-slate-900">
                        {{ $title }}
                    </h2>
                    @if($description)
                        <p id="{{ $id }}-description" class="mt-1 text-sm leading-5 text-slate-600">
                            {{ $description }}
                        </p>
                    @endif
                </div>

                <button
                    type="button"
                    data-close-modal
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                           border border-slate-200 text-slate-500 transition hover:bg-slate-50
                           hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    aria-label="Close dialog"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                {{ $slot }}
            </div>

            @isset($footer)
                <footer class="border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    {{ $footer }}
                </footer>
            @endisset
        </section>
    </div>
</div>
'@
    'resources/js/admin-member-management.js' = @'
/**
 * resources/js/admin-member-management.js
 *
 * Interface behavior only. Laravel remains the source of truth for
 * authorization, validation, duplicate prevention, and archive rules.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-member-management-page]');

    if (!page) {
        return;
    }

    const barangays = safelyParseJson(page.dataset.barangays, []);
    let activeModal = null;
    let activeTrigger = null;

    function safelyParseJson(value, fallback) {
        try {
            return JSON.parse(value ?? '');
        } catch {
            return fallback;
        }
    }

    function focusableElements(modal) {
        return Array.from(
            modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter((element) => !element.hasAttribute('hidden') && element.offsetParent !== null);
    }

    function openModal(modal, trigger = null) {
        if (!modal) return;

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal, false);
        }

        activeModal = modal;
        activeTrigger = trigger ?? document.activeElement;

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => {
            const focusable = focusableElements(modal);
            (focusable[0] ?? modal.querySelector('[data-modal-panel]'))?.focus();
        }, 0);
    }

    function closeModal(modal, restoreFocus = true) {
        if (!modal) return;

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');

        if (restoreFocus && activeTrigger instanceof HTMLElement) {
            activeTrigger.focus();
        }

        if (activeModal === modal) {
            activeModal = null;
            activeTrigger = null;
        }
    }

    function trapFocus(event, modal) {
        if (event.key !== 'Tab') return;

        const focusable = focusableElements(modal);

        if (focusable.length === 0) {
            event.preventDefault();
            modal.querySelector('[data-modal-panel]')?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function text(value, fallback = '—') {
        const normalized = String(value ?? '').trim();
        return normalized === '' ? fallback : normalized;
    }

    function setText(modal, field, value) {
        modal.querySelectorAll(`[data-detail-field="${field}"]`).forEach((element) => {
            element.textContent = text(value);
        });
    }

    function populateBarangays(select, municipalityId, selectedId = '') {
        if (!select) return;

        const matching = barangays.filter(
            (barangay) => String(barangay.area_unit_id) === String(municipalityId)
        );

        const initialLabel = select.dataset.allLabel || 'All barangays';
        select.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = municipalityId
            ? (matching.length ? initialLabel : 'No barangays available')
            : 'Select municipality first';
        select.appendChild(placeholder);

        matching.forEach((barangay) => {
            const option = document.createElement('option');
            option.value = String(barangay.id);
            option.textContent = barangay.name + (barangay.is_archived ? ' (Archived)' : '');
            option.selected = String(barangay.id) === String(selectedId);
            select.appendChild(option);
        });

        select.disabled = !municipalityId;
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(document.getElementById(button.dataset.openModal), button);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.closest('[data-modal]'));
        });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(modal);
                return;
            }

            trapFocus(event, modal);
        });

        modal.querySelector('[data-modal-backdrop]')?.addEventListener('click', (event) => {
            if (event.target === event.currentTarget) {
                closeModal(modal);
            }
        });
    });

    document.querySelectorAll('[data-member-details]').forEach((button) => {
        button.addEventListener('click', () => {
            const member = safelyParseJson(button.dataset.memberDetails, null);
            const modal = document.getElementById('member-details-modal');

            if (!member || !modal) return;

            Object.entries(member).forEach(([key, value]) => setText(modal, key, value));

            const fullRecord = modal.querySelector('[data-member-full-record]');
            if (fullRecord) {
                fullRecord.href = member.show_url || '#';
            }

            openModal(modal, button);
        });
    });

    document.querySelectorAll('[data-edit-member]').forEach((button) => {
        button.addEventListener('click', () => {
            const member = safelyParseJson(button.dataset.editMember, null);
            const modal = document.getElementById('edit-member-modal');
            const form = modal?.querySelector('[data-edit-member-form]');

            if (!member || !modal || !form) return;

            form.action = member.update_url;

            Object.entries(member).forEach(([key, value]) => {
                const field = form.querySelector(`[data-member-field="${key}"]`);
                if (field) {
                    field.value = value ?? '';
                }
            });

            const nameTarget = modal.querySelector('[data-edit-member-name]');
            if (nameTarget) {
                nameTarget.textContent = member.full_name || 'Member';
            }

            openModal(modal, button);
        });
    });

    document.querySelectorAll('[data-archive-member]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('archive-member-modal');
            const form = modal?.querySelector('[data-archive-form]');

            if (!modal || !form) return;

            form.action = button.dataset.archiveUrl || '#';

            const nameTarget = modal.querySelector('[data-archive-member-name]');
            if (nameTarget) {
                nameTarget.textContent = button.dataset.memberName || 'this member';
            }

            openModal(modal, button);
        });
    });

    const filterMunicipality = page.querySelector('[data-filter-municipality]');
    const filterBarangay = page.querySelector('[data-filter-barangay]');

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
});
'@
    'database/migrations/2026_08_08_083300_harden_member_management_integrity.php' = @'
<?php

// database/migrations/2026_08_08_083300_harden_member_management_integrity.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * PostgreSQL DDL should roll back as one unit if a defensive precheck fails.
     */
    public $withinTransaction = true;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->assertNoNormalizedDuplicateMembers();
        $this->assertNoNormalizedDuplicateApplications();
        $this->assertOneMemberPerApplication();

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS members_normalized_identity_unique
            ON members (
                association_id,
                LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                COALESCE(
                    NULLIF(
                        LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                        ''
                    ),
                    ''
                ),
                LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                birthday
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS member_applications_normalized_identity_unique
            ON member_applications (
                association_id,
                LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                COALESCE(
                    NULLIF(
                        LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                        ''
                    ),
                    ''
                ),
                LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                birthday
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS members_application_id_unique
            ON members (application_id)
            WHERE application_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS members_application_id_unique');
        DB::statement('DROP INDEX IF EXISTS member_applications_normalized_identity_unique');
        DB::statement('DROP INDEX IF EXISTS members_normalized_identity_unique');
    }

    private function assertNoNormalizedDuplicateMembers(): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM members
                GROUP BY
                    association_id,
                    LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                    COALESCE(
                        NULLIF(
                            LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                            ''
                        ),
                        ''
                    ),
                    LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                    birthday
                HAVING COUNT(*) > 1
            ) AS has_duplicates
        SQL);

        if ((bool) ($row->has_duplicates ?? false)) {
            throw new \RuntimeException(
                'Cannot harden member identity uniqueness because normalized duplicate members exist.'
            );
        }
    }

    private function assertNoNormalizedDuplicateApplications(): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM member_applications
                GROUP BY
                    association_id,
                    LOWER(REGEXP_REPLACE(BTRIM(first_name), '\s+', ' ', 'g')),
                    COALESCE(
                        NULLIF(
                            LOWER(REGEXP_REPLACE(BTRIM(middle_name), '\s+', ' ', 'g')),
                            ''
                        ),
                        ''
                    ),
                    LOWER(REGEXP_REPLACE(BTRIM(last_name), '\s+', ' ', 'g')),
                    birthday
                HAVING COUNT(*) > 1
            ) AS has_duplicates
        SQL);

        if ((bool) ($row->has_duplicates ?? false)) {
            throw new \RuntimeException(
                'Cannot harden application identity uniqueness because normalized duplicate applications exist.'
            );
        }
    }

    private function assertOneMemberPerApplication(): void
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM members
                WHERE application_id IS NOT NULL
                GROUP BY application_id
                HAVING COUNT(*) > 1
            ) AS has_duplicates
        SQL);

        if ((bool) ($row->has_duplicates ?? false)) {
            throw new \RuntimeException(
                'Cannot enforce one-member-per-application because duplicate application links exist.'
            );
        }
    }
};
'@
    'tests/Feature/AdminMemberManagementRouteTest.php' = @'
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class AdminMemberManagementRouteTest extends TestCase
{
    public function test_member_management_routes_are_registered_with_safe_http_verbs(): void
    {
        $index = RouteFacade::getRoutes()->getByName('members.index');
        $show = RouteFacade::getRoutes()->getByName('members.show');
        $update = RouteFacade::getRoutes()->getByName('members.update');
        $archive = RouteFacade::getRoutes()->getByName('members.archive');
        $applications = RouteFacade::getRoutes()->getByName('members.applications.index');

        $this->assertInstanceOf(Route::class, $index);
        $this->assertInstanceOf(Route::class, $show);
        $this->assertInstanceOf(Route::class, $update);
        $this->assertInstanceOf(Route::class, $archive);
        $this->assertInstanceOf(Route::class, $applications);

        $this->assertContains('GET', $index->methods());
        $this->assertContains('GET', $show->methods());
        $this->assertContains('PUT', $update->methods());
        $this->assertContains('PATCH', $archive->methods());
        $this->assertContains('GET', $applications->methods());
    }

    public function test_member_management_routes_are_admin_protected_and_have_no_admin_approval_or_delete_route(): void
    {
        $memberRoutes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with((string) $route->getName(), 'members.'));

        $this->assertNotEmpty($memberRoutes);

        $memberRoutes->each(function (Route $route): void {
            $this->assertContains(
                'assocmap.auth:System Administrator',
                $route->gatherMiddleware(),
                "Route {$route->getName()} must remain System Administrator only."
            );
        });

        $routeNames = $memberRoutes
            ->map(fn (Route $route): string => (string) $route->getName())
            ->values();

        $this->assertFalse($routeNames->contains(
            fn (string $name): bool => str_contains($name, 'approve')
                || str_contains($name, 'reject')
                || str_contains($name, 'delete')
                || str_contains($name, 'destroy')
        ));
    }
}
'@
    'tests/Unit/AdminMemberManagementPolicyTest.php' = @'
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Association;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\Role;
use App\Models\User;
use App\Policies\MemberApplicationPolicy;
use App\Policies\MemberPolicy;
use Tests\TestCase;

final class AdminMemberManagementPolicyTest extends TestCase
{
    public function test_system_administrator_can_view_update_and_archive_members(): void
    {
        $admin = $this->userWithRole('System Administrator', 1);
        $member = (new Member())->forceFill([
            'id' => 20,
            'association_id' => 5,
            'is_archived' => false,
        ]);

        $policy = new MemberPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $member));
        $this->assertTrue($policy->update($admin, $member));
        $this->assertTrue($policy->archive($admin, $member));
    }

    public function test_field_officer_can_only_view_members_from_assigned_association(): void
    {
        $officer = $this->userWithRole('Field Officer', 9);

        $assignedAssociation = (new Association())->forceFill([
            'id' => 5,
            'field_officer_id' => 9,
        ]);

        $otherAssociation = (new Association())->forceFill([
            'id' => 6,
            'field_officer_id' => 10,
        ]);

        $assignedMember = (new Member())->forceFill(['association_id' => 5]);
        $assignedMember->setRelation('association', $assignedAssociation);

        $otherMember = (new Member())->forceFill(['association_id' => 6]);
        $otherMember->setRelation('association', $otherAssociation);

        $policy = new MemberPolicy();

        $this->assertTrue($policy->view($officer, $assignedMember));
        $this->assertFalse($policy->view($officer, $otherMember));
        $this->assertFalse($policy->update($officer, $assignedMember));
        $this->assertFalse($policy->archive($officer, $assignedMember));
    }

    public function test_admin_can_inspect_applications_but_policy_defines_no_approval_action(): void
    {
        $admin = $this->userWithRole('System Administrator', 1);
        $application = (new MemberApplication())->forceFill([
            'id' => 50,
            'association_id' => 5,
        ]);

        $policy = new MemberApplicationPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $application));
        $this->assertFalse(method_exists($policy, 'approve'));
        $this->assertFalse(method_exists($policy, 'reject'));
    }

    private function userWithRole(string $roleName, int $id): User
    {
        $role = (new Role())->forceFill([
            'id' => 1,
            'role_name' => $roleName,
        ]);

        $user = (new User())->forceFill([
            'id' => $id,
            'is_active' => true,
        ]);
        $user->setRelation('role', $role);

        return $user;
    }
}
'@
    'resources/views/admin-pages/admin-member-management/index.blade.php' = @'
{{--
    resources/views/admin-pages/admin-member-management/index.blade.php

    System Administrator - Member Management
    Official members only. Applications remain a separate read-only Admin workflow view.
--}}
<x-dashboard-layout title="Member Management">
<div
    class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8"
    data-member-management-page
    data-barangays="{{ json_encode($barangays->map(fn ($barangay) => [
        'id' => $barangay->id,
        'area_unit_id' => $barangay->area_unit_id,
        'name' => $barangay->name,
        'is_archived' => (bool) $barangay->is_archived,
    ])->values()) }}"
>
    {{-- Header --}}
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    BFAR SAAD Phase II
                </span>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Member Management
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Manage approved association members, membership records, and historical beneficiary information.
                </p>
            </div>

            <a
                href="{{ route('members.applications.index') }}"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300
                       bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50
                       focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
            >
                View Applications
            </a>
        </div>

        <nav class="mt-5 flex gap-2 border-t border-slate-200 pt-4" aria-label="Member Management sections">
            <a
                href="{{ route('members.index') }}"
                aria-current="page"
                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white"
            >
                Official Members
            </a>
            <a
                href="{{ route('members.applications.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            >
                Applications
            </a>
        </nav>
    </header>

    {{-- Feedback --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Please correct the highlighted information.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Clickable analytics --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Member summary">
        @php
            $summaryCards = [
                [
                    'modal' => 'total-members-modal',
                    'label' => 'Total Official Members',
                    'value' => $summary['total'],
                    'hint' => 'All retained member records',
                ],
                [
                    'modal' => 'current-members-modal',
                    'label' => 'Current Members',
                    'value' => $summary['current'],
                    'hint' => 'Not archived',
                ],
                [
                    'modal' => 'archived-members-modal',
                    'label' => 'Archived Members',
                    'value' => $summary['archived'],
                    'hint' => 'Historical records',
                ],
                [
                    'modal' => 'representatives-modal',
                    'label' => 'Association Representatives',
                    'value' => $summary['representatives'],
                    'hint' => 'Current designated representatives',
                ],
                [
                    'modal' => 'associations-with-members-modal',
                    'label' => 'Associations With Members',
                    'value' => $summary['associations_with_members'],
                    'hint' => 'Associations represented in records',
                ],
            ];
        @endphp

        @foreach ($summaryCards as $card)
            <button
                type="button"
                data-open-modal="{{ $card['modal'] }}"
                data-analytics-card
                class="rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition
                       hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md
                       focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                aria-label="View details for {{ $card['label'] }}"
            >
                <p class="text-sm font-medium text-slate-600">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $card['hint'] }}</p>
            </button>
        @endforeach
    </section>

    {{-- Filters --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('members.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Member or association name"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               text-slate-900 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none
                               focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Association</span>
                    <select
                        name="association_id"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}" @selected((string) ($filters['association_id'] ?? '') === (string) $association->id)>
                                {{ $association->name }}{{ $association->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Record State</span>
                    <select
                        name="record_state"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="current" @selected(($filters['record_state'] ?? 'current') === 'current')>Current</option>
                        <option value="archived" @selected(($filters['record_state'] ?? '') === 'archived')>Archived</option>
                        <option value="all" @selected(($filters['record_state'] ?? '') === 'all')>All</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Municipality</span>
                    <select
                        name="area_unit_id"
                        data-filter-municipality
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All municipalities</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}" @selected((string) ($filters['area_unit_id'] ?? '') === (string) $municipality->id)>
                                {{ $municipality->name }}{{ $municipality->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Barangay</span>
                    <select
                        name="sub_unit_id"
                        data-filter-barangay
                        data-all-label="All barangays"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               disabled:bg-slate-100 disabled:text-slate-400
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">Select municipality first</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sex</span>
                    <select
                        name="sex_id"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All</option>
                        @foreach ($sexOptions as $sex)
                            <option value="{{ $sex->id }}" @selected((string) ($filters['sex_id'] ?? '') === (string) $sex->id)>
                                {{ $sex->sex_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Association Role</span>
                    <select
                        name="role_in_assoc"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $role)
                            <option value="{{ $role }}" @selected(($filters['role_in_assoc'] ?? '') === $role)>
                                {{ $role }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Beneficiary Type</span>
                    <select
                        name="beneficiary_type"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">All beneficiary types</option>
                        @foreach ($beneficiaryTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['beneficiary_type'] ?? '') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Registered From</span>
                    <input
                        type="date"
                        name="registered_from"
                        value="{{ $filters['registered_from'] ?? '' }}"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Registered To</span>
                    <input
                        type="date"
                        name="registered_to"
                        value="{{ $filters['registered_to'] ?? '' }}"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select
                        name="sort"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="name_asc" @selected(($filters['sort'] ?? 'name_asc') === 'name_asc')>Name A–Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z–A</option>
                        <option value="registered_desc" @selected(($filters['sort'] ?? '') === 'registered_desc')>Newest Registered</option>
                        <option value="registered_asc" @selected(($filters['sort'] ?? '') === 'registered_asc')>Oldest Registered</option>
                        <option value="association_asc" @selected(($filters['sort'] ?? '') === 'association_asc')>Association A–Z</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Rows per page</span>
                    <select
                        name="per_page"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) ($filters['per_page'] ?? 15) === $option)>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4">
                <button
                    type="submit"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-800 px-4 py-2
                           text-sm font-semibold text-white transition hover:bg-slate-700
                           focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
                >
                    Apply Filters
                </button>
                <a
                    href="{{ route('members.index') }}"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300
                           bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50
                           focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                >
                    Reset Filters
                </a>
            </div>
        </form>
    </section>

    @php
        /*
         * Build only current-page presentation payloads for quick View/Edit modals.
         * This avoids loading the complete member dataset into JavaScript.
         */
        $memberPresentation = static function ($member) use ($listState): array {
            $fullName = trim(implode(' ', array_filter([
                $member->first_name,
                $member->middle_name,
                $member->last_name,
            ], fn ($part) => filled($part))));

            $locationLabel = trim(
                ($member->association?->subUnit?->name ?? 'No barangay')
                .', '
                .($member->association?->areaUnit?->name ?? 'No municipality')
            );

            $isRepresentative = (int) ($member->association?->representative_member_id ?? 0) === (int) $member->id;

            $reviewerName = $member->application?->reviewer
                ? trim(implode(' ', array_filter([
                    $member->application->reviewer->first_name,
                    $member->application->reviewer->middle_name,
                    $member->application->reviewer->last_name,
                ], fn ($part) => filled($part))))
                : null;

            $detailPayload = [
                'id' => $member->id,
                'full_name' => $fullName,
                'birthday' => $member->birthday?->format('F j, Y'),
                'sex' => $member->sex?->sex_name,
                'contact_number' => $member->contact_number,
                'address' => $member->address,
                'association' => $member->association?->name,
                'municipality' => $member->association?->areaUnit?->name,
                'barangay' => $member->association?->subUnit?->name,
                'role_in_assoc' => $member->role_in_assoc,
                'beneficiary_type' => $member->beneficiary_type,
                'date_registered' => $member->date_registered?->format('F j, Y'),
                'record_state' => $member->is_archived ? 'Archived' : 'Current',
                'representative_state' => $isRepresentative ? 'Current Association Representative' : 'No',
                'application_id' => $member->application_id,
                'application_status' => $member->application?->status?->status_name,
                'application_submitted_at' => $member->application?->created_at?->format('F j, Y g:i A'),
                'reviewed_by' => $reviewerName,
                'reviewed_at' => $member->application?->reviewed_at?->format('F j, Y g:i A'),
                'created_at' => $member->created_at?->format('F j, Y g:i A'),
                'updated_at' => $member->updated_at?->format('F j, Y g:i A'),
                'show_url' => route('members.show', [
                    'member' => $member,
                    ...$listState,
                ]),
            ];

            $editPayload = [
                'full_name' => $fullName,
                'first_name' => $member->first_name,
                'middle_name' => $member->middle_name,
                'last_name' => $member->last_name,
                'birthday' => $member->birthday?->format('Y-m-d'),
                'sex_id' => $member->sex_id,
                'role_in_assoc' => $member->role_in_assoc,
                'beneficiary_type' => $member->beneficiary_type,
                'contact_number' => $member->contact_number,
                'address' => $member->address,
                'date_registered' => $member->date_registered?->format('Y-m-d'),
                'update_url' => route('members.update', $member),
            ];

            $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

            return [
                'full_name' => $fullName,
                'location_label' => $locationLabel,
                'is_representative' => $isRepresentative,
                'detail_payload_json' => json_encode($detailPayload, $jsonFlags),
                'edit_payload_json' => json_encode($editPayload, $jsonFlags),
            ];
        };
    @endphp

    {{-- Records --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="member-records-title">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <h2 id="member-records-title" class="font-semibold text-slate-900">Official Member Records</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Showing {{ $members->firstItem() ?? 0 }}–{{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members
                </p>
            </div>
            <p class="text-xs text-slate-500">Private administrative information</p>
        </div>

        @if ($members->isEmpty())
            <div class="px-5 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8c0-3.5 3.1-6 7-6s7 2.5 7 6" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">No members found</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                    No official member records match the selected filters.
                </p>
                <a href="{{ route('members.index') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset Filters
                </a>
            </div>
        @else
            {{-- Desktop: 7 intentional columns; no horizontal-scroll container. --}}
            <div class="hidden xl:block">
                <table class="w-full table-fixed border-collapse">
                    <caption class="sr-only">Official association members</caption>
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th scope="col" class="w-[18%] px-4 py-3">Member</th>
                            <th scope="col" class="w-[21%] px-4 py-3">Association</th>
                            <th scope="col" class="w-[16%] px-4 py-3">Role / Beneficiary</th>
                            <th scope="col" class="w-[13%] px-4 py-3">Contact</th>
                            <th scope="col" class="w-[11%] px-4 py-3">Registered</th>
                            <th scope="col" class="w-[9%] px-4 py-3">State</th>
                            <th scope="col" class="w-[12%] px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($members as $member)
                            @php
                                $presentation = $memberPresentation($member);
                                $memberFullName = $presentation['full_name'];
                                $locationLabel = $presentation['location_label'];
                                $isRepresentative = $presentation['is_representative'];
                                $detailPayloadJson = $presentation['detail_payload_json'];
                                $editPayloadJson = $presentation['edit_payload_json'];
                            @endphp
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-4">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white">
                                            {{ strtoupper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="break-words text-sm font-semibold text-slate-900">{{ $memberFullName }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $member->sex?->sex_name ?? 'Sex not recorded' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $member->association?->name ?? 'Unknown association' }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $locationLabel }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $member->role_in_assoc ?: 'Unassigned' }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $member->beneficiary_type ?: 'Beneficiary type not set' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ $member->contact_number ?: '—' }}</td>
                                <td class="px-4 py-4 text-sm text-slate-700">{{ $member->date_registered?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $member->is_archived ? 'Archived' : 'Current' }}
                                        </span>
                                        @if ($isRepresentative)
                                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                                Representative
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    @include('admin-pages.admin-member-management.partials.member-actions', [
                                        'member' => $member,
                                        'memberFullName' => $memberFullName,
                                        'detailPayloadJson' => $detailPayloadJson,
                                        'editPayloadJson' => $editPayloadJson,
                                        'isRepresentative' => $isRepresentative,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tablet/smaller laptop: five meaningful information groups. --}}
            <div class="hidden md:block xl:hidden">
                <div class="grid grid-cols-[1.25fr_1.25fr_0.9fr_0.7fr_auto] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <div>Member</div>
                    <div>Association</div>
                    <div>Role</div>
                    <div>State</div>
                    <div class="text-right">Actions</div>
                </div>

                @foreach ($members as $member)
                    @php
                        $presentation = $memberPresentation($member);
                        $memberFullName = $presentation['full_name'];
                        $locationLabel = $presentation['location_label'];
                        $isRepresentative = $presentation['is_representative'];
                        $detailPayloadJson = $presentation['detail_payload_json'];
                        $editPayloadJson = $presentation['edit_payload_json'];
                    @endphp
                    <div class="grid grid-cols-[1.25fr_1.25fr_0.9fr_0.7fr_auto] gap-3 border-b border-slate-100 px-4 py-4 last:border-b-0">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-slate-900">{{ $memberFullName }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $member->sex?->sex_name ?? '—' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="break-words text-sm font-medium text-slate-800">{{ $member->association?->name ?? 'Unknown' }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $locationLabel }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="break-words text-sm text-slate-800">{{ $member->role_in_assoc ?: 'Unassigned' }}</p>
                        </div>
                        <div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $member->is_archived ? 'Archived' : 'Current' }}
                            </span>
                            @if ($isRepresentative)
                                <p class="mt-1 text-[11px] font-medium text-blue-700">Representative</p>
                            @endif
                        </div>
                        <div>
                            @include('admin-pages.admin-member-management.partials.member-actions', [
                                'member' => $member,
                                'memberFullName' => $memberFullName,
                                'detailPayloadJson' => $detailPayloadJson,
                                'editPayloadJson' => $editPayloadJson,
                                'isRepresentative' => $isRepresentative,
                            ])
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Mobile cards --}}
            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($members as $member)
                    @php
                        $presentation = $memberPresentation($member);
                        $memberFullName = $presentation['full_name'];
                        $locationLabel = $presentation['location_label'];
                        $isRepresentative = $presentation['is_representative'];
                        $detailPayloadJson = $presentation['detail_payload_json'];
                        $editPayloadJson = $presentation['edit_payload_json'];
                    @endphp
                    <article class="space-y-4 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-bold text-slate-900">{{ $memberFullName }}</p>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $member->association?->name ?? 'Unknown association' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $member->is_archived ? 'Archived' : 'Current' }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Role</dt>
                                <dd class="mt-1 break-words text-slate-800">{{ $member->role_in_assoc ?: 'Unassigned' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Registered</dt>
                                <dd class="mt-1 text-slate-800">{{ $member->date_registered?->format('M j, Y') ?? '—' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs font-medium text-slate-500">Location</dt>
                                <dd class="mt-1 break-words text-slate-800">{{ $locationLabel }}</dd>
                            </div>
                        </dl>

                        @if ($isRepresentative)
                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                Association Representative
                            </span>
                        @endif

                        @include('admin-pages.admin-member-management.partials.member-actions', [
                            'member' => $member,
                            'memberFullName' => $memberFullName,
                            'detailPayloadJson' => $detailPayloadJson,
                            'editPayloadJson' => $editPayloadJson,
                            'isRepresentative' => $isRepresentative,
                        ])
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-4 py-4 sm:px-5">
                {{ $members->links() }}
            </div>
        @endif
    </section>

    {{-- Analytics modals --}}
    <x-admin-modal id="total-members-modal" title="Total Official Members" description="Aggregate information across retained official member records." size="xl">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['total'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Current</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ $summary['current'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-100 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-600">Archived</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['archived'] }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section>
                <h3 class="text-sm font-semibold text-slate-900">Sex Distribution</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['sex_distribution'] as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>{{ $row->sex_name }}</span>
                            <strong>{{ $row->member_count }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No data available.</p>
                    @endforelse
                </div>
            </section>

            <section>
                <h3 class="text-sm font-semibold text-slate-900">Beneficiary Type Distribution</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['beneficiary_distribution'] as $row)
                        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span class="break-words">{{ $row->beneficiary_type }}</span>
                            <strong>{{ $row->member_count }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No data available.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </x-admin-modal>

    <x-admin-modal id="current-members-modal" title="Current Members" description="Current members are official member records where is_archived is false." size="xl">
        <div class="grid gap-6 lg:grid-cols-2">
            <section>
                <h3 class="text-sm font-semibold text-slate-900">Current Members by Association Role</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['role_distribution'] as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>{{ $row->role_name }}</span>
                            <strong>{{ $row->member_count }}</strong>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No current members available.</p>
                    @endforelse
                </div>
            </section>

            <section>
                <h3 class="text-sm font-semibold text-slate-900">Recent Registrations</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($analytics['recent_registrations'] as $row)
                        <div class="rounded-lg border border-slate-200 px-3 py-2">
                            <p class="break-words text-sm font-semibold text-slate-900">
                                {{ trim($row->first_name.' '.($row->middle_name ?? '').' '.$row->last_name) }}
                            </p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $row->association_name }} · {{ $row->date_registered }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No recent registrations available.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </x-admin-modal>

    <x-admin-modal id="archived-members-modal" title="Archived Members" description="Archived records remain stored for history, reporting, and audit." size="lg">
        <p class="text-3xl font-bold text-slate-900">{{ $summary['archived'] }}</p>
        <p class="mt-1 text-sm text-slate-500">Archived official member records</p>

        <div class="mt-6 space-y-2">
            @forelse ($analytics['members_by_association']->where('archived_count', '>', 0) as $row)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-3 py-3">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ $row->name }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">{{ $row->barangay_name ?? 'No barangay' }}, {{ $row->municipality_name ?? 'No municipality' }}</p>
                    </div>
                    <span class="shrink-0 text-sm font-bold text-slate-900">{{ $row->archived_count }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">No archived members are currently recorded.</p>
            @endforelse
        </div>
    </x-admin-modal>

    <x-admin-modal id="representatives-modal" title="Association Representatives" description="Current representatives identified through Association Management." size="xl">
        <div class="space-y-3">
            @forelse ($analytics['representatives'] as $row)
                <div class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[1.2fr_1.2fr_1fr]">
                    <div>
                        <p class="break-words text-sm font-semibold text-slate-900">
                            {{ trim($row->first_name.' '.($row->middle_name ?? '').' '.$row->last_name) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">{{ $row->role_in_assoc ?: 'Role not assigned' }}</p>
                    </div>
                    <div>
                        <p class="break-words text-sm text-slate-800">{{ $row->association_name }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">{{ $row->barangay_name ?? 'No barangay' }}, {{ $row->municipality_name ?? 'No municipality' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500">Contact</p>
                        <p class="mt-1 text-sm text-slate-800">{{ $row->contact_number ?: 'Not recorded' }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No Association Representatives are currently assigned.</p>
            @endforelse
        </div>
    </x-admin-modal>

    <x-admin-modal id="associations-with-members-modal" title="Associations With Members" description="Top associations by retained official member records." size="xl">
        <div class="space-y-3">
            @forelse ($analytics['members_by_association'] as $row)
                <div class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[1.5fr_repeat(3,0.6fr)]">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-slate-900">{{ $row->name }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">{{ $row->barangay_name ?? 'No barangay' }}, {{ $row->municipality_name ?? 'No municipality' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Total</p>
                        <p class="mt-1 font-bold text-slate-900">{{ $row->total_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Current</p>
                        <p class="mt-1 font-bold text-emerald-700">{{ $row->current_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Archived</p>
                        <p class="mt-1 font-bold text-slate-700">{{ $row->archived_count }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No associations have official member records yet.</p>
            @endforelse
        </div>
    </x-admin-modal>

    {{-- Quick details modal --}}
    <x-admin-modal id="member-details-modal" title="Member Details" description="Authorized administrative record details." size="xl">
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ([
                'Identity' => [
                    ['Full name', 'full_name'],
                    ['Birthday', 'birthday'],
                    ['Sex', 'sex'],
                    ['Contact', 'contact_number'],
                    ['Address', 'address'],
                ],
                'Membership' => [
                    ['Association', 'association'],
                    ['Municipality', 'municipality'],
                    ['Barangay', 'barangay'],
                    ['Association role', 'role_in_assoc'],
                    ['Beneficiary type', 'beneficiary_type'],
                    ['Date registered', 'date_registered'],
                    ['Record state', 'record_state'],
                    ['Representative', 'representative_state'],
                ],
                'Source' => [
                    ['Application ID', 'application_id'],
                    ['Application status', 'application_status'],
                    ['Submitted', 'application_submitted_at'],
                    ['Reviewed by', 'reviewed_by'],
                    ['Reviewed at', 'reviewed_at'],
                ],
                'System' => [
                    ['Member record ID', 'id'],
                    ['Created', 'created_at'],
                    ['Last updated', 'updated_at'],
                ],
            ] as $section => $items)
                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-bold text-slate-900">{{ $section }}</h3>
                    <dl class="mt-3 space-y-3">
                        @foreach ($items as [$label, $field])
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                                <dd data-detail-field="{{ $field }}" class="mt-1 break-words text-sm text-slate-800">—</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <button type="button" data-close-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Close
                </button>
                <a data-member-full-record href="#" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                    Open Full Record
                </a>
            </div>
        </x-slot>
    </x-admin-modal>

    {{-- Edit modal --}}
    <x-admin-modal id="edit-member-modal" title="Edit Member" description="Correct official member profile information without changing the source application or association." size="xl">
        <form method="POST" action="#" data-edit-member-form class="space-y-6">
            @csrf
            @method('PUT')

            <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                Editing: <strong data-edit-member-name class="text-slate-900">Member</strong>
            </p>

            <section>
                <h3 class="text-sm font-bold text-slate-900">Personal Information</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['First Name', 'first_name', true],
                        ['Middle Name', 'middle_name', false],
                        ['Last Name', 'last_name', true],
                    ] as [$label, $name, $required])
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                            <input
                                type="text"
                                name="{{ $name }}"
                                data-member-field="{{ $name }}"
                                maxlength="255"
                                @if($required) required @endif
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            >
                        </label>
                    @endforeach

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Birthday</span>
                        <input type="date" name="birthday" data-member-field="birthday" max="{{ now()->format('Y-m-d') }}" required
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Sex</span>
                        <select name="sex_id" data-member-field="sex_id" required
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                            @foreach ($sexOptions as $sex)
                                <option value="{{ $sex->id }}">{{ $sex->sex_name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5">
                <h3 class="text-sm font-bold text-slate-900">Membership Information</h3>
                <p class="mt-1 text-xs text-slate-500">Association ownership and source application are intentionally not editable here.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Association Role</span>
                        <select name="role_in_assoc" data-member-field="role_in_assoc"
                                class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                            <option value="">Unassigned</option>
                            @foreach ($roleOptions as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Beneficiary Type</span>
                        <input type="text" name="beneficiary_type" data-member-field="beneficiary_type" maxlength="100"
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Date Registered</span>
                        <input type="date" name="date_registered" data-member-field="date_registered" max="{{ now()->format('Y-m-d') }}" required
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5">
                <h3 class="text-sm font-bold text-slate-900">Contact Information</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Contact Number</span>
                        <input type="text" name="contact_number" data-member-field="contact_number" maxlength="50"
                               class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Address</span>
                        <textarea name="address" data-member-field="address" rows="3" maxlength="1000"
                                  class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"></textarea>
                    </label>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                <button type="button" data-close-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    Save Changes
                </button>
            </div>
        </form>
    </x-admin-modal>

    {{-- Archive confirmation --}}
    <x-admin-modal id="archive-member-modal" title="Archive Member?" description="This action keeps the record for history, reporting, and audit." size="sm">
        <p class="text-sm leading-6 text-slate-700">
            Archive <strong data-archive-member-name class="text-slate-900">this member</strong>?
            The member will be removed from current member lists but will not be permanently deleted.
        </p>

        <form method="POST" action="#" data-archive-form class="mt-5">
            @csrf
            @method('PATCH')
            <div class="flex justify-end gap-3">
                <button type="button" data-close-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Archive Member
                </button>
            </div>
        </form>
    </x-admin-modal>
</div>
</x-dashboard-layout>
'@
    'resources/views/admin-pages/admin-member-management/partials/member-actions.blade.php' = @'
{{--
    Compact member actions reused by desktop, tablet, and mobile layouts.
--}}
<div class="flex flex-wrap justify-end gap-2">
    <button
        type="button"
        data-member-details="{{ $detailPayloadJson }}"
        class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700
               transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
    >
        View
    </button>

    @if (!$member->is_archived)
        <button
            type="button"
            data-edit-member="{{ $editPayloadJson }}"
            class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700
                   transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
        >
            Edit
        </button>

        @if ($isRepresentative)
            <span
                class="inline-flex cursor-not-allowed items-center rounded-lg bg-blue-50 px-2.5 py-1.5
                       text-xs font-semibold text-blue-700"
                title="Assign a different Association Representative before archiving this member."
            >
                Representative
            </span>
        @else
            <button
                type="button"
                data-archive-member
                data-archive-url="{{ route('members.archive', $member) }}"
                data-member-name="{{ $memberFullName }}"
                class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700
                       transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400"
            >
                Archive
            </button>
        @endif
    @endif
</div>
'@
    'resources/views/admin-pages/admin-member-management/show.blade.php' = @'
{{--
    resources/views/admin-pages/admin-member-management/show.blade.php
    Full authorized administrative record for one official member.
--}}
<x-dashboard-layout :title="$member->first_name.' '.$member->last_name">
@php
    $fullName = trim(implode(' ', array_filter([
        $member->first_name,
        $member->middle_name,
        $member->last_name,
    ], fn ($part) => filled($part))));

    $reviewerName = $member->application?->reviewer
        ? trim(implode(' ', array_filter([
            $member->application->reviewer->first_name,
            $member->application->reviewer->middle_name,
            $member->application->reviewer->last_name,
        ], fn ($part) => filled($part))))
        : null;

    $isRepresentative = (int) ($member->association?->representative_member_id ?? 0) === (int) $member->id;
@endphp

<div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <a href="{{ $backToListUrl }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
            ← Back to Member Management
        </a>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    Official Member Record
                </span>
                <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $fullName }}
                </h1>
                <p class="mt-2 break-words text-sm text-slate-600">
                    {{ $member->association?->name ?? 'Unknown association' }}
                    · {{ $member->association?->subUnit?->name ?? 'No barangay' }},
                    {{ $member->association?->areaUnit?->name ?? 'No municipality' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold {{ $member->is_archived ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $member->is_archived ? 'Archived' : 'Current' }}
                </span>
                @if ($isRepresentative)
                    <span class="inline-flex rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700">
                        Association Representative
                    </span>
                @endif
            </div>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Identity & Contact</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Full name', $fullName],
                    ['Birthday', $member->birthday?->format('F j, Y')],
                    ['Sex', $member->sex?->sex_name],
                    ['Contact number', $member->contact_number],
                    ['Address', $member->address, true],
                ] as $item)
                    <div class="{{ ($item[2] ?? false) ? 'sm:col-span-2' : '' }}">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Membership</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Association', $member->association?->name],
                    ['Municipality', $member->association?->areaUnit?->name],
                    ['Barangay', $member->association?->subUnit?->name],
                    ['Association role', $member->role_in_assoc],
                    ['Beneficiary type', $member->beneficiary_type],
                    ['Date registered', $member->date_registered?->format('F j, Y')],
                ] as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Source Application</h2>
            @if ($member->application)
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Application ID', '#'.$member->application->id],
                        ['Status', $member->application->status?->status_name],
                        ['Submitted', $member->application->created_at?->format('F j, Y g:i A')],
                        ['Reviewed by', $reviewerName],
                        ['Reviewed at', $member->application->reviewed_at?->format('F j, Y g:i A')],
                    ] as $item)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                            <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <a
                    href="{{ route('members.applications.show', ['application' => $member->application]) }}"
                    class="mt-5 inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Open Application Record
                </a>
            @else
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    This record has no linked member application. It may be a legacy or manually migrated historical record.
                </p>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">System Information</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Member record ID', '#'.$member->id],
                    ['Linked user account', $member->user?->email],
                    ['Created', $member->created_at?->format('F j, Y g:i A')],
                    ['Last updated', $member->updated_at?->format('F j, Y g:i A')],
                ] as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</div>
</x-dashboard-layout>
'@
    'resources/views/admin-pages/admin-member-management/applications.blade.php' = @'
{{--
    resources/views/admin-pages/admin-member-management/applications.blade.php

    Administrator monitoring view only.
    Approval and rejection remain Association Representative responsibilities.
--}}
<x-dashboard-layout title="Member Applications">
<div class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    BFAR SAAD Phase II
                </span>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Member Applications
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Monitor Pending, Approved, and Rejected membership requests without bypassing Association Representative review.
                </p>
            </div>

            <a
                href="{{ route('members.index') }}"
                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5
                       text-sm font-semibold text-slate-700 transition hover:bg-slate-50
                       focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
            >
                Back to Official Members
            </a>
        </div>

        <nav class="mt-5 flex gap-2 border-t border-slate-200 pt-4" aria-label="Member Management sections">
            <a
                href="{{ route('members.index') }}"
                class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            >
                Official Members
            </a>
            <a
                href="{{ route('members.applications.index') }}"
                aria-current="page"
                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white"
            >
                Applications
            </a>
        </nav>
    </header>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">
        <strong>Administrative monitoring only.</strong>
        Only the designated Association Representative may approve or reject member applications.
    </div>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Application summary">
        @foreach ([
            ['Total Applications', $summary['total'], 'All retained requests'],
            ['Pending', $summary['pending'], 'Awaiting representative review'],
            ['Approved', $summary['approved'], 'Converted through the approved workflow'],
            ['Rejected', $summary['rejected'], 'Retained for audit history'],
        ] as [$label, $value, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('members.applications.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Search</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Applicant or association name"
                        class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Association</span>
                    <select name="association_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}" @selected((string) ($filters['association_id'] ?? '') === (string) $association->id)>
                                {{ $association->name }}{{ $association->is_archived ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Status</span>
                    <select name="status_id"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="">All statuses</option>
                        @foreach ($applicationStatuses as $status)
                            <option value="{{ $status->id }}" @selected((string) ($filters['status_id'] ?? '') === (string) $status->id)>
                                {{ $status->status_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Submitted From</span>
                    <input type="date" name="submitted_from" value="{{ $filters['submitted_from'] ?? '' }}"
                           class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                                  focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Submitted To</span>
                    <input type="date" name="submitted_to" value="{{ $filters['submitted_to'] ?? '' }}"
                           class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                                  focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Sort</span>
                    <select name="sort"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        <option value="submitted_desc" @selected(($filters['sort'] ?? 'submitted_desc') === 'submitted_desc')>Newest Submitted</option>
                        <option value="submitted_asc" @selected(($filters['sort'] ?? '') === 'submitted_asc')>Oldest Submitted</option>
                        <option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Applicant A–Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Applicant Z–A</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Rows per page</span>
                    <select name="per_page"
                            class="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                                   focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) ($filters['per_page'] ?? 15) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4">
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    Apply Filters
                </button>
                <a href="{{ route('members.applications.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset Filters
                </a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Application Records</h2>
            <p class="mt-1 text-xs text-slate-500">
                Showing {{ $applications->firstItem() ?? 0 }}–{{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} applications
            </p>
        </div>

        @if ($applications->isEmpty())
            <div class="px-5 py-14 text-center">
                <h3 class="text-base font-semibold text-slate-900">No applications found</h3>
                <p class="mt-2 text-sm text-slate-500">No membership applications match the selected filters.</p>
                <a href="{{ route('members.applications.index') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset Filters
                </a>
            </div>
        @else
            <div class="hidden lg:block">
                <table class="w-full table-fixed border-collapse">
                    <caption class="sr-only">Member applications</caption>
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="w-[23%] px-4 py-3">Applicant</th>
                            <th class="w-[23%] px-4 py-3">Association</th>
                            <th class="w-[12%] px-4 py-3">Status</th>
                            <th class="w-[18%] px-4 py-3">Reviewer / Representative</th>
                            <th class="w-[14%] px-4 py-3">Timeline</th>
                            <th class="w-[10%] px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($applications as $application)
                            @php
                                $applicantName = trim(implode(' ', array_filter([
                                    $application->first_name,
                                    $application->middle_name,
                                    $application->last_name,
                                ], fn ($part) => filled($part))));
                                $reviewerName = $application->reviewer
                                    ? trim(implode(' ', array_filter([
                                        $application->reviewer->first_name,
                                        $application->reviewer->middle_name,
                                        $application->reviewer->last_name,
                                    ], fn ($part) => filled($part))))
                                    : null;
                                $representativeName = $application->association?->representative
                                    ? trim(implode(' ', array_filter([
                                        $application->association->representative->first_name,
                                        $application->association->representative->middle_name,
                                        $application->association->representative->last_name,
                                    ], fn ($part) => filled($part))))
                                    : null;
                                $statusName = $application->status?->status_name ?? 'Unknown';
                                $statusClass = match ($statusName) {
                                    'Approved' => 'bg-emerald-50 text-emerald-700',
                                    'Rejected' => 'bg-red-50 text-red-700',
                                    default => 'bg-amber-50 text-amber-800',
                                };
                            @endphp
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-semibold text-slate-900">{{ $applicantName }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $application->sex?->sex_name ?? 'Sex not recorded' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="break-words text-sm font-medium text-slate-800">{{ $application->association?->name ?? 'Unknown association' }}</p>
                                    <p class="mt-1 break-words text-xs text-slate-500">{{ $application->association?->subUnit?->name ?? 'No barangay' }}, {{ $application->association?->areaUnit?->name ?? 'No municipality' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statusName }}
                                    </span>
                                    @if ($application->member)
                                        <p class="mt-1 text-[11px] text-slate-500">Member #{{ $application->member->id }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if ($reviewerName)
                                        <p class="break-words text-sm text-slate-800">{{ $reviewerName }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Reviewed application</p>
                                    @else
                                        <p class="break-words text-sm text-slate-800">{{ $representativeName ?: 'No representative assigned' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Current representative</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    <p>Submitted {{ $application->created_at?->format('M j, Y') }}</p>
                                    @if ($application->reviewed_at)
                                        <p class="mt-1">Reviewed {{ $application->reviewed_at->format('M j, Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('members.applications.show', ['application' => $application, ...request()->query()]) }}"
                                       class="inline-flex rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden">
                @foreach ($applications as $application)
                    @php
                        $applicantName = trim(implode(' ', array_filter([
                            $application->first_name,
                            $application->middle_name,
                            $application->last_name,
                        ], fn ($part) => filled($part))));
                        $statusName = $application->status?->status_name ?? 'Unknown';
                        $statusClass = match ($statusName) {
                            'Approved' => 'bg-emerald-50 text-emerald-700',
                            'Rejected' => 'bg-red-50 text-red-700',
                            default => 'bg-amber-50 text-amber-800',
                        };
                    @endphp
                    <article class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-bold text-slate-900">{{ $applicantName }}</p>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $application->association?->name ?? 'Unknown association' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusName }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">Submitted {{ $application->created_at?->format('M j, Y') }}</p>
                            <a href="{{ route('members.applications.show', ['application' => $application, ...request()->query()]) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                View
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-4 py-4 sm:px-5">
                {{ $applications->links() }}
            </div>
        @endif
    </section>
</div>
</x-dashboard-layout>
'@
    'resources/views/admin-pages/admin-member-management/application-show.blade.php' = @'
{{--
    resources/views/admin-pages/admin-member-management/application-show.blade.php
    Read-only System Administrator inspection of one membership application.
--}}
<x-dashboard-layout title="Member Application">
@php
    $applicantName = trim(implode(' ', array_filter([
        $application->first_name,
        $application->middle_name,
        $application->last_name,
    ], fn ($part) => filled($part))));

    $reviewerName = $application->reviewer
        ? trim(implode(' ', array_filter([
            $application->reviewer->first_name,
            $application->reviewer->middle_name,
            $application->reviewer->last_name,
        ], fn ($part) => filled($part))))
        : null;

    $representativeName = $application->association?->representative
        ? trim(implode(' ', array_filter([
            $application->association->representative->first_name,
            $application->association->representative->middle_name,
            $application->association->representative->last_name,
        ], fn ($part) => filled($part))))
        : null;

    $statusName = $application->status?->status_name ?? 'Unknown';
    $statusClass = match ($statusName) {
        'Approved' => 'bg-emerald-50 text-emerald-700',
        'Rejected' => 'bg-red-50 text-red-700',
        default => 'bg-amber-50 text-amber-800',
    };
@endphp

<div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <a href="{{ $backToListUrl }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
            ← Back to Member Applications
        </a>

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                    Application #{{ $application->id }}
                </span>
                <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $applicantName }}
                </h1>
                <p class="mt-2 break-words text-sm text-slate-600">
                    {{ $application->association?->name ?? 'Unknown association' }}
                </p>
            </div>

            <span class="inline-flex self-start rounded-full px-3 py-1.5 text-xs font-semibold {{ $statusClass }}">
                {{ $statusName }}
            </span>
        </div>
    </header>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">
        This Admin view is read-only. Approval and rejection remain the responsibility of the designated Association Representative.
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Applicant Information</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Full name', $applicantName],
                    ['Birthday', $application->birthday?->format('F j, Y')],
                    ['Sex', $application->sex?->sex_name],
                    ['Beneficiary type', $application->beneficiary_type],
                    ['Contact number', $application->contact_number],
                    ['Address', $application->address, true],
                ] as $item)
                    <div class="{{ ($item[2] ?? false) ? 'sm:col-span-2' : '' }}">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Association & Workflow</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Association', $application->association?->name],
                    ['Municipality', $application->association?->areaUnit?->name],
                    ['Barangay', $application->association?->subUnit?->name],
                    ['Current representative', $representativeName],
                    ['Application status', $statusName],
                    ['Submitted', $application->created_at?->format('F j, Y g:i A')],
                    ['Reviewed by', $reviewerName],
                    ['Reviewed at', $application->reviewed_at?->format('F j, Y g:i A')],
                ] as $item)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item[0] }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-800">{{ $item[1] ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($statusName === 'Rejected')
            <section class="rounded-xl border border-red-200 bg-red-50 p-5 lg:col-span-2">
                <h2 class="text-base font-bold text-red-900">Rejection Reason</h2>
                <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-red-800">
                    {{ $application->rejection_reason ?: 'No rejection reason is recorded.' }}
                </p>
            </section>
        @endif

        @if ($application->member)
            <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 lg:col-span-2">
                <h2 class="text-base font-bold text-emerald-900">Resulting Official Member</h2>
                <p class="mt-2 break-words text-sm text-emerald-800">
                    This approved application is linked to Member #{{ $application->member->id }}:
                    {{ trim($application->member->first_name.' '.($application->member->middle_name ?? '').' '.$application->member->last_name) }}.
                </p>
                <a href="{{ route('members.show', $application->member) }}"
                   class="mt-4 inline-flex rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Open Member Record
                </a>
            </section>
        @endif
    </div>
</div>
</x-dashboard-layout>
'@
    }

    foreach ($entry in $ManagedFiles.GetEnumerator()) {
        Register-ManagedFile -RelativePath $entry.Key -DesiredContent $entry.Value
    }

    # ---------------------------------------------------------------------
    # routes/web.php - add Admin Member Management routes only once.
    # ---------------------------------------------------------------------
    $routesPath = 'routes/web.php'
    $routesCurrentRaw = Read-Utf8File -Path (Get-ProjectPath -RelativePath $routesPath)
    $routesNewline = Get-NewlineStyle -Text $routesCurrentRaw
    $routesLf = Convert-ToLf -Text $routesCurrentRaw

    $memberRoutesBlock = @'
// ============================================================
// MEMBER-MANAGEMENT-ROUTES
// Member Management Module - System Administrator only.
// Administrator may manage official members and inspect applications.
// Approval / rejection remain Association Representative responsibilities.
// ============================================================
Route::middleware('assocmap.auth:System Administrator')
    ->prefix('admin/members')
    ->name('members.')
    ->group(function (): void {
        // Static application routes must stay before /{member}.
        Route::get('/applications', [\App\Http\Controllers\Admin\MemberApplicationManagementController::class, 'index'])
            ->name('applications.index');
        Route::get('/applications/{application}', [\App\Http\Controllers\Admin\MemberApplicationManagementController::class, 'show'])
            ->whereNumber('application')
            ->name('applications.show');

        Route::get('/', [\App\Http\Controllers\Admin\MemberManagementController::class, 'index'])
            ->name('index');
        Route::get('/{member}', [\App\Http\Controllers\Admin\MemberManagementController::class, 'show'])
            ->whereNumber('member')
            ->name('show');
        Route::put('/{member}', [\App\Http\Controllers\Admin\MemberManagementController::class, 'update'])
            ->whereNumber('member')
            ->name('update');
        Route::patch('/{member}/archive', [\App\Http\Controllers\Admin\MemberManagementController::class, 'archive'])
            ->whereNumber('member')
            ->name('archive');
    });
// MEMBER-MANAGEMENT-ROUTES-END
'@

    if (-not $routesLf.Contains('// MEMBER-MANAGEMENT-ROUTES')) {
        if ($routesLf -match "->name\('members\.") {
            throw 'Member-related routes already exist without the expected Member Management patch markers.'
        }

        $routeMarker = '// ASSOCMAP_ASSOCIATION_ROUTES_END'
        Assert-ExpectedText -Content $routesLf -Expected $routeMarker -Description 'Association route end marker'

        $routesLf = $routesLf.Replace(
            $routeMarker,
            $routeMarker + "`n`n" + (Convert-ToLf -Text $memberRoutesBlock).TrimEnd("`n")
        )
    }
    elseif (-not $routesLf.Contains('// MEMBER-MANAGEMENT-ROUTES-END')) {
        throw 'Member Management route start marker exists without its end marker.'
    }

    Register-UpdatedFile -RelativePath $routesPath -DesiredContent (
        Convert-FromLf -Text $routesLf -Newline $routesNewline
    )

    # ---------------------------------------------------------------------
    # AssociationManagementController - fix current custom-session actor ID
    # and centralize the lookup through SessionUserResolver.
    # ---------------------------------------------------------------------
    $associationControllerPath = 'app/Http/Controllers/Admin/AssociationManagementController.php'
    $associationCurrentRaw = Read-Utf8File -Path (Get-ProjectPath -RelativePath $associationControllerPath)
    $associationNewline = Get-NewlineStyle -Text $associationCurrentRaw
    $associationLf = Convert-ToLf -Text $associationCurrentRaw

    if (-not $associationLf.Contains('use App\Services\SessionUserResolver;')) {
        $associationLf = Replace-RequiredText `
            -Content $associationLf `
            -Old 'use App\Services\AssociationManagementService;' `
            -New "use App\Services\AssociationManagementService;`nuse App\Services\SessionUserResolver;" `
            -Description 'Association controller service imports'
    }

    $oldAssociationConstructor = @'
    public function __construct(
        private readonly AssociationManagementService $service
    ) {
    }
'@
    $newAssociationConstructor = @'
    public function __construct(
        private readonly AssociationManagementService $service,
        private readonly SessionUserResolver $sessionUser
    ) {
    }
'@

    if (-not $associationLf.Contains('private readonly SessionUserResolver $sessionUser')) {
        $associationLf = Replace-RequiredText `
            -Content $associationLf `
            -Old (Convert-ToLf -Text $oldAssociationConstructor).TrimEnd("`n") `
            -New (Convert-ToLf -Text $newAssociationConstructor).TrimEnd("`n") `
            -Description 'Association controller constructor'
    }

    $oldActorMethod = @'
    private function actorId(Request $request): int
    {
        $actorId = auth()->id()
            ?? $request->session()->get('user_id')
            ?? $request->session()->get('authenticated_user_id');

        abort_if(!$actorId, 401, 'Authenticated user could not be identified.');

        return (int) $actorId;
    }
'@
    $newActorMethod = @'
    private function actorId(Request $request): int
    {
        return (int) $this->sessionUser->resolve($request)->id;
    }
'@

    if (-not $associationLf.Contains('return (int) $this->sessionUser->resolve($request)->id;')) {
        $associationLf = Replace-RequiredText `
            -Content $associationLf `
            -Old (Convert-ToLf -Text $oldActorMethod).TrimEnd("`n") `
            -New (Convert-ToLf -Text $newActorMethod).TrimEnd("`n") `
            -Description 'Association controller actor resolver'
    }

    Register-UpdatedFile -RelativePath $associationControllerPath -DesiredContent (
        Convert-FromLf -Text $associationLf -Newline $associationNewline
    )

    # ---------------------------------------------------------------------
    # MemberApplication model - expose resulting official member relation.
    # ---------------------------------------------------------------------
    $applicationModelPath = 'app/Models/MemberApplication.php'
    $applicationModelCurrentRaw = Read-Utf8File -Path (Get-ProjectPath -RelativePath $applicationModelPath)
    $applicationModelNewline = Get-NewlineStyle -Text $applicationModelCurrentRaw
    $applicationModelLf = Convert-ToLf -Text $applicationModelCurrentRaw

    if (-not $applicationModelLf.Contains('use Illuminate\Database\Eloquent\Relations\HasOne;')) {
        $applicationModelLf = Replace-RequiredText `
            -Content $applicationModelLf `
            -Old 'use Illuminate\Database\Eloquent\Relations\BelongsTo;' `
            -New "use Illuminate\Database\Eloquent\Relations\BelongsTo;`nuse Illuminate\Database\Eloquent\Relations\HasOne;" `
            -Description 'MemberApplication HasOne import'
    }

    if (-not $applicationModelLf.Contains('public function member(): HasOne')) {
        $oldReviewerTail = @'
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }
}
'@
        $newReviewerTail = @'
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class, 'application_id');
    }
}
'@
        $applicationModelLf = Replace-RequiredText `
            -Content $applicationModelLf `
            -Old (Convert-ToLf -Text $oldReviewerTail).TrimEnd("`n") `
            -New (Convert-ToLf -Text $newReviewerTail).TrimEnd("`n") `
            -Description 'MemberApplication resulting member relationship'
    }

    Register-UpdatedFile -RelativePath $applicationModelPath -DesiredContent (
        Convert-FromLf -Text $applicationModelLf -Newline $applicationModelNewline
    )

    # ---------------------------------------------------------------------
    # AppServiceProvider - explicitly register Member policies.
    # ---------------------------------------------------------------------
    $providerPath = 'app/Providers/AppServiceProvider.php'
    $providerCurrentRaw = Read-Utf8File -Path (Get-ProjectPath -RelativePath $providerPath)
    $providerNewline = Get-NewlineStyle -Text $providerCurrentRaw
    $providerLf = Convert-ToLf -Text $providerCurrentRaw

    if (-not $providerLf.Contains('use App\Models\Member;')) {
        $providerImports = @'
namespace App\Providers;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Policies\MemberApplicationPolicy;
use App\Policies\MemberPolicy;
use Illuminate\Support\Facades\Gate;
'@
        $providerLf = Replace-RequiredText `
            -Content $providerLf `
            -Old 'namespace App\Providers;' `
            -New (Convert-ToLf -Text $providerImports).TrimEnd("`n") `
            -Description 'AppServiceProvider Member policy imports'
    }

    if (-not $providerLf.Contains('Gate::policy(Member::class, MemberPolicy::class);')) {
        $oldProviderBoot = @'
    public function boot(): void
    {
        //
    }
'@
        $newProviderBoot = @'
    public function boot(): void
    {
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(MemberApplication::class, MemberApplicationPolicy::class);
    }
'@
        $providerLf = Replace-RequiredText `
            -Content $providerLf `
            -Old (Convert-ToLf -Text $oldProviderBoot).TrimEnd("`n") `
            -New (Convert-ToLf -Text $newProviderBoot).TrimEnd("`n") `
            -Description 'AppServiceProvider boot policy registration'
    }

    Register-UpdatedFile -RelativePath $providerPath -DesiredContent (
        Convert-FromLf -Text $providerLf -Newline $providerNewline
    )

    # ---------------------------------------------------------------------
    # resources/js/app.js - remove duplicate Association imports and add
    # Member Management exactly once while preserving unrelated imports.
    # ---------------------------------------------------------------------
    $appJsPath = 'resources/js/app.js'
    $appJsCurrentRaw = Read-Utf8File -Path (Get-ProjectPath -RelativePath $appJsPath)
    $appJsNewline = Get-NewlineStyle -Text $appJsCurrentRaw
    $appJsLf = Convert-ToLf -Text $appJsCurrentRaw
    $appJsLines = $appJsLf -split "`n"
    $appJsResult = New-Object 'System.Collections.Generic.List[string]'
    $memberImportsInserted = $false

    foreach ($line in $appJsLines) {
        $trimmed = $line.Trim()

        if (
            $trimmed -eq "import './admin-association-management';" -or
            $trimmed -eq "import './admin-member-management';"
        ) {
            continue
        }

        $appJsResult.Add($line)

        if ($trimmed -eq "import './admin-area-management';") {
            $appJsResult.Add("import './admin-association-management';")
            $appJsResult.Add("import './admin-member-management';")
            $memberImportsInserted = $true
        }
    }

    if (-not $memberImportsInserted) {
        throw "Cannot normalize resources/js/app.js: expected admin-area-management import was not found."
    }

    $appJsDesiredLf = [string]::Join("`n", $appJsResult)
    Register-UpdatedFile -RelativePath $appJsPath -DesiredContent (
        Convert-FromLf -Text $appJsDesiredLf -Newline $appJsNewline
    )

    Write-Pass ("Prepared {0} file operation(s); unrelated project code is preserved." -f $script:ChangePlan.Count)

    Write-Step -Number 3 -Total 8 -Message 'Creating targeted backups...'

    if ($script:ChangePlan.Count -gt 0) {
        $script:BackupDirectory = Join-Path $script:ProjectRoot (
            ".assocmap-backups\member-management-{0}" -f $Timestamp
        )
        New-Item -ItemType Directory -Path $script:BackupDirectory -Force | Out-Null

        foreach ($change in $script:ChangePlan) {
            if ($change.Operation -eq 'UPDATE') {
                Backup-File -RelativePath $change.RelativePath
            }
        }

        Write-Pass ("Backup directory: {0}" -f $script:BackupDirectory)
    }
    else {
        Write-Skip 'No file changes require backup; patch files are already in the desired state.'
    }

    Write-Step -Number 4 -Total 8 -Message 'Applying Member Management patch...'

    foreach ($change in $script:ChangePlan) {
        if (-not $script:WritesStarted) {
            $script:WritesStarted = $true
        }

        Write-Utf8NoBom -Path $change.Path -Content $change.Content

        if ($change.Operation -eq 'CREATE') {
            $script:CreatedThisRun.Add($change.RelativePath)
            Write-Create $change.RelativePath
        }
        else {
            Write-Update $change.RelativePath
        }
    }

    if ($script:ChangePlan.Count -eq 0) {
        Write-Skip 'All managed files and patches were already configured.'
    }

    Write-Step -Number 5 -Total 8 -Message 'Validating PHP, Blade, routes, and patch intent...'

    $phpLintTargets = @(
        'routes/web.php',
        'app/Http/Controllers/Admin/AssociationManagementController.php',
        'app/Http/Controllers/Admin/MemberManagementController.php',
        'app/Http/Controllers/Admin/MemberApplicationManagementController.php',
        'app/Http/Requests/Admin/UpdateMemberRequest.php',
        'app/Models/MemberApplication.php',
        'app/Policies/MemberPolicy.php',
        'app/Policies/MemberApplicationPolicy.php',
        'app/Providers/AppServiceProvider.php',
        'app/Services/SessionUserResolver.php',
        'app/Services/MemberManagementService.php',
        'app/Services/MemberApplicationManagementService.php',
        'database/migrations/2026_08_08_083300_harden_member_management_integrity.php',
        'tests/Feature/AdminMemberManagementRouteTest.php',
        'tests/Unit/AdminMemberManagementPolicyTest.php'
    )

    foreach ($relativePath in $phpLintTargets) {
        Invoke-CheckedCommand -FilePath 'php' -Arguments @('-l', (Get-ProjectPath -RelativePath $relativePath))
    }

    Invoke-CheckedCommand -FilePath 'php' -Arguments @('artisan', 'optimize:clear')
    Invoke-CheckedCommand -FilePath 'php' -Arguments @('artisan', 'view:cache')

    $routeOutput = Invoke-CheckedCommand `
        -FilePath 'php' `
        -Arguments @('artisan', 'route:list', '--path=admin/members') `
        -CaptureOutput

    $routeText = $routeOutput -join "`n"
    $routeOutput | ForEach-Object { Write-Host $_ }

    foreach ($requiredRouteName in @(
        'members.index',
        'members.show',
        'members.update',
        'members.archive',
        'members.applications.index',
        'members.applications.show'
    )) {
        if (-not $routeText.Contains($requiredRouteName)) {
            throw ("Expected Member Management route is missing: {0}" -f $requiredRouteName)
        }
    }

    $finalRoutes = Convert-ToLf -Text (
        Read-Utf8File -Path (Get-ProjectPath -RelativePath 'routes/web.php')
    )

    if ($finalRoutes -match "members\.[^\r\n]*(approve|reject|destroy|delete)") {
        throw 'Unsafe Admin approval/rejection/delete Member route was detected.'
    }

    if (-not $finalRoutes.Contains("Route::patch('/{member}/archive'")) {
        throw 'Member archive route is not using PATCH.'
    }

    $finalIndex = Read-Utf8File -Path (
        Get-ProjectPath -RelativePath 'resources/views/admin-pages/admin-member-management/index.blade.php'
    )

    if ($finalIndex.Contains('overflow-x-auto')) {
        throw 'Member Management view contains overflow-x-auto; horizontal table scrolling is prohibited.'
    }

    if (-not $finalIndex.Contains('data-analytics-card')) {
        throw 'Clickable Member analytics cards were not found.'
    }

    $finalMemberJs = Read-Utf8File -Path (
        Get-ProjectPath -RelativePath 'resources/js/admin-member-management.js'
    )

    foreach ($requiredJsIntent in @(
        'trapFocus',
        'activeTrigger',
        'data-member-details',
        'data-archive-member'
    )) {
        if (-not $finalMemberJs.Contains($requiredJsIntent)) {
            throw ("Member Management JavaScript intent check failed: {0}" -f $requiredJsIntent)
        }
    }

    $finalAppJs = Convert-ToLf -Text (
        Read-Utf8File -Path (Get-ProjectPath -RelativePath 'resources/js/app.js')
    )

    $associationImportCount = [regex]::Matches(
        $finalAppJs,
        "(?m)^\s*import './admin-association-management';\s*$"
    ).Count
    $memberImportCount = [regex]::Matches(
        $finalAppJs,
        "(?m)^\s*import './admin-member-management';\s*$"
    ).Count

    if ($associationImportCount -ne 1) {
        throw ("admin-association-management import count must be 1; found {0}." -f $associationImportCount)
    }

    if ($memberImportCount -ne 1) {
        throw ("admin-member-management import count must be 1; found {0}." -f $memberImportCount)
    }

    $associationControllerFinal = Read-Utf8File -Path (
        Get-ProjectPath -RelativePath 'app/Http/Controllers/Admin/AssociationManagementController.php'
    )

    if (-not $associationControllerFinal.Contains('$this->sessionUser->resolve($request)->id')) {
        throw 'Association Management actor-ID fix was not applied.'
    }

    Write-Pass 'PHP syntax, Blade compilation, routes, no-horizontal-scroll rule, policy wiring, and JS intent passed.'

    Write-Step -Number 6 -Total 8 -Message 'Running focused and regression tests...'

    Invoke-CheckedCommand -FilePath 'php' -Arguments @('artisan', 'test', '--filter=AdminMemberManagement')
    Invoke-CheckedCommand -FilePath 'php' -Arguments @('artisan', 'test')

    Write-Pass 'Member Management tests and full Laravel regression suite passed.'

    Write-Step -Number 7 -Total 8 -Message 'Building frontend and checking code hygiene...'

    Invoke-CheckedCommand -FilePath 'npm' -Arguments @('run', 'build')
    Invoke-CheckedCommand -FilePath 'git' -Arguments @('diff', '--check')

    # git diff --check validates whitespace introduced in tracked files.
    # Check only files created by this run here, because a whole-file check on
    # tracked files can falsely reject trailing whitespace that already existed
    # in the committed baseline and was not introduced by this patch.
    if ($script:CreatedThisRun.Count -gt 0) {
        Assert-NoTrailingWhitespace -RelativePaths @($script:CreatedThisRun)
    }

    Write-Pass 'Vite build, Git whitespace check, and newly created-file whitespace check passed.'

    Write-Step -Number 8 -Total 8 -Message 'Applying verified PostgreSQL integrity hardening...'

    # Apply only this audited migration. This does not run unrelated pending migrations.
    Invoke-CheckedCommand -FilePath 'php' -Arguments @(
        'artisan',
        'migrate',
        '--path=database/migrations/2026_08_08_083300_harden_member_management_integrity.php',
        '--force'
    )

    Write-Pass 'Normalized member/application identity protection and one-member-per-application protection are applied.'

    Write-Host ''
    Write-Host 'Patch completed successfully.' -ForegroundColor Green
}
catch {
    Write-Host ''
    Write-Host ("ERROR: {0}" -f $_.Exception.Message) -ForegroundColor Red

    try {
        Restore-FileChanges

        if ($script:WritesStarted -and $script:ProjectRoot -and (Get-Command -Name 'npm' -ErrorAction SilentlyContinue)) {
            Write-Warn 'Rebuilding frontend from restored source files...'
            & npm run build
            if ($LASTEXITCODE -ne 0) {
                Write-Warn 'Frontend rebuild after rollback failed; run npm run build manually after reviewing the error.'
            }
        }

        if ($script:WritesStarted -and $script:ProjectRoot -and (Get-Command -Name 'php' -ErrorAction SilentlyContinue)) {
            & php artisan optimize:clear | Out-Null
        }
    }
    catch {
        Write-Host (
            "ERROR DURING FILE ROLLBACK: {0}" -f $_.Exception.Message
        ) -ForegroundColor Red
    }

    if ($script:BackupDirectory) {
        Write-Host ("Backup directory: {0}" -f $script:BackupDirectory) -ForegroundColor Yellow
    }

    Write-Host 'Patch failed. No success confirmation was issued.' -ForegroundColor Red
    exit 1
}
finally {
    if ($script:LocationPushed) {
        try {
            Pop-Location -ErrorAction SilentlyContinue
        }
        catch {
            # No action required; project state reporting above is authoritative.
        }
    }
}
