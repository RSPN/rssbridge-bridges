<?php

class OnThisDayBridge extends BridgeAbstract {
    const NAME = 'On This Day';
    const URI = 'https://www.onthisday.com';
    const DESCRIPTION = 'Fetches todays historical events';
    const MAINTAINER = 'Nebenfrau';
    const PARAMETERS = array();

    public function collectData() {
        $html = getSimpleHTMLDOM(self::URI);

        // Date extraction
        $date = '';
        $timeElem = $html->find('main section.content-heading header div.wrapper time', 0);
        if ($timeElem) {
            $date = trim($timeElem->getAttribute('datetime'));
        }

        // Helper to parse all events in a section
        $parseAllEvents = function($sectionHeaderClass, $category) use ($html, $date) {
            $items = [];
            $header = $html->find("header.section__heading.$sectionHeaderClass", 0);
            if ($header) {
                // Find all following ULs with class "event-list" until you hit the next header or end
                $el = $header->next_sibling();
                while ($el && $el->tag == 'ul' && strpos($el->class, 'event-list') !== false) {
                    foreach ($el->find('li.event') as $li) {
                        // Extract year and content
                        $year = '';
                        $yearLink = $li->find('a.date', 0);
                        if ($yearLink) {
                            $year = trim($yearLink->plaintext);
                            $yearLink->outertext = ''; 
                        }
                        $description = trim($li->plaintext);
                        $description = preg_replace('/^\d{3,4}\s*/', '', $description);

                        // Try to get link for details
                        $link = '';
                        $mainLink = $li->find('a', 0);
                        if ($mainLink) {
                            $href = $mainLink->href;
                            $link = strpos($href, 'http') === 0 ? $href : self::URI . $href;
                        }

                        // Try to extract image if available
                        $image = '';
                        $imgElem = $li->find('.event-photo img', 0);
                        if ($imgElem) {
                            $src = $imgElem->src;
                            $image = strpos($src, 'http') === 0 ? $src : self::URI . $src;
                        }

                        $item = [
                            'uri' => $link,
                            'title' => ($year ? $year . ' - ' : '') . mb_strimwidth($description, 0, 100, '...'),
                            'timestamp' => $date,
                            'content' => ($image ? '<img src="' . $image . '"/><br>' : '') . htmlspecialchars($description),
                            'categories' => [$category],
                        ];
                        $items[] = $item;
                    }
                    $el = $el->next_sibling();
                }
            }
            return $items;
        };

        // General: first two event lists after "header-history"
        $general = [];
        $header = $html->find('header.section__heading.header-history', 0);
        if ($header) {
            $found = 0;
            $el = $header->next_sibling();
            while ($el && $found < 2) {
                if ($el->tag == 'ul' && strpos($el->class, 'event-list') !== false) {
                    foreach ($el->find('li.event') as $li) {
                        $year = '';
                        $yearLink = $li->find('a.date', 0);
                        if ($yearLink) {
                            $year = trim($yearLink->plaintext);
                            $yearLink->outertext = '';
                        }
                        $description = trim($li->plaintext);
                        $description = preg_replace('/^\d{3,4}\s*/', '', $description);
                        $link = '';
                        $mainLink = $li->find('a', 0);
                        if ($mainLink) {
                            $href = $mainLink->href;
                            $link = strpos($href, 'http') === 0 ? $href : self::URI . $href;
                        }
                        $image = '';
                        $imgElem = $li->find('.event-photo img', 0);
                        if ($imgElem) {
                            $src = $imgElem->src;
                            $image = strpos($src, 'http') === 0 ? $src : self::URI . $src;
                        }
                        $general[] = [
                            'uri' => $link,
                            'title' => ($year ? $year . ' - ' : '') . mb_strimwidth($description, 0, 100, '...'),
                            'timestamp' => $date,
                            'content' => ($image ? '<img src="' . $image . '"/><br>' : '') . htmlspecialchars($description),
                            'categories' => ['General'],
                        ];
                    }
                    $found++;
                }
                $el = $el->next_sibling();
            }
        }
        $filmTv = $parseAllEvents('header-film-tv', 'Film & TV');
        $music  = $parseAllEvents('header-music', 'Music');
        $sport  = $parseAllEvents('header-sport', 'Sport');

        // Merge all items
        $this->items = array_merge($general, $filmTv, $music, $sport);
    }
}
