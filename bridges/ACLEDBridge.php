<?php

class ACLEDBridge extends BridgeAbstract {

    const NAME = 'ACLED Bridge';
    const URI = 'https://acleddata.com';
    const DESCRIPTION = 'Returns the latest analysis posts from ACLED (Armed Conflict Location & Event Data Project)';
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
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData() {
        $limit = intval($this->getInput('limit')) ?: 10;
        $category = trim($this->getInput('category'));
        
        $url = 'https://acleddata.com/analysis-search/';
        if ($category) {
            $url .= '?_sft_category=' . urlencode($category);
        }

        $html = getSimpleHTMLDOM($url);
        if (!$html) {
            throw new \Exception('Failed to load ACLED website');
        }

        $posts = $html->find('div.analysis-result');
        $count = 0;

        foreach ($posts as $post) {
            if ($count >= $limit) {
                break;
            }

            $item = [];

            // Get title and URL
            $titleLink = $post->find('h2 a', 0);
            if (!$titleLink) {
                continue; // Skip if no title found
            }
            $item['uri'] = $titleLink->href;
            $item['title'] = trim($titleLink->plaintext);

            // Get date
            $date = $post->find('p.date', 0);
            if ($date) {
                try {
                    $item['timestamp'] = strtotime($date->plaintext);
                } catch (\Exception $e) {
                    $item['timestamp'] = time(); // Fallback to current time
                }
            }

            // Get content from the excerpt paragraph
            $content = $post->find('p p', 0);
            $item['content'] = $content ? trim($content->plaintext) : '';

            // Get categories
            $categories = [];
            $categoryElements = $post->find('ul.post-categories li a');
            foreach ($categoryElements as $cat) {
                $categories[] = trim($cat->plaintext);
            }
            $item['categories'] = $categories;

            // Get author if available
            $author = $post->find('span.author', 0);
            if ($author) {
                $item['author'] = trim($author->plaintext);
            }

            // Get thumbnail if available
            $image = $post->find('img.wp-post-image', 0);
            if ($image) {
                $imgSrc = $image->getAttribute('src');
                if ($imgSrc) {
                    $item['enclosures'] = [$imgSrc];
                    
                    // Add image metadata to content
                    $alt = $image->getAttribute('alt') ?: '';
                    $srcset = $image->getAttribute('srcset') ?: '';
                    $item['content'] = sprintf(
                        '<p><img src="%s" alt="%s" srcset="%s" /></p>%s',
                        htmlspecialchars($imgSrc),
                        htmlspecialchars($alt),
                        htmlspecialchars($srcset),
                        $item['content']
                    );
                }
            }

            // Add "Read More" link to content
            $readMore = $post->find('a.readmore-link', 0);
            if ($readMore) {
                $item['content'] .= sprintf(
                    '<p><a href="%s">Read More</a></p>',
                    htmlspecialchars($readMore->href)
                );
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
        $category = $this->getInput('category');
        $uri = self::URI;
        
        if ($category) {
            $uri .= '/category/' . urlencode($category);
        }
        
        return $uri;
    }

    public function detectParameters($url) {
        $params = [];
        
        // Check if URL matches a category
        if (preg_match('/acleddata\.com\/category\/([^\/]+)/', $url, $matches)) {
            $category = $matches[1];
            // Validate that the category exists
            if (in_array($category, self::PARAMETERS['Global']['category']['values'])) {
                $params['category'] = $category;
                return $params;
            }
        }
        
        return null;
    }
}