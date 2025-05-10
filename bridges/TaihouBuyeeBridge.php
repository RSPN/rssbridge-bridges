<?php

class TaihouBuyeeBridge extends BridgeAbstract {
    const NAME = 'Buyee Taihou Search';
    const URI = 'https://buyee.jp/item/search/query/%E5%A4%A7%E9%B3%B3/category/25888?sort=end&order=d&translationType=98';
    const DESCRIPTION = 'Buyee Taihou Search';
    const MAINTAINER = 'Nebenfrau';

    public function collectData() {
        $html = getSimpleHTMLDOM(self::URI);

        if (!$html) {
            returnServerError('Could not request Buyee Taihou Search');
        }

        // Find the list of items
        $items = $html->find('ul.auctionSearchResult.list_layout li.itemCard');

        foreach ($items as $element) {
            $item = [];

            // Title
            $titleElement = $element->find('div.itemCard__itemName', 0);
            $item['title'] = trim($titleElement->plaintext);

            // URI
            $uriElement = $element->find('a', 0);
            $item['uri'] = urljoin(self::URI, $uriElement->href);

            // Timestamp
            $timestampElement = $element->find('li.itemCard__infoItem', 0);
            $item['timestamp'] = strtotime(trim($timestampElement->plaintext));

            // Author
            $authorElement = $element->find('ul.itemCard__infoList li', 0);
            $item['author'] = trim($authorElement->plaintext);

            // Categories
            $categoriesElement = $element->find('ul.auctionSearchResult__statusList li', 0);
            $item['categories'] = [$categoriesElement ? trim($categoriesElement->plaintext) : ''];

            // Content
            $contentElement = $element->find('div.g-priceDetails ul.g-priceDetails__list', 0);
            $contentHTML = $contentElement ? trim($contentElement->innertext) : '';

            // Image URL (handle lazy loading and trim to remove excess query parameters)
            $imageElement = $element->find('div.g-thumbnail img.lazyLoadV2.g-thumbnail__image', 0);
            $imageHTML = '';
            if ($imageElement) {
                // Check for both `data-src` and `src` attributes
                $imageURL = $imageElement->getAttribute('data-src') ?: $imageElement->src;

                // Use only the base URL up to ".jpg"
                if (!empty($imageURL) && strpos($imageURL, 'spacer.gif') === false) {
                    $parsedURL = preg_replace('/(\.jpg).*/', '$1', $imageURL);

                    // Add the image as part of the content with a fixed size
                    $imageHTML = '<img src="' . htmlspecialchars($parsedURL) . '" alt="' . htmlspecialchars($item['title']) . '" style="max-width:300px; max-height:300px;" /><br>';
                }
            }

            // Combine image and content
            $item['content'] = $imageHTML . $contentHTML;

            // Optionally add the image as an enclosure for RSS readers that support it
            if (!empty($parsedURL)) {
                $item['enclosures'] = [$parsedURL];
            }

            // Add the item to the feed
            $this->items[] = $item;
        }

        // Prevent duplicates using an array of URIs
        $this->items = array_unique($this->items, SORT_REGULAR);
    }
}
