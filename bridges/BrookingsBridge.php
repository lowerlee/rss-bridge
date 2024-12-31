<?php

class BrookingsBridge extends BridgeAbstract {
    const NAME = 'Brookings Institution Bridge';
    const URI = 'https://www.brookings.edu';
    const DESCRIPTION = 'Returns latest research and commentary from Brookings Institution';
    const MAINTAINER = 'Your GitHub Username';
    const CACHE_TIMEOUT = 3600;

    public function collectData() {
        // Get main page
        $html = getSimpleHTMLDOM(self::URI . '/research-commentary/');

        // Find all articles
        foreach($html->find('article.article-nav') as $article) {
            $item = [];
            
            // Get link and title
            $link = $article->find('a.overlay-link', 0);
            $item['uri'] = $link->href;
            $item['title'] = trim($link->find('span.sr-only', 0)->plaintext);

            // Fetch full article
            $fullArticle = getSimpleHTMLDOM($item['uri']);
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

    public function getName() {
        return self::NAME;
    }
}