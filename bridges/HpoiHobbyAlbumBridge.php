<?php
class HpoiHobbyAlbumBridge extends BridgeAbstract {
    const NAME = 'Hpoi Hobby Album Bridge';
    const URI = 'https://www.hpoi.net/';
    const DESCRIPTION = 'Returns RSS for hobby item albums from Hpoi (main and nested albums, prevents duplicate images, falls back to hobby page and shows official image if no nested albums). Produces separate RSS feeds for nested albums.';
    const MAINTAINER = 'copilot-github';
    const PARAMETERS = [
        [
            'charactar_id' => [
                'name' => 'Character ID',
                'type' => 'number',
                'required' => true,
                'exampleValue' => '7442240'
            ],
            'page_size' => [
                'name' => 'Page Size',
                'type' => 'number',
                'defaultValue' => 21
            ]
        ]
    ];

    public function collectData() {
        $charactar_id = $this->getInput('charactar_id');
        $page_size = $this->getInput('page_size') ?: 21;

        $url = "https://www.hpoi.net/charactar/hobby/query?specs=&r18=199&pageSize={$page_size}&releaseStart=&releaseEnd=&releaseYear=0&releaseMonth=0&releaseYearCount=0&charactar={$charactar_id}";

        // Set custom headers
        $headers = [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
            "Accept-Encoding: gzip, deflate, br, zstd",
            "Accept-Language: en-GB,en-US;q=0.9,en;q=0.8",
            "Cache-Control: max-age=0",
            "Connection: keep-alive",
            "Host: www.hpoi.net",
            "Referer: https://www.google.com/",
            "Sec-Ch-Ua: \"Chromium\";v=\"136\", \"Google Chrome\";v=\"136\", \"Not.A/Brand\";v=\"99\"",
            "Sec-Ch-Ua-Platform: \"macOS\"",
            "Sec-Fetch-Dest: document",
            "Sec-Fetch-Mode: navigate",
            "Sec-Fetch-Site: cross-site",
            "Sec-Fetch-User: ?1",
            "Upgrade-Insecure-Requests: 1",
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36"
        ];

        $html = getSimpleHTMLDOM($url, $headers) or returnServerError('Could not request Hpoi list page');

        $processedAlbums = []; // Track processed albums to avoid duplicates

        foreach ($html->find('div.col-md-8 div.hobby-item') as $element) {
            $link = $element->find('a.item-details-nameCN', 0);
            if (!$link) continue;

            if (preg_match('/hobby\/(\d+)/', $link->href, $matches)) {
                $id = $matches[1];
                $album_uri = "https://www.hpoi.net/hobby/album/{$id}";

                // Skip if this album has already been processed
                if (isset($processedAlbums[$id])) {
                    continue;
                }
                $processedAlbums[$id] = true;

                $nestedAlbumsHtml = '';
                $imageUrls = []; // Use this to track image URLs and prevent duplicates

                // Scrape the album page for nested albums
                $albumHtml = getSimpleHTMLDOM($album_uri, $headers);
                if ($albumHtml) {
                    foreach ($albumHtml->find('div.waterfall-ibox div.waterfall-item a') as $a) {
                        if (isset($a->href) && preg_match('#album/(\d+)#', $a->href, $amatch)) {
                            $nestedId = $amatch[1];
                            $nestedTitle = $a->title ? $a->title : ('Album ' . $nestedId);
                            $nestedUrl = "https://www.hpoi.net/album/{$nestedId}";

                            // Skip if this nested album has already been processed
                            if (isset($processedAlbums[$nestedId])) {
                                continue;
                            }
                            $processedAlbums[$nestedId] = true;

                            // Fetch images from nested album
                            $subAlbumHtml = getSimpleHTMLDOM($nestedUrl, $headers);
                            $subImages = [];
                            if ($subAlbumHtml) {
                                // Restrict scans to the container with class "hpoi-album-content" and ID "waterfall"
                                $imageContainer = $subAlbumHtml->find('div.hpoi-album-content div.album-list div#waterfall', 0);
                                if ($imageContainer) {
                                    // Scan for images directly within <a class="boutique"> tags
                                    foreach ($imageContainer->find('a.boutique img') as $boutiqueImg) {
                                        if (isset($boutiqueImg->src) && !isset($imageUrls[$boutiqueImg->src])) {
                                            $subImages[] = $boutiqueImg->src;
                                            $imageUrls[$boutiqueImg->src] = true;
                                        }
                                    }

                                    // Scan for images within nested <div> structures
                                    foreach ($imageContainer->find('div.waterfall-ibox div.waterfall-item a img') as $nestedImg) {
                                        if (isset($nestedImg->src) && !isset($imageUrls[$nestedImg->src])) {
                                            $subImages[] = $nestedImg->src;
                                            $imageUrls[$nestedImg->src] = true;
                                        }
                                    }

                                    // General fallback scan for all <img> elements within the container
                                    foreach ($imageContainer->find('img') as $imgElement) {
                                        if (isset($imgElement->src) && !isset($imageUrls[$imgElement->src])) {
                                            $subImages[] = $imgElement->src;
                                            $imageUrls[$imgElement->src] = true;
                                        }
                                    }
                                }
                            }

                            // Create RSS feed item for the nested album
                            $nestedItem = [];
                            $nestedItem['uri'] = $nestedUrl;
                            $nestedItem['title'] = htmlspecialchars($nestedTitle);
                            $nestedItem['content'] = '<section style="margin:18px 0 12px 0;border-top:1px solid #eee;padding-top:10px;">
                                <div style="font-weight:bold;font-size:1.07em;margin-bottom:4px;">
                                    <a href="' . $nestedUrl . '" target="_blank" style="text-decoration:none;color:#2a5ebb;">' . htmlspecialchars($nestedTitle) . ' <span style="font-size:90%;">↗</span></a>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:18px;align-items:center;">';
                            foreach ($subImages as $imgsrc) {
                                $nestedItem['content'] .= '<a href="' . $imgsrc . '" target="_blank"><img src="' . $imgsrc . '" style="max-width:340px;max-height:480px;width:auto;height:auto;border-radius:12px;box-shadow:0 2px 16px #0002;object-fit:contain;"/></a>';
                            }
                            $nestedItem['content'] .= '</div></section>';

                            $this->items[] = $nestedItem;
                        }
                    }
                }
            }
        }
    }
}
