            if ($infoDiv) {
                $infoContent = $this->getInnerHTML($infoDiv);
            }

            $categories = $xpath->query('.//div[contains(@class,"icategories")]/a', $infoDiv);
            $authors = $xpath->query('.//div[contains(@class,"iauthors")]/a', $infoDiv);

            // Prepare category and author strings (using a single extraction)
            $categoryList = [];
            foreach ($categories as $category) {
                $categoryList[] = $category->textContent;
            }
            $authorList = [];
            foreach ($authors as $author) {
                $authorList[] = $author->textContent;
            }

            $categoryText = count($categoryList) ? 'Category: ' . implode(', ', $categoryList) : '';
            $authorText = count($authorList) ? 'By: ' . implode(', ', $authorList) : '';

            $item = [
                'title' => $title,
                'uri' => $link,
                'content' => $summary,
                'description' => $summary,
            ];

            if ($imageUrl) {
                $item['content'] .= "<br><img src=\"$imageUrl\" alt=\"$title\" />";
                $item['description'] .= "<br><img src=\"$imageUrl\" alt=\"$title\" />";
            }

            if ($headingsContent) {
                $item['content'] .= "<br><strong>Headings:</strong><br>" . $headingsContent;
                $item['description'] .= "<br><strong>Headings:</strong><br>" . $headingsContent;
            }

            if ($infoContent) {
                $item['content'] .= "<br><strong>Additional Info:</strong><br>" . $infoContent;
                $item['description'] .= "<br><strong>Additional Info:</strong><br>" . $infoContent;
            }

            if ($categoryText || $authorText) {
                $item['content'] .= "<br><br>" . $categoryText . " " . $authorText;
                $item['description'] .= "<br><br>" . $categoryText . " " . $authorText;
            }

            $this->items[] = $item;
        }
    }

    private function getInnerHTML(DOMElement $element)
    {
        $innerHTML = '';
        $children = $element->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }
}
?>
