<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use DateTimeInterface;
use Stringable;
use Traversable;
use verbb\formie\base\FieldValueInterface as FormieFieldValueInterface;
use verbb\formie\elements\Submission as FormieSubmission;
use wheelform\db\Message as WheelformMessage;
use wheelform\db\MessageValue as WheelformMessageValue;
use yii\base\BaseObject;

final class FieldValueHelper
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    public static function resolveFieldValue(mixed $context, string $fieldPath, string $format = 'csv'): mixed
    {
        if ($context instanceof FormieSubmission && self::formieSubmissionHasFieldPath($context, $fieldPath)) {
            return self::normalizeResolvedValue($context->getFieldValue($fieldPath), $format);
        }

        $segments = array_values(array_filter(explode('.', $fieldPath), static fn(string $segment): bool => $segment !== ''));
        $value = $context;

        foreach ($segments as $segment) {
            $value = self::drillInto($value, $segment);
        }

        return self::normalizeResolvedValue($value, $format);
    }

    public static function normalizeResolvedValue(mixed $value, string $format = 'csv'): mixed
    {
        if ($value instanceof ElementQueryInterface) {
            $value = $value->all();
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(self::DATE_FORMAT);
        }

        if (is_bool($value)) {
            return $format === 'json' ? $value : ($value ? 'true' : 'false');
        }

        if ($value === null) {
            return $format === 'json' ? null : '';
        }

        if ($value instanceof ElementInterface) {
            return $format === 'json' ? self::normalizeElementForJson($value) : self::labelElementForCsv($value);
        }

        if ($value instanceof Traversable) {
            $value = iterator_to_array($value, false);
        }

        if (is_array($value)) {
            $normalized = array_values(array_map(
                static fn(mixed $item): mixed => self::normalizeResolvedValue($item, $format),
                $value
            ));

            if ($format === 'json') {
                return $normalized;
            }

            $flattened = array_map(static function (mixed $item): string {
                if (is_array($item)) {
                    return json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                }

                return (string)$item;
            }, array_filter($normalized, static fn(mixed $item): bool => $item !== null && $item !== ''));

            return implode(', ', $flattened);
        }

        if ($value instanceof FormieFieldValueInterface) {
            if ($format === 'json') {
                return array_filter(
                    get_object_vars($value),
                    static fn(mixed $item): bool => $item !== null && $item !== '' && $item !== []
                );
            }

            return $value instanceof Stringable
                ? (string)$value
                : (json_encode(get_object_vars($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        if ($value instanceof Stringable) {
            return (string)$value;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_object($value)) {
            if ($format === 'json') {
                return get_object_vars($value);
            }

            return method_exists($value, '__toString') ? (string)$value : json_encode(get_object_vars($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $format === 'json' ? null : '';
    }

    private static function drillInto(mixed $context, string $segment): mixed
    {
        if ($context instanceof ElementQueryInterface) {
            $context = $context->all();
        }

        if ($context instanceof WheelformMessage) {
            $submissionValue = self::resolveWheelformMessageValue($context, $segment);
            if ($submissionValue !== null) {
                return $submissionValue;
            }
        }

        if ($context instanceof ElementInterface) {
            if (self::elementHasCustomField($context, $segment)) {
                return $context->getFieldValue($segment);
            }

            if ($segment === 'fullName') {
                $fallbackFullName = self::resolveFallbackFullName($context);
                if ($fallbackFullName !== null) {
                    return $fallbackFullName;
                }
            }

            foreach (self::elementGetterMap($context) as $key => $getter) {
                if ($key === $segment) {
                    return $getter();
                }
            }
        }

        if (is_object($context)) {
            if ($segment === 'fullName') {
                $fallbackFullName = self::resolveFallbackFullName($context);
                if ($fallbackFullName !== null) {
                    return $fallbackFullName;
                }
            }

            $getter = 'get' . ucfirst($segment);
            if (method_exists($context, $getter)) {
                return $context->{$getter}();
            }

            if ($context instanceof BaseObject && $context->canGetProperty($segment)) {
                return $context->{$segment};
            }

            if (property_exists($context, $segment)) {
                return $context->{$segment};
            }
        }

        if ($context instanceof Traversable) {
            $context = iterator_to_array($context, false);
        }

        if (is_array($context) && array_key_exists($segment, $context)) {
            return $context[$segment];
        }

        if (is_array($context)) {
            $filtered = array_values(array_filter($context, static fn(mixed $item): bool => self::matchesTypeHandle($item, $segment)));

            if ($filtered !== []) {
                return $filtered;
            }

            return array_values(array_map(
                static fn(mixed $item): mixed => self::drillInto($item, $segment),
                $context
            ));
        }

        return null;
    }

    private static function resolveFallbackFullName(object $context): ?string
    {
        $fullName = self::readObjectProperty($context, 'fullName');
        if (is_string($fullName) && trim($fullName) !== '') {
            return trim($fullName);
        }

        $firstName = self::readObjectProperty($context, 'firstName');
        $lastName = self::readObjectProperty($context, 'lastName');
        $combined = trim(implode(' ', array_filter([
            is_string($firstName) ? trim($firstName) : '',
            is_string($lastName) ? trim($lastName) : '',
        ])));

        if ($combined !== '') {
            return $combined;
        }

        foreach (['friendlyName', 'username', 'email'] as $property) {
            $value = self::readObjectProperty($context, $property);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private static function readObjectProperty(object $context, string $property): mixed
    {
        $getter = 'get' . ucfirst($property);
        if (method_exists($context, $getter)) {
            try {
                return $context->{$getter}();
            } catch (\Throwable) {
            }
        }

        if ($context instanceof BaseObject && $context->canGetProperty($property)) {
            try {
                return $context->{$property};
            } catch (\Throwable) {
            }
        }

        if (property_exists($context, $property)) {
            try {
                return $context->{$property};
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private static function formieSubmissionHasFieldPath(FormieSubmission $submission, string $fieldPath): bool
    {
        $firstSegment = explode('.', $fieldPath, 2)[0] ?? '';

        if ($firstSegment === '') {
            return false;
        }

        $form = $submission->getForm();
        if ($form === null) {
            return false;
        }

        foreach ($form->getPages() as $page) {
            foreach ($page->getRows() as $row) {
                foreach ($row->getFields() as $field) {
                    if (($field->handle ?? null) === $firstSegment) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function resolveWheelformMessageValue(WheelformMessage $message, string $segment): mixed
    {
        return match ($segment) {
            'formId' => $message->form_id,
            default => self::resolveWheelformFieldValue($message, $segment),
        };
    }

    private static function resolveWheelformFieldValue(WheelformMessage $message, string $segment): mixed
    {
        $values = $message->value ?? $message->getValue()->with('field')->all();
        foreach ($values as $value) {
            if (!$value instanceof WheelformMessageValue) {
                continue;
            }

            $field = $value->field;
            if ($field === null || (string)$field->name !== $segment) {
                continue;
            }

            return self::extractWheelformValue($value);
        }

        return null;
    }

    private static function extractWheelformValue(WheelformMessageValue $value): mixed
    {
        $field = $value->field;
        if ($field === null) {
            return $value->value;
        }

        if ($field->type === \wheelform\db\FormField::FILE_SCENARIO) {
            $file = json_decode((string)$value->value, true);

            if (!is_array($file)) {
                return (string)$value->value;
            }

            return (string)($file['assetUrl'] ?? $file['name'] ?? '');
        }

        if ($field->type === \wheelform\db\FormField::LIST_SCENARIO) {
            $decoded = json_decode((string)$value->value, true);

            return is_array($decoded) ? $decoded : (string)$value->value;
        }

        return $value->value;
    }

    private static function elementHasCustomField(ElementInterface $element, string $handle): bool
    {
        $layout = method_exists($element, 'getFieldLayout') ? $element->getFieldLayout() : null;

        return $layout !== null && method_exists($layout, 'getFieldByHandle') && $layout->getFieldByHandle($handle) !== null;
    }

    /**
     * @return array<string, callable(): mixed>
     */
    private static function elementGetterMap(ElementInterface $element): array
    {
        return [
            'author' => static fn(): mixed => method_exists($element, 'getAuthor') ? $element->getAuthor() : null,
            'site' => static fn(): mixed => method_exists($element, 'getSite') ? $element->getSite() : null,
            'section' => static fn(): mixed => method_exists($element, 'getSection') ? $element->getSection() : null,
            'type' => static fn(): mixed => method_exists($element, 'getType') ? $element->getType() : null,
            'folder' => static fn(): mixed => method_exists($element, 'getFolder') ? $element->getFolder() : null,
            'uploader' => static fn(): mixed => method_exists($element, 'getUploader') ? $element->getUploader() : null,
            'group' => static fn(): mixed => method_exists($element, 'getGroup') ? $element->getGroup() : null,
        ];
    }

    private static function matchesTypeHandle(mixed $item, string $segment): bool
    {
        if (!is_object($item)) {
            return false;
        }

        foreach (['typeHandle', 'handle'] as $property) {
            if (property_exists($item, $property) && $item->{$property} === $segment) {
                return true;
            }
        }

        if (method_exists($item, 'getType') && is_object($item->getType()) && property_exists($item->getType(), 'handle')) {
            return $item->getType()->handle === $segment;
        }

        return false;
    }

    private static function labelElementForCsv(ElementInterface $element): string
    {
        foreach (['title', 'fullName', 'email', 'username', 'filename', 'name'] as $property) {
            try {
                if (property_exists($element, $property) && $element->{$property}) {
                    return (string)$element->{$property};
                }

                if (method_exists($element, 'canGetProperty') && $element->canGetProperty($property) && $element->{$property}) {
                    return (string)$element->{$property};
                }
            } catch (\Throwable) {
            }
        }

        if (method_exists($element, 'getUrl')) {
            try {
                $url = $element->getUrl();
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            } catch (\Throwable) {
            }
        }

        return sprintf('%s#%s', basename(str_replace('\\', '/', $element::class)), (string)($element->id ?? 'unknown'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeElementForJson(ElementInterface $element): array
    {
        $payload = [
            'id' => $element->id ?? null,
            'uid' => $element->uid ?? null,
            'type' => basename(str_replace('\\', '/', $element::class)),
            'label' => self::labelElementForCsv($element),
        ];

        foreach (['title', 'slug', 'uri', 'url', 'email', 'username', 'filename'] as $property) {
            try {
                if (method_exists($element, 'canGetProperty') && $element->canGetProperty($property)) {
                    $payload[$property] = $element->{$property};
                }
            } catch (\Throwable) {
            }
        }

        return array_filter($payload, static fn(mixed $value): bool => $value !== null && $value !== '');
    }
}
