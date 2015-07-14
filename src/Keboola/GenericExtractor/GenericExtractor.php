<?php

namespace Keboola\GenericExtractor;

use	Keboola\Juicer\Extractor\Extractors\JsonExtractor,
	Keboola\Juicer\Config\Config;
use	Syrup\ComponentBundle\Exception\SyrupComponentException;
use	GuzzleHttp\Client;
use	Keboola\GenericExtractor\GenericExtractorJob,
	Keboola\GenericExtractor\Authentication\AuthInterface,
	Keboola\GenericExtractor\Pagination\ScrollerInterface,
	Keboola\Code\Builder;
use	Keboola\Utils\Utils;

class GenericExtractor extends JsonExtractor
{
	protected $name = "generic";
	protected $prefix = "ex-api";
	protected $baseUrl;
	/**
	 * @var array
	 */
	protected $headers;
	/**
	 * @var ScrollerInterface
	 */
	protected $scroller;
	/**
	 * @var AuthInterface
	 */
	protected $auth;

	public function run(Config $config)
	{
		/**
		 * @var Client
		 */
		$client = new Client(
			[
				"base_url" => $this->baseUrl,
// 				"defaults" => $this->getClientDefaults()
			]
		);
		$client->setDefaultOption('headers', $this->headers);
		$client->getEmitter()->attach($this->getBackoff());

		$this->auth->authenticateClient($client);

		$this->parser->setAllowArrayStringMix(true);

		$builder = new Builder();

		$runTimes = [];
		$jobTimes = [];
		foreach($config["jobs"] as $jobConfig) {
			$this->saveLastJobTime($jobConfig->getJobId(), "start");
			$startTime = time();

			foreach(['start', 'success', 'error', 'success_startTime'] as $timeAttr) {
				if (empty($config['attributes']['job'][$jobConfig->getJobId()][$timeAttr])) {
					$config['attributes']['job'][$jobConfig->getJobId()][$timeAttr] = date(DATE_W3C, 0);
				}
			}

			$job = new GenericExtractorJob($jobConfig, $client, $this->parser);
			$job->setScroller($this->scroller);
			$job->setAttributes($config['attributes']);
			$job->setBuilder($builder);
			try {
				$job->run();
			} catch(\Exception $e) {
				$this->saveLastJobTime($jobConfig->getJobId(), "error");
				$this->saveLastJobTime(
					$jobConfig->getJobId(),
					"error_startTime",
					date(DATE_W3C, $startTime)
				);
				throw $e;
			}

			$jobTimes[$jobConfig->getJobId()]['success'] = date(DATE_W3C);
			$jobTimes[$jobConfig->getJobId()]['success_startTime'] = date(DATE_W3C, $startTime);
			$runTimes[$jobConfig->getJobId()] = $job->getRunTime();
		}

		$this->sapiUpload($this->parser->getCsvFiles());
		foreach($jobTimes as $jobId => $times) {
			$this->saveLastJobTime($jobId, "success", $times['success']);
			$this->saveLastJobTime($jobId, "success_startTime", $times['success_startTime']);
		}

		return $runTimes;
	}

	public function setAppName($api)
	{
		$this->name = $api;
	}

	/**
	 * Get base URL from Config
	 * @param string $url
	 */
	public function setBaseUrl($url)
	{
		$this->baseUrl = $url;
	}

	/**
	 * @param AuthInterface $auth
	 */
	public function setAuth(AuthInterface $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * @param array $headers
	 */
	public function setHeaders(array $headers)
	{
		$this->headers = $headers;
	}

	/**
	 * @param ScrollerInterface $scroller
	 */
	public function setScroller(ScrollerInterface $scroller)
	{
		$this->scroller = $scroller;
	}
}
