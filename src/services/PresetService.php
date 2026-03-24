<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\services;

use craft\base\Component;
use Luremo\DataExportBuilder\helpers\CapabilityHelper;

final class PresetService extends Component
{
    /**
     * @return array<int, array{handle:string,label:string,description:string,fields:array<int, array{path:string,label:string}>}>
     */
    public function getPresetsForElementType(string $elementType): array
    {
        return match ($elementType) {
            'orders' => [[
                'handle' => 'ops',
                'label' => 'Order Ops',
                'description' => 'Operational order export with customer, totals, and address data.',
                'fields' => $this->buildPresetFields([
                    'number' => 'Order Number',
                    'dateCreated' => 'Created At',
                    'email' => 'Email',
                    'customer.email' => 'Customer Email',
                    'orderStatus.handle' => 'Status',
                    'totalPrice' => 'Total',
                    'totalQty' => 'Quantity',
                    'billingAddress.fullName' => 'Billing Name',
                    'billingAddress.addressLine1' => 'Billing Address 1',
                    'billingAddress.locality' => 'Billing City',
                    'shippingAddress.fullName' => 'Shipping Name',
                    'shippingAddress.addressLine1' => 'Shipping Address 1',
                    'shippingAddress.locality' => 'Shipping City',
                ]),
            ]],
            CapabilityHelper::ELEMENT_TYPE_PRODUCTS => [[
                'handle' => 'catalog',
                'label' => 'Catalog Feed',
                'description' => 'Product export for catalog, PIM, or feed handoffs.',
                'fields' => $this->buildPresetFields([
                    'title' => 'Title',
                    'slug' => 'Slug',
                    'uri' => 'URI',
                    'type.name' => 'Product Type',
                    'dateUpdated' => 'Updated At',
                    'defaultVariant.sku' => 'Default SKU',
                    'defaultVariant.price' => 'Default Price',
                    'defaultVariant.stock' => 'Default Stock',
                ]),
            ]],
            CapabilityHelper::ELEMENT_TYPE_VARIANTS => [[
                'handle' => 'inventory',
                'label' => 'Inventory Feed',
                'description' => 'Variant-level inventory and pricing export.',
                'fields' => $this->buildPresetFields([
                    'sku' => 'SKU',
                    'title' => 'Variant Title',
                    'product.title' => 'Product Title',
                    'product.slug' => 'Product Slug',
                    'price' => 'Price',
                    'stock' => 'Stock',
                    'enabled' => 'Enabled',
                    'dateUpdated' => 'Updated At',
                ]),
            ]],
            default => [],
        };
    }

    /**
     * @param array<string, string> $definitions
     * @return array<int, array{path:string,label:string}>
     */
    private function buildPresetFields(array $definitions): array
    {
        $fields = [];

        foreach ($definitions as $path => $label) {
            $fields[] = [
                'path' => $path,
                'label' => $label,
            ];
        }

        return $fields;
    }
}
