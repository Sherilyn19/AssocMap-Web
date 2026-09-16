<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

// One shared contract keeps Create and Edit normalization and recovery consistent.
final class StoreAssociationRequest extends AssociationInputRequest {}
