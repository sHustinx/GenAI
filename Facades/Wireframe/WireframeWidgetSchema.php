<?php
namespace axenox\GenAI\Facades\Wireframe;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\WidgetSchema;

/** Reads the `@uxon-type` annotations of a widget class in one pass instead of one lookup per property. */
final class WireframeWidgetSchema extends WidgetSchema
{
    /**
     * Reads Core class annotations (cached by the schema), never page or business data.
     *
     * @param class-string $widgetClass
     * @return array<string, string> UXON property names mapped to their `@uxon-type` annotations.
     */
    public function getPropertyTypeAnnotations(string $widgetClass): array
    {
        $widgetClass = '\\' . ltrim($widgetClass, '\\');
        // Cache the compact map beside the schema's own entries so a schema cache clear refreshes both.
        $cached = $this->getCache($widgetClass, 'wireframe_types');
        if (is_array($cached)) {
            return $cached;
        }
        $types = [];
        $failed = false;
        foreach ($this->getPropertiesSheet($widgetClass)->getRows() as $row) {
            $property = $row['PROPERTY'] ?? null;
            // The parent schema reports parse failures as a placeholder row that must not be cached.
            if (is_string($property) && str_starts_with($property, '//')) {
                $failed = true;
            } elseif (is_string($property) && $property !== UxonObject::PROPERTY_SNIPPET) {
                $types[$property] = (string) ($row['TYPE'] ?? '');
            }
        }
        if (! $failed) {
            $this->setCache($widgetClass, 'wireframe_types', $types);
        }
        return $types;
    }
}
