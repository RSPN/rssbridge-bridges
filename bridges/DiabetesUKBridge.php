<?php
class DiabetesUKBridge extends BridgeAbstract {
    const NAME = 'Diabetes UK News';
    const URI = 'https://www.diabetes.org.uk/';
    const DESCRIPTION = 'Fetches the latest news articles from Diabetes UK';
    const MAINTAINER = 'YourName';
    const PARAMETERS = array();

    public function collectData() {
        $html = getSimpleHTMLDOM(self::URI . 'about-us/news-and-views/search?category=all')
            or returnServerError('Could not load the website');

        foreach ($html->find('div.views-row.news-listing__item article') as $article) {
            $item = array();

            // Extract the article title
            $titleElement = $article->find('.news-card__title a', 0);
            if ($titleElement) {
                $item['title'] = $titleElement->plaintext;
                $item['uri'] = urljoin(self::URI, $titleElement->href);
            }

            // Extract the publication date
            $dateElement = $article->find('.news-card__date', 0);
            if ($dateElement) {
                $item['timestamp'] = strtotime($dateElement->plaintext);
            }

            // Extract the summary
            $summaryElement = $article->find('.news-card__summary', 0);
            if ($summaryElement) {
                $item['content'] = $summaryElement->plaintext;
            }

            // Extract and correctly format the image
            $imageElement = $article->find('.news-card__img img', 0);
            if ($imageElement) {
                $imageUrl = urljoin(self::URI, ltrim($imageElement->src, '/'));
                $item['content'] .= '<br><img src="' . $imageUrl . '" alt="Article Image" />';
            }

            $this->items[] = $item;
        }
    }
}
