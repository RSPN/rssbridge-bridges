<?php
class HpoiGeneralAlbumBridge extends BridgeAbstract {
    const NAME = 'Hpoi General Character Album Bridge';
    const URI = 'https://www.hpoi.net';
    const DESCRIPTION = 'Fetches character albums from Hpoi.net using the AJAX endpoint (supports dynamic loading) and includes all album images.';
    const MAINTAINER = 'RSPN';
    const PARAMETERS = [
        [
            'char_id' => [
                'name' => 'Character ID',
                'type' => 'text',
                'exampleValue' => '76862',
                'required' => true,
            ],
            'limit' => [
                'name' => 'Limit',
                'type' => 'text', // allows "*", "0" for unlimited
                'defaultValue' => 10,
                'required' => false,
                'title' => 'Maximum number of albums to fetch (use "*", "∞" or "0" for unlimited)',
            ],
            'page' => [
                'name' => 'Page',
                'type' => 'number',
                'defaultValue' => 1,
                'required' => false,
            ],
            'max_images' => [
                'name' => 'Max Images Per Album',
                'type' => 'number',
                'defaultValue' => 5,
                'required' => false,
            ],
        ]
    ];

    public function collectData() {
        $charId = $this->getInput('char_id');
        $limitRaw = $this->getInput('limit');
        $limit = 10;

        // Accept "*", "∞", or "0" as unlimited
        if ($limitRaw === '*' || $limitRaw === '∞' || $limitRaw === '0') {
            $limit = PHP_INT_MAX;
        } else {
            $limit = min(intval($limitRaw ?: 10), 100);
        }

        $page = $this->getInput('page') ?: 1;
        $maxImages = $this->getInput('max_images') ?: 5;

        $url = "https://www.hpoi.net/charactar/album/get?charactarId={$charId}&page={$page}";
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: */*',
                    'User-Agent: Mozilla/5.0 (compatible; RSS-Bridge; +https://github.com/RSS-Bridge/rss-bridge)',
                    'X-Requested-With: XMLHttpRequest',
                    'Referer: https://www.hpoi.net/charactar/general/' . $charId . '?type=album'
                ]
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            returnServerError('Could not fetch album list from AJAX endpoint.');
        }

        $html = str_get_html($response);
        if (!$html) {
            returnServerError('Failed to parse album list HTML.');
        }

        $boxes = $html->find('.waterfall-ibox');
        $count = 0;
        foreach ($boxes as $albumIdx => $box) {
            $a = $box->find('a', 0);
            if (!$a) continue;
            $href = $a->href;
            if (strpos($href, 'album/') === 0) {
                $href = self::URI . '/' . ltrim($href, '/');
            } elseif (strpos($href, 'http') !== 0) {
                $href = self::URI . '/' . ltrim($href, '/');
            }

            $albumTitle = $a->title ?: $a->plaintext;

            // --- Markup and styling for album thumbnails (enlarged) ---
            $desc = <<<HTML
<div style="border: 2px solid #aaccff; border-radius: 16px; box-shadow: 0 4px 24px rgba(30,60,180,0.07); padding: 18px; margin: 20px 0; background: linear-gradient(90deg,#fafdff 60%,#eef6ff 100%);">
  <h3 style="margin-top:0;font-size:1.25em;color:#2364c6;">
    <a href="{$href}" style="color:#2364c6;text-decoration:none;" target="_blank" rel="noopener">{$albumTitle}</a>
  </h3>
  <div style="display:flex;flex-wrap:wrap;gap:24px;justify-content:flex-start;">
HTML;

            $albumHtml = @file_get_contents($href, false, $context);
            if ($albumHtml !== false) {
                $albumDom = str_get_html($albumHtml);
                if ($albumDom) {
                    $waterfall = $albumDom->find('#waterfall', 0);
                    if ($waterfall) {
                        $imageCount = 0;
                        foreach ($waterfall->children() as $div) {
                            $aImg = $div->find('a', 0);
                            if ($aImg) {
                                $imgInner = $aImg->find('img', 0);
                                if ($imgInner && !empty($imgInner->src)) {
                                    $desc .=
                                        '<a href="' . htmlspecialchars($imgInner->src) . '" target="_blank" rel="noopener" style="display:inline-block;overflow:hidden;background:#f6fbff;box-shadow:0 2px 8px rgba(36,80,160,0.07);padding:0;margin:0;vertical-align:top;">' .
                                        '<div style="width:320px;height:320px;display:flex;align-items:center;justify-content:center;border:3px solid #c2e0ff;border-radius:16px;overflow:hidden;background:#eaf3ff;">' .
                                        '<img src="' . htmlspecialchars($imgInner->src) . '" alt="" loading="lazy" style="display:block;max-width:100%;max-height:100%;width:auto;height:auto;" />' .
                                        '</div></a>';
                                    $imageCount++;
                                    // Uncomment below to limit images per album (uses max_images parameter)
                                    // if ($imageCount >= $maxImages) break;
                                }
                            }
                        }
                    }
                }
            }
            $desc .= '</div></div>';

            $this->items[] = [
                'title' => $albumTitle,
                'uri' => $href,
                'content' => $desc,
                'author' => '',
                'uid' => $href,
            ];

            $count++;
            if ($count >= $limit) break;
        }
    }
}
