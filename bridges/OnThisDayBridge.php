<?php
class OnThisDayBridge extends BridgeAbstract {
    const NAME = 'On This Day Full Bridge';
    const URI = 'https://www.onthisday.com';
    const DESCRIPTION = 'Fetches all events for the current date from On This Day.';
    const MAINTAINER = 'RSPN';
    const PARAMETERS = []; // No parameters required for this version.

    public function collectData() {
        // Fetch the main page
        $html = getSimpleHTMLDOM(self::URI);

        // Define sections to scrape
        $sections = [
            'Historical Events' => 'header.header-history',
            'Film & TV' => 'header.header-film-tv',
            'Music' => 'header.header-music',
            'Sport' => 'header.header-sport',
            'Birthdays' => 'header.section__heading a[href="today/birthdays.php"]',
            'Deaths' => 'header.section__heading a[href="today/deaths.php"]',
            'Weddings & Divorces' => 'header.section__heading a[href="today/weddings-divorces.php"]',
        ];

        foreach ($sections as $sectionName => $selector) {
            $sectionHeader = $html->find($selector, 0);

            if ($sectionHeader) {
                $sectionList = $sectionHeader->parent()->find('ul.event-list, ul.photo-list', 0);

                if ($sectionList) {
                    foreach ($sectionList->find('li') as $element) {
                        $item = [];
                        $dateElement = $element->find('a.date', 0);
                        $descriptionElement = $element->find('a', 1) ?? $element->find('span', 0);

                        // Generate item
                        $item['title'] = '[' . $sectionName . '] ' . ($dateElement ? trim($dateElement->plaintext) . ': ' : '') . ($descriptionElement ? trim($descriptionElement->plaintext) : '');
                        $item['uri'] = $dateElement ? self::URI . $dateElement->href : self::URI;
                        $item['content'] = $element->innertext;

                        $this->items[] = $item;
                    }
                }
            }
        }
    }
}
