<?php

namespace TYPO3\Documentation\Search\Indexer;

/*
 * Copyright (C) 2018 Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;
use VDB\Spider\Discoverer\CssSelectorDiscoverer;
use VDB\Spider\Resource;
use VDB\Spider\Spider;

/**
 * Indexer for a single TYPO3 Documentation Manual.
 *
 * A Manual is just a single document, e.g. a reference, manual, guide or extension manual.
 */
class ManualIndexer
{
    /**
     * @var Client
     */
    protected $searchClient;

    public function __construct(Client $client = null)
    {
        if ($client === null) {
            $client = ClientBuilder::create()->build();
        }

        $this->searchClient = $client;
    }

    public function reindexManual(string $manualUrl)
    {
        // TODO: Add some filtering, check domain, etc.
        // $this->validateIncomdingManualUrl($manualUrl);

        // TODO: Delete old entries for manual

        $this->indexManual($manualUrl);
    }

    public function indexManual(string $manualUrl)
    {
        // TODO: Add some filtering, check domain, etc.
        // $this->validateIncomdingManualUrl($manualUrl);

        $this->setupIndexIfNecessary();
        $this->indexSitemap($this->buildSitemapUrl($manualUrl));
    }

    protected function buildSitemapUrl(string $manualUrl): string
    {
        $sitemapUrl = new Uri($manualUrl);
        $sitemapUrl = $sitemapUrl->withPath(
            rtrim($sitemapUrl->getPath(), '/') . '/Sitemap/Index.html'
        );

        return (string) $sitemapUrl;
    }

    protected function indexSitemap(string $sitemapUrl)
    {
        $spider = new Spider($sitemapUrl);
        // TODO: Prevent indexing of Targets.html
        $spider->getDiscovererSet()->set(new CssSelectorDiscoverer('.sitemap a'));

        $spider->crawl();

        foreach ($spider->getDownloader()->getPersistenceHandler() as $resource) {
            $this->indexPage($resource);
        }
    }

    protected function indexPage(Resource $page)
    {
        $uri = new Uri($page->getCrawler()->getUri());

        if ($uri->getFragment()) {
            $this->indexPageFragment($page, $uri->getFragment());
        } else {
            // $this->indexFullPage($page);
        }
    }

    protected function indexPageFragment(Resource $page, string $fragment)
    {
        // TODO: Check, do we always have an ID?
        $this->indexPagePart($page->getCrawler()->filter('#' . $fragment), $page);
    }

    protected function indexPagePart(Crawler $partToIndex, Resource $page)
    {
        list($manual, $version) = $this->explodeProjectTitle(
            $page->getCrawler()->filter('.sidebartop .project')
        );

        $title = $partToIndex->filterXPath(
            '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]'
        )->text();

        $indexEntry = [
            'index' => 'typo3documentation',
            'type' => 'manualentry',
            'id' => $partToIndex->getUri(), // TODO: How to generate?
            'body' => [
                'manual' => $manual,
                'version' => $version,

                'uri' => $partToIndex->getUri(),

                'title' => trim($title, '¶'),
                'content' => str_replace('¶', '', $partToIndex->text()),
            ],
        ];

        // Trim whitespace
        array_walk_recursive($indexEntry, function (&$item) {
            $item = trim($item);
        });

        $this->searchClient->index($indexEntry);
    }

    protected function explodeProjectTitle(Crawler $docTitle): array
    {
        // Replace tags with single whitespace
        // See: https://stackoverflow.com/a/12825031/1888377
        $docTitleString = preg_replace('#<[^>]+>#', ' ', $docTitle->html());
        $docTitleString = trim($docTitleString);

        $titleParts = explode(' ', $docTitleString);
        $version = trim(array_pop($titleParts), '()');

        $title = implode(' ', $titleParts);
        $title = str_replace('latest', '', $title);
        $title = trim($title);

        return [$title, $version];
    }

    protected function setupIndexIfNecessary()
    {
        $index = [
            'index' => 'typo3documentation',
        ];

        if ($this->searchClient->indices()->exists($index)) {
            return;
        }

        $this->searchClient->indices()->create($index);
        $this->searchClient->indices()->putMapping(array_merge($index, [
            'type' => [
                'manualentry',
            ],
            'body' => [
                'manualentry' => [
                    'properties' => [
                        'manual' => [
                            'type' => 'keyword',
                        ],
                        'version' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
            ],
        ]));
    }
}
