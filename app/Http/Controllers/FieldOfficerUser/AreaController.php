<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldOfficerUser;

use App\Http\Controllers\Controller;
use App\Models\AreaUnit;
use App\Services\FieldOfficerUserAccess;
use App\Services\SessionUserResolver;
use Illuminate\Http\Request;

final class AreaController extends Controller
{
    public function index(
        Request $request,
        SessionUserResolver $resolver,
        FieldOfficerUserAccess $access
    ) {
        $actor = $resolver->resolve($request);

        abort_unless($actor, 403);

        $areas = AreaUnit::query()
            ->whereIn(
                'id',
                $access
                    ->associations($actor)
                    ->select('area_unit_id')
            )
            ->withCount('associations')
            ->orderBy('name')
            ->paginate(10);

        return view(
            'field-officer-user.areas.index',
            compact('areas')
        );
    }

    public function show(
        Request $request,
        int $areaUnit,
        SessionUserResolver $resolver,
        FieldOfficerUserAccess $access
    ) {

        $actor = $resolver->resolve($request);

        abort_unless($actor, 403);


        $area = AreaUnit::query()
            ->whereIn(
                'id',
                $access
                    ->associations($actor)
                    ->select('area_unit_id')
            )
            ->with([
                'associations' => function ($query) use ($actor) {
                    $query
                        ->whereIn(
                            'id',
                            app(FieldOfficerUserAccess::class)
                                ->associations($actor)
                                ->select('id')
                        )
                        ->with([
                            'subUnit',
                            'programComponent',
                            'status'
                        ])
                        ->orderBy('name');
                }
            ])
            ->findOrFail($areaUnit);


        return view(
            'field-officer-user.areas.show',
            compact('area')
        );

    }

}