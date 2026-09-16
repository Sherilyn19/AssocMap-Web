<?php

declare(strict_types=1);

namespace App\Session;

use App\Services\AssociationDatabase;
use App\Support\AssociationRequestContext;
use Closure;
use Illuminate\Session\DatabaseSessionHandler;

/** Bounds database sessions only while an Association request is active. */
final class AssociationDatabaseSessionHandler extends DatabaseSessionHandler
{
    private function bounded(string $stage, Closure $operation): mixed
    {
        return app(AssociationRequestContext::class)->active
            ? app(AssociationDatabase::class)->run($operation, $this->connection, $stage)
            : $operation();
    }

    public function read($sessionId): string|false
    {
        return $this->bounded('session_read', fn () => parent::read($sessionId));
    }

    public function write($sessionId, $data): bool
    {
        if (! app(AssociationRequestContext::class)->active) {
            return parent::write($sessionId, $data);
        }

        return $this->bounded('session_write', function () use ($sessionId, $data) {
            $payload = $this->getDefaultPayload($data);
            // One atomic upsert handles simultaneous first saves. Do not catch a PostgreSQL
            // timeout and attempt UPDATE inside the same already-aborted transaction.
            $this->getQuery()->upsert([['id' => $sessionId, ...$payload]], ['id'], array_keys($payload));

            return $this->exists = true;
        });
    }

    public function destroy($sessionId): bool
    {
        return $this->bounded('session_destroy', fn () => parent::destroy($sessionId));
    }

    public function gc($lifetime): int
    {
        return $this->bounded('session_gc', fn () => parent::gc($lifetime));
    }
}
