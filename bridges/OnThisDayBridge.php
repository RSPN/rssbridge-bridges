<?php
class OnThisDayBridge extends BridgeAbstract {
    const NAME = 'On This Day Articles Bridge';
    const URI = 'https://www.onthisday.com';
    const DESCRIPTION = 'Fetches featured articles for the current date from On This Day.';
    const MAINTAINER = 'RSPN';
    const PARAMETERS = []; // No parameters required for this version.

    public function collectData() {
        // Fetch the main page
        $html = getSimpleHTMLDOM(self::URI);

        // Locate the featured article section
        $featuredArticle = $html->find('div.featured-article', 0);

        if ($featuredArticle) {
            $item = [];

            // Extract the title
            $header = $featuredArticle->find('header h3.poi__heading', 0);
            $item['title'] = trim($header->plaintext);

            // Extract the article content
            $content = $featuredArticle->find('p.linked_text', 0);
            $item['content'] = trim($content->plaintext);

            // Extract the date
            $date = $featuredArticle->find('span.linked_date', 0);
            $item['timestamp'] = date('Y-m-d', strtotime(trim($date->plaintext)));

            // Extract the article URL
            $link = $featuredArticle->find('a', 0);
            $item['uri'] = self::URI . $link->href;

            // Add to items
            $this->items[] = $item;
        }
    }
}
