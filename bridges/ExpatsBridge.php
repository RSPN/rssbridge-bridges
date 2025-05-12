<?php

class ExpatsBridge extends BridgeAbstract
{
    const NAME = 'Expats.cz News Bridge';
    const URI = 'https://www.expats.cz';
    const DESCRIPTION = 'Fetch all articles from Expats.cz, ensuring bullet points are handled correctly.';
    const MAINTAINER = 'Nebenfrau';
    const PARAMETERS = [];
    const CACHE_TIMEOUT = 86400; // Cache for one day (24 hours)

    public function collectData()
    {
        // Fetch the HTML content from the main page
        $html = getSimpleHTMLDOM(self::URI)
            or returnServerError('Could not request ' . self::URI);

        // Find the container for articles using XPath-like traversal
        $articleContainer = $html->find('div.content', 0);

        if (!$articleContainer) {
            returnServerError('Could not find the article container');
        }

        // Find all <article> elements within the container
        $articles = $articleContainer->find('article');

        foreach ($articles as $index => $article) {
            $item = [];
            $item['uri'] = self::URI . $article->find('a', 0)->href;
            $item['title'] = $article->find('h3 a', 0)->plaintext;
            $item['author'] = $this->extractAuthor($article);
            $item['categories'] = $this->extractCategories($article);

            // For the first article, include bullet points in place of a summary
            if ($index === 0) {
                $bulletPoints = $this->extractBulletPoints($article);
                if ($bulletPoints) {
                    $item['content'] = $bulletPoints; // Add bullet points as content if available
                } else {
                    $item['content'] = '<p>No summary available</p>';
                }
            } else {
                $item['content'] = $this->extractSummary($article);
            }

            $this->items[] = $item;
        }
    }

    private function extractAuthor($article)
    {
        $authorNode = $article->find('div.iauthors', 0);
        return $authorNode ? $authorNode->plaintext : 'Unknown';
    }

    private function extractCategories($article)
    {
        $categories = [];
        $categoryNodes = $article->find('div.icategories a');
        foreach ($categoryNodes as $categoryNode) {
            $categories[] = $categoryNode->plaintext;
        }
        return implode(', ', $categories);
    }

    private function extractSummary($article)
    {
        $summaryNode = $article->find('p', 0);
        return $summaryNode ? $summaryNode->plaintext : 'No summary available';
    }

    private function extractBulletPoints($article)
    {
        $bulletPointNode = $article->find('div.headings ul', 0);
        if (!$bulletPointNode) {
            return ''; // Return empty string if no bullet points are found
        }

        $bulletPoints = '';
        foreach ($bulletPointNode->find('li') as $bullet) {
            $bulletPoints .= '<li>' . $bullet->plaintext . '</li>';
        }

        return '<ul>' . $bulletPoints . '</ul>';
    }
}
