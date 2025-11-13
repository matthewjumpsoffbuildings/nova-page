<?php

namespace Whitecube\NovaPage\Http\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Nova\DeleteField;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\UpdateResourceRequest;
use Whitecube\NovaFlexibleContent\Flexible;

abstract class ResourceFieldDestroyController extends Controller
{
    /**
     * The queried resource's name
     *
     * @var string
     */
    protected $resourceName;

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
        $resource = $request->findResourceOrFail();
        $resource->authorizeToUpdate($request);

        $fields = $resource->updateFields($request);

        $field = null;
        foreach ($fields as $f) {
            $fieldAttribute = $f->attribute;

            // Handle normal Image/File fields
            if(isset($fieldAttribute) && $fieldAttribute == $request->field) {
                $field = $f;
                break;
            }

            // Handle Image/File fields inside Flexible
            if($f instanceof Flexible) {
                [$key, $attribute] = explode('__', $request->field);
                foreach ($f->value as $index => $value) {
                    if($key == $value['key']) {
                        foreach ($value['attributes'] as $attributeIndex => $attributeValue) {
                            if($attributeValue['attribute'] == $attribute) {
                                $field = File::make('Image', "$fieldAttribute->$attributeIndex.attributes.$attribute");
                                $field->disk('gcs');
                                $field->value = $attributeValue['value'];
                                break;
                            }
                        }
                    }
                };
            }
        };

        if (!($field instanceof File)) {
            abort(404);
        }

        $template = $request->findModelQuery()->firstOrFail();

        DeleteField::forRequest(
            $request,
            $field,
            $template
        )->save();
    }
}
