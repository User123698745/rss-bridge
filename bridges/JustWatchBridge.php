<?php

class JustWatchBridge extends BridgeAbstract
{
    const NAME = 'JustWatch';
    const URI = 'https://justwatch.com';
    const DESCRIPTION = 'Returns latest releases on Streaming Platforms.';
    const MAINTAINER = 'Bocki';
    const CACHE_TIMEOUT = 3600; // 1h
    const PARAMETERS = [[
            'country' => [
                'name' => 'Country',
                'defaultValue' => 'us',
                'type' => 'list',
                'values' => [
                    'North America' => [
                        'Bermuda' => 'bm',
                        'Canada' => 'ca',
                        'Mexico' => 'mx',
                        'United States' => 'us'
                    ],
                    'South America' => [
                        'Argentina' => 'ar',
                        'Bolivia' => 'bo',
                        'Brazil' => 'br',
                        'Chile' => 'cl',
                        'Colombia' => 'co',
                        'Ecuador' => 'ec',
                        'French Guiana' => 'gf',
                        'Paraguay' => 'py',
                        'Peru' => 'pe',
                        'Uruguay' => 'uy',
                        'Venezuela' => 've'
                    ],
                    'Europe' => [
                        'Albania' => 'al',
                        'Andorra' => 'ad',
                        'Austria' => 'at',
                        'Belgium' => 'be',
                        'Bosnia Herzegovina' => 'ba',
                        'Bulgaria' => 'bg',
                        'Croatia' => 'hr',
                        'Czech Republic' => 'cz',
                        'Denmark' => 'dk',
                        'Estonia' => 'ee',
                        'Finland' => 'fi',
                        'France' => 'fr',
                        'Germany' => 'de',
                        'Gibraltar' => 'gi',
                        'Greece' => 'gr',
                        'Guernsey' => 'gg',
                        'Hungary' => 'hu',
                        'Iceland' => 'is',
                        'Ireland' => 'ie',
                        'Italy' => 'it',
                        'Kosovo' => 'xk',
                        'Liechtenstein' => 'li',
                        'Lithuania' => 'lt',
                        'Macedonia' => 'mk',
                        'Malta' => 'mt',
                        'Moldova' => 'md',
                        'Monaco' => 'mc',
                        'Netherlands' => 'nl',
                        'Norway' => 'no',
                        'Poland' => 'pl',
                        'Portugal' => 'pt',
                        'Romania' => 'ro',
                        'Russia' => 'ru',
                        'San Marino' => 'sm',
                        'Serbia' => 'rs',
                        'Slovakia' => 'sk',
                        'Slovenia' => 'si',
                        'Spain' => 'es',
                        'Sweden' => 'se',
                        'Switzerland' => 'ch',
                        'Turkey' => 'tr',
                        'United Kingdom' => 'uk',
                        'Vatican City' => 'va'
                    ],
                    'Asia' => [
                        'Hong Kong' => 'hk',
                        'India' => 'in',
                        'Indonesia' => 'id',
                        'Japan' => 'jp',
                        'Lebanon' => 'lb',
                        'Malaysia' => 'my',
                        'Pakistan' => 'pk',
                        'Philippines' => 'ph',
                        'Singapore' => 'sg',
                        'South Korea' => 'kr',
                        'Taiwan' => 'tw',
                        'Thailand' => 'th'
                    ],
                    'Central America' => [
                        'Costa Rica' => 'cr',
                        'El Salvador' => 'sv',
                        'Guatemala' => 'gt',
                        'Honduras' => 'hn',
                        'Panama' => 'pa'
                    ],
                    'Africa' => [
                        'Algeria' => 'dz',
                        'Cape Verde' => 'cv',
                        'Equatorial Guinea' => 'gq',
                        'Ghana' => 'gh',
                        'Ivory Coast' => 'ci',
                        'Kenya' => 'ke',
                        'Libya' => 'ly',
                        'Mauritius' => 'mu',
                        'Morocco' => 'ma',
                        'Mozambique' => 'mz',
                        'Niger' => 'ne',
                        'Nigeria' => 'ng',
                        'Senegal' => 'sn',
                        'Seychelles' => 'sc',
                        'South Africa' => 'za',
                        'Tunisia' => 'tn',
                        'Uganda' => 'ug',
                        'Zambia' => 'zm'
                    ],
                    'Pacific' => [
                        'Australia' => 'au',
                        'Fiji' => 'fj',
                        'French Polynesia' => 'pf',
                        'New Zealand' => 'nz'
                    ],
                    'Middle East' => [
                        'Bahrain' => 'bh',
                        'Egypt' => 'eg',
                        'Iraq' => 'iq',
                        'Israel' => 'il',
                        'Jordan' => 'jo',
                        'Kuwait' => 'kw',
                        'Oman' => 'om',
                        'Palestine' => 'ps',
                        'Qatar' => 'qa',
                        'Saudi Arabia' => 'sa',
                        'United Arab Emirates' => 'ae',
                        'Yemen' => 'ye'
                    ]
                ]
            ],
            'mediatype' => [
                'name' => 'Type',
                'defaultValue' => '0',
                'type' => 'list',
                'values' => [
                    'All' => 0,
                    'Movies' => 1,
                    'Series' => 2
                ]
            ]
        ]
    ];

    public function collectData()
    {
        $country = $this->getInput('country');

        $providers = self::getProviders($country);

        $providerInput = 'Disney Plus';
        $providerInputLower = trim(strtolower($providerInput));
        $provider = null;
        foreach ($providers as $p) {
            if (trim(strtolower($p->clearName)) === $providerInputLower) {
                $provider = $p;
                break;
            }
        }
        if (!isset($provider)) {
            $providersList = implode(', ', array_column($providers, 'clearName'));
            returnClientError(sprintf('provider "%s" not available in "%s" (available: "%s")', $providerInput, $country, $providersList));
        }

        $today = new Datetime();
        $maxDays = 7;
        $maxCount = 100;
        $filter = [
            "objectTypes" => [
                "MOVIE",
                "SHOW_SEASON",
            ],
            "packages" => [
                $provider->shortName
            ],
            "monetizationTypes" => [
                "FLATRATE",
                "FREE",
                "BUY",
                "RENT",
                "ADS"
            ],
            "releaseYear" => [
                "min" => intval($today->format('Y')) - 1, // only recently released titles
            ]
        ];
        $newTitles = self::getNewTitles($country, $today, $maxDays, $maxCount, $filter);

        $a = 42;

        // $basehtml = getSimpleHTMLDOM($this->getURI());
        // $basehtml = defaultLinkTo($basehtml, self::URI);
        // $overviewhtml = getSimpleHTMLDOM($basehtml->find('.navbar__button__link', 1)->href);
        // $overviewhtml = defaultLinkTo($overviewhtml, self::URI);
        // $html = getSimpleHTMLDOM($overviewhtml->find('.filter-bar-content-type__item', $this->getInput('mediatype'))->find('a', 0)->href);
        // $html = defaultLinkTo($html, self::URI);
        // $today = $html->find('div.title-timeline', 0);
        // $providers = $today->find('div.provider-timeline');

        // foreach ($providers as $provider) {
        //     $titles = $html->find('div.horizontal-title-list__item');
        //     foreach ($titles as $title) {
        //         $item = [];
        //         $item['uri'] = $title->find('a', 0)->href;

        //         $itemTitle = sprintf(
        //             '%s - %s',
        //             $provider->find('picture > img', 0)->alt ?? '',
        //             $title->find('.title-poster__image > img', 0)->alt ?? ''
        //         );
        //         $item['title'] = $itemTitle;

        //         $imageUrl = $title->find('.title-poster__image > img', 0)->attr['src'] ?? '';
        //         if (str_starts_with($imageUrl, 'data')) {
        //             $imageUrl = $title->find('.title-poster__image > img', 0)->attr['data-src'];
        //         }

        //         $content  = '<b>Provider:</b> '
        //             . $provider->find('picture > img', 0)->alt . '<br>';
        //         $content .= '<b>Media:</b> '
        //             . $title->find('.title-poster__image > img', 0)->alt . '<br>';

        //         if (isset($title->find('.title-poster__badge', 0)->plaintext)) {
        //             $content .= '<b>Type:</b> Series<br>';
        //             $content .= '<b>Season:</b> ' . $title->find('.title-poster__badge', 0)->plaintext . '<br>';
        //         } else {
        //             $content .= '<b>Type:</b> Movie<br>';
        //         }

        //         $content .= '<b>Poster:</b><br><a href="'
        //             . $title->find('a', 0)->href
        //             . '"><img src="'
        //             . $imageUrl
        //             . '"></a>';

        //         $item['content'] = $content;
        //         $this->items[] = $item;
        //     }
        // }
    }

    public function getURI()
    {
        return self::URI . '/' . $this->getInput('country');
    }

    public function getName()
    {
        if (!is_null($this->getInput('country'))) {
            return 'JustWatch - ' . $this->getKey('country') . ' - ' . $this->getKey('mediatype');
        }
        return parent::getName();
    }

    public function getIcon()
    {
        return self::URI . '/appassets/favicon.ico';
    }

    private function getNewTitles(string $country, DateTime $date, int $maxDays, int $maxCount, array $filter): array
    {
        $query = new GraphQLQuery(
            <<<'QUERY'
            query GetNewTitles($country: Country!, $language: Language!, $platform: Platform!, $pageType: NewPageType!,
                $date: Date, $first: Int!, $filter: TitleFilter, $posterProfile: PosterProfile, $posterFormat: ImageFormat) {
                newTitles(
                    country: $country
                    pageType: $pageType
                    date: $date
                    first: $first
                    filter: $filter
                ) {
                    edges {
                        newOffer(platform: $platform) {
                            standardWebURL
                            package {
                                shortName
                                clearName
                            }
                            monetizationType
                        }
                        node {
                            objectId
                            content(country: $country, language: $language) {
                                title
                                fullPath
                                posterUrl(profile: $posterProfile, format: $posterFormat)
                                originalReleaseYear
                                scoring {
                                    imdbScore
                                }
                            }
                            ... on Season {
                                totalEpisodeCount
                                show {
                                    content(country: $country, language: $language) {
                                        title
                                    }
                                }
                            }
                            __typename
                        }
                    }
                }
            }
            QUERY,
            [
                "country" => strtoupper($country),
                "language" => "en",
                "platform" => "WEB",
                "pageType" => "NEW",
                "first" => 300,
                "filter" => $filter,
                "posterProfile" => "S276",
                "posterFormat" => "WEBP",
            ]
        );

        $titles = [];

        $oneDay = DateInterval::createFromDateString('1 day');
        $maxDate = (clone $date)->sub(DateInterval::createFromDateString("$maxDays days"));
        while ($date > $maxDate && count($titles) <= $maxCount) {
            $variables = [
                "date" => $date->format('Y-m-d')
            ];
            $data = self::getGraphQLEndpoint()->executeQuery($query, $variables);
            $titles = array_merge($titles, $data->newTitles->edges);
            $date->sub($oneDay);
        }

        return $titles;
    }

    private function getProviders(string $country): array
    {
        $query = new GraphQLQuery(
            <<<'QUERY'
            query GetProviders($country: Country!, $platform: Platform!) {
                packages(country: $country, platform: $platform) {
                    shortName
                    clearName
                }
            }
            QUERY,
            [
                "country" => strtoupper($country),
                "platform" => "WEB",
            ]
        );
        $data = self::getGraphQLEndpoint()->executeQueryWithCache($query);
        return $data->packages;
    }

    private function getGraphQLEndpoint(): GraphQLEndpoint
    {
        return new GraphQLEndpoint('https://apis.justwatch.com/graphql');
    }
}