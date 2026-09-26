<?php

namespace App\Console\Commands;

use App\Exceptions\AssociationDeadlineException;
use App\Services\BootstrapAdministratorService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use PDOException;

class BootstrapAdministrator extends Command
{
    protected $signature = 'assocmap:bootstrap-admin {--name=} {--email=}';

    protected $description = 'Create the first administrator using a hidden password prompt';

    public function handle(BootstrapAdministratorService $service): int
    {
        // Never accept secrets as command-line options, where shell history may retain them.
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter the password securely.');

            return self::FAILURE;
        }
        try {
            $service->create([
                'name' => $this->option('name') ?: $this->ask('Administrator name'),
                'email' => $this->option('email') ?: $this->ask('Administrator email'),
                'password' => $this->secret('Password (at least 12 characters)', false),
                'password_confirmation' => $this->secret('Confirm password', false),
            ]);
        } catch (ValidationException $error) {
            foreach ($error->errors() as $messages) {
                $this->error($messages[0]);
            }

            return self::FAILURE;
        } catch (PDOException|AssociationDeadlineException $error) {
            // The service has already attempted transaction rollback. An uncertain commit
            // must be checked before retrying; never print SQL or credentials to the terminal.
            $this->error('Administrator creation could not be confirmed. Check the database and existing accounts before retrying.');

            return self::FAILURE;
        }
        $this->info('Initial administrator created. No existing account was changed.');

        return self::SUCCESS;
    }
}
