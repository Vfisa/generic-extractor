<?php

namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\Response\Filter,
    Keboola\GenericExtractor\Config\UserFunction;
use	Keboola\Juicer\Extractor\RecursiveJob,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Client\RequestInterface,
	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;
use	Keboola\Code\Builder,
	Keboola\Code\Exception\UserScriptException;

class GenericExtractorJob extends RecursiveJob
{
	/**
	 * @var array
	 */
	protected $params;
	/**
	 * @var array
	 */
	protected $attributes;
	/**
	 * @var array
	 */
	protected $metadata;
	/**
	 * @var string
	 */
	protected $lastResponseHash;
	/**
	 * @var Builder
	 */
	protected $stringBuilder;
	/**
	 * Data to append to the root result
	 * @var string|array
	 */
	protected $userParentId;

	/**
	 * {@inheritdoc}
	 * Verify the latest response isn't identical as the last one
	 * to prevent infinite loop on awkward pagination APIs
	 */
	public function run()
	{
		$this->buildParams($this->config);

		$request = $this->firstPage($this->config);
		while ($request !== false) {
			$response = $this->download($request);

			$responseHash = sha1(serialize($response));
			if ($responseHash == $this->lastResponseHash) {
				Logger::log("DEBUG", sprintf("Job '%s' finished when last response matched the previous!", $this->getJobId()));
				$this->scroller->reset();
				break;
			} else {
				$data = $this->findDataInResponse($response, $this->config->getConfig());
				$data = $this->filterResponse($this->config, $data);
				$this->parse($data, $this->userParentId);

				$this->lastResponseHash = $responseHash;
			}

			$request = $this->nextPage($this->config, $response, $data);
		}
	}

	/**
	 * @param JobConfig $config
	 * @return array
	 */
	protected function buildParams(JobConfig $config)
	{
		$params = UserFunction::build(
            $config->getParams(),
            [
                'attr' => $this->attributes,
                'time' => $this->metadata['time']
            ],
            $this->stringBuilder
        );

		$config->setParams($params);

		return $params;
	}

	/**
	 * Inject $scroller into a child job
	 * {@inheritdoc}
	 */
	protected function createChild(JobConfig $config, array $parentResults)
	{
		$job = parent::createChild($config, $parentResults);
		$scroller = clone $this->scroller;
		$scroller->reset();
		$job->setScroller($scroller);
		$job->setMetadata($this->metadata);
		$job->setAttributes($this->attributes);
		$job->setBuilder($this->stringBuilder);
		return $job;
	}

	/**
	 * Filters the $data array according to
	 * $config->getConfig()['responseFilter'] and
	 * returns the filtered array
	 *
	 * @param JobConfig $config
	 * @param array $data
	 * @return array
	 * @todo belongs to a separate class altogether
	 * @todo allow nesting
	 */
	protected function filterResponse(JobConfig $config, array $data)
	{
		$filter = Filter::create($config);
		return $filter->run($data);
	}

	/**
	 * @param array $attributes
	 */
	public function setAttributes(array $attributes)
	{
		$this->attributes = $attributes;
	}

	/**
	 * @param Builder $builder
	 */
	public function setBuilder(Builder $builder)
	{
		$this->stringBuilder = $builder;
	}

	/**
	 * @param array $metadata
	 */
	public function setMetadata(array $metadata)
	{
		$this->metadata = $metadata;
	}

	public function setUserParentId($id)
	{
		if (!is_array($id)) {
			throw new UserException("User defined parent ID must be a key:value pair, or multiple such pairs.", 0, null, ["id" => $id]);
		}

		$this->userParentId = $id;
	}
}
