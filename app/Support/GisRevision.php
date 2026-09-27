<?php

declare(strict_types=1);

namespace App\Support;

final class GisRevision
{
    // The row version catches changes in other transactions. The digest also catches
    // changes made inside the same transaction, without adding a database column.
    public const SQL = "md5(to_jsonb(gis_locations)::text) || ':' || gis_locations.xmin::text AS revision";
}
