<?php

class GitHubFileAsFeedBridge extends FeedExpander
{
    const MAINTAINER = 'User123698745';
    const NAME = 'GitHub File as Feed';
    const URI = 'https://github.com';
    const CACHE_TIMEOUT = 60 * 15; // 15min
    const DESCRIPTION = 'Returns a xml file from a GitHub repository or gist as rss feed making it possible to host rss feeds on GitHub.com';

    const CONTEXT_GLOBAL = 'global';
    const CONTEXT_REPO   = 'Repository';
    const CONTEXT_GIST = 'Gist';

    const PARAM_GLOBAL_LIMIT = 'limit';
    const PARAM_REPO_OWNER = 'owner';
    const PARAM_REPO_REPO = 'repo';
    const PARAM_REPO_PATH = 'path';
    const PARAM_REPO_BRANCH = 'branch';
    const PARAM_GIST_ID = 'id';
    const PARAM_GIST_FILE = 'file';

    const DEFAULT_LIMIT = 25;

    const PARAMETERS = [
        self::CONTEXT_GLOBAL => [
            self::PARAM_GLOBAL_LIMIT => [
                'name' => 'Limit',
                'type' => 'number',
                'required' => false,
                'title' => 'Specify the maximum number of items to return',
                'defaultValue' => self::DEFAULT_LIMIT
            ]
        ],
        self::CONTEXT_REPO => [
            self::PARAM_REPO_OWNER => [
                'name' => 'Owner',
                'required' => true,
                'title' => 'The account owner of the repository',
                'exampleValue' => 'User123698745'
            ],
            self::PARAM_REPO_REPO => [
                'name' => 'Repository',
                'required' => true,
                'title' => 'The name of the repository without the .git extension',
                'exampleValue' => 'lootscraper-data'
            ],
            self::PARAM_REPO_PATH => [
                'name' => 'File-Path',
                'required' => true,
                'title' => 'Path to the xml file inside the repository',
                'exampleValue' => 'feeds/steam_game.xml'
            ],
            self::PARAM_REPO_BRANCH => [
                'name' => 'Branch',
                'required' => false,
                'title' => "Branch of the repository from which the file will be used. Default: The repository's default branch (usually main)",
                'exampleValue' => 'main'
            ]
        ],
        self::CONTEXT_GIST => [
            self::PARAM_GIST_ID => [
                'name' => 'ID',
                'required' => true,
                'title' => 'The unique identifier of the gist',
                'exampleValue' => '94ca2165861ebf2f1327e8d4c391d92d'
            ],
            self::PARAM_GIST_FILE => [
                'name' => 'File-Name',
                'required' => false,
                'title' => 'Name of the xml file to return. Default: The first file of the gist',
                'exampleValue' => 'XML RSS Feed Example.xml'
            ]
        ]
    ];

    public function getURI()
    {
        $uri = parent::getURI();
        if ($uri != self::URI)
            return $uri;

        return rtrim(self::getRepoUri(), '/');
    }

    private function getRepoUri()
    {
        $uri = self::URI;
        $owner = $this->getInput(self::PARAM_REPO_OWNER);
        $repo = $this->getInput(self::PARAM_REPO_REPO);
        return <<<URI
            {$uri}/{$owner}/{$repo}
            URI;
    }

    public function collectData()
    {
        $feedUri = $this->queriedContext == self::CONTEXT_GIST ?
            self::getGistFeedUri() :
            self::getRepoFeedUri();

        $this->collectExpandableDatas(
            $feedUri,
            $this->getInput('limit') ?: self::DEFAULT_LIMIT
        );
    }

    private function getRepoFeedUri()
    {
        $owner = rawurlencode($this->getInput(self::PARAM_REPO_OWNER));
        $repo = rawurlencode($this->getInput(self::PARAM_REPO_REPO));
        $path = rawurlencode($this->getInput(self::PARAM_REPO_PATH));
        $branch = rawurlencode($this->getInput(self::PARAM_REPO_BRANCH) ?: '');

        $commitsApiUri = <<<URI
            https://api.github.com/repos/{$owner}/{$repo}/commits?path={$path}&sha={$branch}&per_page=1
            URI;
        $commitsJson = getContents($commitsApiUri);

        $latestCommit = json_decode($commitsJson)[0];
        if (!$latestCommit)
        {
            $path = $this->getInput(self::PARAM_REPO_PATH);
            $repoUri = self::getRepoUri();
            returnClientError(<<<MSG
                File "{$path}" not found in repository "{$repoUri}"
                MSG);
        }

        $latestCommitHash = $latestCommit->sha;

        return <<<URI
            https://rawcdn.githack.com/{$owner}/{$repo}/{$latestCommitHash}/{$path}
            URI;
    }

    private function getGistFeedUri()
    {
        $id = rawurlencode($this->getInput(self::PARAM_GIST_ID));
        $file = rawurlencode($this->getInput(self::PARAM_GIST_FILE) ?: '');

        $commitsApiUri = <<<URI
            https://api.github.com/gists/{$id}/commits?per_page=1
            URI;
        $commitsJson = getContents($commitsApiUri);

        $latestCommit = json_decode($commitsJson)[0];

        $latestCommitHash = $latestCommit->version;
        $owner = $latestCommit->user->login;

        return rtrim(<<<URI
            https://gistcdn.githack.com/{$owner}/{$id}/raw/{$latestCommitHash}/{$file}
            URI, '/');
    }
}
