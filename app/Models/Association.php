<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * app/Models/Association.php
 * 
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