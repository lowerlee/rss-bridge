<?php

class USIPBridge extends BridgeAbstract
{
    const NAME = 'USIP Publications Bridge';
    const URI = 'https://www.usip.org/publications';
    const DESCRIPTION = 'Returns titles from USIP Publications';
    const MAINTAINER = 'Your Name';
    const CACHE_TIMEOUT = 3600;

    public function collectData() {
        $html = getSimpleHTMLDOM(self::URI);
        $articles = $html->find('article.summary');

        foreach($articles as $article) {
            $item = [];
            $titleElement = $article->find('h3.summary__heading a', 0);
            if ($titleElement) {
                $item['title'] = $titleElement->plaintext;
                $item['uri'] = 'https://www.usip.org' . $titleElement->href;
            }
            $this->items[] = $item;
        }
    }
}