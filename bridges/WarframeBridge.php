<?php

class WarframeBridge extends BridgeAbstract
{
    const NAME = 'Warframe News Bridge';
    const URI = 'https://www.warframe.com/search';
    const DESCRIPTION = 'Latest news articles from the official Warframe site';
    const MAINTAINER = 'Nebenfrau';

    public function collectData()
    {
        $url = self::URI;
        $dom = getSimpleHTMLDOM($url);
        $newsCards = $dom->find('div.Card.NewsCard');

        if (!$newsCards) {
            throw new \Exception('Could not find any news cards on the Warframe site.');
        }

        foreach ($newsCards as $card) {
            $titleElem = $card->find('div.NewsCard-title', 0);
            $dateElem = $card->find('div.NewsCard-date', 0);
            $descElem = $card->find('div.NewsCard-description', 0);
            $thumbElem = $card->find('a.Card-media', 0);

            if (!$titleElem || !$dateElem || !$descElem || !$thumbElem) {
                continue; // Skip incomplete articles
            }

            $title = trim($titleElem->plaintext);
            $uri = urljoin(self::URI, $thumbElem->href);
            $timestamp = strtotime(trim($dateElem->plaintext));
            $content = '<p>' . htmlspecialchars(trim($descElem->plaintext)) . '</p>';

            if ($thumbElem->style) {
                preg_match('/url\\(["\']?(.*?)["\']?\\)/', $thumbElem->style, $matches);
                if (!empty($matches[1])) {
                    $imageUrl = $matches[1];
                    $content = '<img src="' . $imageUrl . '" /><br>' . $content;
                }
            }

            // Prevent duplicates based on title + date
            $uid = md5($title . $timestamp);

            $this->items[] = [
                'uid' => $uid,
                'title' => $title,
                'uri' => $uri,
                'content' => $content,
                'timestamp' => $timestamp,
            ];
        }
    }
}
