<?php

class ManchesterConsulateBridge extends BridgeAbstract {
    const NAME = 'Manchester Czech Consulate General';
    const URI = 'https://mzv.gov.cz/manchester/en/index.html';
    const DESCRIPTION = 'Fetches articles from the Consulate General';
    const MAINTAINER = 'Nebenfrau';
    const PARAMETERS = array();

    // You can adjust these constants as needed
    const USERAGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    const TIMEOUT = 15; // seconds

    public function collectData() {
        // Set custom context options for useragent and timeout
        $opts = [
            'useragent' => self::USERAGENT,
            'timeout' => self::TIMEOUT,
        ];

        // Try to fetch with custom options, fallback if unavailable
        if (function_exists('getSimpleHTMLDOM')) {
            $html = getSimpleHTMLDOM(self::URI, $opts);
        } else {
            // fallback, should not usually be needed
            $html = getSimpleHTMLDOM(self::URI);
        }

        if (!$html) {
            returnServerError('Could not load site (timeout or blocked). Try increasing timeout or check user agent.');
        }

        // Find all articles in the main article list section
        foreach($html->find('section.article_list > div > article.article') as $article) {
            $item = array();

            // Title and URI
            $titleNode = $article->find('h2.article_title a', 0);
            $item['title'] = html_entity_decode($titleNode ? $titleNode->plaintext : 'No title');
            $item['uri'] = $titleNode ? urljoin(self::URI, $titleNode->href) : self::URI;

            // Date published (and updated, if present)
            $dateNode = $article->find('p.articleDate', 0);
            if ($dateNode) {
                // Date format: 09.05.2025 / 12:45
                $dateText = trim(explode('|', $dateNode->plaintext)[0]);
                $dateText = preg_replace('/Aktualizováno:.*/', '', $dateText); // remove any update text
                $dateText = trim(str_replace("\n", '', $dateText));
                // Convert "dd.mm.YYYY / HH:MM" to ISO 8601 for RSS
                if (preg_match('/(\d{2})\.(\d{2})\.(\d{4}) \/ (\d{2}):(\d{2})/', $dateText, $m)) {
                    $item['timestamp'] = mktime($m[4], $m[5], 0, $m[2], $m[1], $m[3]);
                }
            }

            // Thumbnail (image)
            $imgNode = $article->find('img.illustration, img.float_left', 0);
            $img_html = '';
            if($imgNode) {
                $src = $imgNode->src;
                // Make absolute URL
                if(strpos($src, 'http') !== 0) {
                    $src = urljoin(self::URI, $src);
                }
                $img_html = '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($item['title']) . '" style="max-width:100%">';
                $item['thumbnail'] = $src; // optional
            }

            // Description/summary (perex)
            $descNode = $article->find('p.article_perex', 0);
            $desc_html = '';
            if($descNode) {
                // Remove "more" link
                foreach($descNode->find('a.link_vice') as $a) $a->outertext = '';
                $desc_html = trim($descNode->innertext);
            }

            // Author not present on site - set as Consulate General
            $item['author'] = 'Consulate General of the Czech Republic in Manchester';

            // Compose content: image + description
            $item['content'] = $img_html . '<br>' . $desc_html;

            $this->items[] = $item;
        }
    }
}
