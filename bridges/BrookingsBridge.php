<?php
class BrookingsBridge extends BridgeAbstract {
    const NAME = 'Brookings Institution Bridge';
    const URI = 'https://www.brookings.edu';
    const DESCRIPTION = 'Returns latest research and commentary from Brookings Institution';
    const MAINTAINER = 'Your GitHub Username';
    const CACHE_TIMEOUT = 3600; // 1 hour cache

    const PARAMETERS = [
        'Global' => [
            'limit' => [
                'name' => 'Limit',
                'type' => 'number',
                'required' => false,
                'title' => 'Maximum number of items to return',
                'defaultValue' => 10
            ]
        ]
    ];

    public function collectData() {
        $limit = $this->getInput('limit') ?: 10;
        $url = self::URI . '/research-commentary/';
        $html = getSimpleHTMLDOM($url);

        // Find article cards/elements
        $articles = $html->find('article.article-nav');
        $articles = array_slice($articles, 0, $limit);

        foreach ($articles as $article) {
            $item = [];

            // Get article link and title
            $link = $article->find('a.overlay-link', 0);
            $item['uri'] = $link->href;
            $item['title'] = trim($link->find('span.sr-only', 0)->plaintext);

            // Get thumbnail image if available
            $image = $article->find('img', 0);
            if ($image) {
                $imageUrl = $image->src;
                $item['enclosures'] = [$imageUrl];
                $item['content'] = '<img src="' . $imageUrl . '" alt="' . $image->alt . '"/><br/>';
            }

            // Get author if available
            $author = $article->find('span.author', 0);
            if ($author) {
                $item['author'] = trim($author->plaintext);
            }

            // Get date if available
            $date = $article->find('time', 0);
            if ($date) {
                $item['timestamp'] = strtotime($date->datetime);
            }

            // Get description/excerpt if available
            $excerpt = $article->find('p.description', 0);
            if ($excerpt) {
                $item['content'] = ($item['content'] ?? '') . trim($excerpt->plaintext);
            }

            // Get categories/topics if available
            $topics = $article->find('span.topic', 0);
            if ($topics) {
                $item['categories'] = array_map('trim', explode(',', $topics->plaintext));
            }

            $this->items[] = $item;
        }
    }

    public function getURI() {
        return self::URI . '/research-commentary/';
    }
}