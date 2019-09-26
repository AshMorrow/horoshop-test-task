<?php

namespace Horoshop;

use Horoshop\Exceptions\UnavailablePageException;
use LimitIterator;
use ArrayIterator;
use Exception;

class ProductAggregator
{
    /**
     * @var string
     */
    private $filename;
    /**
     * @var array
     */
    private $currencies;
    /**
     * @var array
     */
    private $categories;
    /**
     * @var array
     */
    private $products;
    /**
     * @var array
     */
    private $discounts;

    /**
     * ProductAggregator constructor.
     * @param string $filename
     * @throws Exception
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;

        if (!file_exists(BASE_PATH . $filename)) {
            throw new Exception('File ' . $filename . 'not fount');
        }

        $data = json_decode(file_get_contents(BASE_PATH . $filename));
        $this->currencies = DataFormat::handle('currencies', $data->currencies);
        $this->categories = DataFormat::handle('categories', $data->categories);
        $this->products = DataFormat::handle('products', $data->products);
        $this->discounts = DataFormat::handle('discounts', $data->discounts);
    }

    /**
     * @param string $currency
     * @param int $page
     * @param int $perPage
     *
     * @return string Json
     * @throws UnavailablePageException|Exception
     */
    public function find(string $currency, int $page, int $perPage): string
    {
        if ($page < 0) {
            throw new UnavailablePageException('Page must be bigger then 0');
        }

        if (!in_array($currency, AVAILABLE_CURRENCY)) {
            throw new Exception('Unavailable currency');
        }

        $totalPages = count($this->products) / $perPage;
        if ($totalPages < $page) {
            throw new UnavailablePageException('Seek position ' . $page . ' is out of range');
        }

        $preparedData = [
            'items' => [],
            'perPage' => $perPage,
            'pages' => $totalPages,
            'page' => $page,
        ];

        $paginationOffset = $page > 1 ? ($perPage * $page) - $perPage : 0;
        $productsOnPage = iterator_to_array(new LimitIterator(new ArrayIterator($this->products), $paginationOffset, $perPage));
        foreach ($productsOnPage as $product) {
            $preparedData['items'][] = [
                'id' => $product['id'],
                'title' => $product['title'],
                'category' => $this->categories[$product['category']],
                'price' => $this->joinPrice($product, $currency)
            ];
        }

        return json_encode($preparedData);
    }

    /**
     * @param array $product
     * @param string $currency
     * @return array
     */
    private function joinPrice(array $product, string $currency): array
    {
        $discount = $this->calculateDiscount($product);
        $discountPrice = $discount['discounted_price'];

        if ($currency != BASE_CURRENCY) {
            $rate = $this->currencies[BASE_CURRENCY][$currency];
            $product['amount'] *= $rate;
            $discountPrice = $discountPrice * $rate;
        }

        $discountPriceFormat = function () use ($discountPrice, $discount) {
            $precision = 2;
            $rounded = $discountPrice;

            if (count(explode('.', $discountPrice)) > 1) {
                $fig = (int)str_pad('1', 1 + $precision, '0');
                $rounded = (ceil($discountPrice * $fig) / $fig);
            }

            return (float)number_format($rounded, 2, '.', '');
        };

        $discountPrice = $discountPriceFormat();
        return [
            'amount' => $product['amount'],
            'discounted_price' => $discountPrice,
            'currency' => $currency,
            'discount' => [
                "type" => $discount['type'],
                "value" => $discount['value'],
                "relation" => $discount['relation']
            ]
        ];
    }

    /**
     * @param array $product
     * @return array
     */
    private function calculateDiscount(array $product): array
    {
        $productDiscount = $this->discounts['product'][$product['id']] ?? [];
        $categoryDiscount = $this->discounts['category'][$product['category']] ?? [];

        $calculateDiscountPrice = function ($discount) use ($product) {
            $discountPrice = $product['amount'];

            if (!isset($discount['type'])) return $discountPrice;

            if ($discount['type'] == 'absolute') {
                $discountPrice -= $discount['value'];
            } else if ($discount['type'] == 'percent') {
                $discountPrice = $product['amount'] * ((100 - $discount['value']) / 100);
            }

            return $discountPrice;
        };

        $productDiscountPrice = $calculateDiscountPrice($productDiscount);
        $categoryDiscountPrice = $calculateDiscountPrice($categoryDiscount);

        $preparedDiscount = [
            'discounted_price' => $product['amount'],
            'type' => '',
            'value' => '',
            'relation' => ''
        ];

        $setDiscount = function ($price, $discountBlock, $setDiscount) use (&$preparedDiscount) {
            $preparedDiscount['discounted_price'] = $price;
            $preparedDiscount['type'] = $discountBlock['type'];
            $preparedDiscount['value'] = $discountBlock['value'];
            $preparedDiscount['relation'] = $setDiscount;
        };

        if ($categoryDiscountPrice < $productDiscountPrice) {
            $setDiscount($categoryDiscountPrice, $categoryDiscount, 'category');
        } else if ($productDiscountPrice < $categoryDiscountPrice) {
            $setDiscount($productDiscountPrice, $productDiscount, 'product');
        }

        return $preparedDiscount;
    }
}