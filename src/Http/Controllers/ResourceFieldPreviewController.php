<?php

namespace Whitecube\NovaPage\Http\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Nova\Contracts\Previewable;
use Laravel\Nova\Http\Requests\NovaRequest;

abstract class ResourceFieldPreviewController extends Controller
{
    /**
     * The queried resource's name
     *
     * @var string
     */
    protected $resourceName;

    /**
     * Delete the file at the given field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(NovaRequest $request)
    {
        $request->validate(['value' => ['nullable', 'string']]);

        $route = call_user_func($request->getRouteResolver());
        $route->setParameter('resource', $this->resourceName);
        $request->findResourceOrFail()->authorizeToUpdate($request);
        $template = $request->findModelQuery()->firstOrFail();

        $resource = $request->newResourceWith($template);

        /** @var \Laravel\Nova\Fields\Field&\Laravel\Nova\Contracts\Previewable $field */
        $field = $resource->availableFields($request)
            ->whereInstanceOf(Previewable::class)
            ->findFieldByAttribute($request->field, function () {
                abort(404);
            });

        return response()->json([
            'preview' => $field->previewFor($request->value),
        ]);
    }
}
