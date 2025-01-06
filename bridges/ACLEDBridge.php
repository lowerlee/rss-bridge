<?php
class ACLEDBridge extends BridgeAbstract {
    const NAME = 'ACLED Bridge';
    const URI = 'https://acleddata.com';
    const DESCRIPTION = 'Returns ACLED analysis articles with full content';
    const MAINTAINER = 'Your Name';
    const PARAMETERS = [
        'Global' => [
            'limit' => [
                'name' => 'Limit',
                'type' => 'number',
                'required' => false,
                'title' => 'Maximum number of items to return',
                'defaultValue' => 10
            ],
            'category' => [
                'name' => 'Category',
                'type' => 'list',
                'required' => false,
                'values' => [
                    'All Categories' => '',
                    'Analysis' => 'analysis',
                    'ACLED Insights' => 'acled-insights',
                    'ACLED Brief' => 'acled-brief',
                    'Infographics' => 'infographics'
                ]
            ]
        ]
    ];
    const CACHE_TIMEOUT = 3600;

    public function getIcon() {
        return 'https://acleddata.com/acleddatanew/wp-content/uploads/2019/10/acled-favi.png';
    }

    public function collectData() {
        $limit = $this->getInput('limit') ?: 10;
        $category = $this->getInput('category');
        
        // Get list of articles from search page
        $url = 'https://acleddata.com/analysis-search/';
        if ($category) {
            $url .= '?_sft_category=' . $category;
        }

        $html = getSimpleHTMLDOM($url);
        $posts = $html->find('div.analysis-result');
        $count = 0;

        foreach ($posts as $post) {
            if ($count >= $limit) {
                break;
            }

            // Get article URL
            $articleUrl = $post->find('h2 a', 0)->href;
            
            // Fetch and parse full article
            $articleHtml = getSimpleHTMLDOM($articleUrl);
            $item = [];

            // Title
            $item['title'] = $articleHtml->find('title', 0)->plaintext;
            
            // URL
            $item['uri'] = $articleHtml->find('link[rel=canonical]', 0)->href;
            
            // Publication Date
            $dateElement = $articleHtml->find('time.entry-date', 0);
            if ($dateElement) {
                $item['timestamp'] = strtotime($dateElement->datetime);
            }

            // Authors
            $authors = [];
            foreach ($articleHtml->find('div.author-info') as $authorDiv) {
                $authorName = $authorDiv->find('.author-heading', 0)->plaintext;
                $authorUrl = $authorDiv->find('h4 a', 0)->href;
                $authorBio = $authorDiv->find('.author-bio', 0)->plaintext;
                $authors[] = [
                    'name' => $authorName,
                    'url' => $authorUrl,
                    'bio' => $authorBio
                ];
            }
            $item['author'] = implode(', ', array_column($authors, 'name'));

            // Categories
            $categories = [];
            foreach ($articleHtml->find('span.category-link a') as $category) {
                $categories[] = $category->plaintext;
            }
            $item['categories'] = $categories;

            // Region
            $regionElement = $articleHtml->find('div.region-meta-single a', 0);
            if ($regionElement) {
                $item['region'] = $regionElement->plaintext;
            }

            // Tags
            $tags = [];
            foreach ($articleHtml->find('div.entry-tags a') as $tag) {
                $tags[] = $tag->plaintext;
            }
            $item['tags'] = $tags;

            // Content
            $content = $articleHtml->find('div.entry-content', 0);
            
            // Clean up content
            foreach ($content->find('script, style, .printfriendly') as $remove) {
                $remove->outertext = '';
            }

            // Add metadata section
            $metadata = "<div class='article-metadata'>";
            if (isset($item['region'])) {
                $metadata .= "<p><strong>Region:</strong> {$item['region']}</p>";
            }
            if (!empty($item['categories'])) {
                $metadata .= "<p><strong>Categories:</strong> " . implode(', ', $item['categories']) . "</p>";
            }
            if (!empty($item['tags'])) {
                $metadata .= "<p><strong>Tags:</strong> " . implode(', ', $item['tags']) . "</p>";
            }
            if (!empty($authors)) {
                $metadata .= "<div class='authors'>";
                foreach ($authors as $author) {
                    $metadata .= "<div class='author'>";
                    $metadata .= "<p><strong><a href='{$author['url']}'>{$author['name']}</a></strong></p>";
                    $metadata .= "<p>{$author['bio']}</p>";
                    $metadata .= "</div>";
                }
                $metadata .= "</div>";
            }
            $metadata .= "</div><hr>";

            $item['content'] = $metadata . $content->innertext;

            // Get featured image
            $featuredImage = $post->find('img.wp-post-image', 0);
            if ($featuredImage) {
                $item['enclosures'] = [$featuredImage->getAttribute('src')];
            }

            $this->items[] = $item;
            $count++;
        }
    }

    public function getName() {
        $category = $this->getInput('category');
        $name = self::NAME;
        
        if ($category) {
            $categories = self::PARAMETERS['Global']['category']['values'];
            $categoryName = array_search($category, $categories);
            if ($categoryName) {
                $name .= ' - ' . $categoryName;
            }
        }
        
        return $name;
    }

    public function getURI() {
        return self::URI;
    }

    public function detectParameters($url) {
        $params = [];
        
        // Check if URL matches a category
        if (preg_match('/acleddata\.com\/category\/([^\/]+)/', $url, $matches)) {
            $params['category'] = $matches[1];
            return $params;
        }
        
        return null;
    }
}