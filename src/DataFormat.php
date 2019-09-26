<?php

namespace Horoshop;

use Exception;

class DataFormat
{

    /**
     * @param string $type
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function handle(string $type, array $data): array
    {
        $availableTypes = [
            'currencies',
            'categories',
            'products',
            'discounts',
        ];

        if (!in_array($type, $availableTypes)) throw new Exception('unknown type');
        return self::$type($data);
    }

    /**
     * @param array $currencies
     * @return array $formattedData
     */
    private static function currencies(Array $currencies): array
    {
        $formattedData = [];
        foreach ($currencies as $currency) {
            $formattedData[$currency->title] = (array)$currency->rates;
        }

        return $formattedData;
    }

    /**
     * @param array $categories
     * @return array
     */
    private static function categories(Array $categories): array
    {
        $formattedData = [];
        foreach ($categories as $category) {
            $formattedData[$category->id] = (array)$category;
        }

        return $formattedData;
    }

    /**
     * @param array $products
     * @return array
     */
    private static function products(Array $products): array
    {
        $formattedData = [];
        foreach ($products as $product) {
            $formattedData[] = (array)$product;
        }

        return $formattedData;
    }

    /**
     * @param array $discounts
     * @return array
     */
    private static function discounts(Array $discounts): array
    {
        $formattedData = [];
        foreach ($discounts as $discount) {
            $formattedData[$discount->relation][$discount->related_id] = (array)$discount;
        }

        return $formattedData;
    }

}