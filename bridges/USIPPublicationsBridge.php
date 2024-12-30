<?php
/**
 * This file is part of RSS-Bridge, a PHP project capable of generating RSS and
 * Atom feeds for websites that don't have one.
 *
 * For the full license information, please view the UNLICENSE file distributed
 * with this source code.
 *
 * @package RSS-Bridge
 */

class USIPPublicationsBridge extends BridgeAbstract
{
    const NAME = 'USIP Publications Bridge';
    const URI = 'https://www.usip.org/publications';
    const DESCRIPTION = 'Returns latest publications from United States Institute of Peace';
    const MAINTAINER = 'Your Name';
    const CACHE_TIMEOUT = 3600; // 1 hour

    const TEST_DETECT_PARAMETERS = [
        'https://www.usip.org/publications?publication_type%5B0%5D=12' => [
            'context' => 'Publication Type',
            'type' => '12'
        ]
    ];

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

        if (!$html) {
            returnServerError('Could not request USIP Publications.');
        }

        $articles = $html->find('article.summary');

        foreach ($articles as $article) {
            $item = [];

            // Get title and URL
            $titleElement = $article->find('h3.summary__heading a', 0);
            if ($titleElement) {
                $item['uri'] = 'https://www.usip.org' . $titleElement->href;
                $item['title'] = trim($titleElement->plaintext);
            }

            // Get date
            $dateElement = $article->find('span.published-date', 0);
            if ($dateElement) {
                $item['timestamp'] = strtotime($dateElement->plaintext);
            }

            // Get author
            $authorElement = $article->find('p.meta.publication-by-line a', 0);
            if ($authorElement) {
                $item['author'] = trim($authorElement->plaintext);
            }

            // Get content
            $contentElement = $article->find('div.summary__text p', 0);
            if ($contentElement) {
                $item['content'] = trim($contentElement->plaintext);
            }

            // Get image
            $imageElement = $article->find('img', 0);
            if ($imageElement) {
                $item['enclosures'] = [$imageElement->src];
            }

            $this->items[] = $item;
        }
    }

    /**
     * Detects parameters from the URL
     * @param string $url URL to detect parameters from
     * @return array|null List of detected parameters or null if detection failed
     */
    public function detectParameters($url)
    {
        $params = [];
        $regex = '/publication_type%5B0%5D=([0-9]+)/';
        if (preg_match($regex, $url, $matches) > 0) {
            $params['context'] = 'Publication Type';
            $params['type'] = $matches[1];
            return $params;
        }
        return null;
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