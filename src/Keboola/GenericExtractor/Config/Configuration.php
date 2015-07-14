<?php

namespace Keboola\GenericExtractor\Config;

use	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;
use	Keboola\Juicer\Config\Configuration as BaseConfiguration,
	Keboola\Juicer\Common\Logger;
use	Keboola\GenericExtractor\Authentication,
	Keboola\GenericExtractor\Pagination;
use	Keboola\Code\Builder;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;

/**
 * {@inheritdoc}
 */
class Configuration extends BaseConfiguration
{
	/**
	 * Should return a class that contains
	 * - info about what attrs store the Auth keys
	 * - callback method that generates a signature
	 * - OR an array of defaults for the client (possibly using the callback ^^)
	 * - type of the auth method TODO - is that needed?
	 * - Method that accepts GuzzleClient as parameter and adds the emitter/defaults to it
	 *
	 * @param string $configName
	 * @return Authentication\AuthInterface
	 */
	public function getAuth($config)
	{
		$bucket = $this->configBucket;
		if (empty($bucket['auth']['type'])) {
			Logger::log("INFO", "Using NO Auth");
			return new Authentication\NoAuth();
		}

		Logger::log("INFO", "Using '{$bucket['auth']['type']}' Auth");
		switch ($bucket['auth']['type']) {
			case 'basic':
				return new Authentication\Basic($bucket['items'][$configName]);
				break;
			case 'bearer':
				throw new ApplicationException(501, "The bearer method is not implemented yet");
				break;
			case 'url.query':
				if (empty($bucket['query'])) {
					throw new UserException("The query authentication method requires query parameters to be defined in the configuration bucket attributes.");
				}

				return new Authentication\Query(new Builder(), $bucket['items'][$configName], $bucket['query']);
				break;
			default:
				throw new UserException("Unknown authorization type '{$bucket['auth']['type']}'");
				break;
		}
	}

	/**
	 * @return string
	 */
	public function getBaseUrl($configName)
	{
		if (empty($this->configBucket['baseUrl'])) {
			throw new UserException("The 'baseUrl' attribute must be set in {$this->configBucketId}");
		}

		if (filter_var($this->configBucket['baseUrl'], FILTER_VALIDATE_URL)) {
			return $this->configBucket['baseUrl'];
		} else {
			try {
				$fn = Utils::json_decode($this->configBucket['baseUrl']);
			} catch(JsonDecodeException $e) {
				throw new UserException("The 'baseUrl' attribute in {$this->configBucketId} is not an URL string, neither a valid JSON containing an user function! Error: " . $e->getMessage(), $e);
			}
			return (new Builder())->run($fn, ['attr' => $this->configBucket['items'][$configName]]);
		}
	}

	/**
	 * @param string $configName
	 * @return array
	 */
	public function getHeaders($configName)
	{
		$bucket = $this->configBucket;
		$tableAttrs = $bucket['items'][$configName];

		if (!empty($bucket['http']['requiredHeaders'])) {
			$requiredHeaders = explode(",", $bucket['http']['requiredHeaders']);
			array_walk($requiredHeaders, function(&$value) {$value = trim($value);});

			foreach($requiredHeaders as $reqHeader) {
				if (empty($tableAttrs['header'][$reqHeader])) {
					throw new UserException("Missing required header {$reqHeader} in configuration table attributes!");
				}
			}
		}

		$tHeaders = empty($tableAttrs['header']) ? [] : $tableAttrs['header'];
		$bHeaders = empty($bucket['http']['header']) ? [] : $bucket['http']['header'];

		return array_replace($bHeaders, $tHeaders);
	}

	/**
	 * Return pagination scoller
	 * @return Pagination\ScrollerInterface
	 * @todo refactor Scrollers to use config arrays
	 */
	public function getScroller()
	{
		if (empty($this->configBucket['pagination']) || empty($this->configBucket['pagination']['method'])) {
			return new Pagination\NoScroller();
		}
		$pagination = $this->configBucket['pagination'];

		switch ($pagination['method']) {
			case 'offset':
				if (empty($pagination['limit'])) {
					throw new UserException("Missing required 'pagination.limit' attribute for pagination");
				}

				return new Pagination\OffsetScroller(
					$pagination['limit'],
					!empty($pagination['limitParam']) ? $pagination['limitParam'] : 'limit',
					!empty($pagination['offsetParam']) ? $pagination['offsetParam'] : 'offset'
				);
				break;
			case 'response.param':
				throw new ApplicationException(501, "Pagination by param Not yet implemented");
				break;
			case 'response.url':
				return new Pagination\ResponseUrlScroller(
					!empty($pagination['urlKey']) ? $pagination['urlKey'] : 'next_page',
					!empty($pagination['includeParams']) ? (bool) $pagination['includeParams'] : false
				);
			case 'pagenum':
				return new Pagination\PageScroller(
					!empty($pagination['pageParam']) ? $pagination['pageParam'] : 'page',
					!empty($pagination['limit']) ? $pagination['limit'] : null,
					!empty($pagination['limitParam']) ? $pagination['limitParam'] : 'limit',
					!empty($pagination['firstPage']) ? $pagination['firstPage'] : 1
				);
				break;
			default:
				throw new UserException("Unknown pagination method '{$pagination['method']}'");
				break;
		}
	}

	/**
	 * @param array $config
	 */
	public function initialize($config)
	{
		var_dump($config);
	}
}
