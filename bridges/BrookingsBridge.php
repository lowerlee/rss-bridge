<?php
class BrookingsBridge extends BridgeAbstract {
    const NAME = 'Brookings Institution Bridge';
    const URI = 'https://www.brookings.edu';
    const DESCRIPTION = 'Returns latest research and commentary from Brookings Institution';
    const MAINTAINER = 'Your GitHub Username';
    const CACHE_TIMEOUT = 3600;

    private function getPage($url) {
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Cache-Control: max-age=0'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            returnClientError('Error ' . $httpCode . ' when fetching ' . $url);
            return null;
        }

        return str_get_html($data);
    }

    public function collectData() {
        $html = $this->getPage(self::URI . '/research-commentary/');
        if (!$html) {
            returnServerError('Unable to get main page');
            return;
        }

        // Find all articles
        foreach($html->find('article.article-nav') as $article) {
            $item = [];
            
            // Get link and title
            $link = $article->find('a.overlay-link', 0);
            $item['uri'] = $link->href;
            $item['title'] = trim($link->find('span.sr-only', 0)->plaintext);

            // Fetch full article with proper headers
            $fullArticle = $this->getPage($item['uri']);
            if (!$fullArticle) {
                continue;
            }

            // Build content from wysiwyg blocks
            $content = '';

            // Get editor's note if exists
            $editorNote = $fullArticle->find('div.editors-note', 0);
            if ($editorNote) {
                $content .= '<div class="editors-note">' . $editorNote->innertext . '</div>';
            }

            // Get main article content blocks
            foreach($fullArticle->find('div[class*="byo-block -narrow wysiwyg-block wysiwyg"]') as $block) {
                // Skip blocks with layout styling classes
                if (strpos($block->class, 'border-t') !== false || 
                    strpos($block->class, 'medium') !== false || 
                    strpos($block->class, 'author-row') !== false) {
                    continue;
                }
                $content .= $block->innertext;
            }

            // Get any images
            $imageBlock = $fullArticle->find('div[class*="byo-block -wide -medium image-block"]', 0);
            if ($imageBlock) {
                $image = $imageBlock->find('img', 0);
                if ($image) {
                    $imageUrl = urljoin(self::URI, $image->src);
                    $item['enclosures'] = [$imageUrl];
                    $content = '<img src="' . $imageUrl . '" alt="' . ($image->alt ?? '') . '"/><br/>' . $content;
                }
            }

            $item['content'] = $content;

            // Get author info
            $authorBlock = $fullArticle->find('div.author-row', 0);
            if ($authorBlock) {
                $authorName = $authorBlock->find('span.author-name', 0);
                if ($authorName) {
                    $item['author'] = trim($authorName->plaintext);
                }
            }

            // Get date from published metadata
            $date = $fullArticle->find('meta[property="article:published_time"]', 0);
            if ($date) {
                $item['timestamp'] = strtotime($date->content);
            }

            $this->items[] = $item;
        }
    }
}