<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Extend the isolated association fixture with the approved GIS relationship and lifecycle. */
abstract class GisDatabaseTestCase extends AssociationDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('ALTER TABLE projects ADD COLUMN title varchar, ADD COLUMN commodity_type varchar');
        (require database_path('migrations/2026_09_29_000001_add_gis_project_and_archive.php'))->up();
    }
}
