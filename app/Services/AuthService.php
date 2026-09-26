<?php

namespace App\Services;

use App\Exceptions\AssociationDeadlineException;
use App\Exceptions\AuthenticationAuditException;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use RuntimeException;

/**
 * ============================================================
 * AuthService
 * app/Services/AuthService.php
 * ============================================================
 * Handles all database operations related to authentication.
 * Uses raw PDO (via Laravel's PDO connection) for all queries.
 * All queries use JOINs to fetch related data.
 *
 * SRP: this class only handles auth-related DB operations.
 * ============================================================
 */
class AuthService
{
    /**
     * Get the raw PDO connection from Laravel's DB layer.
     * This reuses Laravel's configured connection (host, port,
     * dbname, credentials) without duplicating config.
     */
    private function getPdo(): PDO
    {
        return DB::connection()->getPdo();
    }

    /**
     * Find a user by email and JOIN the roles table to get
     * the role name in a single query.
     *
     * SQL:
     *   SELECT users.*, roles.role_name
     *   FROM   users
     *   INNER JOIN roles ON users.role_id = roles.id
     *   WHERE  users.email = :email
     *   LIMIT  1
     *
     * @param  string  $email  The submitted email address
     * @return array|null User row with role_name, or null if not found
     *
     * @throws RuntimeException on PDO error
     */
    public function findUserWithRole(string $email): ?array
    {
        try {
            $pdo = $this->getPdo();

            /*
             * JOIN users → roles so we get role_name without a
             * second query. Prepared statement prevents SQL injection.
             */
            $stmt = $pdo->prepare('
                SELECT
                    u.id,
                    u.name,
                    u.email,
                    u.password,
                    u.role_id,
                    u.is_active,
                    r.role_name
                FROM users u
                INNER JOIN roles r
                    ON u.role_id = r.id
                WHERE u.email = :email
                LIMIT 1
            ');

            $stmt->execute([':email' => $email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Return null (not false) when user is not found
            return $user ?: null;

        } catch (PDOException $e) {
            // SQL diagnostics can contain private connection details and submitted values.
            // Record only the error category; the controller returns a safe login message.
            logger()->error('AuthService::findUserWithRole PDO error', [
                'sqlstate' => (string) ($e->errorInfo[0] ?? $e->getCode()),
            ]);

            throw new RuntimeException('A database error occurred during authentication.');
        }
    }

    /**
     * Write an entry to the audit_logs table.
     * This method only inserts events. Database UPDATE/DELETE privileges are a deployment concern.
     * A failed insert must reach the caller so login can refuse access and logout can still clear it.
     *
     * @param  int  $userId  ID of the user performing the action
     * @param  string  $actionType  e.g. "LOGIN", "LOGOUT"
     * @param  string  $module  e.g. "Auth"
     * @param  string  $details  Human-readable description
     */
    public function writeAuditLog(
        int $userId,
        string $actionType,
        string $module,
        string $details = ''
    ): void {
        try {
            // Use a bounded transaction with no automatic replay after an uncertain commit.
            // A savepoint also isolates failures when called within an existing transaction.
            app(AssociationDatabase::class)->run(function () use ($userId, $actionType, $module, $details): void {
                $pdo = $this->getPdo();

                $stmt = $pdo->prepare('
                INSERT INTO audit_logs
                    (user_id, action_type, module, details, performed_at)
                VALUES
                    (:user_id, :action_type, :module, :details, NOW())
            ');

                $stmt->execute([
                    ':user_id' => $userId,
                    ':action_type' => $actionType,
                    ':module' => $module,
                    ':details' => $details,
                ]);
            });

        } catch (PDOException|AssociationDeadlineException $e) {
            // Never log SQL, bindings, passwords, email addresses, or exception messages.
            // The account ID and action let operators investigate a missing event safely.
            logger()->error('Authentication audit write failed', [
                'type' => get_class($e),
                'sqlstate' => $e instanceof PDOException ? (string) ($e->errorInfo[0] ?? $e->getCode()) : null,
                'user_id' => $userId,
                'actionType' => $actionType,
            ]);

            // Do not attach the original exception: its diagnostics may expose private data.
            throw new AuthenticationAuditException('The authentication audit event could not be confirmed.');
        }
    }
}
