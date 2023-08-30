<?php

namespace Whitecube\NovaPage\Http\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Nova\DeleteField;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\UpdateResourceRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class ResourceFieldDestroyController extends Controller
{
    /**
     * The queried resource's name
     *
     * @var string
     */
    protected $resourceName;

    public $test;

    /**
     * Update a resource.
     *
     * @param  \Laravel\Nova\Http\Requests\UpdateResourceRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function handle(UpdateResourceRequest $request)
    {
        $route = call_user_func($request->getRouteResolver());
        $route->setParameter('resource', $this->resourceName);
        $request->findResourceOrFail()->authorizeToUpdate($request);
        $template = $request->findModelQuery()->firstOrFail();
        $resource = $request->newResourceWith($template);

        if (Str::contains($request->field, '__')) {
            $ex = explode('__', $request->field);
            $requestkey = $ex[0];
            $name = $ex[1];
            $attributes = collect($template->getAttributes());
            $converted = [];

            $datas = ['attribute' => $name];
            foreach ($attributes as $key => $attribute) {
                if (str_contains($attribute, $requestkey)) {
                    $datas['key'] = $requestkey;
                    $datas['layout'] = $key;

                    $childAttributes = json_decode($attribute, true);

                    foreach ($childAttributes as $key => $child) {
                        if ($child['key'] === $requestkey) {
                            $datas['index'] = $key;
                            array_push($converted, ...$childAttributes);
                        }
                    }
                }
            }

            $file = $converted[$datas['index']]['attributes'][$name];
            $exists = Storage::disk(config('nova.storage_disk'))->exists($file);
            if ($exists) {
                Storage::disk(config('nova.storage_disk'))->delete($file);
            }

            unset($converted[$datas['index']]['attributes'][$name]);
            $attributes[$datas['layout']] = json_encode($converted);
            $template->setAttribute('slider', $attributes[$datas['layout']]);

            $template->save();
        } else {
            $field = $resource->updateFields($request)->findFieldByAttribute($request->field);
            if (!($field instanceof File)) {
                abort(404);
            }

            DeleteField::forRequest(
                $request,
                $field,
                $template
            )->save();
        }
    }
}
