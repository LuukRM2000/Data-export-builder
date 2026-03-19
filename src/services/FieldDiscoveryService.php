<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\BaseRelationField;
use craft\fields\Matrix;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;
use Luremo\DataExportBuilder\helpers\FieldValueHelper;
use wheelform\db\Form as WheelformForm;
use wheelform\db\FormField as WheelformFormField;

final class FieldDiscoveryService extends Component
{
    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function getElementTypeOptions(): array
    {
        $options = [];

        foreach (CapabilityHelper::supportedElementTypes() as $handle => $definition) {
            $options[] = [
                'label' => $definition['label'],
                'value' => $handle,
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDiscoveryPayload(
        string $elementType,
        ?string $sectionUid = null,
        bool $onlyPopulated = false,
        ?int $formId = null
    ): array
    {
        $supportsPopulatedFilter = $elementType === 'entries' && $sectionUid !== null && $sectionUid !== '';
        $supportsFormFilter = $elementType === CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS;

        return [
            'elementType' => $elementType,
            'fields' => $this->discoverFields($elementType, $sectionUid, $onlyPopulated, $formId),
            'sections' => $elementType === 'entries' ? $this->getSectionOptions() : [],
            'sites' => $this->getSiteOptions(),
            'forms' => $supportsFormFilter ? $this->getWheelformFormOptions() : [],
            'supportsSectionFilter' => $elementType === 'entries',
            'supportsSiteFilter' => in_array($elementType, ['entries', 'categories', 'assets'], true),
            'supportsFormFilter' => $supportsFormFilter,
            'supportsPopulatedFilter' => $supportsPopulatedFilter,
            'onlyPopulated' => $supportsPopulatedFilter ? $onlyPopulated : false,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function discoverFields(
        string $elementType,
        ?string $sectionUid = null,
        bool $onlyPopulated = false,
        ?int $formId = null
    ): array
    {
        $definitions = [];

        foreach ($this->nativeFieldDefinitions($elementType) as $field) {
            $definitions[$field['path']] = $field;
        }

        foreach ($this->fieldLayoutsForElementType($elementType, $sectionUid) as $layout) {
            if ($layout === null || !method_exists($layout, 'getCustomFields')) {
                continue;
            }

            foreach ($layout->getCustomFields() as $field) {
                if (!$field instanceof FieldInterface) {
                    continue;
                }

                $this->appendCustomFieldDefinitions($definitions, $field);
            }
        }

        if ($onlyPopulated && $elementType === 'entries' && $sectionUid !== null && $sectionUid !== '') {
            $definitions = $this->filterPopulatedDefinitions($definitions, $sectionUid);
        }

        if ($elementType === CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS && $formId !== null && $formId > 0) {
            $this->appendWheelformFieldDefinitions($definitions, $formId);
        }

        if ($elementType !== CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS) {
            ksort($definitions);
        }

        return array_values($definitions);
    }

    /**
     * @param string[] $fieldPaths
     * @return string[]
     */
    public function getEagerLoadPaths(array $fieldPaths): array
    {
        $paths = [];

        foreach ($fieldPaths as $path) {
            $segments = explode('.', $path);
            $first = $segments[0] ?? null;

            if ($first === null || $first === '' || in_array($first, ['section', 'site', 'type', 'group', 'folder'], true)) {
                continue;
            }

            if ($first === 'author') {
                $paths[] = 'author';
                continue;
            }

            if (count($segments) > 1) {
                $paths[] = $first;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function getSectionOptions(): array
    {
        $options = [['label' => 'All sections', 'value' => '']];

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $options[] = [
                'label' => $section->name,
                'value' => $section->uid,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function getSiteOptions(): array
    {
        $options = [['label' => 'All sites', 'value' => '']];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $options[] = [
                'label' => $site->name,
                'value' => $site->uid,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function getWheelformFormOptions(): array
    {
        $options = [['label' => 'Select a Wheel Form', 'value' => '']];

        if (!CapabilityHelper::isWheelFormInstalled()) {
            return $options;
        }

        foreach (
            WheelformForm::find()
                ->where(['active' => 1, 'save_entry' => 1])
                ->orderBy(['name' => SORT_ASC])
                ->all() as $form
        ) {
            $options[] = [
                'label' => (string)$form->name,
                'value' => (string)$form->id,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, mixed>
     */
    private function fieldLayoutsForElementType(string $elementType, ?string $sectionUid = null): array
    {
        $layouts = [];

        switch ($elementType) {
            case 'entries':
                $sections = $this->entrySectionsForUid($sectionUid);
                foreach ($sections as $section) {
                    foreach ($section->getEntryTypes() as $entryType) {
                        $layouts[] = $entryType->getFieldLayout();
                    }
                }
                break;
            case 'users':
                $layouts[] = Craft::$app->getFields()->getLayoutByType(User::class);
                break;
            case 'categories':
                foreach (Craft::$app->getCategories()->getAllGroups() as $group) {
                    $layouts[] = $group->getFieldLayout();
                }
                break;
            case 'assets':
                foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
                    $layouts[] = $volume->getFieldLayout();
                }
                break;
            case 'orders':
                $layouts[] = method_exists(Craft::$app->getFields(), 'getLayoutByType')
                    ? Craft::$app->getFields()->getLayoutByType(\craft\commerce\elements\Order::class)
                    : null;
                break;
            case CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS:
                break;
        }

        return array_filter($layouts);
    }

    /**
     * @return array<int, mixed>
     */
    private function entrySectionsForUid(?string $sectionUid): array
    {
        if ($sectionUid !== null && $sectionUid !== '') {
            $section = Craft::$app->getEntries()->getSectionByUid($sectionUid);

            return $section !== null ? [$section] : [];
        }

        return Craft::$app->getEntries()->getAllSections();
    }

    /**
     * @param array<string, array<string, string>> $definitions
     * @return array<string, array<string, string>>
     */
    private function filterPopulatedDefinitions(array $definitions, string $sectionUid): array
    {
        $section = Craft::$app->getEntries()->getSectionByUid($sectionUid);
        if ($section === null) {
            return $definitions;
        }

        $entries = Entry::find()
            ->section($section->handle)
            ->status(null)
            ->site('*')
            ->limit(50)
            ->all();

        if ($entries === []) {
            return $definitions;
        }

        return array_filter($definitions, static function (array $definition) use ($entries): bool {
            foreach ($entries as $entry) {
                $value = FieldValueHelper::resolveFieldValue($entry, $definition['path'], 'csv');
                if (self::hasDisplayValue($value)) {
                    return true;
                }
            }

            return false;
        });
    }

    private static function hasDisplayValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function nativeFieldDefinitions(string $elementType): array
    {
        if ($elementType === CapabilityHelper::ELEMENT_TYPE_WHEELFORM_SUBMISSIONS) {
            return [
                ['path' => 'id', 'label' => 'Submission ID', 'group' => 'Submission', 'type' => 'number'],
                ['path' => 'formId', 'label' => 'Form ID', 'group' => 'Submission', 'type' => 'number'],
                ['path' => 'read', 'label' => 'Read', 'group' => 'Submission', 'type' => 'boolean'],
                ['path' => 'dateCreated', 'label' => 'Date Created', 'group' => 'Submission', 'type' => 'date'],
            ];
        }

        $definitions = [
            ['path' => 'id', 'label' => 'ID', 'group' => 'Core', 'type' => 'number'],
            ['path' => 'uid', 'label' => 'UID', 'group' => 'Core', 'type' => 'text'],
            ['path' => 'status', 'label' => 'Status', 'group' => 'Core', 'type' => 'text'],
            ['path' => 'enabled', 'label' => 'Enabled', 'group' => 'Core', 'type' => 'boolean'],
            ['path' => 'dateCreated', 'label' => 'Date Created', 'group' => 'Core', 'type' => 'date'],
            ['path' => 'dateUpdated', 'label' => 'Date Updated', 'group' => 'Core', 'type' => 'date'],
        ];

        return match ($elementType) {
            'entries' => array_merge($definitions, [
                ['path' => 'title', 'label' => 'Title', 'group' => 'Entry', 'type' => 'text'],
                ['path' => 'slug', 'label' => 'Slug', 'group' => 'Entry', 'type' => 'text'],
                ['path' => 'uri', 'label' => 'URI', 'group' => 'Entry', 'type' => 'text'],
                ['path' => 'postDate', 'label' => 'Post Date', 'group' => 'Entry', 'type' => 'date'],
                ['path' => 'expiryDate', 'label' => 'Expiry Date', 'group' => 'Entry', 'type' => 'date'],
                ['path' => 'author', 'label' => 'Author', 'group' => 'Relations', 'type' => 'relation'],
                ['path' => 'author.fullName', 'label' => 'Author Full Name', 'group' => 'Relations', 'type' => 'relation'],
                ['path' => 'author.email', 'label' => 'Author Email', 'group' => 'Relations', 'type' => 'relation'],
                ['path' => 'section.handle', 'label' => 'Section Handle', 'group' => 'Meta', 'type' => 'text'],
                ['path' => 'type.handle', 'label' => 'Entry Type Handle', 'group' => 'Meta', 'type' => 'text'],
                ['path' => 'site.handle', 'label' => 'Site Handle', 'group' => 'Meta', 'type' => 'text'],
            ]),
            'users' => array_merge($definitions, [
                ['path' => 'username', 'label' => 'Username', 'group' => 'User', 'type' => 'text'],
                ['path' => 'email', 'label' => 'Email', 'group' => 'User', 'type' => 'text'],
                ['path' => 'fullName', 'label' => 'Full Name', 'group' => 'User', 'type' => 'text'],
                ['path' => 'friendlyName', 'label' => 'Friendly Name', 'group' => 'User', 'type' => 'text'],
                ['path' => 'lastLoginDate', 'label' => 'Last Login Date', 'group' => 'User', 'type' => 'date'],
            ]),
            'categories' => array_merge($definitions, [
                ['path' => 'title', 'label' => 'Title', 'group' => 'Category', 'type' => 'text'],
                ['path' => 'slug', 'label' => 'Slug', 'group' => 'Category', 'type' => 'text'],
                ['path' => 'uri', 'label' => 'URI', 'group' => 'Category', 'type' => 'text'],
                ['path' => 'group.handle', 'label' => 'Category Group Handle', 'group' => 'Meta', 'type' => 'text'],
                ['path' => 'site.handle', 'label' => 'Site Handle', 'group' => 'Meta', 'type' => 'text'],
            ]),
            'assets' => array_merge($definitions, [
                ['path' => 'title', 'label' => 'Title', 'group' => 'Asset', 'type' => 'text'],
                ['path' => 'filename', 'label' => 'Filename', 'group' => 'Asset', 'type' => 'text'],
                ['path' => 'kind', 'label' => 'Kind', 'group' => 'Asset', 'type' => 'text'],
                ['path' => 'mimeType', 'label' => 'MIME Type', 'group' => 'Asset', 'type' => 'text'],
                ['path' => 'size', 'label' => 'Size', 'group' => 'Asset', 'type' => 'number'],
                ['path' => 'url', 'label' => 'URL', 'group' => 'Asset', 'type' => 'text'],
                ['path' => 'folder.name', 'label' => 'Folder Name', 'group' => 'Meta', 'type' => 'text'],
                ['path' => 'uploader.email', 'label' => 'Uploader Email', 'group' => 'Relations', 'type' => 'relation'],
                ['path' => 'site.handle', 'label' => 'Site Handle', 'group' => 'Meta', 'type' => 'text'],
            ]),
            'orders' => array_merge($definitions, [
                ['path' => 'number', 'label' => 'Order Number', 'group' => 'Order', 'type' => 'text'],
                ['path' => 'reference', 'label' => 'Reference', 'group' => 'Order', 'type' => 'text'],
                ['path' => 'email', 'label' => 'Email', 'group' => 'Order', 'type' => 'text'],
                ['path' => 'currency', 'label' => 'Currency', 'group' => 'Order', 'type' => 'text'],
                ['path' => 'itemTotal', 'label' => 'Item Total', 'group' => 'Order', 'type' => 'number'],
                ['path' => 'totalPrice', 'label' => 'Total Price', 'group' => 'Order', 'type' => 'number'],
                ['path' => 'totalQty', 'label' => 'Total Quantity', 'group' => 'Order', 'type' => 'number'],
                ['path' => 'dateOrdered', 'label' => 'Date Ordered', 'group' => 'Order', 'type' => 'date'],
                ['path' => 'isCompleted', 'label' => 'Completed', 'group' => 'Order', 'type' => 'boolean'],
            ]),
            default => $definitions,
        };
    }

    /**
     * @param array<string, array<string, string>> $definitions
     */
    private function appendWheelformFieldDefinitions(array &$definitions, int $formId): void
    {
        if (!CapabilityHelper::isWheelFormInstalled()) {
            return;
        }

        $fields = WheelformFormField::find()
            ->where(['form_id' => $formId, 'active' => 1])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        foreach ($fields as $field) {
            $path = (string)$field->name;
            if ($path === '') {
                continue;
            }

            $definitions[$path] = [
                'path' => $path,
                'label' => (string)$field->getLabel(),
                'group' => 'Form Fields',
                'type' => (string)$field->type,
            ];
        }
    }

    /**
     * @param array<string, array<string, string>> $definitions
     */
    private function appendCustomFieldDefinitions(array &$definitions, FieldInterface $field, string $prefix = ''): void
    {
        $path = ltrim($prefix . $field->handle, '.');
        $definitions[$path] = [
            'path' => $path,
            'label' => $field->name,
            'group' => 'Custom Fields',
            'type' => $this->detectFieldType($field),
        ];

        if ($field instanceof BaseRelationField) {
            foreach ($this->relationFieldPaths($field) as $relationPath => $label) {
                $definitions[$relationPath] = [
                    'path' => $relationPath,
                    'label' => $label,
                    'group' => 'Relations',
                    'type' => 'relation',
                ];
            }
        }

        if ($field instanceof Matrix && method_exists($field, 'getEntryTypes')) {
            foreach ($field->getEntryTypes() as $entryType) {
                $typeHandle = $entryType->handle ?? null;
                $typeName = $entryType->name ?? $typeHandle;
                $layout = method_exists($entryType, 'getFieldLayout') ? $entryType->getFieldLayout() : null;

                if ($typeHandle === null || $layout === null || !method_exists($layout, 'getCustomFields')) {
                    continue;
                }

                foreach ($layout->getCustomFields() as $subField) {
                    if (!$subField instanceof FieldInterface) {
                        continue;
                    }

                    $subPath = sprintf('%s.%s.%s', $field->handle, $typeHandle, $subField->handle);
                    $definitions[$subPath] = [
                        'path' => $subPath,
                        'label' => sprintf('%s -> %s -> %s', $field->name, $typeName, $subField->name),
                        'group' => 'Matrix',
                        'type' => $this->detectFieldType($subField),
                    ];
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function relationFieldPaths(BaseRelationField $field): array
    {
        $base = $field->handle;
        $paths = [
            $base => $field->name,
        ];

        $elementType = method_exists($field, 'elementType') ? $field->elementType() : ($field->elementType ?? null);
        $class = is_string($elementType) ? $elementType : null;

        if ($class === Entry::class || $class === Category::class) {
            $paths[$base . '.title'] = $field->name . ' Title';
            $paths[$base . '.slug'] = $field->name . ' Slug';
            $paths[$base . '.uri'] = $field->name . ' URI';
        }

        if ($class === Asset::class) {
            $paths[$base . '.filename'] = $field->name . ' Filename';
            $paths[$base . '.url'] = $field->name . ' URL';
        }

        if ($class === User::class) {
            $paths[$base . '.fullName'] = $field->name . ' Full Name';
            $paths[$base . '.email'] = $field->name . ' Email';
        }

        return $paths;
    }

    private function detectFieldType(FieldInterface $field): string
    {
        return match (true) {
            $field instanceof BaseRelationField => 'relation',
            $field instanceof Matrix => 'matrix',
            default => 'field',
        };
    }
}
