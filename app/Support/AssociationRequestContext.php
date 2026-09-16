<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\AssociationDeadlineException;
use Closure;
use Illuminate\Support\Str;
use Throwable;

/** One request's clock and safe diagnostics. Never store SQL, form values or session IDs here. */
final class AssociationRequestContext
{
    public bool $active = false;

    public bool $mutationCompleted = false;

    public bool $sessionFailed = false;

    public ?string $failedStage = null;

    public string $reference = '';

    public array $stages = [];

    public int $queryCount = 0;

    public float $queryMs = 0;

    private int $started = 0;

    public function start(): void
    {
        // Reset explicitly too: feature tests may dispatch several requests in one application.
        $this->active = true;
        $this->mutationCompleted = $this->sessionFailed = false;
        $this->failedStage = null;
        $this->stages = [];
        $this->queryCount = 0;
        $this->queryMs = 0;
        $this->reference = (string) Str::uuid();
        $this->started = hrtime(true);
    }

    public function elapsedMs(): float
    {
        return (hrtime(true) - $this->started) / 1_000_000;
    }

    public function remainingMs(): int
    {
        return max(0, (int) config('association.request_timeout_ms') - (int) ceil($this->elapsedMs()));
    }

    public function assertWithinBudget(): void
    {
        if ($this->active && $this->remainingMs() <= 0) {
            throw new AssociationDeadlineException('Association request budget exceeded.');
        }
    }

    public function measure(string $stage, Closure $operation): mixed
    {
        if (! $this->active) {
            return $operation();
        }
        $start = hrtime(true);
        try {
            $this->assertWithinBudget();

            return $operation();
        } catch (Throwable $error) {
            $this->failedStage ??= $stage;
            if (str_starts_with($stage, 'session_')) {
                $this->sessionFailed = true;
            }
            throw $error;
        } finally {
            $this->stages[$stage] = ($this->stages[$stage] ?? 0) + (hrtime(true) - $start) / 1_000_000;
        }
    }
}
