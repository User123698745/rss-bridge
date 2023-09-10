<?php

declare(strict_types=1);

final class GraphQLException extends HttpException
{
    /**
     * A class representing a GraphQL exception.
     * @param string $message The exception message.
     * @param array $errors (optional) The list of errors from a query result.
     */
    public function __construct(string $message, array $errors = [])
    {
        $errorToString = function (object $error): string {
            $errorMessage = $error->message;
            if (isset($error->locations) && count($error->locations) === 1) {
                $location = $error->locations[0];
                $errorMessage = sprintf('%s [%d,%d]', $errorMessage, $location->line, $location->column);
            }
            return $errorMessage;
        };
        $messages = array_merge(
            [$message],
            array_map($errorToString, $errors)
        );
        $message = implode("\n", $messages);
        parent::__construct($message);
    }
}

final class GraphQLQuery
{
    private string $queryName;
    private string $queryString;
    private array $predefinedVariables;

    /**
     * A class representing a GraphQL query.
     * For more information follow the links below.
     * * https://graphql.org
     * * https://graphql.org/learn/queries
     * @param string $queryString The GraphQL query string itself.
     * @param array $predefinedVariables (optional) A predefined list of variables used by the GraphQL query.
     */
    public function __construct(string $queryString, array $predefinedVariables = [])
    {
        $this->queryName = preg_match('/^\s*query\s+(\w+)/i', $queryString, $matches) === 1 ? $matches[1] : null;
        $this->queryString = $queryString;
        $this->predefinedVariables = $predefinedVariables;
    }

    /**
     * Gets the name of this query extracted from the query string.
     * @return string
     */
    public function getName(): string
    {
        return $this->queryName;
    }

    /**
     * Build a data object for a GraphQL query request.
     * @param array $variables (optional) A list of variables used by the GraphQL query. The list gets merged with the predefined list of variables from this query.
     * @return string
     */
    public function build(array $variables = []): array
    {
        $mergedVariables = array_merge($this->predefinedVariables, $variables);
        return [
            'query' => $this->queryString,
            'variables' => $mergedVariables
        ];
    }
}

final class GraphQLEndpoint
{
    private string $url;
    private array $headers;

    /**
     * A helper class for interacting with a GraphQL endpoint.
     * For more information follow the links below.
     * * https://graphql.org
     * * https://graphql.org/learn/serving-over-http
     * @param string $url The url of the GraphQL endpoint (usually ending with /graphql).
     * @param array $customHeaders (optional) A list of cURL headers used for every GraphQL http request. The list gets merged with a predefined list of headers.
     * For more information follow the links below.
     * * https://php.net/manual/en/function.curl-setopt.php
     * * https://curl.haxx.se/libcurl/c/CURLOPT_HTTPHEADER.html
     */
    public function __construct(string $url, array $customHeaders = [])
    {
        $this->url = $url;
        $this->headers = array_merge(
            [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            $customHeaders
        );
    }

    /**
     * Gets the url of this endpoint.
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Gets data from the GraphQL endpoint using the given GraphQL query.
     * @param GraphQLQuery $query The GraphQL query to be executed.
     * @param array $variables (optional) A list of variables used by the GraphQL query. The list gets merged with the predefined list of variables from the given query.
     * @return object The data of the query result as an appropriate PHP type.
     * @throws GraphQLException When the query result contains errors or the response content is invalid.
     */
    public function executeQuery(GraphQLQuery $query, array $variables = []): object
    {
        return self::internalExecuteQuery($query, $variables);
    }

    /**
     * Gets data from the GraphQL endpoint using the given GraphQL query. The result gets cached and re-used for subsequent calls until the cache duration elapsed.
     * @param GraphQLQuery $query The GraphQL query to be executed.
     * @param array $variables (optional) A list of variables used by the GraphQL query. The list gets merged with the predefined list of variables from the given query.
     * @param int $cacheTimeout (optional) Cache duration in seconds (default: 24 hours).
     * @return object The data of the query result as an appropriate PHP type.
     * @throws GraphQLException When the query result contains errors or the response content is invalid.
     */
    public function executeQueryWithCache(GraphQLQuery $query, array $variables = [], $cacheTimeout = 86400): object
    {
        return self::internalExecuteQuery($query, $variables, 'pages', $cacheTimeout);
    }

    /**
     * Internal function for executeQuery and executeQueryWithCache.
     */
    private function internalExecuteQuery(GraphQLQuery $query, array $variables = [], string $cacheScope = null, $cacheTimeout = 86400): object
    {
        $queryData = $query->build($variables);

        $result = null;

        if (isset($cacheScope)) {
            $cache = RssBridge::getCache();
            $cache->setScope($cacheScope);
            $cache->setKey([$this->url, $queryData]);
            $resultJson = $cache->loadData($cacheTimeout);
            $result = json_decode($resultJson ?? '');
        }

        if (!isset($result)) {
            $queryJson = json_encode($queryData);
            $curlOptions = [
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $queryJson,
                CURLOPT_FAILONERROR => false,
            ];
            $resultJson = getContents($this->url, $this->headers, $curlOptions);

            $result = json_decode($resultJson);
            if (!isset($result)) {
                throw new \GraphQLException(sprintf('invalid response content (url: %s)', $this->url));
            }
            if (isset($result->errors)) {
                throw new \GraphQLException(sprintf('result contains errors (query: %s):', $query->getName()), $result->errors);
            }

            if (isset($cacheScope)) {
                $cache = RssBridge::getCache();
                $cache->setScope($cacheScope);
                $cache->setKey([$this->url, $queryData]);
                $cache->saveData($resultJson);
            }
        }

        $data = $result->data;
        if (isset($result->extensions) && !isset($data->extensions)) {
            $data->extensions = $result->extensions;
        }
        return $data;
    }
}
