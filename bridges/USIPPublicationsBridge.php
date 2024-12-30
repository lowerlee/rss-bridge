<?php

class USIPPublicationsBridge extends BridgeAbstract
{
    const NAME = 'USIP Publications Bridge';
    const URI = 'https://www.usip.org/publications';
    const DESCRIPTION = 'Returns the latest publications from the United States Institute of Peace';
    const MAINTAINER = 'Your Name';
    const PARAMETERS = [
        'Global' => [
            'limit' => [
                'name' => 'Limit',
                'type' => 'number',
                'required' => false,
                'defaultValue' => 10,
                'title' => 'Maximum number of publications to return'
            ]
        ],
        'By Type' => [
            'type' => [
                'name' => 'Publication Type',
                'type' => 'list',
                'values' => [
                    'Analysis' => '12',
                    'Congressional Testimony' => '15',
                    'Question and Answer' => '19171',
                    'Report' => '7',
                    'Peace Brief' => '24',
                    'Special Report' => '26'
                ],
                'title' => 'Select publication type'
            ]
        ]
    ];

    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        $html = getSimpleHTMLDOM(self::URI);
        
        $articles = $html->find('article.summary');
        $limit = $this->getInput('limit') ?: 10;
        $count = 0;

        foreach ($articles as $article) {
            if ($count >= $limit) {
                break;
            }

            $item = [];

            // Get title and URL
            $titleElement = $article->find('h3.summary__heading a', 0);
            if (!$titleElement) {
                continue;
            }
            
            $item['title'] = $titleElement->plaintext;
            $item['uri'] = 'https://www.usip.org' . $titleElement->href;

            // Get date
            $dateElement = $article->find('span.published-date', 0);
            if ($dateElement) {
                $item['timestamp'] = strtotime($dateElement->plaintext);
            }

            // Get authors
            $authorElement = $article->find('p.publication-by-line', 0);
            if ($authorElement) {
                $item['author'] = trim(str_replace('By:', '', $authorElement->plaintext));
            }

            // Get content/description
            $contentElement = $article->find('p', 0);
            if ($contentElement) {
                $item['content'] = $contentElement->plaintext;
            }

            // Get image
            $imageElement = $article->find('img', 0);
            if ($imageElement) {
                $item['enclosures'] = [$imageElement->src];
            }

            // Get categories (tags)
            $tagsElement = $article->find('p.tags a', 0);
            if ($tagsElement) {
                $item['categories'] = [$tagsElement->plaintext];
            }

            $this->items[] = $item;
            $count++;
        }
    }

    public function getName()
    {
        if ($this->getInput('type')) {
            $types = $this->getParameters()['By Type']['type']['values'];
            $typeName = array_search($this->getInput('type'), $types);
            return 'USIP Publications - ' . $typeName;
        }
        return parent::getName();
    }

    public function detectParameters($url)
    {
        if (preg_match('/usip\.org\/publications\/?$/', $url)) {
            return [];
        }
        return null;
    }
}