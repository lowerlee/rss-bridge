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
        $limit = $this->getInput('limit') ?: 10;
        $category = $this->getInput('category');
        
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

            $item = [];

            // Get title and URL
            $titleLink = $post->find('h2 a', 0);
            $item['uri'] = $titleLink->href;
            $item['title'] = $titleLink->plaintext;

            // Get date
            $date = $post->find('p.date', 0);
            if ($date) {
                $item['timestamp'] = strtotime($date->plaintext);
            }

            // Get content from the excerpt paragraph inside div.analysis-result
            $content = $post->find('p p', 0); // Get the excerpt specifically
            $item['content'] = $content ? $content->plaintext : '';

            // Get categories
            $categories = [];
            $categoryElements = $post->find('ul.post-categories li a');
            foreach ($categoryElements as $cat) {
                $categories[] = $cat->plaintext;
            }
            $item['categories'] = $categories;

            // Get thumbnail if available
            $image = $post->find('img.wp-post-image', 0);  // More specific selector for the main image
            if ($image) {
                $item['enclosures'] = [$image->getAttribute('src')];
                // Add image metadata to content
                $alt = $image->getAttribute('alt');
                $srcset = $image->getAttribute('srcset');
                $item['content'] = '<p><img src="' . $image->getAttribute('src') . '" 
                                      alt="' . htmlspecialchars($alt) . '" 
                                      srcset="' . htmlspecialchars($srcset) . '" /></p>' 
                                  . $item['content'];
            }

            // Add "Read More" link to content
            $readMore = $post->find('a.readmore-link', 0);
            if ($readMore) {
                $item['content'] .= '<p><a href="' . $readMore->href . '">Read More</a></p>';

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
            $uri .= '/category/' . $category;
        }
        
        return $uri;
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