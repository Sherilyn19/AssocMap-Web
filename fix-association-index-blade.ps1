param(
    [string]$ProjectPath = "D:\Capstone-AssocMap-Web"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

Set-Location $ProjectPath

$target = Join-Path $ProjectPath "resources\views\admin-pages\admin-association-management\index.blade.php"

if (-not (Test-Path $target)) {
    throw "File not found: $target"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backup = "$target.$timestamp.bak"
Copy-Item $target $backup -Force

Write-Host "[ASSOCMAP] Backup created: $backup" -ForegroundColor Cyan

$content = [System.IO.File]::ReadAllText($target)

$old = @'
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
'@

$new = @'
                                    @unless ($association->is_archived)
                                        @php
                                            $editAssociationData = [
                                                'id' => $association->id,
                                                'name' => $association->name,
                                                'area_unit_id' => $association->area_unit_id,
                                                'sub_unit_id' => $association->sub_unit_id,
                                                'program_component_id' => $association->program_component_id,
                                                'field_officer_id' => $association->field_officer_id,
                                                'status_id' => $association->status_id,
                                                'address' => $association->address,
                                                'date_joined' => $association->date_joined?->format('Y-m-d'),
                                                'representative_member_id' => $association->representative_member_id,
                                                'update_url' => route('admin.associations.update', $association),
                                            ];
                                        @endphp

                                        <button type="button"
                                                data-edit-association='@json($editAssociationData)'
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold
                                                       text-slate-700 hover:bg-slate-50">
                                            Edit
                                        </button>
'@

if (-not $content.Contains($old)) {
    throw "The expected broken @json block was not found. No changes were made. Backup: $backup"
}

$content = $content.Replace($old, $new)
Write-Utf8NoBom -Path $target -Content $content

Write-Host "[ASSOCMAP] Blade block corrected." -ForegroundColor Green

& php artisan view:clear
if ($LASTEXITCODE -ne 0) {
    throw "php artisan view:clear failed."
}

& php artisan view:cache
if ($LASTEXITCODE -ne 0) {
    throw "Blade compilation failed. Restore from: $backup"
}

Write-Host ""
Write-Host "Patch completed successfully." -ForegroundColor Green
Write-Host "No migration was created or executed."
