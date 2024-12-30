<?php

class USIPBridge extends BridgeAbstract
{
    const NAME = 'USIP Publications Bridge';
    const URI = 'https://www.usip.org/publications';
    const DESCRIPTION = 'Returns latest publications from United States Institute of Peace';
    const MAINTAINER = 'Your Name';
    const CACHE_TIMEOUT = 3600; // 1 hour
    
    const PARAMETERS = [
        'Publication Type' => [
            'type' => [
                'name' => 'Type',
                'type' => 'list',
                'values' => [
                    'Analysis' => '12',
                    'Report' => '7',
                    'Article' => '4',
                    'Discussion Paper' => '19121'
                ]
            ]
        ]
    ];

    public function collectData()
    {
        $url = self::URI;
        if ($this->getInput('type')) {
            $url .= '?publication_type%5B0%5D=' . $this->getInput('type');
        }

        $html = getSimpleHTMLDOM($url);

        $articles = $html->find('article.summary');

        foreach ($articles as $article) {
            $item = [];

            // Get title and URL
            $titleElement = $article->find('h3.summary__heading a', 0);
            $item['uri'] = 'https://www.usip.org' . $titleElement->href;
            $item['title'] = $titleElement->plaintext;

            // Get date
            $dateElement = $article->find('span.published-date', 0);
            if ($dateElement) {
                $item['timestamp'] = strtotime($dateElement->plaintext);
            }

            // Get author
            $authorElement = $article->find('p.meta.publication-by-line a', 0);
            if ($authorElement) {
                $item['author'] = $authorElement->plaintext;
            }

            // Get content
            $contentElement = $article->find('div.summary__text p', 0);
            if ($contentElement) {
                $item['content'] = $contentElement->plaintext;
            }

            // Get image
            $imageElement = $article->find('img', 0);
            if ($imageElement) {
                $item['enclosures'] = [$imageElement->src];
            }

            $this->items[] = $item;
        }
    }

    public function getName()
    {
        if (!is_null($this->getInput('type'))) {
            $types = $this->getParameters()['Publication Type']['type']['values'];
            $typeName = array_search($this->getInput('type'), $types);
            return 'USIP Publications - ' . $typeName;
        }
        return parent::getName();
    }
}