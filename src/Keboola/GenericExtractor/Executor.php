<?php

namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\Config\Configuration,
	Keboola\GenericExtractor\GenericExtractor;
use	Keboola\Temp\Temp;
use	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Exception\UserException;

class Executor
{
	public function run()
	{
		$temp = new Temp(APP_NAME);

		Logger::initLogger(APP_NAME);

		$arguments = getopt("d::", ["data::"]);
		if (!isset($arguments["data"])) {
			throw new UserException('Data folder not set.');
		}

		$configuration = new Configuration($arguments['data'], APP_NAME, $temp);

		$configs = $configuration->getMultipleConfigs();

		$metadata = $configuration->getConfigMetadata() ?: [];
		$metadata['time']['previousStart'] = empty($metadata['time']['previousStart']) ? 0 : $metadata['time']['previousStart'];
		$metadata['time']['currentStart'] = time();

		$parsers = [];
		foreach($configs as $config) {
			// Reinitialize logger depending on debug status
			if ($config->getAttribute('debug')) {
				Logger::initLogger(APP_NAME, true);
			} else {
				Logger::initLogger(APP_NAME);
			}

			$api = $configuration->getApi($config);

			$outputBucket = $config->getAttribute('outputBucket') ?:
				'ex-api-' . $api->getName() . "-" . $config->getConfigName();

			$extractor = new GenericExtractor($temp);
			$extractor->setLogger(Logger::getLogger());
			if (!empty($parsers[$outputBucket])) {
				$extractor->setParser($parsers[$outputBucket]);
			}
			$extractor->setApi($api);
			$extractor->setMetadata($metadata);

			$extractor->run($config);

			$metadata = $extractor->getMetadata();

			$parsers[$outputBucket] = $extractor->getParser();
		}

		foreach($parsers as $bucket => $parser) {
			$configuration->storeResults(
				$parser->getResults(),
				$bucket
			);
		}

		$metadata['time']['previousStart'] = $metadata['time']['currentStart'];
		unset($metadata['time']['currentStart']);
		$configuration->saveConfigMetadata($metadata);
	}
}